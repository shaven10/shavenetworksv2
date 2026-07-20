<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireLogin();

if (!canAccess('customers')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare(
    'SELECT c.*, p.name as plan_name, p.speed_mbps, p.monthly_fee, u.full_name as created_by_name
     FROM customers c
     JOIN service_plans p ON c.plan_id = p.id
     LEFT JOIN users u ON c.created_by = u.id
     WHERE c.id = ?'
);
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('/customers/index.php');
}

$pageTitle = $customer['full_name'];
$currentPage = 'customers';

$period = getBillingPeriod($customer['installation_date']);
$nextBilling = getNextBillingDate($customer['installation_date']);

$stmt = getDB()->prepare(
    'SELECT * FROM bills WHERE customer_id = ? ORDER BY billing_period_start DESC LIMIT 12'
);
$stmt->execute([$id]);
$bills = $stmt->fetchAll();

$stmt = getDB()->prepare(
    'SELECT p.*, u.full_name as collector_name FROM payments p
     JOIN users u ON p.collected_by = u.id
     WHERE p.customer_id = ? ORDER BY p.payment_date DESC LIMIT 10'
);
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($customer['full_name']) ?></h1>
        <p><?= e($customer['account_number']) ?> · <?= statusBadge($customer['status']) ?></p>
    </div>
    <div class="header-actions">
        <?php if (hasRole('owner', 'technical')): ?>
        <a href="<?= APP_URL ?>/customers/edit.php?id=<?= $id ?>" class="btn btn-outline">Edit</a>
        <?php endif; ?>
        <?php if (hasRole('owner', 'collector')): ?>
        <a href="<?= APP_URL ?>/payments/advance.php?customer_id=<?= $id ?>" class="btn btn-outline">Advance Payment</a>
        <a href="<?= APP_URL ?>/billing/collect.php" class="btn btn-outline">Collect Multiple</a>
        <a href="<?= APP_URL ?>/payments/collect.php?customer_id=<?= $id ?>" class="btn btn-primary">Collect Payment</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid-3">
    <div class="info-card">
        <h3>Contact</h3>
        <dl>
            <dt>Phone</dt><dd><?= e($customer['phone']) ?></dd>
            <dt>Email</dt><dd><?= e($customer['email'] ?: '—') ?></dd>
            <?php if (!empty($customer['city']) || !empty($customer['province'])): ?>
            <dt>Street</dt><dd><?= e($customer['address']) ?></dd>
            <?php if (!empty($customer['barangay'])): ?>
            <dt>Barangay</dt><dd><?= e($customer['barangay']) ?></dd>
            <?php endif; ?>
            <dt>City</dt><dd><?= e($customer['city'] ?: '—') ?></dd>
            <dt>Province</dt><dd><?= e($customer['province'] ?: '—') ?></dd>
            <?php else: ?>
            <dt>Address</dt><dd><?= e($customer['address']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <div class="info-card">
        <h3>Service</h3>
        <dl>
            <dt>Plan</dt><dd><?= e($customer['plan_name']) ?> (<?= $customer['speed_mbps'] ?> Mbps)</dd>
            <dt>Connection</dt><dd><?= connectionMediumBadge($customer['connection_medium'] ?? null) ?></dd>
            <dt>Monthly Fee</dt><dd><?= formatMoney($customer['monthly_fee']) ?></dd>
            <dt>Installed</dt><dd><?= formatDate($customer['installation_date']) ?></dd>
            <dt>Installed By</dt><dd><?= e($customer['created_by_name'] ?? '—') ?></dd>
        </dl>
    </div>
    <div class="info-card">
        <h3>Billing Cycle</h3>
        <dl>
            <dt>Current Period</dt>
            <dd><?= formatDate($period['start']) ?> — <?= formatDate($period['end']) ?></dd>
            <dt>Due Date</dt><dd><?= formatDate($period['due_date']) ?></dd>
            <dt>Next Billing</dt><dd><?= formatDate($nextBilling) ?></dd>
            <dt>Advance Credit</dt>
            <dd><strong><?= formatMoney((float) ($customer['advance_balance'] ?? 0)) ?></strong></dd>
        </dl>
        <?php if (hasRole('owner', 'collector') && (float) ($customer['advance_balance'] ?? 0) > 0): ?>
        <form method="POST" action="<?= APP_URL ?>/payments/apply_advance.php" style="margin-top:12px">
            <input type="hidden" name="customer_id" value="<?= $id ?>">
            <input type="hidden" name="redirect" value="<?= APP_URL ?>/customers/view.php?id=<?= $id ?>">
            <button type="submit" class="btn btn-sm btn-outline">Apply Credit to Bills</button>
        </form>
        <?php endif; ?>
        <p class="form-hint">Billing is anchored to installation date (day <?= date('d', strtotime($customer['installation_date'])) ?> of each month).</p>
    </div>
</div>

<?php if ($customer['notes']): ?>
<div class="card"><strong>Notes:</strong> <?= e($customer['notes']) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Billing History</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Period</th><th>Amount</th><th>Due</th><th>Status</th></tr>
                </thead>
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
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Amount</th><th>Method</th><th>Invoice</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="5" class="text-muted text-center">No payments recorded.</td></tr>
                    <?php else: foreach ($payments as $p): ?>
                    <tr>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><?= e(paymentTypeLabel($p['payment_type'] ?? 'bill')) ?></td>
                        <td><?= formatMoney($p['amount']) ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
                        <td>
                            <?php if (($p['payment_type'] ?? 'bill') === 'advance'): ?>
                            <a href="<?= APP_URL ?>/payments/advance_invoice.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">View</a>
                            <?php elseif (!empty($p['invoice_number'])): ?>
                            <a href="<?= APP_URL ?>/payments/invoice.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">View</a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderCustomerDeleteSection($customer); ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
