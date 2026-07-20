<?php
/**
 * Run once: php database/migrate_billing_year_range.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$columns = [
    'billing_generate_from_year' => 'ADD COLUMN billing_generate_from_year SMALLINT UNSIGNED NULL AFTER advance_balance',
    'billing_generate_to_year'   => 'ADD COLUMN billing_generate_to_year SMALLINT UNSIGNED NULL AFTER billing_generate_from_year',
];

foreach ($columns as $name => $sql) {
    $col = $pdo->query("SHOW COLUMNS FROM customers LIKE '{$name}'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE customers {$sql}");
        echo "OK: Added {$name} to customers.\n";
    } else {
        echo "Skip: {$name} already exists.\n";
    }
}

echo "Billing year range migration complete.\n";
