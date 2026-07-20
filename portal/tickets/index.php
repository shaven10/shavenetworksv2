<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'Repair Tickets';
$currentPage = 'repair_tickets';
$customer = getLinkedCustomer();

$status = $_GET['status'] ?? '';
$listPage = getListPage();
$perPage = getListPerPage();

$fromWhere = 'FROM repair_tickets WHERE customer_id = ?';
$params = [$customer['id']];

if ($status) {
    $fromWhere .= ' AND status = ?';
    $params[] = $status;
}

$result = paginatedSelect('SELECT *', $fromWhere, $params, 'ORDER BY created_at DESC', $listPage, $perPage);
$tickets = $result['data'];
$filterParams = paginationQuery(['status' => $status, 'per_page' => $perPage]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Repair Tickets</h1>
        <p>Report and track connection or equipment issues</p>
    </div>
    <a href="<?= APP_URL ?>/portal/tickets/create.php" class="btn btn-primary">+ New Ticket</a>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All Status</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
        <select name="per_page">
            <?php foreach (perPageOptions() as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>

    <?= renderPagination($result, $filterParams) ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="7" class="text-center text-muted">No repair tickets found.</td></tr>
                <?php else: foreach ($tickets as $t): ?>
                <tr>
                    <td><strong><?= e($t['ticket_number']) ?></strong></td>
                    <td><?= e($t['subject']) ?></td>
                    <td><?= e(ticketCategoryLabel($t['category'])) ?></td>
                    <td><?= priorityBadge($t['priority']) ?></td>
                    <td><?= ticketStatusBadge($t['status']) ?></td>
                    <td><?= formatDate($t['created_at']) ?></td>
                    <td><a href="<?= APP_URL ?>/portal/tickets/view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($result, $filterParams) ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
