<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if (!canAccess('inquiries')) {
    http_response_code(403);
    die('Access denied.');
}

$pageTitle = 'Customer Inquiries';
$currentPage = 'staff_inquiries';

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$listPage = getListPage();
$perPage = getListPerPage();

$fromWhere = 'FROM inquiries i JOIN customers c ON i.customer_id = c.id WHERE 1=1';
$params = [];

if ($status) {
    $fromWhere .= ' AND i.status = ?';
    $params[] = $status;
}
if ($search) {
    $fromWhere .= ' AND (i.inquiry_number LIKE ? OR i.subject LIKE ? OR c.full_name LIKE ? OR c.account_number LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$result = paginatedSelect(
    'SELECT i.*, c.full_name, c.account_number',
    $fromWhere,
    $params,
    'ORDER BY FIELD(i.status, "open","answered","closed"), i.created_at DESC',
    $listPage,
    $perPage
);
$inquiries = $result['data'];
$filterParams = paginationQuery(['search' => $search, 'status' => $status, 'per_page' => $perPage]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Customer Inquiries</h1>
        <p>Review and respond to subscriber account inquiries</p>
    </div>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search inquiry, customer..." value="<?= e($search) ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="answered" <?= $status === 'answered' ? 'selected' : '' ?>>Answered</option>
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
                    <th>Inquiry #</th>
                    <th>Customer</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inquiries)): ?>
                <tr><td colspan="7" class="text-center text-muted">No inquiries found.</td></tr>
                <?php else: foreach ($inquiries as $inq): ?>
                <tr>
                    <td><strong><?= e($inq['inquiry_number']) ?></strong></td>
                    <td>
                        <?= e($inq['full_name']) ?><br>
                        <small class="text-muted"><?= e($inq['account_number']) ?></small>
                    </td>
                    <td><?= e($inq['subject']) ?></td>
                    <td><?= e(inquiryCategoryLabel($inq['category'])) ?></td>
                    <td><?= inquiryStatusBadge($inq['status']) ?></td>
                    <td><?= formatDate($inq['created_at']) ?></td>
                    <td><a href="<?= APP_URL ?>/support/inquiries/view.php?id=<?= $inq['id'] ?>" class="btn btn-sm btn-outline">Respond</a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($result, $filterParams) ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
