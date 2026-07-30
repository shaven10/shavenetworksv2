<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();



if (!canAccess('customers')) {

    http_response_code(403);

    die('Access denied.');

}



$pageTitle = 'Customers';

$currentPage = 'customers';



$search = trim($_GET['search'] ?? '');

$status = $_GET['status'] ?? '';

$planId = (int) ($_GET['plan_id'] ?? 0);

$listPage = getListPage();

$perPage = getListPerPage();



$fromWhere = 'FROM customers c JOIN service_plans p ON c.plan_id = p.id WHERE 1=1';

$params = [];



if ($search) {

    $fromWhere .= ' AND (c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';

    $params[] = "%{$search}%";

    $params[] = "%{$search}%";

    $params[] = "%{$search}%";

    $params[] = "%{$search}%";

}

if ($status) {

    $fromWhere .= ' AND c.status = ?';

    $params[] = $status;

}

if ($planId) {

    $fromWhere .= ' AND c.plan_id = ?';

    $params[] = $planId;

}



$result = paginatedSelect(

    'SELECT c.*, p.name as plan_name, p.monthly_fee',

    $fromWhere,

    $params,

    'ORDER BY c.created_at DESC',

    $listPage,

    $perPage

);

$customers = $result['data'];

$plans = getDB()->query('SELECT id, name FROM service_plans WHERE is_active = 1 ORDER BY name')->fetchAll();

$filterPlanName = '';
if ($planId) {
    $planStmt = getDB()->prepare('SELECT name FROM service_plans WHERE id = ?');
    $planStmt->execute([$planId]);
    $filterPlanName = $planStmt->fetchColumn() ?: '';
}



$filterParams = paginationQuery([

    'search'   => $search,

    'status'   => $status,

    'plan_id'  => $planId ?: '',

    'per_page' => $perPage,

]);



require __DIR__ . '/../includes/header.php';

?>



<div class="page-header">

    <div>

        <h1>Customers</h1>

        <?php if ($filterPlanName): ?>
        <p>Subscribers on <strong><?= e($filterPlanName) ?></strong>
            <a href="<?= APP_URL ?>/customers/index.php" class="filter-clear-link">Show all</a>
        </p>
        <?php else: ?>
        <p>Manage subscriber accounts and installations</p>
        <?php endif; ?>

    </div>

    <?php if (hasRole('owner', 'technical')): ?>
    <div class="header-actions">
        <?php if (hasRole('owner')): ?>
        <a href="<?= APP_URL ?>/customers/import.php" class="btn btn-outline">Import Excel</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/customers/create.php" class="btn btn-primary">+ Add Customer</a>
    </div>
    <?php endif; ?>
</div>



<div class="card">

    <form method="GET" class="filter-bar">

        <input type="text" name="search" placeholder="Search name, account, phone, email..." value="<?= e($search) ?>">

        <select name="status">

            <option value="">All Status</option>

            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>

            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>

            <option value="disconnected" <?= $status === 'disconnected' ? 'selected' : '' ?>>Disconnected</option>

        </select>

        <select name="plan_id">

            <option value="">All Plans</option>

            <?php foreach ($plans as $plan): ?>

            <option value="<?= $plan['id'] ?>" <?= $planId === (int) $plan['id'] ? 'selected' : '' ?>>

                <?= e($plan['name']) ?>

            </option>

            <?php endforeach; ?>

        </select>

        <select name="per_page">

            <?php foreach (perPageOptions() as $option): ?>

            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>

            <?php endforeach; ?>

        </select>

        <button type="submit" class="btn btn-outline">Filter</button>

        <?php if ($search || $status || $planId): ?>

        <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-outline">Clear</a>

        <?php endif; ?>

    </form>



    <?= renderPagination($result, $filterParams) ?>



    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>Account #</th>

                    <th>Customer</th>

                    <th>Plan</th>

                    <th>Connection</th>

                    <th>Installation Date</th>

                    <th>Next Billing</th>

                    <th>Monthly Fee</th>

                    <th>Status</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($customers)): ?>

                <tr><td colspan="9" class="text-center text-muted">No customers found.</td></tr>

                <?php else: foreach ($customers as $c):

                    $nextBilling = getNextBillingDate($c['installation_date']);

                ?>

                <tr>

                    <td>
                        <a href="<?= APP_URL ?>/customers/view.php?id=<?= $c['id'] ?>" class="account-link">
                            <strong><?= e($c['account_number']) ?></strong>
                        </a>
                    </td>

                    <td>

                        <?= e($c['full_name']) ?><br>

                        <small class="text-muted"><?= e($c['phone']) ?></small>

                    </td>

                    <td><?= e($c['plan_name']) ?></td>

                    <td><?= connectionMediumBadge($c['connection_medium'] ?? null) ?></td>

                    <td><?= formatDate($c['installation_date']) ?></td>

                    <td><?= formatDate($nextBilling) ?></td>

                    <td><?= formatMoney($c['monthly_fee']) ?></td>

                    <td><?= statusBadge($c['status']) ?></td>

                    <td class="actions">

                        <a href="<?= APP_URL ?>/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">View</a>

                        <?php if (hasRole('owner', 'technical')): ?>

                        <a href="<?= APP_URL ?>/customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Edit</a>

                        <?php endif; ?>

                    </td>

                </tr>

                <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>



    <?= renderPagination($result, $filterParams) ?>

</div>



<?php require __DIR__ . '/../includes/footer.php'; ?>

