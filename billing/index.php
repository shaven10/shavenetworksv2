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
$nextPayableBillMap = getNextPayableBillIdMap(array_column(array_column($customerGroups, 'customer'), 'id'));

$filterParams = paginationQuery(['per_page' => $perPage]);

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
        <?php if (hasRole('owner', 'collector')): ?>
        <a href="<?= APP_URL ?>/billing/generate_current.php" class="btn btn-outline">Current Month</a>
        <?php endif; ?>
        <?php if (hasRole('owner')): ?>
        <a href="<?= APP_URL ?>/billing/generate.php" class="btn btn-primary">Generate Bills</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card">
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
    <p class="text-center text-muted billing-empty">No bills found.</p>
    <?php else: ?>
    <div class="billing-table-toolbar">
        <div class="module-search-field module-search-field-inline billing-list-filter">
            <span class="module-search-icon" aria-hidden="true">🔍</span>
            <input type="search"
                   id="billing-list-filter"
                   class="module-search-input"
                   placeholder="Filter visible customers on this page..."
                   autocomplete="off"
                   aria-label="Filter visible customers on this page">
        </div>
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
                $searchBlob = strtolower(
                    $customer['full_name'] . ' '
                    . $customer['account_number'] . ' '
                    . implode(' ', array_column($bills, 'bill_number'))
                );
            ?>
            <tbody class="billing-customer-group is-collapsed"
                   id="billing-bills-<?= (int) $customer['id'] ?>"
                   data-customer-id="<?= (int) $customer['id'] ?>"
                   data-search="<?= e($searchBlob) ?>">
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
                    $isNextPayable = ((int) ($nextPayableBillMap[(int) $customer['id']] ?? 0)) === (int) $bill['id'];
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
                        <?php if ((float) $bill['paid_amount'] > 0 && hasRole('owner', 'collector')): ?>
                        <a href="<?= APP_URL ?>/billing/bill_payments.php?bill_id=<?= (int) $bill['id'] ?>" class="btn btn-sm btn-outline">Payments</a>
                        <?php endif; ?>
                        <?php if ($balance > 0 && hasRole('owner', 'collector') && $isNextPayable): ?>
                        <a href="<?= APP_URL ?>/payments/collect.php?bill_id=<?= $bill['id'] ?>" class="btn btn-sm btn-primary">Collect</a>
                        <?php elseif ($balance > 0 && hasRole('owner', 'collector')): ?>
                        <span class="text-muted payment-order-blocked" title="Pay older billing periods first">Pay older first</span>
                        <?php elseif ((float) $bill['paid_amount'] <= 0): ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>
    <p class="text-center text-muted billing-empty billing-list-filter-empty hidden" id="billing-list-filter-empty">
        No customers on this page match your filter.
    </p>
    <?php endif; ?>

    <?= renderPagination($pagination, $filterParams) ?>
</div>

<script src="<?= APP_URL ?>/assets/js/billing-accordion.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
