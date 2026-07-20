<?php
/**
 * Import Maralag / Dumingag customer list from spreadsheet.
 * Run: php database/seed_maralag_customers.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/customers.php';

function ensureShavenPlans(PDO $db): array
{
    $plans = [
        'Plan 250'  => [5,  250.00,  'Residential 5Mbps plan'],
        'Plan 500'  => [10, 500.00,  'Residential 10Mbps plan'],
        'Plan 1000' => [20, 1000.00, 'Residential 20Mbps plan'],
        'Others'    => [8,  350.00,  'Custom / other subscription'],
    ];

    $map = [];
    foreach ($plans as $name => [$speed, $fee, $desc]) {
        $stmt = $db->prepare('SELECT id FROM service_plans WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $insert = $db->prepare(
                'INSERT INTO service_plans (name, speed_mbps, monthly_fee, description) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$name, $speed, $fee, $desc]);
            $id = $db->lastInsertId();
        }

        $map[$name] = (int) $id;
    }

    return $map;
}

function mapSpreadsheetMedium(string $value): string
{
    $value = strtolower(trim($value));

    return match ($value) {
        'olt'            => 'fiber_olt',
        'mediacon'       => 'fiber_mediacon',
        'wireless radio' => 'wireless_radio',
        default          => 'fiber_olt',
    };
}

function parseSpreadsheetAddress(string $raw): array
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    $default = [
        'address'  => 'Maralag',
        'barangay' => 'Maralag',
        'city'     => 'Dumingag',
        'province' => 'Zamboanga Del Sur',
    ];

    if ($raw === '') {
        return $default;
    }

    if (stripos($raw, 'Pugwan') !== false && stripos($raw, 'Mahayag') !== false) {
        return [
            'address'  => 'Pugwan',
            'barangay' => 'Pugwan',
            'city'     => 'Mahayag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (stripos($raw, 'Manguiles') !== false) {
        return [
            'address'  => 'Manguiles',
            'barangay' => 'Manguiles',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (stripos($raw, 'Lawis') !== false) {
        return [
            'address'  => 'Lawis',
            'barangay' => 'Maralag',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (preg_match('/^(P\d+)\b/i', $raw, $match)) {
        return [
            'address'  => strtoupper($match[1]),
            'barangay' => 'Maralag',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    return $default;
}

function nextAccountNumbers(PDO $db, int $count): array
{
    $year = date('Y');
    $start = (int) $db->query("SELECT COUNT(*) FROM customers WHERE account_number LIKE 'SN-{$year}-%'")->fetchColumn();

    $numbers = [];
    for ($i = 1; $i <= $count; $i++) {
        $numbers[] = sprintf('SN-%s-%04d', $year, $start + $i);
    }

    return $numbers;
}

$records = [
    ['Jaime Joaquin', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Wilbert Rupinta', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 250'],
    ['Narding Uy', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Badilla', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Alfren Sumolung', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Jillboy Gallego', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Others'],
    ['Jelma Abellana', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Sarona', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Kuya Dodong', 'Manguiles, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Maam Aloyan', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Dyna Ostan', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Boboy Insek', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 500'],
    ['Janice Uy', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 500'],
    ['Dongie Agnes', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Peta Baran', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Felipe Rabanal', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Diding Coop', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Regie Gose', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Alrey Arsenal', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Slarez', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Velez', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Loloy Jamero', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Erma Jalem', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 500'],
    ['Ducks Gose', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Jojo Jamero', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Melita Agnes', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Oging Abellana', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Ronnie Demegillo', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Gagang Joaquin', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Gomez Ubos', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Jerry Gallego', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Monico Adaza', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Dondong Papa', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Pedyu Ebisa', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Joy Gose', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Andrade', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Malacad', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Restauro', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Tonyo Sabayle', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Jocel Sareno', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Castro', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Amar Alcorin Dool Drier', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Aranas', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Janen Tala', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Geraldizo', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Sarry Lemetares', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Alcorin Nilo', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Caberte Dodong Drier', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Riza Ledesma', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Jhunax', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Basin', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Bon2x Galliguit', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Tarik Ebisa', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 250'],
    ['Neneng Capalihan', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Others'],
    ['Manalo', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Gallego Pulis', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Tiling Alcorin', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Maam Palma', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Manang Vek2x', 'Maralag, Dumingag, Zamboanga Del Sur', 'Wireless Radio', 'Plan 1000'],
    ['Plondaya', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 500'],
    ['Jeza Montimayor', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Pare Buloy Adaza', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 250'],
    ['SB Pakit', 'Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Others'],
    ['Roselyn Manalo', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Agudera', 'P3, Maralag, Dumingag Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Durnala', 'P7, Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Gloria', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 500'],
    ['Duerme Joel', 'P3, Maralag, Dumingag, Zamboanga Del Sur', 'Mediacon', 'Plan 1000'],
    ['Kulano Arsenal', '', 'OLT', 'Plan 500'],
    ['Albert Arsenal', 'Maralag, Dumingag, Zambo. Sur', 'OLT', 'Plan 500'],
    ['Bontuyan Rhoem', 'Maralag, Dumingag, Zambo.Sur', 'OLT', 'Plan 500'],
    ['May2x Moring', 'Lawis, Maralag, Dumingag, Zambo. Sur', 'OLT', 'Plan 500'],
    ['Gege Basin', 'Lawis, Maralag, Dumingag, Zambo. Sur', 'OLT', 'Plan 500'],
    ['Maralag ES', 'Maralag, Dumingag, ZDS', 'Wireless Radio', 'Plan 1000'],
    ['Maralag Baranggay', 'Maralag, Dumingag, Zamboanga Del Sur', 'OLT', 'Plan 1000'],
    ['Benjie Insek', 'Maralag Dumingag ZDS', 'OLT', 'Plan 500'],
    ['Romero Lucille', 'Maralag, DZDS', 'OLT', 'Plan 1000'],
    ['Ricky Ayunan', '', 'OLT', 'Plan 1000'],
    ['Eda Manalo', 'Maralag, Dumingag, Zambo. Sur', 'OLT', 'Plan 1000'],
    ['Muno Pikas Mar.', 'Maralag, Dumingag, Zambo. Sur', 'OLT', 'Plan 500'],
    ['Manang Minang', 'Pugwan, Mahayag, ZDS', 'OLT', 'Plan 500'],
    ['Jeje Arsenal', 'Maralag, Duminga, Zambo Sur', 'Wireless Radio', 'Plan 500'],
    ['Langga Ayunan', 'Maralag, DZDS', 'OLT', 'Plan 500'],
];

try {
    $db = getDB();
    $planMap = ensureShavenPlans($db);
    $techId = (int) ($db->query("SELECT id FROM users WHERE role = 'technical' LIMIT 1")->fetchColumn() ?: 1);
    $accounts = nextAccountNumbers($db, count($records));

    $stmt = $db->prepare(
        'INSERT INTO customers (account_number, full_name, email, phone, connection_medium, address, barangay, city, province, plan_id, installation_date, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $db->beginTransaction();
    $inserted = 0;
    $skipped = 0;

    foreach ($records as $index => [$name, $addressRaw, $medium, $planName]) {
        $check = $db->prepare(
            'SELECT id FROM customers WHERE full_name = ? AND city = ? LIMIT 1'
        );
        $addr = parseSpreadsheetAddress($addressRaw);
        $check->execute([$name, $addr['city']]);
        if ($check->fetchColumn()) {
            $skipped++;
            continue;
        }

        if (!isset($planMap[$planName])) {
            throw new RuntimeException("Unknown plan: {$planName}");
        }

        $phone = '09' . str_pad((string) (700000000 + $index + 1), 9, '0', STR_PAD_LEFT);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name));
        $email = trim($slug, '.') . '@shaven.local';

        $stmt->execute([
            $accounts[$index],
            $name,
            $email,
            $phone,
            mapSpreadsheetMedium($medium),
            $addr['address'],
            $addr['barangay'],
            $addr['city'],
            $addr['province'],
            $planMap[$planName],
            '2025-01-15',
            'active',
            $techId,
        ]);
        $inserted++;
    }

    $db->commit();

    $total = (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    echo "Imported {$inserted} customers (skipped {$skipped} duplicates). Total customers: {$total}.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
