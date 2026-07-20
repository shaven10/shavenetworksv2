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
$canAccessCustomers = canAccess('customers');
$canAccessBilling = canAccess('billing');
$canAccessPayments = canAccess('payments');
$canAccessPlans = canAccess('plans');

$collectionsSeries = fillMonthlySeries($analytics['monthly_collections'], 'total');
$currentMonthFrom = date('Y-m-01');
$currentMonthTo = date('Y-m-d');

$customerStatusLinks = [
    APP_URL . '/customers/index.php?status=active',
    APP_URL . '/customers/index.php?status=suspended',
    APP_URL . '/customers/index.php?status=disconnected',
];

$billStatusLinks = [
    APP_URL . '/billing/index.php?status=paid',
    APP_URL . '/billing/index.php?status=pending',
    APP_URL . '/billing/index.php?status=partial',
    APP_URL . '/billing/index.php?status=overdue',
];

$planSubscriberLinks = array_map(
    static fn(array $plan): string => APP_URL . '/customers/index.php?plan_id=' . (int) $plan['id'],
    $analytics['plan_subscribers']
);

$collectionsLinks = array_map(
    static fn(array $row): string => APP_URL . '/payments/index.php?from=' . $row['from'] . '&to=' . $row['to'],
    $collectionsSeries
);

$chartData = [
    'customerStatus' => [
        'labels' => ['Active', 'Suspended', 'Disconnected'],
        'values' => [
            (int) ($analytics['customer_status']['active'] ?? 0),
            (int) ($analytics['customer_status']['suspended'] ?? 0),
            (int) ($analytics['customer_status']['disconnected'] ?? 0),
        ],
        'links' => $customerStatusLinks,
    ],
    'billStatus' => [
        'labels' => ['Paid', 'Pending', 'Partial', 'Overdue'],
        'values' => [
            (int) ($analytics['bill_status']['paid'] ?? 0),
            (int) ($analytics['bill_status']['pending'] ?? 0),
            (int) ($analytics['bill_status']['partial'] ?? 0),
            (int) ($analytics['bill_status']['overdue'] ?? 0),
        ],
        'links' => $billStatusLinks,
    ],
    'monthlyCollections' => [
        'labels' => array_column($collectionsSeries, 'label'),
        'values' => array_column($collectionsSeries, 'value'),
        'links'  => $collectionsLinks,
    ],
    'planSubscribers' => [
        'labels' => array_column($analytics['plan_subscribers'], 'name'),
        'values' => array_map('intval', array_column($analytics['plan_subscribers'], 'count')),
        'links'  => $planSubscriberLinks,
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
    <div class="header-actions">
        <?php if (hasRole('owner', 'collector')): ?>
        <a href="<?= APP_URL ?>/billing/generate_current.php" class="btn btn-outline btn-sm">Current Month</a>
        <?php endif; ?>
        <?php if (hasRole('owner')): ?>
        <a href="<?= APP_URL ?>/billing/generate.php" class="btn btn-primary btn-sm">Generate Bills</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<section class="dashboard-section">
    <div class="stats-grid stats-grid-simple">
        <?php if ($canAccessCustomers): ?>
        <a href="<?= APP_URL ?>/customers/index.php" class="stat-card stat-simple dashboard-stat-link">
            <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
            <div class="stat-label">Customers</div>
            <div class="stat-sub"><?= number_format($stats['active_customers']) ?> active</div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple">
            <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
            <div class="stat-label">Customers</div>
            <div class="stat-sub"><?= number_format($stats['active_customers']) ?> active</div>
        </div>
        <?php endif; ?>

        <?php if ($canSeeFinancials): ?>
        <?php if ($canAccessBilling): ?>
        <a href="<?= APP_URL ?>/billing/index.php" class="stat-card stat-simple stat-warning dashboard-stat-link">
            <div class="stat-value"><?= number_format($stats['pending_bills']) ?></div>
            <div class="stat-label">Unpaid Bills</div>
            <div class="stat-sub">
                <span><?= number_format($stats['overdue_bills']) ?> overdue</span>
            </div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple stat-warning">
            <div class="stat-value"><?= number_format($stats['pending_bills']) ?></div>
            <div class="stat-label">Unpaid Bills</div>
            <div class="stat-sub"><?= number_format($stats['overdue_bills']) ?> overdue</div>
        </div>
        <?php endif; ?>

        <?php if ($canAccessPayments): ?>
        <a href="<?= APP_URL ?>/payments/index.php?from=<?= e($currentMonthFrom) ?>&to=<?= e($currentMonthTo) ?>" class="stat-card stat-simple stat-info dashboard-stat-link">
            <div class="stat-value"><?= formatMoney($stats['monthly_revenue']) ?></div>
            <div class="stat-label">Collected This Month</div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple stat-info">
            <div class="stat-value"><?= formatMoney($stats['monthly_revenue']) ?></div>
            <div class="stat-label">Collected This Month</div>
        </div>
        <?php endif; ?>

        <?php if ($canAccessBilling): ?>
        <a href="<?= APP_URL ?>/billing/index.php" class="stat-card stat-simple stat-danger dashboard-stat-link">
            <div class="stat-value"><?= formatMoney($stats['outstanding']) ?></div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-sub"><?= $stats['collection_rate'] ?>% collection rate</div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple stat-danger">
            <div class="stat-value"><?= formatMoney($stats['outstanding']) ?></div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-sub"><?= $stats['collection_rate'] ?>% collection rate</div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <?php if ($canAccessCustomers): ?>
        <a href="<?= APP_URL ?>/customers/index.php?status=active" class="stat-card stat-simple stat-success dashboard-stat-link">
            <div class="stat-value"><?= number_format($stats['active_customers']) ?></div>
            <div class="stat-label">Active Subscribers</div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple stat-success">
            <div class="stat-value"><?= number_format($stats['active_customers']) ?></div>
            <div class="stat-label">Active Subscribers</div>
        </div>
        <?php endif; ?>

        <?php if ($canAccessPlans): ?>
        <a href="<?= APP_URL ?>/plans/index.php" class="stat-card stat-simple dashboard-stat-link">
            <div class="stat-value"><?= number_format($stats['total_plans']) ?></div>
            <div class="stat-label">Service Plans</div>
        </a>
        <?php else: ?>
        <div class="stat-card stat-simple">
            <div class="stat-value"><?= number_format($stats['total_plans']) ?></div>
            <div class="stat-label">Service Plans</div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="charts-grid charts-grid-simple">
        <div class="card chart-card dashboard-chart-card">
            <div class="card-header">
                <h2>Customers</h2>
                <?php if ($canAccessCustomers): ?>
                <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-sm btn-outline">Browse</a>
                <?php endif; ?>
            </div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartCustomerStatus"></canvas></div>
            <p class="dashboard-chart-hint">Click a segment to browse customers by status</p>
        </div>

        <div class="card chart-card dashboard-chart-card">
            <div class="card-header">
                <h2>Subscribers by Plan</h2>
                <?php if ($canAccessPlans): ?>
                <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-sm btn-outline">Browse</a>
                <?php endif; ?>
            </div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartPlanSubscribers"></canvas></div>
            <p class="dashboard-chart-hint">Click a bar to browse active subscribers on that plan</p>
        </div>

        <?php if ($canSeeFinancials): ?>
        <div class="card chart-card dashboard-chart-card">
            <div class="card-header">
                <h2>Bills</h2>
                <?php if ($canAccessBilling): ?>
                <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-sm btn-outline">Browse</a>
                <?php endif; ?>
            </div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartBillStatus"></canvas></div>
            <p class="dashboard-chart-hint">Click a segment to browse bills by status</p>
        </div>

        <div class="card chart-card dashboard-chart-card">
            <div class="card-header">
                <h2>Collections (6 Months)</h2>
                <?php if ($canAccessPayments): ?>
                <a href="<?= APP_URL ?>/payments/index.php" class="btn btn-sm btn-outline">Browse</a>
                <?php endif; ?>
            </div>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chartCollections"></canvas></div>
            <p class="dashboard-chart-hint">Click a point to browse payments for that month</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <?php if ($canSeeFinancials && $canAccessBilling): ?>
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
                        'SELECT b.*, c.full_name, c.id AS customer_id FROM bills b
                         JOIN customers c ON b.customer_id = c.id
                         ORDER BY b.generated_at DESC LIMIT 5'
                    )->fetchAll();
                    if (empty($bills)):
                    ?>
                    <tr><td colspan="4" class="text-center text-muted">No bills yet.</td></tr>
                    <?php else: foreach ($bills as $bill): ?>
                    <tr class="dashboard-row-link"
                        tabindex="0"
                        role="link"
                        data-href="<?= APP_URL ?>/billing/index.php?search=<?= urlencode($bill['bill_number']) ?>&status=<?= urlencode($bill['status']) ?>">
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

    <?php if ($canAccessCustomers): ?>
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
                    <tr class="dashboard-row-link"
                        tabindex="0"
                        role="link"
                        data-href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $c['id'] ?>">
                        <td><?= e($c['account_number']) ?></td>
                        <td><?= e($c['full_name']) ?></td>
                        <td><?= statusBadge($c['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php renderRecentAnnouncements(5); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
