<?php

function getReportsMonthBounds(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    return [
        'month'       => $month,
        'month_start' => $monthStart,
        'month_end'   => $monthEnd,
        'label'       => date('F Y', strtotime($monthStart)),
    ];
}

function getReportsData(string $month): array
{
    $bounds = getReportsMonthBounds($month);
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
         FROM payments
         WHERE payment_date BETWEEN ? AND ?'
    );
    $stmt->execute([$bounds['month_start'], $bounds['month_end']]);
    $monthlyCollections = $stmt->fetch() ?: ['total' => 0, 'count' => 0];

    $stmt = $db->prepare(
        'SELECT p.payment_method, SUM(p.amount) AS total, COUNT(*) AS count
         FROM payments p
         WHERE p.payment_date BETWEEN ? AND ?
         GROUP BY p.payment_method
         ORDER BY total DESC'
    );
    $stmt->execute([$bounds['month_start'], $bounds['month_end']]);
    $byMethod = $stmt->fetchAll() ?: [];

    $stmt = $db->prepare(
        'SELECT u.full_name, SUM(p.amount) AS total, COUNT(*) AS count
         FROM payments p
         JOIN users u ON p.collected_by = u.id
         WHERE p.payment_date BETWEEN ? AND ?
         GROUP BY p.collected_by
         ORDER BY total DESC'
    );
    $stmt->execute([$bounds['month_start'], $bounds['month_end']]);
    $byCollector = $stmt->fetchAll() ?: [];

    $planStats = $db->query(
        'SELECT p.id, p.name, p.monthly_fee, COUNT(c.id) AS subscribers,
                SUM(CASE WHEN c.status = "active" THEN 1 ELSE 0 END) AS active_subs
         FROM service_plans p
         LEFT JOIN customers c ON p.id = c.plan_id
         GROUP BY p.id
         ORDER BY subscribers DESC'
    )->fetchAll() ?: [];

    $overdueTotal = (float) $db->query(
        "SELECT COALESCE(SUM(amount - paid_amount), 0) FROM bills WHERE status = 'overdue'"
    )->fetchColumn();

    return [
        'bounds'              => $bounds,
        'monthly_collections' => $monthlyCollections,
        'by_method'           => $byMethod,
        'by_collector'        => $byCollector,
        'plan_stats'          => $planStats,
        'overdue_total'       => $overdueTotal,
        'generated_at'        => date('Y-m-d H:i:s'),
    ];
}

function paymentMethodLabel(string $method): string
{
    return ucfirst(str_replace('_', ' ', $method));
}

function xlsxEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsxColLetter(int $index): string
{
    $letter = '';
    $n = $index;
    do {
        $letter = chr(65 + ($n % 26)) . $letter;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $letter;
}

function buildXlsxSheetXml(array $rows): string
{
    $xmlRows = '';
    foreach ($rows as $i => $values) {
        $rowNum = $i + 1;
        $cells = '';
        foreach (array_values($values) as $col => $value) {
            $ref = xlsxColLetter($col) . $rowNum;
            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
            } else {
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                    . xlsxEscape((string) $value) . '</t></is></c>';
            }
        }
        $xmlRows .= '<row r="' . $rowNum . '">' . $cells . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $xmlRows . '</sheetData></worksheet>';
}

function buildReportsExcel(array $data): string
{
    $bounds = $data['bounds'];
    $summary = [
        ['SHAVEN Networks — Monthly Report'],
        ['Period', $bounds['label']],
        ['Generated', $data['generated_at']],
        [''],
        ['Metric', 'Value'],
        ['Collections Total', (float) $data['monthly_collections']['total']],
        ['Payments Received', (int) $data['monthly_collections']['count']],
        ['Total Overdue', (float) $data['overdue_total']],
    ];

    $methods = [['Payment Method', 'Count', 'Total']];
    foreach ($data['by_method'] as $row) {
        $methods[] = [
            paymentMethodLabel((string) $row['payment_method']),
            (int) $row['count'],
            (float) $row['total'],
        ];
    }
    if (count($methods) === 1) {
        $methods[] = ['No data', 0, 0];
    }

    $collectors = [['Collector', 'Count', 'Total']];
    foreach ($data['by_collector'] as $row) {
        $collectors[] = [
            (string) $row['full_name'],
            (int) $row['count'],
            (float) $row['total'],
        ];
    }
    if (count($collectors) === 1) {
        $collectors[] = ['No data', 0, 0];
    }

    $plans = [['Plan', 'Monthly Fee', 'Total Subscribers', 'Active', 'Potential Revenue']];
    foreach ($data['plan_stats'] as $row) {
        $plans[] = [
            (string) $row['name'],
            (float) $row['monthly_fee'],
            (int) $row['subscribers'],
            (int) $row['active_subs'],
            (float) $row['monthly_fee'] * (int) $row['active_subs'],
        ];
    }

    $sheets = [
        'Summary'    => buildXlsxSheetXml($summary),
        'By Method'  => buildXlsxSheetXml($methods),
        'Collectors' => buildXlsxSheetXml($collectors),
        'Plans'      => buildXlsxSheetXml($plans),
    ];

    return buildWorkbookXlsx($sheets);
}

/**
 * @param array<string, string> $sheets Map of sheet name => worksheet XML
 */
function buildWorkbookXlsx(array $sheets): string
{
    if (empty($sheets)) {
        throw new InvalidArgumentException('At least one worksheet is required.');
    }

    $sheetXml = '';
    $relsXml = '';
    $overrides = '';
    $i = 1;
    foreach (array_keys($sheets) as $name) {
        $safeName = xlsxEscape($name);
        $sheetXml .= '<sheet name="' . $safeName . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $relsXml .= '<Relationship Id="rId' . $i . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            . 'Target="worksheets/sheet' . $i . '.xml"/>';
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $i++;
    }

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $sheetXml . '</sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $relsXml . '</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . $overrides
        . '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'snxlsx');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temporary Excel file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Unable to build Excel file.');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

    $i = 1;
    foreach ($sheets as $xml) {
        $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml);
        $i++;
    }
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);

    if ($bytes === false) {
        throw new RuntimeException('Unable to read Excel file.');
    }

    return $bytes;
}

function renderReportDocument(array $data, bool $forPrint = false): void
{
    $bounds = $data['bounds'];
    $monthLabel = $bounds['label'];
    ?>
    <div class="report-document<?= $forPrint ? ' report-document-print' : '' ?>">
        <div class="report-doc-header">
            <div class="report-brand">
                <div class="brand-icon">SN</div>
                <div>
                    <strong>SHAVEN Networks</strong>
                    <small>ISP Billing System</small>
                </div>
            </div>
            <div class="report-doc-meta">
                <h1>Monthly Report</h1>
                <p><?= e($monthLabel) ?></p>
                <small>Generated <?= e(date('M d, Y g:i A', strtotime($data['generated_at']))) ?></small>
            </div>
        </div>

        <div class="stats-grid report-stats">
            <a href="<?= APP_URL ?>/payments/index.php?from=<?= e(urlencode($bounds['month_start'])) ?>&to=<?= e(urlencode($bounds['month_end'])) ?>"
               class="stat-card stat-info dashboard-stat-link" id="report-collections">
                <div class="stat-value"><?= formatMoney((float) $data['monthly_collections']['total']) ?></div>
                <div class="stat-label">Collections (<?= e($monthLabel) ?>)</div>
                <div class="stat-sub">Open payments for this month</div>
            </a>
            <a href="<?= APP_URL ?>/payments/index.php?from=<?= e(urlencode($bounds['month_start'])) ?>&to=<?= e(urlencode($bounds['month_end'])) ?>"
               class="stat-card dashboard-stat-link">
                <div class="stat-value"><?= (int) $data['monthly_collections']['count'] ?></div>
                <div class="stat-label">Payments Received</div>
                <div class="stat-sub">Browse payment records</div>
            </a>
            <a href="<?= APP_URL ?>/billing/index.php?status=overdue" class="stat-card stat-danger dashboard-stat-link" id="report-overdue">
                <div class="stat-value"><?= formatMoney((float) $data['overdue_total']) ?></div>
                <div class="stat-label">Total Overdue</div>
                <div class="stat-sub">View overdue bills</div>
            </a>
        </div>

        <?php if (!$forPrint): ?>
        <div class="charts-grid charts-grid-simple report-charts no-print" id="report-methods">
            <div class="card chart-card dashboard-chart-card">
                <div class="card-header"><h2>Collections by Method</h2></div>
                <div class="chart-wrap chart-wrap-sm"><canvas id="reportChartMethods"></canvas></div>
                <p class="dashboard-chart-hint">Click a bar to filter payments by method</p>
            </div>
            <div class="card chart-card dashboard-chart-card">
                <div class="card-header"><h2>Collections by Collector</h2></div>
                <div class="chart-wrap chart-wrap-sm"><canvas id="reportChartCollectors"></canvas></div>
                <p class="dashboard-chart-hint">Click a bar to browse this month’s payments</p>
            </div>
            <div class="card chart-card dashboard-chart-card">
                <div class="card-header"><h2>Plan Mix</h2></div>
                <div class="chart-wrap chart-wrap-sm"><canvas id="reportChartPlans"></canvas></div>
                <p class="dashboard-chart-hint">Click a segment to browse subscribers on that plan</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid-2 report-grid">
            <div class="card">
                <div class="card-header"><h2>Collections by Payment Method</h2></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if (empty($data['by_method'])): ?>
                            <tr><td colspan="3" class="text-muted text-center">No data.</td></tr>
                            <?php else: foreach ($data['by_method'] as $row): ?>
                            <tr class="dashboard-row-link" tabindex="0" role="link"
                                data-href="<?= APP_URL ?>/payments/index.php?payment_method=<?= e(urlencode((string) $row['payment_method'])) ?>&from=<?= e(urlencode($bounds['month_start'])) ?>&to=<?= e(urlencode($bounds['month_end'])) ?>">
                                <td><?= e(paymentMethodLabel((string) $row['payment_method'])) ?></td>
                                <td><?= (int) $row['count'] ?></td>
                                <td><?= formatMoney((float) $row['total']) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Collections by Collector</h2></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Collector</th><th>Count</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if (empty($data['by_collector'])): ?>
                            <tr><td colspan="3" class="text-muted text-center">No data.</td></tr>
                            <?php else: foreach ($data['by_collector'] as $row): ?>
                            <tr class="dashboard-row-link" tabindex="0" role="link"
                                data-href="<?= APP_URL ?>/payments/index.php?from=<?= e(urlencode($bounds['month_start'])) ?>&to=<?= e(urlencode($bounds['month_end'])) ?>">
                                <td><?= e($row['full_name']) ?></td>
                                <td><?= (int) $row['count'] ?></td>
                                <td><?= formatMoney((float) $row['total']) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Plan Subscribers</h2></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Monthly Fee</th>
                            <th>Total Subscribers</th>
                            <th>Active</th>
                            <th>Potential Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['plan_stats'])): ?>
                        <tr><td colspan="5" class="text-muted text-center">No plans found.</td></tr>
                        <?php else: foreach ($data['plan_stats'] as $row): ?>
                        <tr class="dashboard-row-link" tabindex="0" role="link"
                            data-href="<?= APP_URL ?>/customers/index.php?plan_id=<?= (int) $row['id'] ?>">
                            <td><?= e($row['name']) ?></td>
                            <td><?= formatMoney((float) $row['monthly_fee']) ?></td>
                            <td><?= (int) $row['subscribers'] ?></td>
                            <td><?= (int) $row['active_subs'] ?></td>
                            <td><?= formatMoney((float) $row['monthly_fee'] * (int) $row['active_subs']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
