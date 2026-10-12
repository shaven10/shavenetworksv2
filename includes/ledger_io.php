<?php

require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/subscriber_import.php';

function ledgerExportHeaders(): array
{
    return [
        'Date',
        'Date Label',
        'Description',
        'IN',
        'OUT',
        'Row Type',
        'Highlight',
    ];
}

function ledgerImportExpectedHeaders(): array
{
    return [
        'date',
        'date_label',
        'description',
        'in',
        'out',
        'row_type',
        'highlight',
    ];
}

function normalizeLedgerHeader(string $header): string
{
    $header = strtolower(trim($header));
    // Strip BOM if present on first header
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = preg_replace('/[\s\-]+/', '_', $header) ?? $header;
    $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

    $aliases = [
        'entry_date'      => 'date',
        'txn_date'        => 'date',
        'transaction_date'=> 'date',
        'date_display'    => 'date_label',
        'display_date'    => 'date_label',
        'label'           => 'date_label',
        'desc'            => 'description',
        'particulars'     => 'description',
        'amount_in'       => 'in',
        'hatagon'         => 'in',
        'hatagon_beben'   => 'in',
        'sweldo'          => 'in',
        'credit'          => 'in',
        'amount_out'      => 'out',
        'utang'           => 'out',
        'debit'           => 'out',
        'cash_advance'    => 'out',
        'type'            => 'row_type',
        'row'             => 'row_type',
        'entry_type'      => 'row_type',
        'hl'              => 'highlight',
        'mark'            => 'highlight',
    ];

    return $aliases[$header] ?? $header;
}

function clearLedgerEntries(int $ledgerId): void
{
    ensureEmployeeLedgerTables();
    getDB()->prepare('DELETE FROM employee_ledger_entries WHERE ledger_id = ?')->execute([$ledgerId]);
    touchEmployeeLedger($ledgerId);
}

function buildLedgerExportRows(array $ledger, array $entries): array
{
    $rows = [ledgerExportHeaders()];

    foreach ($entries as $entry) {
        $type = (string) ($entry['row_type'] ?? 'entry');
        $in = $type === 'entry' ? (float) $entry['amount_in'] : 0.0;
        $out = $type === 'entry' ? (float) $entry['amount_out'] : 0.0;

        $rows[] = [
            (string) ($entry['entry_date'] ?? ''),
            (string) ($entry['date_display'] ?? ''),
            (string) ($entry['description'] ?? ''),
            $in > 0 ? $in : '',
            $out > 0 ? $out : '',
            $type,
            (string) ($entry['highlight'] ?? 'none'),
        ];
    }

    return $rows;
}

function buildLedgerMetaRows(array $ledger, array $totals = [], string $generatedAt = ''): array
{
    return [
        ['Field', 'Value'],
        ['Employee Name', (string) ($ledger['employee_name'] ?? '')],
        ['Title', (string) ($ledger['title'] ?? '')],
        ['IN Label', (string) ($ledger['in_label'] ?? '')],
        ['OUT Label', (string) ($ledger['out_label'] ?? '')],
        ['Balance As Of', (string) ($ledger['balance_as_of'] ?? '')],
        ['Notes', (string) ($ledger['notes'] ?? '')],
        ['Total IN', (float) ($totals['total_in'] ?? 0)],
        ['Total OUT', (float) ($totals['total_out'] ?? 0)],
        ['UTANGAN', (float) ($totals['balance'] ?? 0)],
        ['Generated', $generatedAt !== '' ? $generatedAt : date('Y-m-d H:i:s')],
    ];
}

function buildLedgerCsv(array $ledger, array $entries): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        throw new RuntimeException('Unable to build CSV export.');
    }

    $meta = [
        'employee_name' => (string) ($ledger['employee_name'] ?? ''),
        'title'         => (string) ($ledger['title'] ?? ''),
        'in_label'      => (string) ($ledger['in_label'] ?? ''),
        'out_label'     => (string) ($ledger['out_label'] ?? ''),
        'balance_as_of' => (string) ($ledger['balance_as_of'] ?? ''),
        'notes'         => (string) ($ledger['notes'] ?? ''),
    ];

    foreach ($meta as $key => $value) {
        fwrite($out, '# ' . $key . ': ' . str_replace(["\r", "\n"], ' ', $value) . "\n");
    }
    fwrite($out, "#\n");

    foreach (buildLedgerExportRows($ledger, $entries) as $row) {
        fputcsv($out, $row);
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    if ($csv === false) {
        throw new RuntimeException('Unable to read CSV export.');
    }

    return "\xEF\xBB\xBF" . $csv;
}

function buildLedgerExcel(array $ledger, array $entries, array $meta = []): string
{
    $totals = getLedgerTotals($entries);
    $generatedAt = (string) ($meta['generated_at'] ?? date('Y-m-d H:i:s'));

    return buildWorkbookXlsx([
        'Entries' => buildXlsxSheetXml(buildLedgerExportRows($ledger, $entries)),
        'Meta'    => buildXlsxSheetXml(buildLedgerMetaRows($ledger, $totals, $generatedAt)),
    ]);
}

function buildLedgerImportTemplateCsv(): string
{
    $sampleLedger = [
        'employee_name' => 'Nonoy Balansi',
        'title'         => 'NONOY BALANSI Sample',
        'in_label'      => 'Hatagon Beben',
        'out_label'     => 'Utang Nonoy',
        'balance_as_of' => date('Y-m-d'),
        'notes'         => 'Sample import template',
    ];

    $sampleEntries = [
        [
            'entry_date'   => '2025-06-15',
            'date_display' => '',
            'description'  => 'Opening balance',
            'amount_in'    => 0,
            'amount_out'   => 1000,
            'row_type'     => 'entry',
            'highlight'    => 'opening',
        ],
        [
            'entry_date'   => '',
            'date_display' => 'Month of June 2025',
            'description'  => 'MONTLY SWELDO',
            'amount_in'    => 5000,
            'amount_out'   => 0,
            'row_type'     => 'entry',
            'highlight'    => 'none',
        ],
        [
            'entry_date'   => '',
            'date_display' => '',
            'description'  => 'Year 2026',
            'amount_in'    => 0,
            'amount_out'   => 0,
            'row_type'     => 'year_header',
            'highlight'    => 'year',
        ],
    ];

    return buildLedgerCsv($sampleLedger, $sampleEntries);
}

function buildLedgerImportTemplateXlsx(): string
{
    $sampleLedger = [
        'employee_name' => 'Nonoy Balansi',
        'title'         => 'NONOY BALANSI Sample',
        'in_label'      => 'Hatagon Beben',
        'out_label'     => 'Utang Nonoy',
        'balance_as_of' => date('Y-m-d'),
        'notes'         => 'Sample import template — edit Meta sheet, then Entries.',
    ];

    $sampleEntries = [
        [
            'entry_date'   => '2025-06-15',
            'date_display' => '',
            'description'  => 'Opening balance',
            'amount_in'    => 0,
            'amount_out'   => 1000,
            'row_type'     => 'entry',
            'highlight'    => 'opening',
        ],
        [
            'entry_date'   => '',
            'date_display' => 'Month of June 2025',
            'description'  => 'MONTLY SWELDO',
            'amount_in'    => 5000,
            'amount_out'   => 0,
            'row_type'     => 'entry',
            'highlight'    => 'none',
        ],
        [
            'entry_date'   => '',
            'date_display' => '',
            'description'  => 'Year 2026',
            'amount_in'    => 0,
            'amount_out'   => 0,
            'row_type'     => 'year_header',
            'highlight'    => 'year',
        ],
    ];

    return buildLedgerExcel($sampleLedger, $sampleEntries, [
        'generated_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * @return array{meta: array<string,string>, rows: array<int, array<string,string>>}
 */
function readLedgerSpreadsheet(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return readLedgerCsv($path);
    }

    if ($ext === 'xlsx') {
        return readLedgerXlsx($path);
    }

    throw new RuntimeException('Unsupported file type. Please upload an .xlsx or .csv file.');
}

/**
 * @return array{meta: array<string,string>, rows: array<int, array<string,string>>}
 */
function readLedgerCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open CSV file.');
    }

    $meta = [];
    $headers = [];
    $rows = [];
    $line = 0;
    $headerFound = false;

    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        $first = trim((string) ($data[0] ?? ''));

        // Strip UTF-8 BOM from first cell of file
        if ($line === 1) {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
            $data[0] = $first;
        }

        if (!$headerFound && ($first === '' || str_starts_with($first, '#'))) {
            if (str_starts_with($first, '#')) {
                $comment = ltrim(substr($first, 1));
                if (preg_match('/^([a-z_]+)\s*:\s*(.*)$/i', $comment, $m)) {
                    $key = normalizeLedgerMetaKey($m[1]);
                    if ($key !== '') {
                        $meta[$key] = trim($m[2]);
                    }
                }
            }
            continue;
        }

        if (!$headerFound) {
            $headers = array_map(
                static fn($h) => normalizeLedgerHeader((string) $h),
                $data
            );
            $headerFound = true;
            continue;
        }

        if (spreadsheetRowIsEmpty($data)) {
            continue;
        }

        // Skip TOTAL / GRAND TOTAL / UTANGAN summary rows if present
        $descProbe = strtolower(trim((string) ($data[array_search('description', $headers, true)] ?? $data[1] ?? '')));
        if (preg_match('/^(total|grand\s*total|utangan)\b/', $descProbe)) {
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

    if (!$headerFound || !$headers) {
        throw new RuntimeException('CSV file has no header row.');
    }

    return ['meta' => $meta, 'rows' => $rows];
}

/**
 * @return array{meta: array<string,string>, rows: array<int, array<string,string>>}
 */
function readLedgerXlsx(string $path): array
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

    $sheetFiles = [];
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $nameToPath = [];

    if ($workbookXml !== false && $relsXml !== false) {
        $rels = @simplexml_load_string($relsXml);
        $idToTarget = [];
        if ($rels) {
            foreach ($rels->Relationship as $rel) {
                $id = (string) ($rel['Id'] ?? '');
                $target = str_replace('\\', '/', (string) ($rel['Target'] ?? ''));
                if ($id === '' || $target === '') {
                    continue;
                }
                $target = ltrim($target, '/');
                if (!str_starts_with($target, 'xl/')) {
                    $target = 'xl/' . $target;
                }
                $idToTarget[$id] = $target;
            }
        }

        $wb = @simplexml_load_string($workbookXml);
        if ($wb) {
            foreach ($wb->sheets->sheet ?? [] as $sheet) {
                $name = (string) ($sheet['name'] ?? '');
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string) ($attrs['id'] ?? '');
                if ($name !== '' && $rid !== '' && isset($idToTarget[$rid])) {
                    $nameToPath[strtolower($name)] = $idToTarget[$rid];
                }
            }
        }
    }

    // Fallback sheet discovery
    if (!$nameToPath) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $m)) {
                $sheetFiles[(int) $m[1]] = $name;
            }
        }
        ksort($sheetFiles);
        $idx = 1;
        foreach ($sheetFiles as $pathName) {
            $nameToPath['sheet' . $idx] = $pathName;
            $idx++;
        }
    }

    $entriesPath = $nameToPath['entries'] ?? ($nameToPath['sheet1'] ?? reset($nameToPath) ?: null);
    $metaPath = $nameToPath['meta'] ?? ($nameToPath['sheet2'] ?? null);

    if (!$entriesPath) {
        $zip->close();
        throw new RuntimeException('Excel worksheet not found.');
    }

    $entriesXml = $zip->getFromName($entriesPath);
    $metaXml = $metaPath ? $zip->getFromName($metaPath) : false;
    $zip->close();

    if ($entriesXml === false) {
        throw new RuntimeException('Excel Entries sheet not found.');
    }

    $meta = [];
    if ($metaXml !== false) {
        $metaMatrix = parseXlsxSheetMatrix($metaXml, $sharedStrings);
        foreach ($metaMatrix as $cells) {
            $key = normalizeLedgerMetaKey((string) ($cells[0] ?? ''));
            $value = trim((string) ($cells[1] ?? ''));
            if ($key === '' || $key === 'field' || $key === 'generated' || str_starts_with($key, 'total') || $key === 'utangan') {
                continue;
            }
            $meta[$key] = $value;
        }
    }

    $matrix = parseXlsxSheetMatrix($entriesXml, $sharedStrings);
    if (!$matrix) {
        throw new RuntimeException('Excel Entries sheet has no data rows.');
    }

    ksort($matrix);
    $firstRowNum = array_key_first($matrix);
    $headerCells = $matrix[$firstRowNum] ?? [];
    ksort($headerCells);
    $maxCol = $headerCells ? max(array_keys($headerCells)) : -1;

    $headers = [];
    for ($col = 0; $col <= $maxCol; $col++) {
        $headers[$col] = normalizeLedgerHeader((string) ($headerCells[$col] ?? ''));
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

        $desc = strtolower((string) ($row['description'] ?? ''));
        if (preg_match('/^(total|grand\s*total|utangan)\b/', $desc)) {
            continue;
        }

        $rows[$rowNum] = $row;
    }

    return ['meta' => $meta, 'rows' => $rows];
}

/**
 * @return array<int, array<int, string>>
 */
function parseXlsxSheetMatrix(string $sheetXml, array $sharedStrings): array
{
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

    return $matrix;
}

function normalizeLedgerMetaKey(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[\s\-]+/', '_', $key) ?? $key;
    $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? $key;

    $aliases = [
        'employee'         => 'employee_name',
        'name'             => 'employee_name',
        'employee_name'    => 'employee_name',
        'ledger_title'     => 'title',
        'in_label'         => 'in_label',
        'hatagon_label'    => 'in_label',
        'out_label'        => 'out_label',
        'utang_label'      => 'out_label',
        'balance_as_of'    => 'balance_as_of',
        'as_of'            => 'balance_as_of',
        'note'             => 'notes',
        'notes'            => 'notes',
        'title'            => 'title',
    ];

    return $aliases[$key] ?? $key;
}

function parseLedgerMoney(string $value): float
{
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return 0.0;
    }
    $value = str_replace([',', '₱', 'PHP', ' '], '', $value);
    if (!is_numeric($value)) {
        return 0.0;
    }
    return max(0.0, (float) $value);
}

function parseLedgerImportDate(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    // Excel serial
    if (is_numeric($value) && strpos($value, '.') === false) {
        $serial = (int) $value;
        if ($serial > 20000 && $serial < 80000) {
            $unix = ($serial - 25569) * 86400;
            if ($unix > 0) {
                return gmdate('Y-m-d', $unix);
            }
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function inferLedgerRowType(string $description, string $rowType, float $in, float $out): string
{
    $rowType = strtolower(trim($rowType));
    $map = [
        'entry'       => 'entry',
        'transaction' => 'entry',
        'txn'         => 'entry',
        'year'        => 'year_header',
        'year_header' => 'year_header',
        'header'      => 'year_header',
        'end'         => 'end_marker',
        'end_marker'  => 'end_marker',
        'nothing'     => 'end_marker',
        'footer'      => 'end_marker',
    ];
    if (isset($map[$rowType])) {
        return $map[$rowType];
    }

    $desc = strtolower(trim($description));
    if (preg_match('/^year\s+\d{4}$/', $desc) || preg_match('/^year\s*20\d{2}/', $desc)) {
        return 'year_header';
    }
    if (str_contains($desc, 'nothing follows') || preg_match('/^x-x-x/', $desc)) {
        return 'end_marker';
    }
    if ($in <= 0 && $out <= 0 && preg_match('/^year\b/i', $description)) {
        return 'year_header';
    }

    return 'entry';
}

function inferLedgerHighlight(string $highlight, string $rowType, string $description): string
{
    $highlight = strtolower(trim($highlight));
    $map = [
        'none'    => 'none',
        ''        => 'none',
        'opening' => 'opening',
        'open'    => 'opening',
        'balance' => 'opening',
        'year'    => 'year',
        'yellow'  => 'year',
    ];
    if (isset($map[$highlight])) {
        $highlight = $map[$highlight];
    } else {
        $highlight = 'none';
    }

    if ($rowType === 'year_header') {
        return 'year';
    }

    $desc = strtolower($description);
    if ($highlight === 'none' && (str_contains($desc, 'prev balansi') || str_contains($desc, 'opening') || str_contains($desc, 'prev balance'))) {
        return 'opening';
    }

    return $highlight;
}

/**
 * Validate one import row into entry payload.
 *
 * @return array{ok:bool, errors:string[], data:?array, preview:array}
 */
function prepareLedgerImportRow(array $row): array
{
    $preview = [
        'date'        => trim((string) ($row['date'] ?? '')),
        'date_label'  => trim((string) ($row['date_label'] ?? '')),
        'description' => trim((string) ($row['description'] ?? '')),
        'in'          => trim((string) ($row['in'] ?? '')),
        'out'         => trim((string) ($row['out'] ?? '')),
        'row_type'    => trim((string) ($row['row_type'] ?? '')),
        'highlight'   => trim((string) ($row['highlight'] ?? '')),
    ];

    $errors = [];
    $amountIn = parseLedgerMoney($preview['in']);
    $amountOut = parseLedgerMoney($preview['out']);
    $rowType = inferLedgerRowType($preview['description'], $preview['row_type'], $amountIn, $amountOut);
    $highlight = inferLedgerHighlight($preview['highlight'], $rowType, $preview['description']);

    if ($preview['description'] === '' && $rowType === 'entry') {
        $errors[] = 'Description is required.';
    }

    $entryDate = parseLedgerImportDate($preview['date']);
    if ($preview['date'] !== '' && $entryDate === null) {
        $errors[] = 'Invalid date: ' . $preview['date'];
    }

    $dateDisplay = $preview['date_label'] !== '' ? $preview['date_label'] : null;

    // Month-of rows often only have date label
    if ($rowType === 'entry' && $entryDate === null && $dateDisplay === null && $preview['date'] !== '') {
        $dateDisplay = $preview['date'];
        $entryDate = null;
    }

    if ($rowType === 'entry' && $amountIn <= 0 && $amountOut <= 0) {
        $errors[] = 'Enter an IN or OUT amount.';
    }

    if ($rowType === 'year_header' && $preview['description'] === '') {
        $errors[] = 'Year header description is required.';
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'data' => null, 'preview' => $preview];
    }

    if ($rowType === 'end_marker' && $preview['description'] === '') {
        $preview['description'] = 'x-x-x-nothing follows-x-x-x';
    }

    return [
        'ok'      => true,
        'errors'  => [],
        'preview' => $preview,
        'data'    => [
            'entry_date'   => $entryDate,
            'date_display' => $dateDisplay,
            'description'  => $preview['description'],
            'amount_in'    => $amountIn,
            'amount_out'   => $amountOut,
            'row_type'     => $rowType,
            'highlight'    => $highlight,
        ],
    ];
}

/**
 * Import prepared entries into a ledger.
 *
 * @param array<int, array> $preparedEntries list of data payloads from prepareLedgerImportRow
 * @return int number of entries inserted
 */
function importLedgerEntries(int $ledgerId, array $preparedEntries, bool $replace = true): int
{
    if (!getEmployeeLedger($ledgerId)) {
        throw new RuntimeException('Ledger not found.');
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        if ($replace) {
            clearLedgerEntries($ledgerId);
            $order = 1;
        } else {
            $order = nextLedgerSortOrder($ledgerId);
        }

        foreach ($preparedEntries as $payload) {
            $payload['sort_order'] = $order++;
            addLedgerEntry($ledgerId, $payload);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return count($preparedEntries);
}

function ledgerExportFilename(array $ledger, string $ext): string
{
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', (string) ($ledger['employee_name'] ?? 'ledger')) ?? 'ledger';
    $name = trim(strtolower($name), '-');
    return 'ledger-' . ($name !== '' ? $name : 'export') . '-' . date('Ymd-His') . '.' . $ext;
}
