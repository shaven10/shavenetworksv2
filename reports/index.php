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
