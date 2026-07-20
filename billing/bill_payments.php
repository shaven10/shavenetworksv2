<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$billId = (int) ($_GET['bill_id'] ?? $_POST['bill_id'] ?? 0);
$bill = getBillById($billId);

if (!$bill) {
    flash('danger', 'Bill not found.');
    redirect('/billing/index.php');
}

$pageTitle = 'Bill Payments — ' . $bill['bill_number'];
$currentPage = 'billing';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $confirmText = strtoupper(trim($_POST['confirm_text'] ?? ''));

    if ($confirmText !== 'REVERT') {
        $errors[] = 'Type REVERT to confirm payment reversal.';
    }

    if (empty($errors)) {
        try {
            $result = revertBillPayment($paymentId, (int) currentUser()['id']);
            $typeLabel = paymentTypeLabel($result['payment_type']);

            logActivity(
                'payment_reverted',
                "Reverted {$typeLabel} of " . formatMoney($result['amount']) . " for bill {$result['bill_number']}"
            );

            flash(
                'success',
                formatMoney($result['amount']) . ' payment reverted. Bill ' . $result['bill_number']
                . ' is now ' . ucfirst($result['new_status']) . ' with ' . formatMoney($result['new_paid']) . ' paid.'
            );

            redirect('/billing/bill_payments.php?bill_id=' . $billId);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$payments = getBillPayments($billId);
$balance = (float) $bill['amount'] - (float) $bill['paid_amount'];
$nextPayableBillId = getNextPayableBillId((int) $bill['customer_id']);
$canCollect = $balance > 0 && $nextPayableBillId === $billId;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Bill Payments</h1>
        <p><?= e($bill['bill_number']) ?> · <?= e($bill['full_name']) ?></p>
    </div>
    <div class="header-actions">
        <?php if ($canCollect): ?>
        <a href="<?= APP_URL ?>/payments/collect.php?bill_id=<?= $billId ?>" class="btn btn-primary btn-sm">Collect Payment</a>
        <?php elseif ($balance > 0): ?>
        <span class="text-muted payment-order-blocked">Pay older billing periods first</span>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline btn-sm">← Billing</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="bulk-payment-summary">
        <div>
            <strong><?= e($bill['full_name']) ?></strong>
            <span class="text-muted"><?= e($bill['account_number']) ?></span>
        </div>
        <div class="bulk-total-box">
            <span>Bill Balance</span>
            <strong><?= formatMoney($balance) ?></strong>
        </div>
    </div>

    <dl class="bill-payment-summary">
        <dt>Billing Period</dt>
        <dd><?= formatDate($bill['billing_period_start']) ?> — <?= formatDate($bill['billing_period_end']) ?></dd>
        <dt>Due Date</dt>
        <dd><?= formatDate($bill['due_date']) ?></dd>
        <dt>Amount</dt>
        <dd><?= formatMoney((float) $bill['amount']) ?></dd>
        <dt>Paid</dt>
        <dd><?= formatMoney((float) $bill['paid_amount']) ?></dd>
        <dt>Status</dt>
        <dd><?= statusBadge($bill['status']) ?></dd>
    </dl>
</div>

<div class="card">
    <div class="card-header">
        <h2>Payment History</h2>
    </div>

    <?php if (empty($payments)): ?>
    <p class="text-muted billing-empty">No payments recorded for this bill.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Collected By</th>
                    <th>Reference</th>
                    <th>Invoice</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= formatDate($payment['payment_date']) ?></td>
                    <td><?= e(paymentTypeLabel($payment['payment_type'] ?? 'bill')) ?></td>
                    <td><strong><?= formatMoney((float) $payment['amount']) ?></strong></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                    <td><?= e($payment['collector_name']) ?></td>
                    <td><?= e($payment['reference_number'] ?: '—') ?></td>
                    <td>
                        <?php if (!empty($payment['batch_invoice_number'])): ?>
                        <a href="<?= APP_URL ?>/billing/batch_invoice.php?id=<?= (int) $payment['batch_id'] ?>" class="btn btn-sm btn-outline">Batch</a>
                        <?php elseif (!empty($payment['invoice_number'])): ?>
                        <a href="<?= APP_URL ?>/payments/invoice.php?id=<?= (int) $payment['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($payment['revert']['allowed']): ?>
                        <button type="button"
                                class="btn btn-sm btn-danger"
                                data-open-revert-modal
                                data-payment-id="<?= (int) $payment['id'] ?>"
                                data-payment-amount="<?= e(formatMoney((float) $payment['amount'])) ?>"
                                data-payment-type="<?= e(paymentTypeLabel($payment['payment_type'] ?? 'bill')) ?>">
                            Revert
                        </button>
                        <?php else: ?>
                        <span class="text-muted revert-blocked" title="<?= e($payment['revert']['reason']) ?>">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card danger-zone">
    <div class="card-header"><h2>Revert Payment</h2></div>
    <p class="danger-intro">
        Reverting removes the payment record and restores the bill balance.
        Advance-applied credits are returned to the customer's advance balance.
        Payments included in a pending or confirmed remittance cannot be reverted.
    </p>
</div>

<div class="modal-overlay" id="revert-payment-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Revert Payment</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" id="revert-payment-form">
            <input type="hidden" name="bill_id" value="<?= $billId ?>">
            <input type="hidden" name="payment_id" id="revert-payment-id" value="">
            <div class="modal-body">
                <p class="modal-short-message">
                    Revert <strong id="revert-payment-type">payment</strong> of
                    <strong id="revert-payment-amount">₱0.00</strong> for bill
                    <strong><?= e($bill['bill_number']) ?></strong>?
                </p>
                <p class="modal-short-meta text-muted">This action cannot be undone.</p>
                <div class="form-group">
                    <label for="confirm_text">Type REVERT to confirm *</label>
                    <input type="text" id="confirm_text" name="confirm_text" required autocomplete="off" placeholder="REVERT">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Revert Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('revert-payment-modal');
    var form = document.getElementById('revert-payment-form');
    var paymentIdInput = document.getElementById('revert-payment-id');
    var amountEl = document.getElementById('revert-payment-amount');
    var typeEl = document.getElementById('revert-payment-type');
    var confirmInput = document.getElementById('confirm_text');

    function openModal(button) {
        paymentIdInput.value = button.dataset.paymentId;
        amountEl.textContent = button.dataset.paymentAmount;
        typeEl.textContent = button.dataset.paymentType;
        confirmInput.value = '';
        modal.hidden = false;
        document.body.classList.add('modal-open');
        confirmInput.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-open-revert-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button);
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
