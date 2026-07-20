<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!canAccess('plans')) {
    http_response_code(403);
    die('Access denied.');
}

$pageTitle = 'Service Plans';
$currentPage = 'plans';

$plans = getDB()->query(
    'SELECT p.*, (SELECT COUNT(*) FROM customers c WHERE c.plan_id = p.id) as subscriber_count
     FROM service_plans p ORDER BY p.monthly_fee'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Service Plans</h1>
        <p>Internet packages and monthly rates</p>
    </div>
    <?php if (hasRole('owner')): ?>
    <a href="<?= APP_URL ?>/plans/create.php" class="btn btn-primary">+ Add Plan</a>
    <?php endif; ?>
</div>

<div class="plans-grid">
    <?php foreach ($plans as $plan): ?>
    <div class="plan-card <?= !$plan['is_active'] ? 'inactive' : '' ?>">
        <div class="plan-speed"><?= $plan['speed_mbps'] ?> <small>Mbps</small></div>
        <h3><?= e($plan['name']) ?></h3>
        <div class="plan-price"><?= formatMoney($plan['monthly_fee']) ?><span>/month</span></div>
        <p><?= e($plan['description']) ?></p>
        <div class="plan-meta">
            <?php if (canAccess('customers')): ?>
            <a href="<?= APP_URL ?>/customers/index.php?plan_id=<?= $plan['id'] ?>" class="plan-subscribers-link">
                <?= number_format((int) $plan['subscriber_count']) ?> subscriber<?= (int) $plan['subscriber_count'] === 1 ? '' : 's' ?>
            </a>
            <?php else: ?>
            <span><?= number_format((int) $plan['subscriber_count']) ?> subscribers</span>
            <?php endif; ?>
            <?= $plan['is_active'] ? statusBadge('active') : '<span class="badge badge-secondary">Inactive</span>' ?>
        </div>
        <?php if (canAccess('customers')): ?>
        <a href="<?= APP_URL ?>/customers/index.php?plan_id=<?= $plan['id'] ?>" class="btn btn-outline btn-sm btn-block plan-view-subscribers">
            View Subscribers
        </a>
        <?php endif; ?>
        <?php if (hasRole('owner')): ?>
        <div class="plan-actions">
            <a href="<?= APP_URL ?>/plans/edit.php?id=<?= $plan['id'] ?>" class="btn btn-outline btn-sm">Edit Plan</a>
            <?php if ((int) $plan['subscriber_count'] === 0): ?>
            <form method="POST" action="<?= APP_URL ?>/plans/delete.php"
                  onsubmit='return confirm(<?= json_encode('Delete ' . $plan['name'] . '? This cannot be undone.') ?>)'>
                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
