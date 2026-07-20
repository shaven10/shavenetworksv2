<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireLogin();

if (!canAccess('payments')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);
$payment = getAdvancePaymentInvoice($id);

if (!$payment) {
    flash('danger', 'Advance invoice not found.');
    redirect('/payments/index.php');
}

$pageTitle = 'Advance Invoice ' . $payment['invoice_number'];
$methodLabel = ucfirst(str_replace('_', ' ', $payment['payment_method']));

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1>Advance Payment Invoice</h1>
        <p><?= e($payment['invoice_number']) ?></p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Invoice</button>
        <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $payment['customer_id'] ?>" class="btn btn-outline">Customer Profile</a>
        <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-outline">← Payments</a>
    </div>
</div>

<div class="card invoice-document">
    <div class="invoice-header">
        <div class="invoice-brand">
            <div class="brand-icon">SN</div>
            <div>
                <strong>SHAVEN Networks</strong>
                <small>Advance Payment Receipt</small>
            </div>
        </div>
        <div class="invoice-meta">
            <h2>ADVANCE</h2>
            <p><strong>No:</strong> <?= e($payment['invoice_number']) ?></p>
            <p><strong>Date:</strong> <?= formatDate($payment['payment_date']) ?></p>
        </div>
    </div>

    <div class="invoice-parties">
        <div>
            <h3>Customer</h3>
            <p><strong><?= e($payment['full_name']) ?></strong></p>
            <p><?= e($payment['account_number']) ?></p>
            <p><?= e($payment['phone']) ?></p>
            <p><?= e(formatCustomerAddress($payment)) ?></p>
        </div>
        <div>
            <h3>Details</h3>
            <p><strong>Plan:</strong> <?= e($payment['plan_name']) ?></p>
            <p><strong>Collected By:</strong> <?= e($payment['collector_name']) ?></p>
            <p><strong>Current Credit:</strong> <?= formatMoney((float) $payment['advance_balance']) ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Advance payment credit<br>
                        <small class="text-muted">Auto-applies to outstanding bills, then future billing</small>
                    </td>
                    <td class="text-right"><strong><?= formatMoney((float) $payment['amount']) ?></strong></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-right"><strong>Advance Received</strong></td>
                    <td class="text-right"><strong><?= formatMoney((float) $payment['amount']) ?></strong></td>
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
            <?php if ($payment['notes']): ?>
            <dt>Notes</dt>
            <dd><?= e($payment['notes']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <div class="invoice-footer">
        <p>This advance payment is stored as account credit and will be applied to bills automatically.</p>
        <p class="text-muted">Generated on <?= date('M d, Y h:i A') ?> · SHAVEN Networks ISP Billing System</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
