<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'New Repair Ticket';
$currentPage = 'repair_tickets';
$customer = getLinkedCustomer();
$user = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $priority = $_POST['priority'] ?? 'medium';

    if (!$subject) $errors[] = 'Subject is required.';
    if (!$description) $errors[] = 'Please describe the issue.';

    if (empty($errors)) {
        $stmt = getDB()->prepare(
            'INSERT INTO repair_tickets (ticket_number, customer_id, subject, description, category, priority, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            generateTicketNumber(), $customer['id'], $subject, $description,
            $category, $priority, $user['id'],
        ]);
        logActivity('ticket_created', 'Customer created repair ticket');
        flash('success', 'Repair ticket submitted successfully. Our technical team will review it.');
        redirect('/portal/tickets/index.php');
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Report an Issue</h1>
    <a href="<?= APP_URL ?>/portal/tickets/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required value="<?= e($_POST['subject'] ?? '') ?>"
                       placeholder="e.g. No internet since this morning">
            </div>
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="no_internet">No Internet</option>
                    <option value="slow_connection">Slow Connection</option>
                    <option value="router_issue">Router / Equipment</option>
                    <option value="billing_related">Billing Related</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="description">Issue Description *</label>
                <textarea id="description" name="description" rows="5" required
                          placeholder="Describe the problem, when it started, and any troubleshooting you've tried..."><?= e($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Ticket</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
