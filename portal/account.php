<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'My Account';
$currentPage = 'my_account';
$customer = getLinkedCustomer();
$id = $customer['id'];

$period = getBillingPeriod($customer['installation_date']);
$nextBilling = getNextBillingDate($customer['installation_date']);

$stmt = getDB()->prepare('SELECT * FROM bills WHERE customer_id = ? ORDER BY billing_period_start DESC LIMIT 10');
$stmt->execute([$id]);
$bills = $stmt->fetchAll();

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
    <h1>My Account</h1>
    <p><?= e($customer['account_number']) ?></p>
</div>

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
        </dl>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>My Bills</h2></div>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
