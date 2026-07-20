<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$pageTitle = 'Add Service Plan';
$currentPage = 'plans';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $speed = (int) ($_POST['speed_mbps'] ?? 0);
    $fee = (float) ($_POST['monthly_fee'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (!$name) $errors[] = 'Plan name is required.';
    if ($speed <= 0) $errors[] = 'Speed must be greater than 0.';
    if ($fee <= 0) $errors[] = 'Monthly fee must be greater than 0.';

    if (empty($errors)) {
        $stmt = getDB()->prepare(
            'INSERT INTO service_plans (name, speed_mbps, monthly_fee, description) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $speed, $fee, $description ?: null]);
        logActivity('plan_created', "Created plan: {$name}");
        flash('success', 'Service plan created.');
        redirect('/plans/index.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add Service Plan</h1>
    <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Plan Name *</label>
                <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="speed_mbps">Speed (Mbps) *</label>
                <input type="number" id="speed_mbps" name="speed_mbps" min="1" required value="<?= e($_POST['speed_mbps'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="monthly_fee">Monthly Fee (₱) *</label>
                <input type="number" id="monthly_fee" name="monthly_fee" min="0.01" step="0.01" required value="<?= e($_POST['monthly_fee'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Plan</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
