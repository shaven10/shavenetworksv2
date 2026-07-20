<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('collector');

$pageTitle = 'Submit Remittance';
$currentPage = 'remittances';
$errors = [];
$collectorId = (int) currentUser()['id'];

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$payments = getUnremittedPayments($collectorId, $from, $to);
$unremittedTotal = getUnremittedPaymentsTotal($collectorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['selected_payments'] ?? [];
    $notes = trim($_POST['notes'] ?? '');

    if (empty($errors)) {
        try {
            $result = createRemittance($collectorId, $selected, $notes ?: null);

            logActivity(
                'remittance_submitted',
                "Submitted remittance {$result['remittance_number']} with {$result['payment_count']} payment(s) totaling "
                . formatMoney($result['total_amount'])
            );

            flash('success', 'Remittance submitted to owner for confirmation.');
            redirect('/remittances/view.php?id=' . $result['id']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Submit Payment Remittance</h1>
        <p>Select collected payments to turn over to the owner for confirmation</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/remittances/index.php" class="btn btn-outline btn-sm">← Remittances</a>
    </div>
</div>

<div class="card card-form">
    <form method="GET" class="filter-bar">
        <label>From</label>
        <input type="date" name="from" value="<?= e($from) ?>">
        <label>To</label>
        <input type="date" name="to" value="<?= e($to) ?>">
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="bulk-payment-summary">
        <div>
            <strong><?= e(currentUser()['full_name']) ?></strong>
            <span class="text-muted">Collector · Unremitted total: <?= formatMoney($unremittedTotal) ?></span>
        </div>
        <div class="bulk-total-box">
            <span>Selected for Remittance</span>
            <strong id="remittance-selected-total">₱0.00</strong>
        </div>
    </div>

    <?php if (empty($payments)): ?>
    <p class="text-muted">No unremitted payments in this period. All collected payments may already be submitted.</p>
    <?php else: ?>
    <form method="POST" id="remittance-form">
        <div class="bulk-payment-toolbar">
            <label class="checkbox-label">
                <input type="checkbox" id="select-all-payments">
                Select all payments
            </label>
        </div>

        <div class="table-responsive">
            <table class="table table-compact remittance-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Bill #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="payment-select" name="selected_payments[]"
                                   value="<?= $payment['id'] ?>" data-amount="<?= (float) $payment['amount'] ?>">
                        </td>
                        <td><?= formatDate($payment['payment_date']) ?></td>
                        <td>
                            <?= e($payment['full_name']) ?><br>
                            <small class="text-muted"><?= e($payment['account_number']) ?></small>
                        </td>
                        <td><?= e($payment['bill_number']) ?></td>
                        <td><strong class="payment-amount"><?= formatMoney((float) $payment['amount']) ?></strong></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                        <td><?= e($payment['reference_number'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right"><strong>Total Selected</strong></td>
                        <td colspan="3"><strong id="remittance-selected-count">0 payment(s)</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="form-grid" style="margin-top:20px">
            <div class="form-group full-width">
                <label for="notes">Remittance Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Optional notes for the owner (e.g. deposit slip, collection area)"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="open-remittance-modal">Submit to Owner</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="remittance-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Confirm Remittance Submission</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-intro">These payments will be sent to the owner for confirmation. You cannot edit the remittance after submission.</p>
            <dl class="confirm-list">
                <dt>Payments Selected</dt><dd id="remittance-confirm-count">0</dd>
                <dt>Total Amount</dt><dd id="remittance-confirm-total" class="confirm-amount">₱0.00</dd>
            </dl>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirm-remittance-submit">Submit Remittance</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/remittance.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
