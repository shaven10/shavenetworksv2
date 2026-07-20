<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'Inquiries';
$currentPage = 'inquiries';
$customer = getLinkedCustomer();

$status = $_GET['status'] ?? '';
$listPage = getListPage();
$perPage = getListPerPage();

$fromWhere = 'FROM inquiries WHERE customer_id = ?';
$params = [$customer['id']];

if ($status) {
    $fromWhere .= ' AND status = ?';
    $params[] = $status;
}

$result = paginatedSelect('SELECT *', $fromWhere, $params, 'ORDER BY created_at DESC', $listPage, $perPage);
$inquiries = $result['data'];
$filterParams = paginationQuery(['status' => $status, 'per_page' => $perPage]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Account Inquiries</h1>
        <p>Ask questions about your account, billing, or service</p>
    </div>
    <a href="<?= APP_URL ?>/portal/inquiries/create.php" class="btn btn-primary">+ New Inquiry</a>
</div>

<div class="card">
    <form method="GET" class="filter-bar">
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
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inquiries)): ?>
                <tr><td colspan="6" class="text-center text-muted">No inquiries found.</td></tr>
                <?php else: foreach ($inquiries as $inq): ?>
                <tr>
                    <td><strong><?= e($inq['inquiry_number']) ?></strong></td>
                    <td><?= e($inq['subject']) ?></td>
                    <td><?= e(inquiryCategoryLabel($inq['category'])) ?></td>
                    <td><?= inquiryStatusBadge($inq['status']) ?></td>
                    <td><?= formatDate($inq['created_at']) ?></td>
                    <td><a href="<?= APP_URL ?>/portal/inquiries/view.php?id=<?= $inq['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= renderPagination($result, $filterParams) ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
