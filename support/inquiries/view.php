<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!canAccess('inquiries')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare(
    'SELECT i.*, c.full_name, c.account_number, c.phone, u.full_name as responder_name
     FROM inquiries i
     JOIN customers c ON i.customer_id = c.id
     LEFT JOIN users u ON i.responded_by = u.id
     WHERE i.id = ?'
);
$stmt->execute([$id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    flash('danger', 'Inquiry not found.');
    redirect('/support/inquiries/index.php');
}

$pageTitle = $inquiry['inquiry_number'];
$currentPage = 'staff_inquiries';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = trim($_POST['response'] ?? '');
    $status = $_POST['status'] ?? 'answered';

    if ($response) {
        $stmt = getDB()->prepare(
            'UPDATE inquiries SET response=?, status=?, responded_by=?, responded_at=NOW() WHERE id=?'
        );
        $stmt->execute([$response, $status, $user['id'], $id]);
        logActivity('inquiry_answered', "Responded to inquiry {$inquiry['inquiry_number']}");
        flash('success', 'Response saved.');
    } else {
        $stmt = getDB()->prepare('UPDATE inquiries SET status=? WHERE id=?');
        $stmt->execute([$status, $id]);
        flash('success', 'Inquiry status updated.');
    }
    redirect("/support/inquiries/view.php?id={$id}");
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($inquiry['inquiry_number']) ?></h1>
        <p><?= e($inquiry['subject']) ?> · <?= inquiryStatusBadge($inquiry['status']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/support/inquiries/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Customer Inquiry</h3>
        <dl class="detail-list">
            <dt>Customer</dt><dd><?= e($inquiry['full_name']) ?> (<?= e($inquiry['account_number']) ?>)</dd>
            <dt>Phone</dt><dd><?= e($inquiry['phone']) ?></dd>
            <dt>Category</dt><dd><?= e(inquiryCategoryLabel($inquiry['category'])) ?></dd>
            <dt>Submitted</dt><dd><?= formatDate($inquiry['created_at']) ?></dd>
        </dl>
        <h4 style="margin-top:16px">Message</h4>
        <p><?= nl2br(e($inquiry['message'])) ?></p>
    </div>

    <div class="card card-form">
        <h3>Your Response</h3>
        <form method="POST">
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="open" <?= $inquiry['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="answered" <?= $inquiry['status'] === 'answered' ? 'selected' : '' ?>>Answered</option>
                    <option value="closed" <?= $inquiry['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="response">Response to Customer</label>
                <textarea id="response" name="response" rows="6" placeholder="Write your response..."><?= e($inquiry['response']) ?></textarea>
            </div>
            <?php if ($inquiry['responded_at']): ?>
            <p class="form-hint">Last responded by <?= e($inquiry['responder_name']) ?> on <?= formatDate($inquiry['responded_at']) ?></p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Save Response</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
