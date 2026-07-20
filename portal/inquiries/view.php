<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$id = (int) ($_GET['id'] ?? 0);
$customer = getLinkedCustomer();

$stmt = getDB()->prepare(
    'SELECT i.*, u.full_name as responder_name FROM inquiries i
     LEFT JOIN users u ON i.responded_by = u.id
     WHERE i.id = ? AND i.customer_id = ?'
);
$stmt->execute([$id, $customer['id']]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    flash('danger', 'Inquiry not found.');
    redirect('/portal/inquiries/index.php');
}

$pageTitle = $inquiry['inquiry_number'];
$currentPage = 'inquiries';

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($inquiry['inquiry_number']) ?></h1>
        <p><?= e($inquiry['subject']) ?> · <?= inquiryStatusBadge($inquiry['status']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/portal/inquiries/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Your Inquiry</h3>
        <dl class="detail-list">
            <dt>Category</dt><dd><?= e(inquiryCategoryLabel($inquiry['category'])) ?></dd>
            <dt>Submitted</dt><dd><?= formatDate($inquiry['created_at']) ?></dd>
        </dl>
        <h4 style="margin-top:16px">Message</h4>
        <p><?= nl2br(e($inquiry['message'])) ?></p>
    </div>
    <div class="card">
        <h3>Response</h3>
        <?php if ($inquiry['response']): ?>
        <dl class="detail-list">
            <dt>Responded By</dt><dd><?= e($inquiry['responder_name']) ?></dd>
            <dt>Date</dt><dd><?= formatDate($inquiry['responded_at']) ?></dd>
        </dl>
        <p style="margin-top:12px"><?= nl2br(e($inquiry['response'])) ?></p>
        <?php else: ?>
        <p class="text-muted">Your inquiry is being reviewed. A response will appear here once available.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
