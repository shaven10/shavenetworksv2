<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/reports.php';

requireLogin();
if (!canAccess('customers')) {
    http_response_code(403);
    die('Access denied.');
}

$search = trim($_GET['search'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$planId = (int) ($_GET['plan_id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));

$customers = getCustomersForExport($search, $status, $planId);
$filtersLabel = describeCustomerExportFilters($search, $status, $planId);
$generatedAt = date('Y-m-d H:i:s');
$slug = 'customers-' . date('Ymd-His');

$query = http_build_query(array_filter([
    'search'  => $search,
    'status'  => $status,
    'plan_id' => $planId ?: null,
], static fn ($v) => $v !== null && $v !== ''));

if ($format === 'excel' || $format === 'xlsx') {
    $bytes = buildCustomersExcel($customers, [
        'generated_at'  => $generatedAt,
        'filters_label' => $filtersLabel,
    ]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $slug . '.xlsx"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

if ($format === 'csv') {
    $csv = buildCustomersCsv($customers);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.csv"');
    header('Content-Length: ' . strlen($csv));
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

$pageTitle = 'Export Customers';
$autoPrint = ($format === 'pdf' || $format === 'print');
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1>Export Customers</h1>
        <p><?= e($filtersLabel) ?> · <?= number_format(count($customers)) ?> records</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
        <a href="<?= APP_URL ?>/customers/export.php?format=excel&<?= e($query) ?>" class="btn btn-outline">Download Excel</a>
        <a href="<?= APP_URL ?>/customers/index.php?<?= e($query) ?>" class="btn btn-outline">← Back</a>
    </div>
</div>

<?php if ($autoPrint): ?>
<div class="alert alert-info no-print">
    Use your browser print dialog and choose <strong>Save as PDF</strong> / <strong>Microsoft Print to PDF</strong>.
</div>
<?php endif; ?>

<?php renderCustomersPrintDocument($customers, $filtersLabel, $generatedAt); ?>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
