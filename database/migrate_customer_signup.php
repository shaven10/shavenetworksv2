<?php
/**
 * Run once: php database/migrate_customer_signup.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'approval_status'")->fetch();
if (!$col) {
    $pdo->exec(
        "ALTER TABLE users
         ADD COLUMN approval_status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved'
         AFTER is_active"
    );
    echo "OK: Added approval_status to users.\n";
} else {
    echo "Skip: approval_status already exists.\n";
}

$pdo->exec("UPDATE users SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = ''");
echo "Customer signup migration complete.\n";
