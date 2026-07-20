<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!canAccess('announcements')) {
    http_response_code(403);
    die('Access denied.');
}

$pageTitle = 'Announcements';
$currentPage = 'announcements';
$isOwner = hasRole('owner');

$listPage = getListPage();
$perPage = getListPerPage();

if ($isOwner) {
    $result = getAnnouncementsForManage($listPage, $perPage);
    $announcements = $result['data'];
    $filterParams = paginationQuery(['per_page' => $perPage]);
} else {
    $offset = ($listPage - 1) * $perPage;
    $announcements = getPublishedAnnouncements($perPage, $offset);
    $total = countPublishedAnnouncements();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $result = [
        'total'       => $total,
        'page'        => min($listPage, $totalPages),
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'from'        => $total ? $offset + 1 : 0,
        'to'          => min($offset + $perPage, $total),
    ];
    $filterParams = paginationQuery(['per_page' => $perPage]);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Announcements</h1>
        <p><?= $isOwner ? 'Post updates for all system users and customers' : 'Recent news and updates from SHAVEN Networks' ?></p>
    </div>
    <?php if ($isOwner): ?>
    <a href="<?= APP_URL ?>/announcements/create.php" class="btn btn-primary">+ Post Announcement</a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($announcements)): ?>
    <p class="text-center text-muted" style="padding: 32px 0;">No announcements yet.</p>
    <?php else: ?>
    <?= renderPagination($result, $filterParams) ?>

    <div class="announcement-feed">
        <?php foreach ($announcements as $item): ?>
        <article class="announcement-card<?= (int) $item['is_pinned'] ? ' is-pinned' : '' ?>">
            <div class="announcement-card-header">
                <div>
                    <?php if ((int) $item['is_pinned']): ?>
                    <span class="badge badge-warning">Pinned</span>
                    <?php endif; ?>
                    <?php if ($isOwner): ?>
                    <?= announcementStatusBadge($item) ?>
                    <?php endif; ?>
                    <h2>
                        <a href="<?= APP_URL ?>/announcements/view.php?id=<?= (int) $item['id'] ?>">
                            <?= e($item['title']) ?>
                        </a>
                    </h2>
                </div>
                <?php if ($isOwner): ?>
                <div class="announcement-card-actions">
                    <a href="<?= APP_URL ?>/announcements/edit.php?id=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="announcement-card-body">
                <?= formatAnnouncementBody(strlen($item['body']) > 280 ? substr($item['body'], 0, 280) . '…' : $item['body']) ?>
            </div>
            <div class="announcement-card-meta">
                Posted by <?= e($item['author_name']) ?>
                · <?= e($item['published_at'] ? formatDate($item['published_at']) : formatDate($item['created_at'])) ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?= renderPagination($result, $filterParams) ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
