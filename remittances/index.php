<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('owner', 'collector');

$pageTitle = 'Payment Remittances';
$currentPage = 'remittances';

$status = $_GET['status'] ?? '';
$collectorId = hasRole('owner') ? (int) ($_GET['collector_id'] ?? 0) : (int) currentUser()['id'];
$listPage = getListPage();
$perPage = getListPerPage();

$fromWhere = 'FROM remittances r JOIN users u ON r.submitted_by = u.id WHERE 1=1';
$params = [];

if (hasRole('collector')) {
    $fromWhere .= ' AND r.submitted_by = ?';
    $params[] = currentUser()['id'];
} elseif ($collectorId) {
    $fromWhere .= ' AND r.submitted_by = ?';
    $params[] = $collectorId;
}

if ($status && in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
    $fromWhere .= ' AND r.status = ?';
    $params[] = $status;
}

$result = paginatedSelect(
    'SELECT r.*, u.full_name AS collector_name',
    $fromWhere,
    $params,
    'ORDER BY r.submitted_at DESC',
    $listPage,
    $perPage
);
$remittances = $result['data'];

$collectors = hasRole('owner')
    ? getDB()->query("SELECT id, full_name FROM users WHERE role = 'collector' AND is_active = 1 ORDER BY full_name")->fetchAll()
    : [];

$pendingCount = hasRole('owner') ? countPendingRemittances() : 0;
$unremittedTotal = hasRole('collector') ? getUnremittedPaymentsTotal((int) currentUser()['id']) : 0;
$unremittedCount = hasRole('collector') ? getUnremittedPaymentCount((int) currentUser()['id']) : 0;

$filterParams = paginationQuery([
    'status'       => $status,
    'collector_id' => $collectorId ?: '',
    'per_page'     => $perPage,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payment Remittances</h1>
        <p><?= hasRole('collector')
            ? 'Submit collected payments to the owner for confirmation'
            : 'Review and confirm payment remittances from collectors' ?></p>
    </div>
    <?php if (hasRole('collector') && $unremittedCount > 0): ?>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/remittances/create.php" class="btn btn-primary">Submit Remittance</a>
    </div>
    <?php endif; ?>
</div>

<?php if (hasRole('owner') && $pendingCount > 0): ?>
<div class="alert alert-warning">
    <?= $pendingCount ?> remittance<?= $pendingCount === 1 ? '' : 's' ?> waiting for your confirmation.
    <a href="<?= APP_URL ?>/remittances/index.php?status=pending">Review pending</a>
</div>
<?php endif; ?>

<?php if (hasRole('collector')): ?>
<div class="stats-grid-simple remittance-stats">
    <div class="stat-card">
        <span class="stat-label">Unremitted Payments</span>
        <strong class="stat-value"><?= number_format($unremittedCount) ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-label">Amount to Remit</span>
        <strong class="stat-value"><?= formatMoney($unremittedTotal) ?></strong>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <?php if (hasRole('owner')): ?>
        <select name="collector_id">
            <option value="">All Collectors</option>
            <?php foreach ($collectors as $collector): ?>
            <option value="<?= $collector['id'] ?>" <?= $collectorId === (int) $collector['id'] ? 'selected' : '' ?>>
                <?= e($collector['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="per_page">
            <?php foreach (perPageOptions() as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($status || ($collectorId && hasRole('owner'))): ?>
        <a href="<?= APP_URL ?>/remittances/index.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <?= renderPagination($result, $filterParams) ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Remittance #</th>
                    <?php if (hasRole('owner')): ?><th>Collector</th><?php endif; ?>
                    <th>Submitted</th>
                    <th>Payments</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($remittances)): ?>
                <tr><td colspan="<?= hasRole('owner') ? 8 : 7 ?>" class="text-center text-muted">No remittances found.</td></tr>
                <?php else: foreach ($remittances as $row): ?>
                <tr>
                    <td><strong><?= e($row['remittance_number']) ?></strong></td>
                    <?php if (hasRole('owner')): ?>
                    <td><?= e($row['collector_name']) ?></td>
                    <?php endif; ?>
                    <td><?= formatDate($row['submitted_at']) ?></td>
                    <td><?= (int) $row['payment_count'] ?></td>
                    <td><strong><?= formatMoney((float) $row['total_amount']) ?></strong></td>
                    <td><?= remittanceStatusBadge($row['status']) ?></td>
                    <td><?= $row['reviewed_at'] ? formatDate($row['reviewed_at']) : '—' ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/remittances/view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline">
                            <?= $row['status'] === 'pending' && hasRole('owner') ? 'Review' : 'View' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($result, $filterParams) ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
