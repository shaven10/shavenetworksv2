<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$pageTitle = 'Record Advance Payment';
$currentPage = 'payments';
$errors = [];
$customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

$customers = getDB()->query(
    'SELECT id, account_number, full_name, advance_balance FROM customers WHERE status != "disconnected" ORDER BY full_name'
)->fetchAll();

$selectedCustomer = null;
if ($customerId) {
    foreach ($customers as $c) {
        if ((int) $c['id'] === $customerId) {
            $selectedCustomer = $c;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $reference = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$customerId) {
        $errors[] = 'Please select a customer.';
    }
    if ($amount <= 0) {
        $errors[] = 'Advance amount must be greater than zero.';
    }

    if (empty($errors)) {
        try {
            $result = recordAdvancePayment(
                $customerId,
                $amount,
                $method,
                $reference ?: null,
                $notes ?: null,
                currentUser()['id']
            );

            $message = 'Advance payment recorded. Invoice ' . $result['invoice_number'] . ' generated.';
            if ($result['applied'] > 0) {
                $message .= ' ' . formatMoney($result['applied']) . ' was auto-applied to outstanding bills.';
            }
            if ($result['remaining_credit'] > 0) {
                $message .= ' Remaining credit: ' . formatMoney($result['remaining_credit']) . '.';
            }

            logActivity('advance_payment_recorded', "Advance {$result['invoice_number']} for customer #{$customerId}: " . formatMoney($amount));
            flash('success', $message);
            redirect('/payments/advance_invoice.php?id=' . $result['payment_id']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Record Advance Payment</h1>
        <p>Accept prepayment credit to apply automatically to future or outstanding bills</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/payments/collect.php" class="btn btn-outline btn-sm">Bill Payment</a>
        <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-outline btn-sm">← Payments</a>
    </div>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="advance-payment-form">
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="customer_id">Customer *</label>
                <select id="customer_id" name="customer_id" required onchange="updateAdvanceHint(this)">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"
                            data-balance="<?= (float) $c['advance_balance'] ?>"
                            <?= $customerId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['account_number']) ?> — <?= e($c['full_name']) ?>
                        <?php if ((float) $c['advance_balance'] > 0): ?>
                        (Credit: <?= formatMoney((float) $c['advance_balance']) ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint" id="advance-balance-hint">
                    <?php if ($selectedCustomer && (float) $selectedCustomer['advance_balance'] > 0): ?>
                    Current advance credit: <?= formatMoney((float) $selectedCustomer['advance_balance']) ?>
                    <?php else: ?>
                    Advance credit is stored on the account and auto-applied to unpaid bills.
                    <?php endif; ?>
                </small>
            </div>

            <div class="form-group">
                <label for="amount">Advance Amount (₱) *</label>
                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required
                       value="<?= e($_POST['amount'] ?? '') ?>">
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

            <div class="form-group">
                <label for="reference_number">Reference Number</label>
                <input type="text" id="reference_number" name="reference_number"
                       value="<?= e($_POST['reference_number'] ?? '') ?>">
            </div>

            <div class="form-group full-width">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Optional notes"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="open-advance-modal">Record Advance</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="advance-payment-modal" hidden>
    <div class="modal-dialog modal-dialog-short" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Confirm Advance Payment</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body modal-body-short">
            <p class="modal-short-message">Record advance of <strong id="advance-confirm-amount">₱0.00</strong> for <strong id="advance-confirm-customer">—</strong>?</p>
            <p class="modal-short-meta">Method: <span id="advance-confirm-method">Cash</span></p>
            <p class="text-muted" style="font-size:13px;margin:0">Credit will auto-apply to any outstanding bills, then remain for future billing.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirm-advance-payment">Confirm</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('advance-payment-form');
    var modal = document.getElementById('advance-payment-modal');
    var customerSelect = document.getElementById('customer_id');

    window.updateAdvanceHint = function (select) {
        var option = select.options[select.selectedIndex];
        var hint = document.getElementById('advance-balance-hint');
        if (!option || !option.value) {
            hint.textContent = 'Advance credit is stored on the account and auto-applied to unpaid bills.';
            return;
        }
        var balance = parseFloat(option.dataset.balance || '0');
        hint.textContent = balance > 0
            ? 'Current advance credit: ₱' + balance.toFixed(2)
            : 'No advance credit on this account yet.';
    };

    function openModal() {
        if (!form.reportValidity()) return;
        var option = customerSelect.options[customerSelect.selectedIndex];
        document.getElementById('advance-confirm-customer').textContent = option.text.split('—').pop().trim();
        document.getElementById('advance-confirm-amount').textContent = '₱' + parseFloat(document.getElementById('amount').value || 0).toFixed(2);
        var methods = { cash: 'Cash', gcash: 'GCash', bank_transfer: 'Bank Transfer', check: 'Check' };
        var method = document.getElementById('payment_method').value;
        document.getElementById('advance-confirm-method').textContent = methods[method] || method;
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    document.getElementById('open-advance-modal').addEventListener('click', openModal);
    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.getElementById('confirm-advance-payment').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Processing...';
        form.submit();
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
