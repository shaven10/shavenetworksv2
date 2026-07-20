<?php
/**
 * Run once: php database/migrate_customer_address.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$alterations = [
    "ALTER TABLE customers ADD COLUMN connection_medium ENUM('fiber_olt','fiber_mediacon','wireless_radio') NOT NULL DEFAULT 'fiber_olt' AFTER phone",
    "ALTER TABLE customers ADD COLUMN barangay VARCHAR(100) NULL AFTER address",
    "ALTER TABLE customers ADD COLUMN city VARCHAR(100) NULL AFTER barangay",
    "ALTER TABLE customers ADD COLUMN province VARCHAR(100) NULL AFTER city",
];

foreach ($alterations as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: {$sql}\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "Skip (exists): {$sql}\n";
        } else {
            throw $e;
        }
    }
}

$pdo->exec(
    "UPDATE customers SET city = TRIM(SUBSTRING_INDEX(address, ',', -1)),
     address = TRIM(SUBSTRING_INDEX(address, ',', 1)),
     province = 'Metro Manila'
     WHERE (city IS NULL OR city = '') AND address LIKE '%,%'"
);

echo "Customer address migration complete.\n";
