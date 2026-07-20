<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$id = (int) ($_GET['id'] ?? 0);
$customer = getLinkedCustomer();

$stmt = getDB()->prepare(
    'SELECT t.*, u.full_name as assigned_name FROM repair_tickets t
     LEFT JOIN users u ON t.assigned_to = u.id
     WHERE t.id = ? AND t.customer_id = ?'
);
$stmt->execute([$id, $customer['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('/portal/tickets/index.php');
}

$pageTitle = $ticket['ticket_number'];
$currentPage = 'repair_tickets';

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($ticket['ticket_number']) ?></h1>
        <p><?= e($ticket['subject']) ?> · <?= ticketStatusBadge($ticket['status']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/portal/tickets/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Issue Details</h3>
        <dl class="detail-list">
            <dt>Category</dt><dd><?= e(ticketCategoryLabel($ticket['category'])) ?></dd>
            <dt>Priority</dt><dd><?= priorityBadge($ticket['priority']) ?></dd>
            <dt>Status</dt><dd><?= ticketStatusBadge($ticket['status']) ?></dd>
            <dt>Assigned To</dt><dd><?= e($ticket['assigned_name'] ?? 'Pending assignment') ?></dd>
            <dt>Submitted</dt><dd><?= formatDate($ticket['created_at']) ?></dd>
            <?php if ($ticket['resolved_at']): ?>
            <dt>Resolved</dt><dd><?= formatDate($ticket['resolved_at']) ?></dd>
            <?php endif; ?>
        </dl>
        <h4 style="margin-top:16px">Description</h4>
        <p><?= nl2br(e($ticket['description'])) ?></p>
    </div>
    <div class="card">
        <h3>Resolution</h3>
        <?php if ($ticket['resolution_notes']): ?>
        <p><?= nl2br(e($ticket['resolution_notes'])) ?></p>
        <?php else: ?>
        <p class="text-muted">No resolution notes yet. Our team is working on your request.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
