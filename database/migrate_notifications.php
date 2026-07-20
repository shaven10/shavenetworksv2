<?php
/**
 * Run once: php database/migrate_notifications.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

ensureNotificationReadsTable();
echo "notification_reads table ready.\n";
