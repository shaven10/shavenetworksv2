<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('owner', 'collector');

$id = (int) ($_GET['id'] ?? 0);
$remittance = getRemittance($id);

if (!$remittance || !canViewRemittance($remittance)) {
    flash('danger', 'Remittance not found.');
    redirect('/remittances/index.php');
}

$pageTitle = 'Remittance ' . $remittance['remittance_number'];
$currentPage = 'remittances';
$errors = [];
$payments = getRemittancePayments($id);
$canReview = canReviewRemittance($remittance);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReview) {
    $action = $_POST['action'] ?? '';
    $ownerNotes = trim($_POST['owner_notes'] ?? '');

    try {
        if ($action === 'confirm') {
            confirmRemittance($id, (int) currentUser()['id'], $ownerNotes ?: null);
            logActivity(
                'remittance_confirmed',
                "Confirmed remittance {$remittance['remittance_number']} from {$remittance['collector_name']}"
            );
            flash('success', 'Remittance confirmed successfully.');
        } elseif ($action === 'reject') {
            rejectRemittance($id, (int) currentUser()['id'], $ownerNotes);
            logActivity(
                'remittance_rejected',
                "Rejected remittance {$remittance['remittance_number']} from {$remittance['collector_name']}"
            );
            flash('success', 'Remittance rejected. Payments are available for resubmission.');
        } else {
            throw new RuntimeException('Invalid action.');
        }

        redirect('/remittances/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }

    $remittance = getRemittance($id);
    $canReview = canReviewRemittance($remittance);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($remittance['remittance_number']) ?></h1>
        <p><?= e($remittance['collector_name']) ?> · Submitted <?= formatDate($remittance['submitted_at']) ?></p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/remittances/index.php" class="btn btn-outline btn-sm">← Remittances</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="bulk-receipt-summary remittance-detail-summary">
        <div>
            <span class="text-muted">Status</span>
            <strong><?= remittanceStatusBadge($remittance['status']) ?></strong>
        </div>
        <div>
            <span class="text-muted">Payments</span>
            <strong><?= (int) $remittance['payment_count'] ?></strong>
        </div>
        <div>
            <span class="text-muted">Total Amount</span>
            <strong class="confirm-amount"><?= formatMoney((float) $remittance['total_amount']) ?></strong>
        </div>
        <?php if ($remittance['reviewed_at']): ?>
        <div>
            <span class="text-muted">Reviewed</span>
            <strong><?= formatDate($remittance['reviewed_at']) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($remittance['notes']): ?>
    <div class="remittance-notes">
        <strong>Collector Notes</strong>
        <p><?= nl2br(e($remittance['notes'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($remittance['owner_notes']): ?>
    <div class="remittance-notes">
        <strong>Owner Response</strong>
        <p><?= nl2br(e($remittance['owner_notes'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-compact">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Bill #</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= formatDate($payment['payment_date']) ?></td>
                    <td>
                        <?= e($payment['full_name']) ?><br>
                        <small class="text-muted"><?= e($payment['account_number']) ?></small>
                    </td>
                    <td><?= e($payment['bill_number']) ?></td>
                    <td><strong><?= formatMoney((float) $payment['amount']) ?></strong></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                    <td>
                        <?php if (!empty($payment['batch_id'])): ?>
                        <a href="<?= APP_URL ?>/billing/batch_invoice.php?id=<?= (int) $payment['batch_id'] ?>" class="btn btn-sm btn-outline">Batch Invoice</a>
                        <?php else: ?>
                        <a href="<?= APP_URL ?>/payments/invoice.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canReview): ?>
<div class="card card-form">
    <h2>Owner Review</h2>
    <p class="text-muted">Confirm that the collector has turned over the listed payments, or reject with a reason.</p>

    <form method="POST" id="remittance-review-form">
        <div class="form-group full-width">
            <label for="owner_notes">Owner Notes</label>
            <textarea id="owner_notes" name="owner_notes" rows="3" placeholder="Optional for confirmation. Required when rejecting."><?= e($_POST['owner_notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-outline" id="open-reject-modal">Reject</button>
            <button type="button" class="btn btn-primary" id="open-confirm-modal">Confirm Remittance</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="confirm-remittance-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Confirm Remittance</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-intro">Confirm that you received <?= formatMoney((float) $remittance['total_amount']) ?> from <?= e($remittance['collector_name']) ?> for <?= (int) $remittance['payment_count'] ?> payment(s).</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-primary" data-review-action="confirm">Confirm</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="reject-remittance-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Reject Remittance</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-intro">Rejected payments will return to the collector's unremitted list for resubmission. Please provide a reason.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-danger" data-review-action="reject">Reject Remittance</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/remittance-review.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
