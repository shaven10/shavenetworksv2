<?php
/**
 * Run once: php database/migrate_user_avatar.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/user_avatar.php';

$pdo = getDB();

try {
    $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email');
    echo "OK: Added avatar column to users table.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "Skip: avatar column already exists.\n";
    } else {
        throw $e;
    }
}

ensureUserAvatarDir();
echo "Avatar storage directory ready.\n";
