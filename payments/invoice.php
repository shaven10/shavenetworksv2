<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireLogin();

if (!canAccess('payments')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare('SELECT payment_type FROM payments WHERE id = ?');
$stmt->execute([$id]);
$paymentType = $stmt->fetchColumn();
if ($paymentType === 'advance') {
    redirect('/payments/advance_invoice.php?id=' . $id);
}

$payment = getPaymentInvoice($id);

if (!$payment) {
    flash('danger', 'Invoice not found.');
    redirect('/payments/index.php');
}

$pageTitle = 'Invoice ' . ($payment['invoice_number'] ?? '');

require __DIR__ . '/../includes/header.php';

$balance = $payment['bill_amount'] - $payment['bill_paid'];
$methodLabel = ucfirst(str_replace('_', ' ', $payment['payment_method']));
?>

<div class="page-header no-print">
    <div>
        <h1>Payment Invoice</h1>
        <p><?= e($payment['invoice_number'] ?? 'INV-' . $payment['id']) ?></p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Invoice</button>
        <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-outline">← Back to Payments</a>
    </div>
</div>

<div class="card invoice-document">
    <div class="invoice-header">
        <div class="invoice-brand">
            <div class="brand-icon">SN</div>
            <div>
                <strong>SHAVEN Networks</strong>
                <small>Official Payment Receipt / Invoice</small>
            </div>
        </div>
        <div class="invoice-meta">
            <h2>INVOICE</h2>
            <p><strong>No:</strong> <?= e($payment['invoice_number'] ?? 'INV-' . $payment['id']) ?></p>
            <p><strong>Date:</strong> <?= formatDate($payment['payment_date']) ?></p>
            <p><strong>Payment ID:</strong> #<?= $payment['id'] ?></p>
        </div>
    </div>

    <div class="invoice-parties">
        <div>
            <h3>Billed To</h3>
            <p><strong><?= e($payment['full_name']) ?></strong></p>
            <p><?= e($payment['account_number']) ?></p>
            <p><?= e($payment['phone']) ?></p>
            <p><?= e(formatCustomerAddress($payment)) ?></p>
            <?php if (!empty($payment['connection_medium'])): ?>
            <p><strong>Connection:</strong> <?= e(connectionMediumLabel($payment['connection_medium'])) ?></p>
            <?php endif; ?>
            <?php if ($payment['email']): ?>
            <p><?= e($payment['email']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Service</h3>
            <p><strong>Plan:</strong> <?= e($payment['plan_name']) ?></p>
            <p><strong>Collected By:</strong> <?= e($payment['collector_name']) ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Billing Period</th>
                    <th>Bill Reference</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Internet Service Payment</td>
                    <td><?= formatDate($payment['billing_period_start']) ?> — <?= formatDate($payment['billing_period_end']) ?></td>
                    <td><?= e($payment['bill_number']) ?></td>
                    <td class="text-right"><?= formatMoney($payment['amount']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right"><strong>Amount Paid</strong></td>
                    <td class="text-right"><strong><?= formatMoney($payment['amount']) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right">Bill Total</td>
                    <td class="text-right"><?= formatMoney($payment['bill_amount']) ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right">Total Paid on Bill</td>
                    <td class="text-right"><?= formatMoney($payment['bill_paid']) ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right">Remaining Balance</td>
                    <td class="text-right"><?= formatMoney(max(0, $balance)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="invoice-payment-details">
        <h3>Payment Details</h3>
        <dl>
            <dt>Payment Method</dt>
            <dd><?= e($methodLabel) ?></dd>
            <dt>Reference Number</dt>
            <dd><?= e($payment['reference_number'] ?: '—') ?></dd>
            <dt>Bill Status</dt>
            <dd><?= statusBadge($payment['bill_status']) ?></dd>
            <?php if ($payment['notes']): ?>
            <dt>Notes</dt>
            <dd><?= e($payment['notes']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <div class="invoice-footer">
        <p>Thank you for your payment. This document serves as your official receipt.</p>
        <p class="text-muted">Generated on <?= date('M d, Y h:i A') ?> · SHAVEN Networks ISP Billing System</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
