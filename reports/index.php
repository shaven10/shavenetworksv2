<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/reports.php';
requireRole('owner');

$pageTitle = 'Reports';
$currentPage = 'reports';

$month = $_GET['month'] ?? date('Y-m');
$data = getReportsData($month);
$bounds = $data['bounds'];

$reportCharts = [
    'methods' => [
        'labels' => array_map(
            static fn(array $row): string => paymentMethodLabel((string) $row['payment_method']),
            $data['by_method']
        ),
        'values' => array_map(static fn(array $row): float => (float) $row['total'], $data['by_method']),
        'links' => array_map(
            static fn(array $row): string => APP_URL . '/payments/index.php?payment_method='
                . urlencode((string) $row['payment_method'])
                . '&from=' . urlencode($bounds['month_start'])
                . '&to=' . urlencode($bounds['month_end']),
            $data['by_method']
        ),
    ],
    'collectors' => [
        'labels' => array_column($data['by_collector'], 'full_name'),
        'values' => array_map(static fn(array $row): float => (float) $row['total'], $data['by_collector']),
        'links' => array_fill(
            0,
            count($data['by_collector']),
            APP_URL . '/payments/index.php?from=' . urlencode($bounds['month_start'])
                . '&to=' . urlencode($bounds['month_end'])
        ),
    ],
    'plans' => [
        'labels' => array_column($data['plan_stats'], 'name'),
        'values' => array_map(static fn(array $row): int => (int) $row['active_subs'], $data['plan_stats']),
        'links' => array_map(
            static fn(array $row): string => APP_URL . '/customers/index.php?plan_id=' . (int) $row['id'],
            $data['plan_stats']
        ),
    ],
];

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
    . '<script>window.reportChartData = ' . json_encode($reportCharts) . ';</script>'
    . '<script src="' . APP_URL . '/assets/js/reports.js"></script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1>Reports</h1>
        <p>Revenue and collection analytics</p>
    </div>
    <div class="header-actions report-toolbar">
        <form method="GET" class="inline-form">
            <input type="month" name="month" value="<?= e($bounds['month']) ?>" onchange="this.form.submit()">
        </form>
        <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
        <a href="<?= APP_URL ?>/reports/export.php?format=pdf&month=<?= e(urlencode($bounds['month'])) ?>"
           class="btn btn-outline" target="_blank" rel="noopener">Export PDF</a>
        <a href="<?= APP_URL ?>/reports/export.php?format=excel&month=<?= e(urlencode($bounds['month'])) ?>"
           class="btn btn-primary">Export Excel</a>
    </div>
</div>

<?php renderReportDocument($data, false); ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
