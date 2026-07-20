<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$pageTitle = 'Generate Current Month Bills';
$currentPage = 'billing';

$activeCount = (int) getDB()->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
$periodLabel = date('F Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = generateCurrentPeriodBillsForActiveCustomers();
    logActivity(
        'bills_generated_current',
        "Generated {$result['generated']} current-period bill(s) for {$periodLabel}, skipped {$result['skipped']}"
    );

    if ($result['generated'] > 0) {
        flash(
            'success',
            "Generated {$result['generated']} bill(s) for the current billing period ({$periodLabel}). {$result['skipped']} active customer(s) already had a bill or were skipped."
        );
    } else {
        flash(
            'info',
            "No new bills generated for {$periodLabel}. All active customers may already have their current-period bill."
        );
    }

    redirect('/billing/index.php');
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Generate Current Month Bills</h1>
        <p>Create the current billing period only for all active customers</p>
    </div>
    <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline">← Back to Billing</a>
</div>

<div class="card">
    <div class="info-box">
        <h3>Current Period Billing — <?= e($periodLabel) ?></h3>
        <ul>
            <li>Generates <strong>one bill per active customer</strong> for their current billing cycle only.</li>
            <li>Each customer's cycle follows their <strong>installation date</strong> (not the calendar month).</li>
            <li>Customers who already have a bill for the current period are skipped.</li>
            <li>Does not backfill older missing periods — use <a href="<?= APP_URL ?>/billing/generate.php">Generate Bills</a> for that.</li>
            <li>Advance credit is auto-applied after each new bill is created.</li>
        </ul>
    </div>

    <p>Ready to generate current-period bills for <strong><?= $activeCount ?></strong> active customer(s).</p>

    <form method="POST" onsubmit="return confirm('Generate current billing period bills for all <?= $activeCount ?> active customer(s)?');">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">Generate Current Month Bills</button>
            <a href="<?= APP_URL ?>/billing/generate.php" class="btn btn-outline">Full Bill Generation</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
