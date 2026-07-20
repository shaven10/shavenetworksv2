<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/billing.php';

requireRole('owner', 'collector');



$pageTitle = 'Record Payment';

$currentPage = 'payments';



$billId = (int) ($_GET['bill_id'] ?? 0);

$customerId = (int) ($_GET['customer_id'] ?? 0);

$errors = [];



$pendingBills = [];
$nextPayableBillId = null;

if ($customerId) {
    updateOverdueBills();
    $pendingBills = getCustomerOutstandingBills($customerId);
    $nextPayableBillId = getNextPayableBillId($customerId);
}

$selectedBill = null;
if ($billId) {
    $stmt = getDB()->prepare(
        'SELECT b.*, c.full_name, c.account_number FROM bills b
         JOIN customers c ON b.customer_id = c.id WHERE b.id = ?'
    );
    $stmt->execute([$billId]);
    $selectedBill = $stmt->fetch();
    if ($selectedBill) {
        $customerId = (int) $selectedBill['customer_id'];
        $pendingBills = getCustomerOutstandingBills($customerId);
        $nextPayableBillId = getNextPayableBillId($customerId);
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $billId = (int) ($_POST['bill_id'] ?? 0);

    $amount = (float) ($_POST['amount'] ?? 0);

    $method = $_POST['payment_method'] ?? 'cash';

    $reference = trim($_POST['reference_number'] ?? '');

    $notes = trim($_POST['notes'] ?? '');



    if (!$billId) $errors[] = 'Please select a bill.';

    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';



    if (empty($errors)) {
        $user = currentUser();

        try {
            $paymentId = recordPayment($billId, $amount, $method, $reference ?: null, $notes ?: null, $user['id']);
            logActivity('payment_recorded', "Payment of {$amount} for bill #{$billId}");
            flash('success', 'Payment recorded. Invoice generated.');
            redirect("/payments/invoice.php?id={$paymentId}");
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

}



$customers = getDB()->query(
    'SELECT id, account_number, full_name FROM customers WHERE status != "disconnected" ORDER BY full_name'
)->fetchAll();

$activeCustomer = null;
foreach ($customers as $c) {
    if ((int) $c['id'] === $customerId) {
        $activeCustomer = $c;
        break;
    }
}
$activeCustomerName = $activeCustomer['full_name'] ?? ($selectedBill['full_name'] ?? '');
$activeCustomerAccount = $activeCustomer['account_number'] ?? ($selectedBill['account_number'] ?? '');

require __DIR__ . '/../includes/header.php';

?>



<div class="page-header">

    <h1>Record Payment</h1>

    <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-outline">← Back</a>

</div>



<div class="card card-form">

    <?php if ($errors): ?>

    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>

    <?php endif; ?>



    <form method="POST" id="payment-form">
        <?php if ($pendingBills): ?>
        <div class="info-box">
            <p>Bills must be paid in order from the <strong>oldest billing period</strong> to the newest. Only the earliest unpaid bill can be collected until it is fully paid.</p>
        </div>
        <?php endif; ?>
        <div class="form-grid">

            <div class="form-group">

                <label for="customer_select">Customer</label>

                <select id="customer_select" onchange="location.href='?customer_id='+this.value">

                    <option value="">Select customer to load bills...</option>

                    <?php foreach ($customers as $c): ?>

                    <option value="<?= $c['id'] ?>" <?= $customerId == $c['id'] ? 'selected' : '' ?>>

                        <?= e($c['account_number']) ?> — <?= e($c['full_name']) ?>

                    </option>

                    <?php endforeach; ?>

                </select>

            </div>



            <div class="form-group">

                <label for="bill_id">Bill *</label>

                <select id="bill_id" name="bill_id" required>

                    <option value="">Select bill...</option>

                    <?php if ($selectedBill):
                        $bal = (float) $selectedBill['amount'] - (float) $selectedBill['paid_amount'];
                        $isSelectedPayable = $nextPayableBillId && (int) $selectedBill['id'] === (int) $nextPayableBillId;
                    ?>
                    <option value="<?= $selectedBill['id'] ?>" selected
                            data-balance="<?= $bal ?>"
                            data-bill-number="<?= e($selectedBill['bill_number']) ?>"
                            data-customer="<?= e($selectedBill['full_name']) ?>"
                            data-account="<?= e($selectedBill['account_number']) ?>"
                            <?= $isSelectedPayable ? '' : 'disabled' ?>>
                        <?= e($selectedBill['bill_number']) ?> — Balance: <?= formatMoney($bal) ?><?= $isSelectedPayable ? '' : ' (pay older bills first)' ?>
                    </option>
                    <?php endif; ?>

                    <?php foreach ($pendingBills as $b):
                        if ($selectedBill && $b['id'] == $selectedBill['id']) continue;
                        $bal = (float) $b['balance'];
                        $isPayable = $nextPayableBillId && (int) $b['id'] === (int) $nextPayableBillId;
                    ?>
                    <option value="<?= $b['id'] ?>" data-balance="<?= $bal ?>"
                            data-bill-number="<?= e($b['bill_number']) ?>"
                            data-customer="<?= e($activeCustomerName) ?>"
                            data-account="<?= e($activeCustomerAccount) ?>"
                            <?= $isPayable ? '' : 'disabled' ?>
                            <?= $billId == $b['id'] ? 'selected' : '' ?>>
                        <?= e($b['bill_number']) ?> — <?= formatDate($b['billing_period_start']) ?> to <?= formatDate($b['billing_period_end']) ?> — Balance: <?= formatMoney($bal) ?><?= $isPayable ? '' : ' (pay older bills first)' ?>
                    </option>
                    <?php endforeach; ?>

                </select>

            </div>



            <div class="form-group">

                <label for="amount">Amount (₱) *</label>

                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required

                       value="<?= e($_POST['amount'] ?? '') ?>">

                <small class="form-hint" id="balance-hint"></small>

            </div>



            <div class="form-group">

                <label for="payment_method">Payment Method *</label>

                <select id="payment_method" name="payment_method" required>

                    <option value="cash">Cash</option>

                    <option value="gcash">GCash</option>

                    <option value="bank_transfer">Bank Transfer</option>

                    <option value="check">Check</option>

                </select>

            </div>



            <div class="form-group full-width">

                <label for="reference_number">Reference Number</label>

                <input type="text" id="reference_number" name="reference_number"

                       value="<?= e($_POST['reference_number'] ?? '') ?>">

            </div>



            <div class="form-group full-width">

                <label for="notes">Notes</label>

                <textarea id="notes" name="notes" rows="2"><?= e($_POST['notes'] ?? '') ?></textarea>

            </div>

        </div>

        <div class="form-actions">

            <button type="button" class="btn btn-primary" id="open-payment-modal">Record Payment</button>

        </div>

    </form>

</div>



<div class="modal-overlay" id="payment-modal" hidden>

    <div class="modal-dialog" role="dialog" aria-labelledby="modal-title" aria-modal="true">

        <div class="modal-header">

            <h2 id="modal-title">Confirm Payment</h2>

            <button type="button" class="modal-close" id="close-payment-modal" aria-label="Close">&times;</button>

        </div>

        <div class="modal-body">

            <p class="modal-intro">Please review the payment details before recording. An invoice will be generated after confirmation.</p>

            <dl class="confirm-list">

                <dt>Customer</dt>

                <dd id="confirm-customer">—</dd>

                <dt>Account #</dt>

                <dd id="confirm-account">—</dd>

                <dt>Bill Reference</dt>

                <dd id="confirm-bill">—</dd>

                <dt>Amount to Collect</dt>

                <dd id="confirm-amount" class="confirm-amount">—</dd>

                <dt>Payment Method</dt>

                <dd id="confirm-method">—</dd>

                <dt>Reference No.</dt>

                <dd id="confirm-reference">—</dd>

                <dt>Notes</dt>

                <dd id="confirm-notes">—</dd>

            </dl>

        </div>

        <div class="modal-footer">

            <button type="button" class="btn btn-outline" id="cancel-payment-modal">Cancel</button>

            <button type="button" class="btn btn-primary" id="confirm-payment-submit">Confirm & Generate Invoice</button>

        </div>

    </div>

</div>



<script src="<?= APP_URL ?>/assets/js/payment-modal.js"></script>



<?php require __DIR__ . '/../includes/footer.php'; ?>

