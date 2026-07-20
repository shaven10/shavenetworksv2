<?php
/**
 * Run customer portal migration:
 * php database/migrate_customer_portal.php
 */

require_once __DIR__ . '/../config/database.php';

$messages = [];

try {
    $pdo = getDB();

    $pdo->exec("ALTER TABLE users MODIFY role ENUM('owner', 'technical', 'collector', 'customer') NOT NULL");

    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'customer_id'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE users ADD COLUMN customer_id INT NULL AFTER role');
        $pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL');
        $messages[] = 'Added customer_id to users table.';
    }

    $sql = file_get_contents(__DIR__ . '/migrate_customer_portal.sql');
    $parts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($parts as $statement) {
        if ($statement && stripos($statement, 'ALTER TABLE users') === false) {
            $pdo->exec($statement);
        }
    }

    $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'customer1'")->fetchColumn();
    if (!$exists) {
        $customerId = $pdo->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetchColumn();
        if ($customerId) {
            $hash = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, email, role, customer_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(['customer1', $hash, 'Pedro Santos', 'pedro@email.com', 'customer', $customerId]);
            $messages[] = 'Created demo customer user: customer1 / password123';
        }
    }

    echo "Migration completed successfully.\n";
    foreach ($messages as $msg) {
        echo "- {$msg}\n";
    }
} catch (PDOException $e) {
    echo 'Migration error: ' . $e->getMessage() . "\n";
    exit(1);
}
