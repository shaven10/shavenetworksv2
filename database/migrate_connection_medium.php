<?php
/**
 * Run once: php database/migrate_connection_medium.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

try {
    $pdo->query('SELECT connection_medium FROM customers LIMIT 1');
} catch (PDOException) {
    echo "connection_medium column not found. Run migrate_customer_address.php first.\n";
    exit(1);
}

$pdo->exec("ALTER TABLE customers MODIFY connection_medium VARCHAR(30) NOT NULL DEFAULT 'fiber_olt'");

$pdo->exec(
    "UPDATE customers SET connection_medium = CASE
        WHEN connection_medium = 'wireless' THEN 'wireless_radio'
        WHEN connection_medium = 'fiber' THEN 'fiber_olt'
        WHEN connection_medium IN ('fiber_olt','fiber_mediacon','wireless_radio') THEN connection_medium
        ELSE 'fiber_mediacon'
     END"
);

$pdo->exec(
    "ALTER TABLE customers MODIFY connection_medium
     ENUM('fiber_olt','fiber_mediacon','wireless_radio') NOT NULL DEFAULT 'fiber_olt'"
);

echo "Connection medium values updated.\n";
