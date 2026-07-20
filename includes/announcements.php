<?php

function ensureAnnouncementsTable(): void
{
    getDB()->exec(
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT NOT NULL,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )"
    );
}

function getAnnouncementById(int $id, bool $ownerView = false): ?array
{
    ensureAnnouncementsTable();

    $sql = 'SELECT a.*, u.full_name AS author_name
            FROM announcements a
            JOIN users u ON a.created_by = u.id
            WHERE a.id = ?';
    if (!$ownerView) {
        $sql .= ' AND a.is_published = 1';
    }

    $stmt = getDB()->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function getPublishedAnnouncements(int $limit = 20, int $offset = 0): array
{
    ensureAnnouncementsTable();

    $stmt = getDB()->prepare(
        'SELECT a.*, u.full_name AS author_name
         FROM announcements a
         JOIN users u ON a.created_by = u.id
         WHERE a.is_published = 1
         ORDER BY a.is_pinned DESC, a.published_at DESC, a.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function countPublishedAnnouncements(): int
{
    ensureAnnouncementsTable();

    return (int) getDB()->query('SELECT COUNT(*) FROM announcements WHERE is_published = 1')->fetchColumn();
}

function getAnnouncementsForManage(int $listPage, int $perPage): array
{
    ensureAnnouncementsTable();

    return paginatedSelect(
        'SELECT a.*, u.full_name AS author_name',
        'FROM announcements a JOIN users u ON a.created_by = u.id',
        [],
        'ORDER BY a.is_pinned DESC, a.created_at DESC',
        $listPage,
        $perPage
    );
}

function createAnnouncement(string $title, string $body, bool $publish, bool $pin, int $ownerId): int
{
    ensureAnnouncementsTable();

    $title = trim($title);
    $body = trim($body);

    if ($title === '') {
        throw new RuntimeException('Title is required.');
    }
    if ($body === '') {
        throw new RuntimeException('Announcement content is required.');
    }

    $stmt = getDB()->prepare(
        'INSERT INTO announcements (title, body, is_published, is_pinned, created_by, published_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $body,
        $publish ? 1 : 0,
        $pin ? 1 : 0,
        $ownerId,
        $publish ? date('Y-m-d H:i:s') : null,
    ]);

    return (int) getDB()->lastInsertId();
}

function updateAnnouncement(int $id, string $title, string $body, bool $publish, bool $pin): void
{
    ensureAnnouncementsTable();

    $existing = getAnnouncementById($id, true);
    if (!$existing) {
        throw new RuntimeException('Announcement not found.');
    }

    $title = trim($title);
    $body = trim($body);

    if ($title === '') {
        throw new RuntimeException('Title is required.');
    }
    if ($body === '') {
        throw new RuntimeException('Announcement content is required.');
    }

    $wasPublished = (int) $existing['is_published'] === 1;
    $publishedAt = $existing['published_at'];

    if ($publish && !$wasPublished) {
        $publishedAt = date('Y-m-d H:i:s');
    } elseif (!$publish) {
        $publishedAt = null;
    }

    $stmt = getDB()->prepare(
        'UPDATE announcements
         SET title = ?, body = ?, is_published = ?, is_pinned = ?, published_at = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $title,
        $body,
        $publish ? 1 : 0,
        $pin ? 1 : 0,
        $publishedAt,
        $id,
    ]);
}

function deleteAnnouncement(int $id): void
{
    ensureAnnouncementsTable();

    $stmt = getDB()->prepare('DELETE FROM announcements WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Announcement not found.');
    }
}

function announcementStatusBadge(array $announcement): string
{
    if ((int) $announcement['is_published'] === 1) {
        return '<span class="badge badge-success">Published</span>';
    }

    return '<span class="badge badge-secondary">Draft</span>';
}

function formatAnnouncementBody(string $body): string
{
    return nl2br(e($body));
}

function getUnreadAnnouncementNotifications(int $userId, int $limit = 6): array
{
    ensureAnnouncementsTable();
    ensureNotificationReadsTable();

    $stmt = getDB()->prepare(
        'SELECT a.id, a.title, a.body, a.is_pinned, a.published_at, a.created_at, u.full_name AS author_name
         FROM announcements a
         JOIN users u ON a.created_by = u.id
         WHERE a.is_published = 1
           AND a.created_by != ?
           AND NOT EXISTS (
               SELECT 1 FROM notification_reads nr
               WHERE nr.user_id = ? AND nr.notification_key = CONCAT("announcement:", a.id)
           )
         ORDER BY a.is_pinned DESC, COALESCE(a.published_at, a.created_at) DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function markAnnouncementRead(int $announcementId): void
{
    markNotificationsRead(['announcement:' . $announcementId]);
}

function announcementNotificationPreview(string $body, int $max = 80): string
{
    $text = preg_replace('/\s+/', ' ', trim($body));
    if (strlen($text) <= $max) {
        return $text;
    }

    return substr($text, 0, $max - 1) . '…';
}

function renderAnnouncementForm(array $data, string $mode, ?int $id = null): void
{
    $isEdit = $mode === 'edit';
    ?>
<div class="page-header">
    <h1><?= $isEdit ? 'Edit Announcement' : 'Post Announcement' ?></h1>
    <a href="<?= APP_URL ?>/announcements/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <?php if (!empty($data['errors'])): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($data['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= $isEdit ? APP_URL . '/announcements/edit.php?id=' . (int) $id : APP_URL . '/announcements/create.php' ?>">
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required maxlength="200"
                       value="<?= e($data['title'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
                <label for="body">Message *</label>
                <textarea id="body" name="body" rows="8" required placeholder="Write your announcement or update..."><?= e($data['body'] ?? '') ?></textarea>
            </div>
            <div class="form-group full-width announcement-options">
                <label>Options</label>
                <div class="checkbox-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_published" value="1" <?= !empty($data['is_published']) ? 'checked' : '' ?>>
                        Publish immediately
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_pinned" value="1" <?= !empty($data['is_pinned']) ? 'checked' : '' ?>>
                        Pin to top
                    </label>
                </div>
                <small class="form-hint">Unpublished announcements are saved as drafts visible only to the owner.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Post Announcement' ?></button>
            <?php if ($isEdit && $id): ?>
            <a href="<?= APP_URL ?>/announcements/view.php?id=<?= (int) $id ?>" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>
    <?php
}

function renderRecentAnnouncements(int $limit = 5): void
{
    $items = getPublishedAnnouncements($limit);

    if (empty($items)) {
        return;
    }
    ?>
<div class="card announcement-widget">
    <div class="card-header">
        <h2>Recent Announcements</h2>
        <a href="<?= APP_URL ?>/announcements/index.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <ul class="announcement-list">
        <?php foreach ($items as $item): ?>
        <li class="announcement-list-item">
            <a href="<?= APP_URL ?>/announcements/view.php?id=<?= (int) $item['id'] ?>" class="announcement-list-link">
                <?php if ((int) $item['is_pinned']): ?>
                <span class="badge badge-warning announcement-pin-badge">Pinned</span>
                <?php endif; ?>
                <strong><?= e($item['title']) ?></strong>
                <span class="announcement-list-meta">
                    <?= e($item['published_at'] ? formatDate($item['published_at']) : formatDate($item['created_at'])) ?>
                </span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
    <?php
}
