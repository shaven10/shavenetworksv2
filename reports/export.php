<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/reports.php';
requireRole('owner');

$month = $_GET['month'] ?? date('Y-m');
$format = strtolower((string) ($_GET['format'] ?? ''));
$data = getReportsData($month);
$bounds = $data['bounds'];
$slug = 'report-' . $bounds['month'];

if ($format === 'excel' || $format === 'xlsx') {
    $bytes = buildReportsExcel($data);
    $filename = $slug . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

if ($format === 'csv') {
    $out = fopen('php://temp', 'r+');
    fputcsv($out, ['SHAVEN Networks Monthly Report', $bounds['label']]);
    fputcsv($out, ['Generated', $data['generated_at']]);
    fputcsv($out, []);
    fputcsv($out, ['Collections Total', $data['monthly_collections']['total']]);
    fputcsv($out, ['Payments Received', $data['monthly_collections']['count']]);
    fputcsv($out, ['Total Overdue', $data['overdue_total']]);
    fputcsv($out, []);
    fputcsv($out, ['Payment Method', 'Count', 'Total']);
    foreach ($data['by_method'] as $row) {
        fputcsv($out, [
            paymentMethodLabel((string) $row['payment_method']),
            $row['count'],
            $row['total'],
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Collector', 'Count', 'Total']);
    foreach ($data['by_collector'] as $row) {
        fputcsv($out, [$row['full_name'], $row['count'], $row['total']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Plan', 'Monthly Fee', 'Subscribers', 'Active', 'Potential Revenue']);
    foreach ($data['plan_stats'] as $row) {
        fputcsv($out, [
            $row['name'],
            $row['monthly_fee'],
            $row['subscribers'],
            $row['active_subs'],
            (float) $row['monthly_fee'] * (int) $row['active_subs'],
        ]);
    }
    rewind($out);
    $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
    fclose($out);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

// PDF / print-ready document
$pageTitle = 'Report ' . $bounds['label'];
$autoPrint = ($format === 'pdf');
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1>Export Report</h1>
        <p><?= e($bounds['label']) ?> — print or save as PDF</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
        <a href="<?= APP_URL ?>/reports/export.php?format=excel&month=<?= e(urlencode($bounds['month'])) ?>" class="btn btn-outline">Download Excel</a>
        <a href="<?= APP_URL ?>/reports/index.php?month=<?= e(urlencode($bounds['month'])) ?>" class="btn btn-outline">← Back</a>
    </div>
</div>

<?php if ($autoPrint): ?>
<div class="alert alert-info no-print">
    Use your browser print dialog and choose <strong>Save as PDF</strong> / <strong>Microsoft Print to PDF</strong>.
</div>
<?php endif; ?>

<?php renderReportDocument($data, true); ?>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
