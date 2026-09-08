<?php
/**
 * Run once: php database/migrate_sms.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sms.php';

ensureSmsNotificationsTable();
echo "sms_notifications table ready.\n";

$defaults = getDefaultApiSettings();
$path = getApiSettingsFilePath();
if (!is_file($path)) {
    file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Created storage/api_settings.json with defaults.\n";
} else {
    echo "storage/api_settings.json already exists.\n";
}
