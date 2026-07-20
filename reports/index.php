<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner');

$pageTitle = 'Reports';
$currentPage = 'reports';

$month = $_GET['month'] ?? date('Y-m');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$stmt = getDB()->prepare(
    'SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM payments
     WHERE payment_date BETWEEN ? AND ?'
);
$stmt->execute([$monthStart, $monthEnd]);
$monthlyCollections = $stmt->fetch();

$stmt = getDB()->prepare(
    'SELECT p.payment_method, SUM(p.amount) as total, COUNT(*) as count
     FROM payments p WHERE p.payment_date BETWEEN ? AND ?
     GROUP BY p.payment_method ORDER BY total DESC'
);
$stmt->execute([$monthStart, $monthEnd]);
$byMethod = $stmt->fetchAll();

$stmt = getDB()->prepare(
    'SELECT u.full_name, SUM(p.amount) as total, COUNT(*) as count
     FROM payments p JOIN users u ON p.collected_by = u.id
     WHERE p.payment_date BETWEEN ? AND ?
     GROUP BY p.collected_by ORDER BY total DESC'
);
$stmt->execute([$monthStart, $monthEnd]);
$byCollector = $stmt->fetchAll();

$planStats = getDB()->query(
    'SELECT p.name, p.monthly_fee, COUNT(c.id) as subscribers,
            SUM(CASE WHEN c.status = "active" THEN 1 ELSE 0 END) as active_subs
     FROM service_plans p LEFT JOIN customers c ON p.id = c.plan_id
     GROUP BY p.id ORDER BY subscribers DESC'
)->fetchAll();

$overdueTotal = (float) getDB()->query(
    "SELECT COALESCE(SUM(amount - paid_amount), 0) FROM bills WHERE status = 'overdue'"
)->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Reports</h1>
        <p>Revenue and collection analytics</p>
    </div>
    <form method="GET" class="inline-form">
        <input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card stat-info">
        <div class="stat-value"><?= formatMoney($monthlyCollections['total']) ?></div>
        <div class="stat-label">Collections (<?= date('F Y', strtotime($monthStart)) ?>)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $monthlyCollections['count'] ?></div>
        <div class="stat-label">Payments Received</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-value"><?= formatMoney($overdueTotal) ?></div>
        <div class="stat-label">Total Overdue</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Collections by Payment Method</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead>
                <tbody>
                    <?php if (empty($byMethod)): ?>
                    <tr><td colspan="3" class="text-muted text-center">No data.</td></tr>
                    <?php else: foreach ($byMethod as $row): ?>
                    <tr>
                        <td><?= e(ucfirst(str_replace('_', ' ', $row['payment_method']))) ?></td>
                        <td><?= $row['count'] ?></td>
                        <td><?= formatMoney($row['total']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Collections by Collector</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Collector</th><th>Count</th><th>Total</th></tr></thead>
                <tbody>
                    <?php if (empty($byCollector)): ?>
                    <tr><td colspan="3" class="text-muted text-center">No data.</td></tr>
                    <?php else: foreach ($byCollector as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= $row['count'] ?></td>
                        <td><?= formatMoney($row['total']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Plan Subscribers</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Plan</th><th>Monthly Fee</th><th>Total Subscribers</th><th>Active</th><th>Potential Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($planStats as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= formatMoney($row['monthly_fee']) ?></td>
                    <td><?= $row['subscribers'] ?></td>
                    <td><?= $row['active_subs'] ?></td>
                    <td><?= formatMoney($row['monthly_fee'] * $row['active_subs']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
