<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireLogin();

if (!canAccess('payments')) {
    http_response_code(403);
    die('Access denied.');
}

$pageTitle = 'Payments';
$currentPage = 'payments';

$filters = getPaymentFiltersFromRequest();
$listPage = getListPage();
$perPage = getListPerPage();

$result = getPaymentCustomersGrouped($filters, $listPage, $perPage);
$customerGroups = $result['groups'];
$summary = $result['summary'];

$filterParams = paginationQuery([
    'from'           => $filters['from'],
    'to'             => $filters['to'],
    'search'         => $filters['search'],
    'payment_method' => $filters['payment_method'],
    'customer_id'    => $filters['customer_id'] ?: '',
    'per_page'       => $perPage,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payments</h1>
        <p>Collection records grouped by customer account</p>
    </div>
    <?php if (hasRole('owner', 'collector')): ?>
    <div class="header-actions">
        <?php if (hasRole('collector') && getUnremittedPaymentCount((int) currentUser()['id']) > 0): ?>
        <a href="<?= APP_URL ?>/remittances/create.php" class="btn btn-outline">Submit Remittance</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/payments/advance.php" class="btn btn-outline">Advance Payment</a>
        <a href="<?= APP_URL ?>/billing/collect.php" class="btn btn-outline">Collect Multiple</a>
        <a href="<?= APP_URL ?>/payments/collect.php" class="btn btn-primary">+ Record Payment</a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search customer, bill, reference..." value="<?= e($filters['search']) ?>">
        <label>From</label>
        <input type="date" name="from" value="<?= e($filters['from']) ?>">
        <label>To</label>
        <input type="date" name="to" value="<?= e($filters['to']) ?>">
        <select name="payment_method">
            <option value="">All Methods</option>
            <option value="cash" <?= $filters['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="gcash" <?= $filters['payment_method'] === 'gcash' ? 'selected' : '' ?>>GCash</option>
            <option value="bank_transfer" <?= $filters['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
            <option value="check" <?= $filters['payment_method'] === 'check' ? 'selected' : '' ?>>Check</option>
        </select>
        <select name="per_page">
            <?php foreach (perPageOptions() as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($filters['search'] || $filters['payment_method']): ?>
        <a href="<?= APP_URL ?>/payments/index.php?from=<?= e($filters['from']) ?>&to=<?= e($filters['to']) ?>" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <div class="summary-bar billing-summary-bar">
        <strong><?= number_format((int) $summary['customer_count']) ?></strong> customer(s)
        · <strong><?= number_format((int) $summary['payment_count']) ?></strong> payment(s)
        · Total Collected: <strong><?= formatMoney((float) $summary['total_collected']) ?></strong>
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
    <p class="text-center text-muted billing-empty">No payments found for the selected filters.</p>
    <?php else: ?>
    <?php $shownBatchLinks = []; ?>
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
                    <th>Date</th>
                    <th>Bill #</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Collected By</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <?php foreach ($customerGroups as $group):
                $customer = $group['customer'];
                $payments = $group['payments'];
                $totalCollected = (float) $customer['total_collected'];
            ?>
            <tbody class="billing-customer-group is-collapsed" id="payments-customer-<?= (int) $customer['id'] ?>" data-customer-id="<?= (int) $customer['id'] ?>">
                <tr class="billing-group-header">
                    <td class="billing-col-toggle">
                        <button type="button"
                                class="billing-toggle"
                                aria-expanded="false"
                                aria-controls="payments-customer-<?= (int) $customer['id'] ?>"
                                title="Toggle payments">
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
                                    <span class="billing-customer-bills-count"><?= (int) $customer['payment_count'] ?> payment(s)</span>
                                    <span class="billing-customer-collected">Collected: <strong><?= formatMoney($totalCollected) ?></strong></span>
                                </div>
                            </div>
                            <div class="billing-customer-row-actions">
                                <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $customer['id'] ?>" class="btn btn-sm btn-outline">View</a>
                                <?php if (hasRole('owner', 'collector')): ?>
                                <a href="<?= APP_URL ?>/payments/collect.php?customer_id=<?= (int) $customer['id'] ?>" class="btn btn-sm btn-primary">Record Payment</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php foreach ($payments as $payment): ?>
                <tr class="billing-bill-row">
                    <td class="billing-col-toggle"></td>
                    <td class="billing-bill-customer-label">
                        <span class="text-muted"><?= e($customer['full_name']) ?></span>
                    </td>
                    <td><?= formatDate($payment['payment_date']) ?></td>
                    <td><?= e($payment['bill_number']) ?></td>
                    <td><?= e(paymentTypeLabel($payment['payment_type'] ?? 'bill')) ?></td>
                    <td><strong><?= formatMoney((float) $payment['amount']) ?></strong></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                    <td><?= e($payment['reference_number'] ?: '—') ?></td>
                    <td><?= e($payment['collector_name']) ?></td>
                    <td>
                        <?php
                        $batchId = (int) ($payment['batch_invoice_id'] ?? 0);
                        if ($batchId && empty($shownBatchLinks[$batchId])):
                            $shownBatchLinks[$batchId] = true;
                        ?>
                        <a href="<?= APP_URL ?>/billing/batch_invoice.php?id=<?= $batchId ?>" class="btn btn-sm btn-outline" title="<?= e($payment['batch_invoice_number'] ?? '') ?>">Batch Invoice</a>
                        <?php elseif ($batchId): ?>
                        <span class="text-muted"><?= e($payment['batch_invoice_number'] ?? 'Batch') ?></span>
                        <?php elseif (($payment['payment_type'] ?? 'bill') === 'advance'): ?>
                        <a href="<?= APP_URL ?>/payments/advance_invoice.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline">Advance</a>
                        <?php elseif (($payment['payment_type'] ?? 'bill') === 'advance_applied'): ?>
                        <span class="text-muted">Credit applied</span>
                        <?php elseif (!empty($payment['invoice_number'])): ?>
                        <a href="<?= APP_URL ?>/payments/invoice.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline">View</a>
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
