<?php
/**
 * Run once: php database/migrate_customer_plan_history.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS customer_plan_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        plan_id INT NOT NULL,
        plan_name VARCHAR(100) NOT NULL,
        speed_mbps INT NOT NULL,
        monthly_fee DECIMAL(10,2) NOT NULL,
        started_at DATETIME NOT NULL,
        ended_at DATETIME NULL,
        changed_by INT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        FOREIGN KEY (plan_id) REFERENCES service_plans(id),
        FOREIGN KEY (changed_by) REFERENCES users(id),
        INDEX idx_customer_plan_started (customer_id, started_at)
    )"
);
echo "OK: customer_plan_history table ready.\n";

$count = (int) $pdo->query('SELECT COUNT(*) FROM customer_plan_history')->fetchColumn();
if ($count === 0) {
    $inserted = $pdo->exec(
        "INSERT INTO customer_plan_history
            (customer_id, plan_id, plan_name, speed_mbps, monthly_fee, started_at, ended_at, changed_by, notes)
         SELECT
            c.id,
            c.plan_id,
            p.name,
            p.speed_mbps,
            p.monthly_fee,
            COALESCE(CONCAT(c.installation_date, ' 00:00:00'), c.created_at),
            NULL,
            c.created_by,
            'Backfilled from current plan'
         FROM customers c
         JOIN service_plans p ON c.plan_id = p.id"
    );
    echo "OK: Backfilled {$inserted} current plan history row(s).\n";
} else {
    echo "Skip: plan history already has data.\n";
}

echo "Customer plan history migration complete.\n";
