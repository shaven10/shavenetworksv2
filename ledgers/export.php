<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ledger_io.php';
requireRole('owner');
ensureEmployeeLedgerTables();

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? ''));

$ledger = getEmployeeLedger($id);
if (!$ledger) {
    flash('danger', 'Ledger not found.');
    redirect('/ledgers/index.php');
}

$entries = getLedgerEntries($id);
$generatedAt = date('Y-m-d H:i:s');

if ($format === 'excel' || $format === 'xlsx') {
    $bytes = buildLedgerExcel($ledger, $entries, ['generated_at' => $generatedAt]);
    $filename = ledgerExportFilename($ledger, 'xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

if ($format === 'csv') {
    $csv = buildLedgerCsv($ledger, $entries);
    $filename = ledgerExportFilename($ledger, 'csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv));
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

$pageTitle = 'Export Ledger';
$currentPage = 'ledgers';
$totals = getLedgerTotals($entries);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Export Ledger</h1>
        <p><?= e($ledger['title']) ?> · <?= number_format(count($entries)) ?> row(s)</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/ledgers/export.php?id=<?= $id ?>&format=excel" class="btn btn-primary">Download Excel</a>
        <a href="<?= APP_URL ?>/ledgers/export.php?id=<?= $id ?>&format=csv" class="btn btn-outline">Download CSV</a>
        <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= $id ?>" class="btn btn-outline">← Back</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Summary</h2></div>
    <div class="table-responsive">
        <table class="table">
            <tbody>
                <tr><th>Employee</th><td><?= e($ledger['employee_name']) ?></td></tr>
                <tr><th>Title</th><td><?= e($ledger['title']) ?></td></tr>
                <tr><th>Total IN</th><td><?= e(number_format($totals['total_in'], 2)) ?></td></tr>
                <tr><th>Total OUT</th><td><?= e(number_format($totals['total_out'], 2)) ?></td></tr>
                <tr><th>UTANGAN</th><td><strong><?= e(number_format($totals['balance'], 2)) ?></strong></td></tr>
            </tbody>
        </table>
    </div>
    <p class="text-muted" style="padding:0 16px 16px;margin:0">
        Excel includes an <strong>Entries</strong> sheet and a <strong>Meta</strong> sheet (labels &amp; totals).
        CSV includes the same entry columns plus <code>#</code> metadata lines at the top.
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
