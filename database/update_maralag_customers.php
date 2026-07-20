<?php
/**
 * Update Maralag customer records from spreadsheet (dates + plans).
 * Run: php database/update_maralag_customers.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/customers.php';

require_once __DIR__ . '/maralag_customer_data.php';

function ensureMaralagPlans(PDO $db): array
{
    $plans = [
        'Basic 5Mbps'      => [5,  250.00,  'Basic residential 5Mbps'],
        'Standard 10Mbps'  => [10, 500.00,  'Standard residential 10Mbps'],
        'Premium 25Mbps'   => [25, 1000.00, 'Premium residential 25Mbps'],
        'Others'           => [8,  350.00,  'Custom / other subscription'],
    ];

    $map = [];
    foreach ($plans as $name => [$speed, $fee, $desc]) {
        $stmt = $db->prepare('SELECT id FROM service_plans WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $db->prepare(
                'INSERT INTO service_plans (name, speed_mbps, monthly_fee, description) VALUES (?, ?, ?, ?)'
            )->execute([$name, $speed, $fee, $desc]);
            $id = $db->lastInsertId();
        }

        $map[$name] = (int) $id;
    }

    return $map;
}

function findCustomerId(PDO $db, string $name): ?int
{
    static $aliases = [
        'Siarez'           => 'Slarez',
        'Titing Alcorin'   => 'Tiling Alcorin',
        'Pare Bulloy Adaza'=> 'Pare Buloy Adaza',
    ];

    $names = array_unique([$name, $aliases[$name] ?? null]);
    $stmt = $db->prepare('SELECT id, full_name FROM customers WHERE full_name = ? LIMIT 1');

    foreach ($names as $candidate) {
        if (!$candidate) {
            continue;
        }
        $stmt->execute([$candidate]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
    }

    return null;
}

try {
    $db = getDB();
    $planMap = ensureMaralagPlans($db);

    $update = $db->prepare(
        'UPDATE customers SET full_name = ?, connection_medium = ?, address = ?, barangay = ?, city = ?, province = ?,
         plan_id = ?, installation_date = ? WHERE id = ?'
    );

    $updated = 0;
    $missing = [];

    $db->beginTransaction();

    foreach ($maralagCustomerRecords as [$name, $addressRaw, $installDate, $medium, $planName]) {
        $customerId = findCustomerId($db, $name);
        if (!$customerId) {
            $missing[] = $name;
            continue;
        }

        if (!isset($planMap[$planName])) {
            throw new RuntimeException("Unknown plan: {$planName}");
        }

        $addr = parseSpreadsheetAddress($addressRaw);

        $update->execute([
            $name,
            mapSpreadsheetMedium($medium),
            $addr['address'],
            $addr['barangay'],
            $addr['city'],
            $addr['province'],
            $planMap[$planName],
            $installDate,
            $customerId,
        ]);
        $updated++;
    }

    $db->commit();

    echo "Updated {$updated} customer records.\n";
    if ($missing) {
        echo 'Not found (' . count($missing) . '): ' . implode(', ', $missing) . "\n";
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Update failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
