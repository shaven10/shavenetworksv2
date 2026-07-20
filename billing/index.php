<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireLogin();

if (!canAccess('billing')) {
    http_response_code(403);
    die('Access denied.');
}

updateOverdueBills();

$pageTitle = 'Billing';
$currentPage = 'billing';

$filters = getBillingFiltersFromRequest();
$listPage = getListPage();
$perPage = getListPerPage();

$result = getBillingCustomersGrouped($filters, $listPage, $perPage);
$customerGroups = $result['groups'];
$summary = $result['summary'];

$filterParams = paginationQuery([
    'search'      => $filters['search'],
    'status'      => $filters['status'],
    'due_from'    => $filters['due_from'],
    'due_to'      => $filters['due_to'],
    'customer_id' => $filters['customer_id'] ?: '',
    'per_page'    => $perPage,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Billing</h1>
        <p>Bills grouped by customer account</p>
    </div>
    <?php if (hasRole('owner', 'collector')): ?>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/billing/collect.php" class="btn btn-outline">Collect Multiple</a>
        <a href="<?= APP_URL ?>/billing/generate.php" class="btn btn-primary">Generate Bills</a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search customer, account, bill..." value="<?= e($filters['search']) ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="partial" <?= $filters['status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
            <option value="overdue" <?= $filters['status'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
        <label>Due from</label>
        <input type="date" name="due_from" value="<?= e($filters['due_from']) ?>">
        <label>Due to</label>
        <input type="date" name="due_to" value="<?= e($filters['due_to']) ?>">
        <select name="per_page">
            <?php foreach (perPageOptions() as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($filters['search'] || $filters['status'] || $filters['due_from'] || $filters['due_to'] || $filters['customer_id']): ?>
        <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <div class="summary-bar billing-summary-bar">
        <strong><?= number_format((int) $summary['customer_count']) ?></strong> customer(s)
        · <strong><?= number_format((int) $summary['bill_count']) ?></strong> bill(s)
        · Billed: <strong><?= formatMoney((float) $summary['total_amount']) ?></strong>
        · Paid: <strong><?= formatMoney((float) $summary['total_paid']) ?></strong>
        · Balance: <strong class="text-danger"><?= formatMoney((float) $summary['total_balance']) ?></strong>
    </div>

    <?php
    $pagination = [
        'total'       => $result['total'],
        'page'        => $result['page'],
        'per_page'    => $result['per_page'],
        'total_pages' => $result['total_pages'],
        'from'        => $result['from'],
        'to'          => $result['to'],
    ];
    ?>
    <?= renderPagination($pagination, $filterParams) ?>

    <?php if (empty($customerGroups)): ?>
    <p class="text-center text-muted billing-empty">No bills found for the selected filters.</p>
    <?php else: ?>
    <div class="billing-table-toolbar">
        <button type="button" class="btn btn-outline btn-sm" id="billing-expand-all">Expand All</button>
        <button type="button" class="btn btn-outline btn-sm" id="billing-collapse-all">Collapse All</button>
    </div>

    <div class="table-responsive">
        <table class="table billing-datatable">
            <thead>
                <tr>
                    <th class="billing-col-toggle" aria-label="Expand"></th>
                    <th>Customer</th>
                    <th>Bill #</th>
                    <th>Billing Period</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <?php foreach ($customerGroups as $group):
                $customer = $group['customer'];
                $bills = $group['bills'];
                $customerBalance = (float) $customer['total_balance'];
            ?>
            <tbody class="billing-customer-group is-collapsed" id="billing-bills-<?= (int) $customer['id'] ?>" data-customer-id="<?= (int) $customer['id'] ?>">
                <tr class="billing-group-header">
                    <td class="billing-col-toggle">
                        <button type="button"
                                class="billing-toggle"
                                aria-expanded="false"
                                aria-controls="billing-bills-<?= (int) $customer['id'] ?>"
                                title="Toggle bills">
                            <span class="billing-toggle-icon" aria-hidden="true"></span>
                        </button>
                    </td>
                    <td colspan="9">
                        <div class="billing-group-header-content">
                            <div class="billing-customer-summary">
                                <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $customer['id'] ?>" class="billing-customer-name">
                                    <?= e($customer['full_name']) ?>
                                </a>
                                <small class="text-muted billing-customer-account"><?= e($customer['account_number']) ?></small>
                                <div class="billing-customer-meta">
                                    <?= statusBadge($customer['customer_status']) ?>
                                    <span class="billing-customer-bills-count"><?= (int) $customer['bill_count'] ?> bill(s)</span>
                                    <span class="billing-customer-balance">Balance: <strong><?= formatMoney($customerBalance) ?></strong></span>
                                </div>
                            </div>
                            <div class="billing-customer-row-actions">
                                <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $customer['id'] ?>" class="btn btn-sm btn-outline">View</a>
                                <?php if ($customerBalance > 0 && hasRole('owner', 'collector')): ?>
                                <a href="<?= APP_URL ?>/billing/collect.php?customer_id=<?= (int) $customer['id'] ?>" class="btn btn-sm btn-primary">Pay All</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php foreach ($bills as $bill):
                    $balance = (float) $bill['amount'] - (float) $bill['paid_amount'];
                ?>
                <tr class="billing-bill-row">
                    <td class="billing-col-toggle"></td>
                    <td class="billing-bill-customer-label">
                        <span class="text-muted"><?= e($customer['full_name']) ?></span>
                    </td>
                    <td><strong><?= e($bill['bill_number']) ?></strong></td>
                    <td><?= formatDate($bill['billing_period_start']) ?> — <?= formatDate($bill['billing_period_end']) ?></td>
                    <td><?= formatDate($bill['due_date']) ?></td>
                    <td><?= formatMoney((float) $bill['amount']) ?></td>
                    <td><?= formatMoney((float) $bill['paid_amount']) ?></td>
                    <td><strong><?= formatMoney($balance) ?></strong></td>
                    <td><?= statusBadge($bill['status']) ?></td>
                    <td>
                        <?php if ($balance > 0 && hasRole('owner', 'collector')): ?>
                        <a href="<?= APP_URL ?>/payments/collect.php?bill_id=<?= $bill['id'] ?>" class="btn btn-sm btn-outline">Collect</a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <?= renderPagination($pagination, $filterParams) ?>
</div>

<script src="<?= APP_URL ?>/assets/js/billing-accordion.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
