<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireLogin();

if (!canAccess('payments')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);
$batch = getBatchInvoice($id);

if (!$batch) {
    flash('danger', 'Batch invoice not found.');
    redirect('/payments/index.php');
}

$pageTitle = 'Batch Invoice ' . $batch['batch_invoice_number'];
$methodLabel = ucfirst(str_replace('_', ' ', $batch['payment_method']));

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1>Batch Payment Invoice</h1>
        <p><?= e($batch['batch_invoice_number']) ?></p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Invoice</button>
        <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $batch['customer_id'] ?>" class="btn btn-outline">Customer Profile</a>
        <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-outline">← Payments</a>
    </div>
</div>

<div class="card invoice-document">
    <div class="invoice-header">
        <div class="invoice-brand">
            <div class="brand-icon">SN</div>
            <div>
                <strong>SHAVEN Networks</strong>
                <small>Official Batch Payment Receipt / Invoice</small>
            </div>
        </div>
        <div class="invoice-meta">
            <h2>BATCH INVOICE</h2>
            <p><strong>No:</strong> <?= e($batch['batch_invoice_number']) ?></p>
            <p><strong>Date:</strong> <?= formatDate($batch['payment_date']) ?></p>
            <p><strong>Payments:</strong> <?= (int) $batch['payment_count'] ?></p>
        </div>
    </div>

    <div class="invoice-parties">
        <div>
            <h3>Billed To</h3>
            <p><strong><?= e($batch['full_name']) ?></strong></p>
            <p><?= e($batch['account_number']) ?></p>
            <p><?= e($batch['phone']) ?></p>
            <p><?= e(formatCustomerAddress($batch)) ?></p>
            <?php if (!empty($batch['connection_medium'])): ?>
            <p><strong>Connection:</strong> <?= e(connectionMediumLabel($batch['connection_medium'])) ?></p>
            <?php endif; ?>
            <?php if ($batch['email']): ?>
            <p><?= e($batch['email']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Collection</h3>
            <p><strong>Plan:</strong> <?= e($batch['plan_name']) ?></p>
            <p><strong>Collected By:</strong> <?= e($batch['collector_name']) ?></p>
            <p><strong>Method:</strong> <?= e($methodLabel) ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table invoice-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Reference</th>
                    <th>Billing Period</th>
                    <th>Due Date</th>
                    <th class="text-right">Amount Paid</th>
                    <th>Bill Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batch['lines'] as $index => $line): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e($line['bill_number']) ?></td>
                    <td><?= formatDate($line['billing_period_start']) ?> — <?= formatDate($line['billing_period_end']) ?></td>
                    <td><?= formatDate($line['due_date']) ?></td>
                    <td class="text-right"><?= formatMoney((float) $line['amount']) ?></td>
                    <td><?= statusBadge($line['bill_status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right"><strong>Total Collected</strong></td>
                    <td class="text-right"><strong><?= formatMoney((float) $batch['total_amount']) ?></strong></td>
                    <td></td>
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
            <dd><?= e($batch['reference_number'] ?: '—') ?></dd>
            <dt>Total Payments</dt>
            <dd><?= (int) $batch['payment_count'] ?></dd>
            <?php if ($batch['notes']): ?>
            <dt>Notes</dt>
            <dd><?= e($batch['notes']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>

    <div class="invoice-footer">
        <p>Thank you for your payment. This document serves as your official batch receipt for all listed bills.</p>
        <p class="text-muted">Generated on <?= date('M d, Y h:i A') ?> · SHAVEN Networks ISP Billing System</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
