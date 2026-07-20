<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDB();
    $col = $pdo->query("SHOW COLUMNS FROM payments LIKE 'invoice_number'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE payments ADD COLUMN invoice_number VARCHAR(30) NULL UNIQUE AFTER id');
        echo "Added invoice_number column to payments.\n";
    }
    $pdo->exec(
        "UPDATE payments SET invoice_number = CONCAT('INV-', DATE_FORMAT(payment_date,'%Y%m%d'), '-', LPAD(id, 5, '0'))
         WHERE invoice_number IS NULL OR invoice_number = ''"
    );
    echo "Invoice migration completed.\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
