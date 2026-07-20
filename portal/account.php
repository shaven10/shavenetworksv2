<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'My Account';
$currentPage = 'my_account';
$customer = getLinkedCustomer();
$id = (int) $customer['id'];

updateOverdueBills();

$showUnpaidReport = ($_GET['status'] ?? '') === 'unpaid';
$pageTitle = $showUnpaidReport ? 'Unpaid Bills Report' : 'My Account';

$period = getBillingPeriod($customer['installation_date']);
$nextBilling = getNextBillingDate($customer['installation_date']);

if ($showUnpaidReport) {
    $bills = getCustomerOutstandingBills($id);
    $outstandingTotal = getCustomerOutstandingTotal($id);
} else {
    $stmt = getDB()->prepare('SELECT * FROM bills WHERE customer_id = ? ORDER BY billing_period_start DESC LIMIT 10');
    $stmt->execute([$id]);
    $bills = $stmt->fetchAll();
    $outstandingTotal = getCustomerOutstandingTotal($id);
}

$stmt = getDB()->prepare(
    'SELECT p.*, u.full_name as collector_name FROM payments p
     LEFT JOIN users u ON p.collected_by = u.id
     WHERE p.customer_id = ? ORDER BY p.payment_date DESC LIMIT 10'
);
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($customer['account_number']) ?> · <?= e($customer['full_name']) ?></p>
    </div>
    <div class="header-actions">
        <?php if ($showUnpaidReport): ?>
        <a href="<?= APP_URL ?>/portal/account.php" class="btn btn-outline btn-sm">All Bills</a>
        <?php elseif ($outstandingTotal > 0): ?>
        <a href="<?= APP_URL ?>/portal/account.php?status=unpaid" class="btn btn-outline btn-sm">Unpaid Bills Report</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/portal/index.php" class="btn btn-outline btn-sm">← Dashboard</a>
    </div>
</div>

<?php if ($showUnpaidReport): ?>
<div class="card">
    <div class="summary-bar billing-summary-bar">
        <strong><?= number_format(count($bills)) ?></strong> unpaid bill(s)
        · Total Balance Due: <strong class="text-danger"><?= formatMoney($outstandingTotal) ?></strong>
    </div>

    <?php if (empty($bills)): ?>
    <p class="text-center text-muted" style="padding: 24px 0;">You have no unpaid bills. Thank you!</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Billing Period</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $bill):
                    $balance = (float) $bill['amount'] - (float) $bill['paid_amount'];
                ?>
                <tr>
                    <td><strong><?= e($bill['bill_number']) ?></strong></td>
                    <td><?= formatDate($bill['billing_period_start']) ?> — <?= formatDate($bill['billing_period_end']) ?></td>
                    <td><?= formatDate($bill['due_date']) ?></td>
                    <td><?= formatMoney((float) $bill['amount']) ?></td>
                    <td><?= formatMoney((float) $bill['paid_amount']) ?></td>
                    <td><strong><?= formatMoney($balance) ?></strong></td>
                    <td><?= statusBadge($bill['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total Balance Due</strong></td>
                    <td colspan="2"><strong class="text-danger"><?= formatMoney($outstandingTotal) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="grid-3">
    <div class="info-card">
        <h3>Contact Information</h3>
        <dl>
            <dt>Name</dt><dd><?= e($customer['full_name']) ?></dd>
            <dt>Phone</dt><dd><?= e($customer['phone']) ?></dd>
            <dt>Email</dt><dd><?= e($customer['email'] ?: '—') ?></dd>
            <dt>Address</dt><dd><?= e(formatCustomerAddress($customer)) ?></dd>
        </dl>
    </div>
    <div class="info-card">
        <h3>Service Details</h3>
        <dl>
            <dt>Plan</dt><dd><?= e($customer['plan_name']) ?> (<?= $customer['speed_mbps'] ?> Mbps)</dd>
            <dt>Connection</dt><dd><?= connectionMediumBadge($customer['connection_medium'] ?? null) ?></dd>
            <dt>Monthly Fee</dt><dd><?= formatMoney($customer['monthly_fee']) ?></dd>
            <dt>Status</dt><dd><?= statusBadge($customer['status']) ?></dd>
            <dt>Installed</dt><dd><?= formatDate($customer['installation_date']) ?></dd>
        </dl>
    </div>
    <div class="info-card">
        <h3>Billing Cycle</h3>
        <dl>
            <dt>Current Period</dt>
            <dd><?= formatDate($period['start']) ?> — <?= formatDate($period['end']) ?></dd>
            <dt>Due Date</dt><dd><?= formatDate($period['due_date']) ?></dd>
            <dt>Next Billing</dt><dd><?= formatDate($nextBilling) ?></dd>
            <?php if ($outstandingTotal > 0): ?>
            <dt>Balance Due</dt>
            <dd><a href="<?= APP_URL ?>/portal/account.php?status=unpaid"><strong><?= formatMoney($outstandingTotal) ?></strong></a></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>My Bills</h2>
            <?php if ($outstandingTotal > 0): ?>
            <a href="<?= APP_URL ?>/portal/account.php?status=unpaid" class="btn btn-sm btn-outline">Unpaid Report</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Period</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                    <tr><td colspan="4" class="text-muted text-center">No bills yet.</td></tr>
                    <?php else: foreach ($bills as $bill): ?>
                    <tr>
                        <td><?= formatDate($bill['billing_period_start']) ?> — <?= formatDate($bill['billing_period_end']) ?></td>
                        <td><?= formatMoney($bill['amount']) ?></td>
                        <td><?= formatDate($bill['due_date']) ?></td>
                        <td><?= statusBadge($bill['status']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Payment History</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Date</th><th>Amount</th><th>Method</th></tr></thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="3" class="text-muted text-center">No payments recorded.</td></tr>
                    <?php else: foreach ($payments as $p): ?>
                    <tr>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><?= formatMoney($p['amount']) ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
