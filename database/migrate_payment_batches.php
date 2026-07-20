<?php
/**
 * Run once: php database/migrate_payment_batches.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_invoice_number VARCHAR(30) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_count INT NOT NULL,
            payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check') DEFAULT 'cash',
            reference_number VARCHAR(50) NULL,
            notes TEXT NULL,
            collected_by INT NOT NULL,
            payment_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (collected_by) REFERENCES users(id)
        )"
    );
    echo "OK: payment_batches table ready.\n";
} catch (PDOException $e) {
    throw $e;
}

try {
    $col = $pdo->query("SHOW COLUMNS FROM payments LIKE 'batch_id'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE payments ADD COLUMN batch_id INT NULL AFTER invoice_number');
        $pdo->exec('ALTER TABLE payments ADD CONSTRAINT fk_payments_batch FOREIGN KEY (batch_id) REFERENCES payment_batches(id) ON DELETE SET NULL');
        echo "OK: Added batch_id to payments.\n";
    } else {
        echo "Skip: batch_id column already exists.\n";
    }
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "Skip: batch_id already configured.\n";
    } else {
        throw $e;
    }
}

echo "Payment batch migration complete.\n";
