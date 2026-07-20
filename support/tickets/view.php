<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('owner', 'technical');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare(
    'SELECT t.*, c.full_name, c.account_number, c.phone, u.full_name as assigned_name
     FROM repair_tickets t
     JOIN customers c ON t.customer_id = c.id
     LEFT JOIN users u ON t.assigned_to = u.id
     WHERE t.id = ?'
);
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    flash('danger', 'Ticket not found.');
    redirect('/support/tickets/index.php');
}

$pageTitle = $ticket['ticket_number'];
$currentPage = 'tickets';
$user = currentUser();
$technicians = getDB()->query("SELECT id, full_name FROM users WHERE role IN ('owner','technical') AND is_active = 1")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? $ticket['status'];
    $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');

    $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = getDB()->prepare(
        'UPDATE repair_tickets SET status=?, assigned_to=?, resolution_notes=?, resolved_at=COALESCE(?, resolved_at) WHERE id=?'
    );
    $stmt->execute([$status, $assignedTo, $resolutionNotes ?: null, $resolvedAt, $id]);

    logActivity('ticket_updated', "Updated ticket {$ticket['ticket_number']} to {$status}");
    flash('success', 'Ticket updated.');
    redirect("/support/tickets/view.php?id={$id}");
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($ticket['ticket_number']) ?></h1>
        <p><?= e($ticket['subject']) ?> · <?= ticketStatusBadge($ticket['status']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/support/tickets/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Customer & Issue</h3>
        <dl class="detail-list">
            <dt>Customer</dt><dd><?= e($ticket['full_name']) ?> (<?= e($ticket['account_number']) ?>)</dd>
            <dt>Phone</dt><dd><?= e($ticket['phone']) ?></dd>
            <dt>Category</dt><dd><?= e(ticketCategoryLabel($ticket['category'])) ?></dd>
            <dt>Priority</dt><dd><?= priorityBadge($ticket['priority']) ?></dd>
            <dt>Submitted</dt><dd><?= formatDate($ticket['created_at']) ?></dd>
        </dl>
        <h4 style="margin-top:16px">Description</h4>
        <p><?= nl2br(e($ticket['description'])) ?></p>
    </div>

    <div class="card card-form">
        <h3>Update Ticket</h3>
        <form method="POST">
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="assigned_to">Assign To</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($technicians as $tech): ?>
                    <option value="<?= $tech['id'] ?>" <?= $ticket['assigned_to'] == $tech['id'] ? 'selected' : '' ?>>
                        <?= e($tech['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="resolution_notes">Resolution Notes</label>
                <textarea id="resolution_notes" name="resolution_notes" rows="4"><?= e($ticket['resolution_notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Update</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
