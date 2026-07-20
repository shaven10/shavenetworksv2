<?php
/**
 * Insert 100 sample customer records.
 * Run: php database/seed_customers.php
 * Or via browser: http://localhost/shaven_networks_v2/database/seed_customers.php
 */

require_once __DIR__ . '/../config/database.php';

$count = 100;

$firstNames = ['Pedro', 'Maria', 'Juan', 'Ana', 'Carlos', 'Rosa', 'Jose', 'Liza', 'Miguel', 'Grace', 'Antonio', 'Elena', 'Ramon', 'Carmen', 'Felipe', 'Teresa', 'Ricardo', 'Joy', 'Alberto', 'Nina'];
$lastNames = ['Santos', 'Reyes', 'Cruz', 'Mendoza', 'Garcia', 'Ramos', 'Torres', 'Flores', 'Aquino', 'Bautista', 'Diaz', 'Lopez', 'Morales', 'Castillo', 'Rivera', 'Gonzales', 'Fernandez', 'Domingo', 'Pascual', 'Villanueva'];
$cities = ['Manila', 'Quezon City', 'Makati', 'Pasig', 'Taguig', 'Mandaluyong', 'Caloocan', 'Paranaque', 'Las Pinas', 'Marikina'];
$streets = ['Rizal St', 'Mabini Ave', 'Bonifacio Rd', 'Aguinaldo St', 'Luna St', 'Del Pilar St', 'Quezon Ave', 'EDSA', 'Ortigas Ave', 'Commonwealth Ave'];
$barangays = ['Poblacion', 'Central', 'San Roque', 'Kapitolyo', 'San Lorenzo', 'Plainview', 'Addition Hills', 'Ugong', 'Bambang', 'Talipapa'];
$mediums = ['fiber_olt', 'fiber_olt', 'fiber_mediacon', 'wireless_radio'];
$statuses = ['active', 'active', 'active', 'active', 'active', 'active', 'active', 'suspended', 'disconnected'];

try {
    $db = getDB();

    $planCount = (int) $db->query('SELECT COUNT(*) FROM service_plans WHERE is_active = 1')->fetchColumn();
    if ($planCount === 0) {
        throw new RuntimeException('No active service plans found. Run install.php first.');
    }

    $techId = $db->query("SELECT id FROM users WHERE role = 'technical' LIMIT 1")->fetchColumn() ?: 1;

    $year = date('Y');
    $existing = (int) $db->query("SELECT COUNT(*) FROM customers WHERE account_number LIKE 'SN-{$year}-%'")->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO customers (account_number, full_name, email, phone, connection_medium, address, barangay, city, province, plan_id, installation_date, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $inserted = 0;
    $db->beginTransaction();

    for ($i = 1; $i <= $count; $i++) {
        $seq = $existing + $i;
        $accountNumber = sprintf('SN-%s-%04d', $year, $seq);

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $fullName = $firstName . ' ' . $lastName;
        $slug = strtolower($firstName . '.' . $lastName . $seq);

        $phone = '09' . random_int(10, 99) . random_int(1000000, 9999999);
        $city = $cities[array_rand($cities)];
        $address = random_int(1, 999) . ' ' . $streets[array_rand($streets)];
        $barangay = $barangays[array_rand($barangays)];
        $medium = $mediums[array_rand($mediums)];
        $planId = random_int(1, $planCount);
        $installDay = random_int(1, 28);
        $installMonth = random_int(1, 12);
        $installYear = random_int(2023, (int) date('Y'));
        $installationDate = sprintf('%04d-%02d-%02d', $installYear, $installMonth, $installDay);
        $status = $statuses[array_rand($statuses)];

        $stmt->execute([
            $accountNumber,
            $fullName,
            $slug . '@email.com',
            $phone,
            $medium,
            $address,
            $barangay,
            $city,
            'Metro Manila',
            $planId,
            $installationDate,
            $status,
            $techId,
        ]);

        $inserted++;
    }

    $db->commit();

    $total = (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $message = "Successfully inserted {$inserted} customer records. Total customers: {$total}.";
    echo $message . PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
