<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
ensureEmployeeLedgerTables();

$user = currentUser();
$ledger = getVisibleLedgerForUser((int) $user['id']);

if (!$ledger) {
    flash('danger', 'No ledger is available on your account.');
    redirect(hasRole('customer') ? '/portal/index.php' : '/index.php');
}

$pageTitle = 'My Ledger';
$currentPage = 'my_ledger';
$entries = getLedgerEntries((int) $ledger['id']);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1><?= e($ledger['title']) ?></h1>
        <p>Read-only employee balance sheet</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Ledger</button>
    </div>
</div>

<div class="card ledger-print-card" style="overflow:hidden">
    <?php renderEmployeeLedgerSheet($ledger, $entries, false); ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
