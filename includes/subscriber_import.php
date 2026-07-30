<?php

/**
 * Read spreadsheet rows from .xlsx or .csv. Returns list of associative rows keyed by header.
 *
 * @return array{headers: string[], rows: array<int, array<string, string>>}
 */
function readSubscriberSpreadsheet(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return readSubscriberCsv($path);
    }

    if ($ext === 'xlsx') {
        return readSubscriberXlsx($path);
    }

    throw new RuntimeException('Unsupported file type. Please upload an .xlsx or .csv file.');
}

function subscriberImportExpectedHeaders(): array
{
    return [
        'full_name',
        'phone',
        'email',
        'plan',
        'connection_medium',
        'installation_date',
        'address',
        'barangay',
        'city',
        'province',
        'notes',
        'status',
    ];
}

function normalizeSpreadsheetHeader(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s\-]+/', '_', $header) ?? $header;
    $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

    $aliases = [
        'name'               => 'full_name',
        'fullname'          => 'full_name',
        'customer_name'      => 'full_name',
        'subscriber'         => 'full_name',
        'subscriber_name'    => 'full_name',
        'mobile'             => 'phone',
        'contact'            => 'phone',
        'contact_number'     => 'phone',
        'phone_number'       => 'phone',
        'service_plan'       => 'plan',
        'plan_name'          => 'plan',
        'medium'             => 'connection_medium',
        'connection'         => 'connection_medium',
        'medium_of_connection' => 'connection_medium',
        'install_date'       => 'installation_date',
        'date_installed'     => 'installation_date',
        'street'             => 'address',
        'street_address'     => 'address',
        'municipality'       => 'city',
        'city_municipality'  => 'city',
    ];

    return $aliases[$header] ?? $header;
}

function readSubscriberCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open CSV file.');
    }

    $headers = [];
    $rows = [];
    $line = 0;

    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if ($line === 1) {
            $headers = array_map(
                static fn($h) => normalizeSpreadsheetHeader((string) $h),
                $data
            );
            continue;
        }

        if (spreadsheetRowIsEmpty($data)) {
            continue;
        }

        $row = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string) ($data[$i] ?? ''));
        }
        $rows[$line] = $row;
    }

    fclose($handle);

    if (!$headers) {
        throw new RuntimeException('CSV file has no header row.');
    }

    return ['headers' => $headers, 'rows' => $rows];
}

function readSubscriberXlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP zip extension is required to read Excel files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open Excel file.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = @simplexml_load_string($sharedXml);
        if ($shared) {
            foreach ($shared->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        // Fallback to first worksheet entry
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromIndex($i);
                break;
            }
        }
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Excel worksheet not found.');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet) {
        throw new RuntimeException('Unable to parse Excel worksheet.');
    }

    $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowNodes = $sheet->xpath('//m:sheetData/m:row') ?: [];

    $matrix = [];
    foreach ($rowNodes as $rowNode) {
        $rowNum = (int) ($rowNode['r'] ?? 0);
        if ($rowNum < 1) {
            continue;
        }
        foreach ($rowNode->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            $col = xlsxColumnIndex($m[1]);
            $matrix[$rowNum][$col] = xlsxCellValue($cell, $sharedStrings);
        }
    }

    if (!$matrix) {
        throw new RuntimeException('Excel file has no data rows.');
    }

    ksort($matrix);
    $firstRowNum = array_key_first($matrix);
    $headerCells = $matrix[$firstRowNum] ?? [];
    ksort($headerCells);
    $maxCol = $headerCells ? max(array_keys($headerCells)) : -1;

    $headers = [];
    for ($col = 0; $col <= $maxCol; $col++) {
        $headers[$col] = normalizeSpreadsheetHeader((string) ($headerCells[$col] ?? ''));
    }

    $rows = [];
    foreach ($matrix as $rowNum => $cells) {
        if ($rowNum === $firstRowNum) {
            continue;
        }

        $values = [];
        for ($col = 0; $col <= $maxCol; $col++) {
            $values[] = (string) ($cells[$col] ?? '');
        }
        if (spreadsheetRowIsEmpty($values)) {
            continue;
        }

        $row = [];
        foreach ($headers as $col => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string) ($cells[$col] ?? ''));
        }
        $rows[$rowNum] = $row;
    }

    return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
}

function xlsxColumnIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) ($cell['t'] ?? '');
    $raw = isset($cell->v) ? (string) $cell->v : '';

    if ($type === 's') {
        $idx = (int) $raw;
        return (string) ($sharedStrings[$idx] ?? '');
    }

    if ($type === 'inlineStr') {
        return isset($cell->is->t) ? (string) $cell->is->t : '';
    }

    if ($type === 'b') {
        return $raw === '1' ? '1' : '0';
    }

    // Excel date serial → Y-m-d when it looks like a date serial in a date-ish range
    if ($raw !== '' && is_numeric($raw) && strpos($raw, '.') === false) {
        $serial = (int) $raw;
        if ($serial > 20000 && $serial < 80000) {
            $unix = ($serial - 25569) * 86400;
            if ($unix > 0) {
                return gmdate('Y-m-d', $unix);
            }
        }
    }

    return $raw;
}

function spreadsheetRowIsEmpty(array $values): bool
{
    foreach ($values as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

function mapImportConnectionMedium(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $options = connectionMediumOptions();
    if (isset($options[$value])) {
        return $value;
    }

    $normalized = strtolower(preg_replace('/[\s\-]+/', ' ', $value) ?? $value);
    $normalized = trim($normalized);

    $map = [
        'fiber_olt'              => 'fiber_olt',
        'fiber olt'              => 'fiber_olt',
        'fiber optic (olt)'      => 'fiber_olt',
        'fiber optic olt'        => 'fiber_olt',
        'olt'                    => 'fiber_olt',
        'fiber_mediacon'         => 'fiber_mediacon',
        'fiber mediacon'         => 'fiber_mediacon',
        'fiber optic (mediacon)' => 'fiber_mediacon',
        'fiber optic mediacon'   => 'fiber_mediacon',
        'mediacon'               => 'fiber_mediacon',
        'wireless_radio'         => 'wireless_radio',
        'wireless radio'         => 'wireless_radio',
        'wireless'               => 'wireless_radio',
        'radio'                  => 'wireless_radio',
    ];

    foreach ($options as $key => $label) {
        if (strtolower($label) === $normalized) {
            return $key;
        }
    }

    return $map[$normalized] ?? '';
}

function parseImportDate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    // Excel-style or common PH formats
    $formats = ['m/d/Y', 'n/j/Y', 'd/m/Y', 'j/n/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y', 'M d, Y', 'F d, Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return '';
}

function resolveImportPlanId(string $planName, array $plansByName): int
{
    $planName = trim($planName);
    if ($planName === '') {
        return 0;
    }

    $key = strtolower($planName);
    if (isset($plansByName[$key])) {
        return (int) $plansByName[$key];
    }

    // Partial match: "Plan 500" vs "500"
    foreach ($plansByName as $name => $id) {
        if ($name === $key || str_contains($name, $key) || str_contains($key, $name)) {
            return (int) $id;
        }
    }

    return 0;
}

function getActivePlansByName(): array
{
    $plans = getDB()->query(
        'SELECT id, name FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee, name'
    )->fetchAll();

    $byName = [];
    foreach ($plans as $plan) {
        $byName[strtolower(trim($plan['name']))] = (int) $plan['id'];
    }

    return $byName;
}

/**
 * Validate and normalize one import row.
 *
 * @return array{ok: bool, data?: array, errors: string[], preview: array}
 */
function prepareSubscriberImportRow(array $row, array $plansByName): array
{
    $preview = [
        'full_name'         => trim((string) ($row['full_name'] ?? '')),
        'phone'             => trim((string) ($row['phone'] ?? '')),
        'email'             => trim((string) ($row['email'] ?? '')),
        'plan'              => trim((string) ($row['plan'] ?? '')),
        'connection_medium' => trim((string) ($row['connection_medium'] ?? '')),
        'installation_date' => trim((string) ($row['installation_date'] ?? '')),
        'address'           => trim((string) ($row['address'] ?? '')),
        'barangay'          => trim((string) ($row['barangay'] ?? '')),
        'city'              => trim((string) ($row['city'] ?? '')),
        'province'          => trim((string) ($row['province'] ?? '')),
        'notes'             => trim((string) ($row['notes'] ?? '')),
        'status'            => trim((string) ($row['status'] ?? 'active')),
    ];

    $medium = mapImportConnectionMedium($preview['connection_medium']);
    $installDate = parseImportDate($preview['installation_date']);
    $planId = resolveImportPlanId($preview['plan'], $plansByName);
    $status = strtolower($preview['status'] ?: 'active');
    if (!in_array($status, ['active', 'suspended', 'disconnected'], true)) {
        $status = '';
    }

    $data = normalizeCustomerFormData([
        'full_name'         => $preview['full_name'],
        'phone'             => $preview['phone'],
        'email'             => $preview['email'],
        'connection_medium' => $medium,
        'address'           => $preview['address'],
        'barangay'          => $preview['barangay'],
        'city'              => $preview['city'],
        'province'          => $preview['province'],
        'plan_id'           => $planId,
        'installation_date' => $installDate,
        'status'            => $status ?: 'active',
        'notes'             => $preview['notes'],
    ]);

    $errors = [];
    if (!$data['full_name']) {
        $errors[] = 'Full name is required.';
    }
    if (!$data['phone']) {
        $errors[] = 'Phone is required.';
    }
    if (!$data['plan_id']) {
        $errors[] = $preview['plan'] === ''
            ? 'Service plan is required.'
            : 'Unknown service plan "' . $preview['plan'] . '".';
    }
    if (!$installDate) {
        $errors[] = $preview['installation_date'] === ''
            ? 'Installation date is required.'
            : 'Invalid installation date.';
    }
    if ($preview['status'] !== '' && $status === '') {
        $errors[] = 'Status must be active, suspended, or disconnected.';
    }
    $errors = array_merge($errors, validateCustomerAddressFields($data));

    $preview['connection_medium'] = $medium ?: $preview['connection_medium'];
    $preview['installation_date'] = $installDate ?: $preview['installation_date'];
    $preview['status'] = $data['status'];
    $preview['plan_id'] = $data['plan_id'];

    return [
        'ok'      => empty($errors),
        'data'    => $data,
        'errors'  => $errors,
        'preview' => $preview,
    ];
}

function insertImportedSubscriber(array $data, int $createdBy): string
{
    $accountNumber = generateAccountNumber();
    $db = getDB();

    $stmt = $db->prepare(
        'INSERT INTO customers
            (account_number, full_name, email, phone, connection_medium, address, barangay, city, province,
             plan_id, installation_date, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $accountNumber,
        $data['full_name'],
        $data['email'] ?: null,
        $data['phone'],
        $data['connection_medium'],
        $data['address'],
        $data['barangay'] ?: null,
        $data['city'],
        $data['province'],
        $data['plan_id'],
        $data['installation_date'],
        $data['status'] ?: 'active',
        $data['notes'] ?: null,
        $createdBy,
    ]);

    $customerId = (int) $db->lastInsertId();
    recordCustomerPlanStart(
        $customerId,
        (int) $data['plan_id'],
        $data['installation_date'] . ' 00:00:00',
        $createdBy,
        'Imported initial plan'
    );

    return $accountNumber;
}

function buildSubscriberImportTemplateRows(?array $planNames = null): array
{
    $planNames = array_values(array_filter(array_map('trim', $planNames ?? [])));
    $plan1 = $planNames[0] ?? 'Plan 500';
    $plan2 = $planNames[1] ?? ($planNames[0] ?? 'Plan 1000');
    $plan3 = $planNames[2] ?? ($planNames[0] ?? 'Basic 5Mbps');
    $today = date('Y-m-d');

    return [
        [
            'Juan Dela Cruz',
            '09171234567',
            'juan@example.com',
            $plan1,
            'fiber_olt',
            $today,
            'Purok 1, Main Street',
            'Maralag',
            'Dumingag',
            'Zamboanga Del Sur',
            'Sample row — replace with real data',
            'active',
        ],
        [
            'Maria Santos',
            '09181234567',
            '',
            $plan2,
            'fiber_mediacon',
            $today,
            'Purok 3, Riverside',
            'Maralag',
            'Dumingag',
            'Zamboanga Del Sur',
            '',
            'active',
        ],
        [
            'Pedro Reyes',
            '09201234567',
            'pedro@example.com',
            $plan3,
            'wireless_radio',
            $today,
            'Sitio Lawis',
            'Maralag',
            'Dumingag',
            'Zamboanga Del Sur',
            '',
            'active',
        ],
    ];
}

function buildSubscriberImportTemplateCsv(?array $planNames = null): string
{
    $headers = subscriberImportExpectedHeaders();
    $rows = buildSubscriberImportTemplateRows($planNames);
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        throw new RuntimeException('Unable to build CSV template.');
    }

    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    if ($csv === false) {
        throw new RuntimeException('Unable to read CSV template.');
    }

    // UTF-8 BOM so Excel opens accents/headers correctly
    return "\xEF\xBB\xBF" . $csv;
}

function buildSubscriberImportTemplateXlsx(?array $planNames = null): string
{
    $headers = subscriberImportExpectedHeaders();
    $samples = buildSubscriberImportTemplateRows($planNames);
    $planList = $planNames
        ? implode(', ', $planNames)
        : 'Use exact active plan names from Service Plans';

    $escape = static function (string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    $buildRowXml = static function (int $rowNum, array $values) use ($escape): string {
        $cells = '';
        foreach (array_values($values) as $col => $value) {
            $ref = xlsxColumnLetters($col) . $rowNum;
            $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escape((string) $value) . '</t></is></c>';
        }
        return '<row r="' . $rowNum . '">' . $cells . '</row>';
    };

    $sheetRows = $buildRowXml(1, $headers);
    $rowNum = 2;
    foreach ($samples as $sample) {
        $sheetRows .= $buildRowXml($rowNum, $sample);
        $rowNum++;
    }

    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $instructions = [
        ['SHAVEN Networks — Subscriber Import Template'],
        [''],
        ['How to use'],
        ['1. Open the "Subscribers" sheet.'],
        ['2. Keep the header row (row 1) unchanged.'],
        ['3. Replace the sample rows with real subscriber data.'],
        ['4. Save as .xlsx (or CSV) and upload on Import Subscribers.'],
        [''],
        ['Required columns'],
        ['full_name — Subscriber full name'],
        ['phone — Contact number'],
        ['plan — Must match an active service plan name'],
        ['connection_medium — fiber_olt, fiber_mediacon, wireless_radio (or OLT / Mediacon / Wireless Radio)'],
        ['installation_date — Prefer YYYY-MM-DD'],
        ['address — Street / building address'],
        ['city — City / municipality'],
        ['province — Province'],
        [''],
        ['Optional columns'],
        ['email, barangay, notes, status (active / suspended / disconnected)'],
        [''],
        ['Active plans'],
        [$planList],
    ];

    $instrRows = '';
    foreach ($instructions as $i => $line) {
        $instrRows .= $buildRowXml($i + 1, $line);
    }

    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $instrRows . '</sheetData></worksheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>'
        . '<sheet name="Subscribers" sheetId="1" r:id="rId1"/>'
        . '<sheet name="Instructions" sheetId="2" r:id="rId2"/>'
        . '</sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        . '</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'snxlsx');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temporary template file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Unable to build Excel template.');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);

    if ($bytes === false) {
        throw new RuntimeException('Unable to read Excel template.');
    }

    return $bytes;
}

function xlsxColumnLetters(int $index): string
{
    $letters = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }

    return $letters;
}
