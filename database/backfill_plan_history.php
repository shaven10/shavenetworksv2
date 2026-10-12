<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/customers.php';

ensureCustomerPlanHistoryTable();
$db = getDB();
$ids = $db->query('SELECT id FROM customers')->fetchAll(PDO::FETCH_COLUMN);
$n = 0;
foreach ($ids as $id) {
    ensureCustomerHasPlanHistory((int) $id);
    $n++;
}
echo "Ensured plan history for {$n} customers.\n";
echo 'History rows: ' . $db->query('SELECT COUNT(*) FROM customer_plan_history')->fetchColumn() . "\n";
