<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!canAccess('tickets')) {
    http_response_code(403);
    die('Access denied.');
}

$pageTitle = 'Repair Tickets';
$currentPage = 'tickets';

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$listPage = getListPage();
$perPage = getListPerPage();

$fromWhere = 'FROM repair_tickets t JOIN customers c ON t.customer_id = c.id LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1';
$params = [];

if ($status) {
    $fromWhere .= ' AND t.status = ?';
    $params[] = $status;
}
if ($search) {
    $fromWhere .= ' AND (t.ticket_number LIKE ? OR t.subject LIKE ? OR c.full_name LIKE ? OR c.account_number LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$result = paginatedSelect(
    'SELECT t.*, c.full_name, c.account_number, u.full_name as assigned_name',
    $fromWhere,
    $params,
    'ORDER BY FIELD(t.status, "open","in_progress","resolved","closed"), t.created_at DESC',
    $listPage,
    $perPage
);
$tickets = $result['data'];
$filterParams = paginationQuery(['search' => $search, 'status' => $status, 'per_page' => $perPage]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Repair Tickets</h1>
        <p>Manage customer connection and equipment issues</p>
    </div>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search ticket, customer..." value="<?= e($search) ?>">
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
                    <th>Customer</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="8" class="text-center text-muted">No tickets found.</td></tr>
                <?php else: foreach ($tickets as $t): ?>
                <tr>
                    <td><strong><?= e($t['ticket_number']) ?></strong></td>
                    <td>
                        <?= e($t['full_name']) ?><br>
                        <small class="text-muted"><?= e($t['account_number']) ?></small>
                    </td>
                    <td><?= e($t['subject']) ?></td>
                    <td><?= e(ticketCategoryLabel($t['category'])) ?></td>
                    <td><?= priorityBadge($t['priority']) ?></td>
                    <td><?= ticketStatusBadge($t['status']) ?></td>
                    <td><?= e($t['assigned_name'] ?? '—') ?></td>
                    <td><a href="<?= APP_URL ?>/support/tickets/view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline">Manage</a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($result, $filterParams) ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
