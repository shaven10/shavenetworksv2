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

$filterParams = paginationQuery([
    'search'      => $filters['search'],
    'status'      => $filters['status'],
    'due_from'    => $filters['due_from'],
    'due_to'      => $filters['due_to'],
    'customer_id' => $filters['customer_id'] ?: '',
    'per_page'    => $perPage,
]);
$hasSearch = $filters['search'] !== '';

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

    <div class="billing-table-toolbar">
        <form method="GET" action="<?= APP_URL ?>/billing/index.php" class="module-search-field module-search-field-inline billing-list-filter" id="billing-search-form">
            <?php if ($filters['status']): ?>
            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
            <?php endif; ?>
            <?php if ($filters['due_from']): ?>
            <input type="hidden" name="due_from" value="<?= e($filters['due_from']) ?>">
            <?php endif; ?>
            <?php if ($filters['due_to']): ?>
            <input type="hidden" name="due_to" value="<?= e($filters['due_to']) ?>">
            <?php endif; ?>
            <?php if ($filters['customer_id']): ?>
            <input type="hidden" name="customer_id" value="<?= (int) $filters['customer_id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
            <span class="module-search-icon" aria-hidden="true">🔍</span>
            <input type="search"
                   id="billing-list-filter"
                   name="search"
                   class="module-search-input"
                   placeholder="Search all customers, accounts, bills..."
                   value="<?= e($filters['search']) ?>"
                   autocomplete="off"
                   aria-label="Search all billing records">
        </form>
        <?php if ($hasSearch): ?>
        <a href="<?= APP_URL ?>/billing/index.php?<?= e(http_build_query(array_filter([
            'status'      => $filters['status'],
            'due_from'    => $filters['due_from'],
            'due_to'      => $filters['due_to'],
            'customer_id' => $filters['customer_id'] ?: '',
            'per_page'    => $perPage,
        ], static fn ($value) => $value !== '' && $value !== null))) ?>" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
        <?php if (!empty($customerGroups)): ?>
        <button type="button" class="btn btn-outline btn-sm" id="billing-expand-all">Expand All</button>
        <button type="button" class="btn btn-outline btn-sm" id="billing-collapse-all">Collapse All</button>
        <?php endif; ?>
    </div>

    <?php if (empty($customerGroups)): ?>
    <p class="text-center text-muted billing-empty"><?= $hasSearch ? 'No billing records match your search.' : 'No bills found.' ?></p>
    <?php else: ?>
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
                $isExpanded = $hasSearch;
            ?>
            <tbody class="billing-customer-group <?= $isExpanded ? 'is-expanded' : 'is-collapsed' ?>"
                   id="billing-bills-<?= (int) $customer['id'] ?>"
                   data-customer-id="<?= (int) $customer['id'] ?>">
                <tr class="billing-group-header">
                    <td class="billing-col-toggle">
                        <button type="button"
                                class="billing-toggle"
                                aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
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
                                <?php if (hasRole('owner')): ?>
                                <button type="button"
                                        class="btn btn-sm btn-danger"
                                        data-open-billing-delete
                                        data-delete-scope="customer"
                                        data-customer-id="<?= (int) $customer['id'] ?>"
                                        data-customer-name="<?= e($customer['full_name']) ?>"
                                        data-account-number="<?= e($customer['account_number']) ?>"
                                        data-bill-count="<?= (int) $customer['bill_count'] ?>">
                                    Delete Bills
                                </button>
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
                        <div class="billing-bill-actions">
                            <?php if ((float) $bill['paid_amount'] > 0 && hasRole('owner', 'collector')): ?>
                            <a href="<?= APP_URL ?>/billing/bill_payments.php?bill_id=<?= (int) $bill['id'] ?>" class="btn btn-sm btn-outline">Payments</a>
                            <?php endif; ?>
                            <?php if ($balance > 0 && hasRole('owner', 'collector') && $isNextPayable): ?>
                            <a href="<?= APP_URL ?>/payments/collect.php?bill_id=<?= $bill['id'] ?>" class="btn btn-sm btn-primary">Collect</a>
                            <?php elseif ($balance > 0 && hasRole('owner', 'collector')): ?>
                            <span class="text-muted payment-order-blocked" title="Pay older billing periods first">Pay older first</span>
                            <?php endif; ?>
                            <?php if (hasRole('owner')): ?>
                            <button type="button"
                                    class="btn btn-sm btn-danger"
                                    data-open-billing-delete
                                    data-delete-scope="bill"
                                    data-bill-id="<?= (int) $bill['id'] ?>"
                                    data-bill-number="<?= e($bill['bill_number']) ?>"
                                    data-customer-id="<?= (int) $customer['id'] ?>"
                                    data-customer-name="<?= e($customer['full_name']) ?>"
                                    data-account-number="<?= e($customer['account_number']) ?>">
                                Delete
                            </button>
                            <?php elseif ((float) $bill['paid_amount'] <= 0 && $balance <= 0): ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
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

<?php if (hasRole('owner')): ?>
<div class="modal-overlay" id="billing-delete-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="billing-delete-title">
        <div class="modal-header">
            <h2 id="billing-delete-title">Delete Billing Records</h2>
            <button type="button" class="modal-close" data-close-billing-delete>&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/billing/delete.php" id="billing-delete-form">
            <input type="hidden" name="scope" id="billing-delete-scope" value="">
            <input type="hidden" name="bill_id" id="billing-delete-bill-id" value="">
            <input type="hidden" name="customer_id" id="billing-delete-customer-id" value="">
            <input type="hidden" name="search" value="<?= e($filters['search']) ?>">
            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
            <input type="hidden" name="due_from" value="<?= e($filters['due_from']) ?>">
            <input type="hidden" name="due_to" value="<?= e($filters['due_to']) ?>">
            <input type="hidden" name="filter_customer_id" value="<?= (int) $filters['customer_id'] ?: '' ?>">
            <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
            <input type="hidden" name="page" value="<?= (int) $listPage ?>">
            <div class="modal-body">
                <p class="modal-short-message" id="billing-delete-message"></p>
                <p class="modal-short-meta text-muted">
                    Related payments will also be removed. Advance credits applied to these bills will be restored.
                    This action cannot be undone.
                </p>
                <div class="form-group">
                    <label for="billing-delete-confirm">Type DELETE to confirm *</label>
                    <input type="text" id="billing-delete-confirm" name="confirm_text" required autocomplete="off" placeholder="DELETE">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-billing-delete>Cancel</button>
                <button type="submit" class="btn btn-danger" id="billing-delete-submit">Delete</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/billing-accordion.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
