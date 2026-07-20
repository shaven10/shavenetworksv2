<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$pageTitle = 'Generate Bills';
$currentPage = 'billing';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = generateAllBills();
    logActivity('bills_generated', "Generated {$result['generated']} bills, skipped {$result['skipped']}");
    flash('success', "Generated {$result['generated']} new bill(s). {$result['skipped']} customer(s) had no new bills (already up to date or inactive).");
    redirect('/billing/index.php');
}

$activeCount = (int) getDB()->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Generate Monthly Bills</h1>
    <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card">
    <div class="info-box">
        <h3>How Installation-Date Billing Works</h3>
        <ul>
            <li>Each customer's billing cycle is anchored to their <strong>installation date</strong>.</li>
            <li>Example: Installed on Jan 15 → billing period runs 15th to 14th of the next month.</li>
            <li>Due date is set to <strong>7 days after</strong> the billing period ends.</li>
            <li>Only <strong>active</strong> customers receive bills.</li>
            <li>Generates <strong>all missing periods</strong> from installation through the current month.</li>
            <li>Periods that already have a bill are automatically skipped.</li>
        </ul>
    </div>

    <p>Ready to generate bills for <strong><?= $activeCount ?></strong> active customer(s).</p>

    <form method="POST" onsubmit="return confirm('Generate bills for all active customers?');">
        <button type="submit" class="btn btn-primary btn-lg">Generate Bills Now</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
