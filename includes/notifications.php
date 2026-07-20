<?php

function ensureNotificationReadsTable(): void
{
    getDB()->exec(
        'CREATE TABLE IF NOT EXISTS notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(80) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_notification (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );
}

function getReadNotificationKeys(?int $userId = null): array
{
    $userId = $userId ?? (currentUser()['id'] ?? 0);
    if (!$userId) {
        return [];
    }

    ensureNotificationReadsTable();

    $stmt = getDB()->prepare('SELECT notification_key FROM notification_reads WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function markNotificationsRead(array $keys): int
{
    $user = currentUser();
    if (!$user || empty($keys)) {
        return 0;
    }

    ensureNotificationReadsTable();

    $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
    $stmt = getDB()->prepare(
        'INSERT IGNORE INTO notification_reads (user_id, notification_key) VALUES (?, ?)'
    );

    $marked = 0;
    foreach ($keys as $key) {
        if ($key === '' || strlen($key) > 80) {
            continue;
        }
        $stmt->execute([$user['id'], $key]);
        $marked += (int) $stmt->rowCount();
    }

    return $marked;
}

function markAllHeaderNotificationsRead(): int
{
    $data = getHeaderNotifications(true);
    $keys = [];

    foreach ($data['groups'] as $group) {
        foreach ($group['items'] as $item) {
            $keys[] = $item['key'];
        }
    }

    return markNotificationsRead($keys);
}

function filterUnreadNotifications(array $groups, array $readKeys): array
{
    $filtered = [];

    foreach ($groups as $group) {
        $items = [];
        foreach ($group['items'] as $item) {
            if (!in_array($item['key'], $readKeys, true)) {
                $items[] = $item;
            }
        }

        if ($items) {
            $group['items'] = $items;
            $filtered[] = $group;
        }
    }

    return $filtered;
}

function notificationResponsePayload(): array
{
    $data = getHeaderNotifications();

    return [
        'total'  => $data['total'],
        'groups' => $data['groups'],
        'html'   => renderNotificationGroupsHtml($data['groups']),
    ];
}

function renderNotificationGroupsHtml(array $groups): string
{
    if (empty($groups)) {
        return '<div class="notification-empty" id="notification-empty-state">'
            . '<span>✓</span><p>All caught up — no new alerts.</p></div>';
    }

    ob_start();
    foreach ($groups as $group): ?>
    <div class="notification-group" data-group-type="<?= e($group['type'] ?? '') ?>">
        <div class="notification-group-header">
            <strong><?= e($group['label']) ?></strong>
            <a href="<?= e($group['url']) ?>">View all</a>
        </div>
        <ul class="notification-list">
            <?php foreach ($group['items'] as $item): ?>
            <li class="notification-entry" data-notification-key="<?= e($item['key']) ?>">
                <a href="<?= e($item['url']) ?>" class="notification-item severity-<?= e($item['severity']) ?>"
                   data-notification-key="<?= e($item['key']) ?>">
                    <span class="notification-item-icon">
                        <?= match ($group['type'] ?? '') {
                            'announcements' => '📢',
                            'tickets' => '🔧',
                            'remittances' => '🏦',
                            'signups' => '👤',
                            default => '📄',
                        } ?>
                    </span>
                    <span class="notification-item-content">
                        <strong><?= e($item['title']) ?></strong>
                        <span><?= e($item['message']) ?></span>
                        <small><?= e($item['meta']) ?> · <?= notificationTimeAgo($item['time']) ?></small>
                    </span>
                    <span class="notification-unread-dot" aria-hidden="true"></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach;

    return ob_get_clean();
}
