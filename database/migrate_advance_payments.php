<?php
/**
 * Run once: php database/migrate_advance_payments.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

try {
    $col = $pdo->query("SHOW COLUMNS FROM customers LIKE 'advance_balance'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE customers ADD COLUMN advance_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER status');
        echo "OK: Added advance_balance to customers.\n";
    } else {
        echo "Skip: advance_balance already exists.\n";
    }
} catch (PDOException $e) {
    throw $e;
}

try {
    $col = $pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_type'")->fetch();
    if (!$col) {
        $pdo->exec(
            "ALTER TABLE payments
             ADD COLUMN payment_type ENUM('bill', 'advance', 'advance_applied') NOT NULL DEFAULT 'bill' AFTER batch_id"
        );
        echo "OK: Added payment_type to payments.\n";
    } else {
        echo "Skip: payment_type already exists.\n";
    }
} catch (PDOException $e) {
    throw $e;
}

try {
    $pdo->exec('ALTER TABLE payments MODIFY bill_id INT NULL');
    echo "OK: payments.bill_id is nullable.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate')) {
        echo "Skip: bill_id already nullable.\n";
    } else {
        echo "Note: bill_id modify — " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec(
        "ALTER TABLE payments
         MODIFY payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check', 'advance_credit') DEFAULT 'cash'"
    );
    echo "OK: Extended payment_method enum.\n";
} catch (PDOException $e) {
    echo "Note: payment_method enum — " . $e->getMessage() . "\n";
}

echo "Advance payment migration complete.\n";
