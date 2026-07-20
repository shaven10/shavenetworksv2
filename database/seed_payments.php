<?php
/**
 * Insert 100 sample payment records.
 * Run: php database/seed_payments.php
 */

require_once __DIR__ . '/../config/database.php';

$count = 100;
$methods = ['cash', 'gcash', 'bank_transfer', 'check'];

try {
    $db = getDB();

    $collectorId = (int) $db->query(
        "SELECT id FROM users WHERE role IN ('collector','owner') AND is_active = 1 ORDER BY role = 'collector' DESC LIMIT 1"
    )->fetchColumn();

    if (!$collectorId) {
        throw new RuntimeException('No collector or owner user found.');
    }

    $customers = $db->query(
        'SELECT c.id, c.full_name, p.monthly_fee
         FROM customers c JOIN service_plans p ON c.plan_id = p.id
         ORDER BY RAND()'
    )->fetchAll();

    if (empty($customers)) {
        throw new RuntimeException('No customers found. Run seed_customers.php first.');
    }

    $billStmt = $db->prepare(
        'INSERT INTO bills (customer_id, bill_number, billing_period_start, billing_period_end, due_date, amount, status, paid_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $payStmt = $db->prepare(
        'INSERT INTO payments (bill_id, customer_id, amount, payment_method, reference_number, notes, collected_by, payment_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $updateBillStmt = $db->prepare('UPDATE bills SET paid_amount = ?, status = ? WHERE id = ?');

    $inserted = 0;
    $db->beginTransaction();

    for ($i = 1; $i <= $count; $i++) {
        $customer = $customers[array_rand($customers)];
        $amount = (float) $customer['monthly_fee'];

        $monthsAgo = random_int(0, 5);
        $periodStart = date('Y-m-d', strtotime("-{$monthsAgo} months"));
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +1 month -1 day'));
        $dueDate = date('Y-m-d', strtotime($periodEnd . ' +7 days'));
        $paymentDate = date('Y-m-d', strtotime($periodStart . ' +' . random_int(5, 25) . ' days'));

        if (strtotime($paymentDate) > time()) {
            $paymentDate = date('Y-m-d', strtotime('-' . random_int(1, 14) . ' days'));
        }

        $billNumber = 'BILL-SEED-' . date('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . random_int(100, 999);

        $isPartial = random_int(1, 10) <= 2;
        $payAmount = $isPartial ? round($amount * (random_int(40, 80) / 100), 2) : $amount;
        $billStatus = $isPartial ? 'partial' : 'paid';
        $paidOnBill = $payAmount;

        $billStmt->execute([
            $customer['id'],
            $billNumber,
            $periodStart,
            $periodEnd,
            $dueDate,
            $amount,
            $billStatus,
            $paidOnBill,
        ]);
        $billId = (int) $db->lastInsertId();

        $method = $methods[array_rand($methods)];
        $reference = in_array($method, ['gcash', 'bank_transfer', 'check'], true)
            ? strtoupper(substr($method, 0, 1)) . random_int(100000, 999999)
            : null;

        $payStmt->execute([
            $billId,
            $customer['id'],
            $payAmount,
            $method,
            $reference,
            'Sample payment record #' . $i,
            $collectorId,
            $paymentDate,
        ]);

        $updateBillStmt->execute([$paidOnBill, $billStatus, $billId]);
        $inserted++;
    }

    $db->commit();

    $total = (int) $db->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    $collected = (float) $db->query('SELECT COALESCE(SUM(amount), 0) FROM payments')->fetchColumn();

    echo "Successfully inserted {$inserted} payment records.\n";
    echo "Total payments in database: {$total}\n";
    echo "Total collected: PHP " . number_format($collected, 2) . "\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
