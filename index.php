<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/billing.php';
requireLogin();

if (hasRole('customer')) {
    redirect('/portal/index.php');
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$stats = getDashboardStats();
$analytics = getDashboardAnalytics();
$user = currentUser();

$canSeeFinancials = hasRole('owner', 'collector');
$collectionsSeries = fillMonthlySeries($analytics['monthly_collections'], 'total');

$chartData = [
    'customerStatus' => [
        'labels' => ['Active', 'Suspended', 'Disconnected'],
        'values' => [
            (int) ($analytics['customer_status']['active'] ?? 0),
            (int) ($analytics['customer_status']['suspended'] ?? 0),
            (int) ($analytics['customer_status']['disconnected'] ?? 0),
        ],
    ],
    'billStatus' => [
        'labels' => ['Paid', 'Pending', 'Partial', 'Overdue'],
        'values' => [
            (int) ($analytics['bill_status']['paid'] ?? 0),
            (int) ($analytics['bill_status']['pending'] ?? 0),
            (int) ($analytics['bill_status']['partial'] ?? 0),
            (int) ($analytics['bill_status']['overdue'] ?? 0),
        ],
    ],
    'monthlyCollections' => [
        'labels' => array_column($collectionsSeries, 'label'),
        'values' => array_column($collectionsSeries, 'value'),
    ],
    'planSubscribers' => [
        'labels' => array_column($analytics['plan_subscribers'], 'name'),
        'values' => array_map('intval', array_column($analytics['plan_subscribers'], 'count')),
    ],
];

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
    . '<script>window.dashboardData = ' . json_encode($chartData) . ';</script>'
    . '<script src="' . APP_URL . '/assets/js/dashboard.js"></script>';

require __DIR__ . '/includes/header.php';
?>

<div class="page-header page-header-compact">
    <div>
        <p class="page-subtitle">Welcome, <?= e($user['full_name']) ?> · <?= roleLabel($user['role']) ?></p>
    </div>
    <?php if ($canSeeFinancials): ?>
    <a href="<?= APP_URL ?>/billing/generate.php" class="btn btn-primary btn-sm">Generate Bills</a>
    <?php endif; ?>
</div>

<section class="dashboard-section">
    <div class="stats-grid stats-grid-simple">
        <div class="stat-card stat-simple">
            <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
            <div class="stat-label">Customers</div>
            <div class="stat-sub"><?= number_format($stats['active_customers']) ?> active</div>
        </div>

        <?php if ($canSeeFinancials): ?>
        <div class="stat-card stat-simple stat-warning">
            <div class="stat-value"><?= number_format($stats['pending_bills']) ?></div>
            <div class="stat-label">Unpaid Bills</div>
            <div class="stat-sub"><?= number_format($stats['overdue_bills']) ?> overdue</div>
        </div>
        <div class="stat-card stat-simple stat-info">
            <div class="stat-value"><?= formatMoney($stats['monthly_revenue']) ?></div>
            <div class="stat-label">Collected This Month</div>
        </div>
        <div class="stat-card stat-simple stat-danger">
            <div class="stat-value"><?= formatMoney($stats['outstanding']) ?></div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-sub"><?= $stats['collection_rate'] ?>% collection rate</div>
        </div>
        <?php else: ?>
        <div class="stat-card stat-simple stat-success">
            <div class="stat-value"><?= number_format($stats['active_customers']) ?></div>
            <div class="stat-label">Active Subscribers</div>
        </div>
        <div class="stat-card stat-simple">
            <div class="stat-value"><?= number_format($stats['total_plans']) ?></div>
            <div class="stat-label">Service Plans</div>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="charts-grid charts-grid-simple">
        <div class="card chart-card">
            <div class="card-header"><h2>Customers</h2></div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartCustomerStatus"></canvas></div>
        </div>
        <div class="card chart-card">
            <div class="card-header"><h2>Subscribers by Plan</h2></div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartPlanSubscribers"></canvas></div>
        </div>
        <?php if ($canSeeFinancials): ?>
        <div class="card chart-card">
            <div class="card-header"><h2>Bills</h2></div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartBillStatus"></canvas></div>
        </div>
        <div class="card chart-card">
            <div class="card-header"><h2>Collections (6 Months)</h2></div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartCollections"></canvas></div>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <?php if ($canSeeFinancials): ?>
    <div class="card">
        <div class="card-header">
            <h2>Recent Bills</h2>
            <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-compact">
                <thead>
                    <tr><th>Bill #</th><th>Customer</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php
                    $bills = getDB()->query(
                        'SELECT b.*, c.full_name FROM bills b
                         JOIN customers c ON b.customer_id = c.id
                         ORDER BY b.generated_at DESC LIMIT 5'
                    )->fetchAll();
                    if (empty($bills)):
                    ?>
                    <tr><td colspan="4" class="text-center text-muted">No bills yet.</td></tr>
                    <?php else: foreach ($bills as $bill): ?>
                    <tr>
                        <td><?= e($bill['bill_number']) ?></td>
                        <td><?= e($bill['full_name']) ?></td>
                        <td><?= formatDate($bill['due_date']) ?></td>
                        <td><?= statusBadge($bill['status']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Recent Customers</h2>
            <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-compact">
                <thead>
                    <tr><th>Account</th><th>Name</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php
                    $customers = getDB()->query('SELECT * FROM customers ORDER BY created_at DESC LIMIT 5')->fetchAll();
                    foreach ($customers as $c):
                    ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/customers/view.php?id=<?= $c['id'] ?>"><?= e($c['account_number']) ?></a></td>
                        <td><?= e($c['full_name']) ?></td>
                        <td><?= statusBadge($c['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
