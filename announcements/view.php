<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (!canAccess('announcements')) {
    http_response_code(403);
    die('Access denied.');
}

$id = (int) ($_GET['id'] ?? 0);
$isOwner = hasRole('owner');
$announcement = getAnnouncementById($id, $isOwner);

if (!$announcement) {
    flash('danger', 'Announcement not found.');
    redirect('/announcements/index.php');
}

if ((int) $announcement['is_published'] === 1) {
    markAnnouncementRead((int) $announcement['id']);
}

$pageTitle = $announcement['title'];
$currentPage = 'announcements';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($announcement['title']) ?></h1>
        <p class="announcement-view-meta">
            <?php if ((int) $announcement['is_pinned']): ?>
            <span class="badge badge-warning">Pinned</span>
            <?php endif; ?>
            <?php if ($isOwner): ?>
            <?= announcementStatusBadge($announcement) ?>
            <?php endif; ?>
            Posted by <?= e($announcement['author_name']) ?>
            · <?= e($announcement['published_at'] ? formatDate($announcement['published_at']) : formatDate($announcement['created_at'])) ?>
        </p>
    </div>
    <div class="header-actions">
        <?php if ($isOwner): ?>
        <a href="<?= APP_URL ?>/announcements/edit.php?id=<?= (int) $announcement['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/announcements/index.php" class="btn btn-outline btn-sm">← All Announcements</a>
    </div>
</div>

<div class="card announcement-view">
    <div class="announcement-view-body">
        <?= formatAnnouncementBody($announcement['body']) ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
