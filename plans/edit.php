<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM service_plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();

if (!$plan) {
    flash('danger', 'Plan not found.');
    redirect('/plans/index.php');
}

$pageTitle = 'Edit Plan';
$currentPage = 'plans';
$errors = [];

$countStmt = getDB()->prepare('SELECT COUNT(*) FROM customers WHERE plan_id = ?');
$countStmt->execute([$id]);
$subscriberCount = (int) $countStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $speed = (int) ($_POST['speed_mbps'] ?? 0);
    $fee = (float) ($_POST['monthly_fee'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Plan name is required.';
    if ($speed <= 0) $errors[] = 'Speed must be greater than 0.';
    if ($fee <= 0) $errors[] = 'Monthly fee must be greater than 0.';

    if (empty($errors)) {
        $stmt = getDB()->prepare(
            'UPDATE service_plans SET name=?, speed_mbps=?, monthly_fee=?, description=?, is_active=? WHERE id=?'
        );
        $stmt->execute([$name, $speed, $fee, $description ?: null, $isActive, $id]);
        flash('success', 'Plan updated.');
        redirect('/plans/index.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Plan — <?= e($plan['name']) ?></h1>
    <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Plan Name *</label>
                <input type="text" id="name" name="name" required value="<?= e($plan['name']) ?>">
            </div>
            <div class="form-group">
                <label for="speed_mbps">Speed (Mbps) *</label>
                <input type="number" id="speed_mbps" name="speed_mbps" min="1" required value="<?= $plan['speed_mbps'] ?>">
            </div>
            <div class="form-group">
                <label for="monthly_fee">Monthly Fee (₱) *</label>
                <input type="number" id="monthly_fee" name="monthly_fee" min="0.01" step="0.01" required value="<?= $plan['monthly_fee'] ?>">
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?= $plan['is_active'] ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2"><?= e($plan['description']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php if ($subscriberCount === 0): ?>
<div class="card danger-zone">
    <div class="card-header"><h2>Delete Plan</h2></div>
    <p class="danger-intro">Permanently remove this plan. Only available when no customers are subscribed.</p>
    <form method="POST" action="<?= APP_URL ?>/plans/delete.php"
          onsubmit="return confirm(<?= json_encode('Delete ' . $plan['name'] . '? This cannot be undone.') ?>)">
        <input type="hidden" name="id" value="<?= $plan['id'] ?>">
        <button type="submit" class="btn btn-danger">Delete Plan</button>
    </form>
</div>
<?php else: ?>
<div class="card">
    <p class="text-muted">This plan has <?= number_format($subscriberCount) ?> subscriber(s) and cannot be deleted. Reassign customers or mark the plan inactive instead.</p>
    <?php if (canAccess('customers')): ?>
    <a href="<?= APP_URL ?>/customers/index.php?plan_id=<?= $plan['id'] ?>" class="btn btn-outline btn-sm">View Subscribers</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
