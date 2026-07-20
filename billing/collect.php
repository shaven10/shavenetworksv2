<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$pageTitle = 'Collect Multiple Payments';
$currentPage = 'billing';
$errors = [];
$customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$customer = null;
$outstandingBills = [];

if ($customerId) {
    $stmt = getDB()->prepare(
        'SELECT c.*, p.name as plan_name, p.monthly_fee
         FROM customers c
         JOIN service_plans p ON c.plan_id = p.id
         WHERE c.id = ?'
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        flash('danger', 'Customer not found.');
        redirect('/billing/collect.php');
    }

    updateOverdueBills();
    $outstandingBills = getCustomerOutstandingBills($customerId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer) {
    $method = $_POST['payment_method'] ?? 'cash';
    $reference = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $selected = $_POST['selected_bills'] ?? [];
    $amounts = $_POST['amounts'] ?? [];

    $billPayments = [];
    foreach ($selected as $billId) {
        $billId = (int) $billId;
        $amount = (float) ($amounts[$billId] ?? 0);
        if ($amount > 0) {
            $billPayments[$billId] = $amount;
        }
    }

    if (empty($billPayments)) {
        $errors[] = 'Select at least one bill and enter a payment amount.';
    }

    if (empty($errors)) {
        try {
            $result = recordMultiplePayments(
                $customerId,
                $billPayments,
                $method,
                $reference ?: null,
                $notes ?: null,
                currentUser()['id']
            );

            logActivity(
                'batch_payment_recorded',
                "Recorded {$result['count']} payment(s) totaling " . formatMoney($result['total']) . " for {$customer['account_number']} ({$result['batch_invoice_number']})"
            );

            flash('success', 'Batch payment recorded. Batch invoice ' . $result['batch_invoice_number'] . ' generated.');
            redirect('/billing/batch_invoice.php?id=' . $result['batch_id']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$customers = getDB()->query(
    'SELECT id, account_number, full_name FROM customers WHERE status != "disconnected" ORDER BY full_name'
)->fetchAll();

$outstandingTotal = $customer ? getCustomerOutstandingTotal($customerId) : 0;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Collect Multiple Payments</h1>
        <p>Pay several generated bills for one customer in a single collection</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/payments/collect.php" class="btn btn-outline btn-sm">Single Payment</a>
        <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline btn-sm">← Billing</a>
    </div>
</div>

<div class="card card-form">
    <form method="GET" class="filter-bar">
        <div class="filter-bar-field">
            <label for="customer_id">Customer</label>
            <select id="customer_id" name="customer_id" onchange="this.form.submit()" required>
                <option value="">Select customer...</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $customerId === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['account_number']) ?> — <?= e($c['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($customer): ?>
<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="bulk-payment-summary">
        <div>
            <strong><?= e($customer['full_name']) ?></strong>
            <span class="text-muted"><?= e($customer['account_number']) ?> · <?= e($customer['plan_name']) ?></span>
            <?php $advanceBalance = getCustomerAdvanceBalance($customerId); ?>
            <?php if ($advanceBalance > 0): ?>
            <div class="advance-credit-badge">Advance credit: <strong><?= formatMoney($advanceBalance) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="bulk-total-box">
            <span>Total Outstanding</span>
            <strong><?= formatMoney($outstandingTotal) ?></strong>
        </div>
    </div>

    <?php if (empty($outstandingBills)): ?>
    <p class="text-muted">No unpaid bills for this customer.</p>
    <?php else: ?>
    <div class="info-box">
        <p>Bills must be paid in order from the <strong>oldest billing period</strong> to the newest. Later months cannot be paid while earlier months still have a balance.</p>
    </div>
    <form method="POST" id="bulk-payment-form">
        <input type="hidden" name="customer_id" value="<?= $customerId ?>">

        <div class="bulk-payment-toolbar">
            <label class="checkbox-label">
                <input type="checkbox" id="select-all-bills">
                Select all bills
            </label>
            <button type="button" class="btn btn-outline btn-sm" id="fill-all-balances">Fill full balances</button>
        </div>

        <div class="table-responsive">
            <table class="table table-compact bulk-payment-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Bill #</th>
                        <th>Billing Period</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Pay Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outstandingBills as $index => $bill):
                        $balance = (float) $bill['balance'];
                        $isOldest = $index === 0;
                    ?>
                    <tr class="bulk-bill-row" data-row-index="<?= $index ?>" data-bill-id="<?= (int) $bill['id'] ?>" data-balance="<?= $balance ?>">
                        <td>
                            <input type="checkbox" class="bill-select" name="selected_bills[]"
                                   value="<?= $bill['id'] ?>" data-bill-id="<?= $bill['id'] ?>"
                                   <?= $isOldest ? '' : 'disabled' ?>>
                        </td>
                        <td><strong><?= e($bill['bill_number']) ?></strong></td>
                        <td><?= formatDate($bill['billing_period_start']) ?> — <?= formatDate($bill['billing_period_end']) ?></td>
                        <td><?= formatDate($bill['due_date']) ?></td>
                        <td><?= formatMoney($bill['amount']) ?></td>
                        <td><?= formatMoney($bill['paid_amount']) ?></td>
                        <td><strong class="bill-balance" data-balance="<?= $balance ?>"><?= formatMoney($balance) ?></strong></td>
                        <td>
                            <input type="number" class="bill-amount-input" name="amounts[<?= $bill['id'] ?>]"
                                   min="0" max="<?= $balance ?>" step="0.01" placeholder="0.00"
                                   data-bill-id="<?= $bill['id'] ?>" data-max="<?= $balance ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" class="text-right"><strong>Total to Collect</strong></td>
                        <td><strong id="bulk-payment-total">₱0.00</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="form-grid" style="margin-top:20px">
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
            <button type="button" class="btn btn-primary" id="open-bulk-payment-modal">Record Payments</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="bulk-payment-modal" hidden>
    <div class="modal-dialog modal-dialog-short" role="dialog" aria-modal="true" aria-labelledby="bulk-modal-title">
        <div class="modal-header">
            <h2 id="bulk-modal-title">Confirm Payments</h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>

        <div class="modal-body modal-body-short">
            <p class="modal-short-message">Record <strong id="bulk-short-count">0</strong> payment(s) for <strong id="bulk-short-customer"><?= e($customer['full_name']) ?></strong>?</p>
            <div class="modal-short-total">
                <span>Total to collect</span>
                <strong id="bulk-short-total" class="confirm-amount">₱0.00</strong>
            </div>
            <p class="modal-short-meta">
                <span id="bulk-short-method">Cash</span>
                <span id="bulk-short-reference-wrap" hidden> · Ref: <span id="bulk-short-reference"></span></span>
            </p>

            <button type="button"
                    class="modal-short-details-toggle"
                    id="bulk-modal-toggle"
                    aria-expanded="false"
                    aria-controls="bulk-modal-details">
                <span class="modal-section-toggle-icon" aria-hidden="true"></span>
                <span class="modal-section-toggle-label">Show bill breakdown</span>
            </button>

            <div class="modal-short-details is-collapsed" id="bulk-modal-details">
                <ul id="bulk-confirm-list" class="bulk-confirm-list"></ul>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirm-bulk-payment">Confirm</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/bulk-payment.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
