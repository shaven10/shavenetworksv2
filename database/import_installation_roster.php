<?php
/**
 * Replace customers with the official installation roster spreadsheet.
 * Usage: D:\SC30\php\php.exe database/import_installation_roster.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Spreadsheet rows: [MM-DD-YY, name, medium label, plan label]
 */
$roster = [
    ['11-05-20', 'Kuya Dodong', 'Wireless Radio', 'Premium 1'],
    ['11-24-20', 'Tarik Ebisa', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['12-20-20', 'Janen Tala', 'Wireless Radio', 'Premium 1'],
    ['12-27-20', 'Jaime Joaquin', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['01-01-21', 'Wilbert Rupinta', 'Fiber Optic (Mediacon)', 'Basic SMB'],
    ['01-17-21', 'Tonyo Sabayle', 'Fiber Optic (OLT)', 'Standard 1'],
    ['01-23-21', 'Kring2x Duerme', 'Fiber Optic (Mediacon)', 'Premium 2'],
    ['03-25-21', 'Neneng Capalihan', 'Wireless Radio', 'Standard 1'],
    ['04-25-21', 'Manalo', 'Wireless Radio', 'Standard 1'],
    ['05-22-21', 'Caberte Dodong Drier', 'Fiber Optic (OLT)', 'Standard 1'],
    ['06-20-21', 'Geraldizo', 'Wireless Radio', 'Premium 2'],
    ['06-23-21', 'Basin', 'Wireless Radio', 'Standard 1'],
    ['07-01-21', 'Buloy Adaza', 'Fiber Optic (Mediacon)', 'Basic SMB'],
    ['07-10-21', 'Diding Coop', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['07-28-21', 'Manang Vek2x', 'Wireless Radio', 'Standard 1'],
    ['08-07-21', 'Boboy Insek', 'Wireless Radio', 'Standard 1'],
    ['08-07-21', 'Janice Uy', 'Wireless Radio', 'Standard 1'],
    ['08-07-21', 'Dyna Ostan', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['08-10-21', 'Regie Gose', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['08-17-21', 'Ducks Gose', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['08-22-21', 'Riza Ledesma', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-25-21', 'Flordaya', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-12-21', 'Jojo Jamero', 'Fiber Optic (OLT)', 'Premium 2'],
    ['09-13-21', 'Ronnie Damagilo', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['09-15-21', 'Joy Gose', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-23-21', 'Bor2x Galiguit', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-26-21', 'Gallego Pulis', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-26-21', 'Titing Alcorin', 'Fiber Optic (OLT)', 'Premium 2'],
    ['09-26-21', 'Maam Palma', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-06-21', 'Maam Aloyan', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-07-21', 'Dongie Agnes', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['10-10-21', 'Suarez', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-10-21', 'Velez', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-10-21', 'Loloy Jamero', 'Fiber Optic (Mediacon)', 'Premium 2'],
    ['10-13-21', 'Gomez Ubos', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['10-13-21', 'Gaging Joaquin', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-16-21', 'Andrade', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['11-02-21', 'Narding Uy', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['11-03-21', 'Badilla', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['11-04-21', 'Sarona', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-10-21', 'Alvey Arsenal', 'Wireless Radio', 'Premium 2'],
    ['12-19-21', 'Castro', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-20-21', 'Arenas', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['01-14-22', 'Jerry Gallego', 'Fiber Optic (OLT)', 'Standard 1'],
    ['03-05-22', 'Alfven Sumulong', 'Fiber Optic (OLT)', 'Standard 1'],
    ['03-05-22', 'Alboy Gallego', 'Fiber Optic (OLT)', 'Others'],
    ['03-07-22', 'Riza Basin', 'Fiber Optic (OLT)', 'Standard 1'],
    ['03-12-22', 'Melda Agnes', 'Fiber Optic (OLT)', 'Standard 1'],
    ['03-17-22', 'Elping Abellana', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-10-22', 'Irma Idern', 'Wireless Radio', 'Standard 1'],
    ['08-21-22', 'Larry Lemetares', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-21-22', 'Alcorin Nilo', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-16-22', 'Israel Seseco', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-03-22', 'Belma Abellana', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-05-22', 'Felipe Rabanel', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-13-22', 'Malapad', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-18-22', 'Balbino', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-19-22', 'Ame Alcorin Noel Drier', 'Fiber Optic (OLT)', 'Standard 1'],
    ['05-16-23', 'Monica Adaza', 'Fiber Optic (OLT)', 'Premium 2'],
    ['07-15-23', 'Dodong Papa', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-15-23', 'Rudy Ebisa', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-18-23', 'Jhon Montimayor', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-18-23', 'Paquito Duerme', 'Fiber Optic (Mediacon)', 'Business 3'],
    ['07-18-23', 'Roselyn Manalo', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-01-23', 'Aguilera', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-03-23', 'Dumala', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-03-23', 'Gloria', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-03-23', 'Duerme Joel', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['02-22-23', 'Kulano Arsenal', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-23-23', 'Albert Arsenal', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-30-23', 'Bontuyan Rhoem', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-06-23', 'May2x Moring', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-06-23', 'Gogo Basin', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-06-23', 'Romero Lucille', 'Fiber Optic (OLT)', 'Premium 2'],
    ['12-15-23', 'Maralag ES', 'Wireless Radio', 'Premium 2'],
    ['01-08-24', 'Maralag Barangay', 'Fiber Optic (OLT)', 'Premium 2'],
    ['01-13-24', 'Borjie Insek', 'Fiber Optic (OLT)', 'Standard 1'],
    ['02-23-24', 'Ricky Ayunan', 'Fiber Optic (OLT)', 'Premium 2'],
    ['03-04-24', 'Muro Pilas Mar.', 'Fiber Optic (OLT)', 'Standard 1'],
    ['03-17-24', 'Edia Manalo', 'Fiber Optic (OLT)', 'Standard 1'],
    ['04-07-24', 'Manang Mirang', 'Fiber Optic (OLT)', 'Standard 1'],
    ['05-28-24', 'Jojo Arsenal', 'Wireless Radio', 'Standard 1'],
    ['08-28-24', 'Langga Ayunan', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-19-24', 'Castro Reactivated', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-12-24', 'Guillen', 'Fiber Optic (OLT)', 'Premium 2'],
    ['10-23-24', 'Ar2x Joaquin', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-21-24', 'Neneng C. Reactivated', 'Fiber Optic (OLT)', 'Standard 1'],
    ['01-10-25', 'Janice Uy Opis', 'Fiber Optic (OLT)', 'Standard 1'],
    ['02-02-25', 'Susan', 'Fiber Optic (OLT)', 'Standard 1'],
    ['02-09-25', 'Tonying', 'Fiber Optic (Mediacon)', 'Standard 1'],
    ['03-07-25', 'Gloria Reactivated', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-22-25', 'Baran', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-02-25', 'Mission', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-03-25', 'Bestudio', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-25-25', 'Brgy. Maralag React', 'Fiber Optic (OLT)', 'Standard 1'],
    ['09-25-25', 'Terante', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-15-25', 'Pacquiao', 'Fiber Optic (OLT)', 'Standard 1'],
    ['12-16-25', 'Macot', 'Fiber Optic (OLT)', 'Standard 1'],
    ['01-08-26', 'Salvador', 'Fiber Optic (OLT)', 'Standard 1'],
    ['01-12-26', 'Telesforo', 'Fiber Optic (OLT)', 'Standard 1'],
    ['02-22-26', 'Elena Bontuyan', 'Fiber Optic (OLT)', 'Standard 1'],
    ['04-05-26', 'Emely Moring', 'Fiber Optic (OLT)', 'Standard 1'],
    ['04-13-26', 'Elona', 'Fiber Optic (OLT)', 'Standard 1'],
    ['05-13-26', 'Garlik', 'Fiber Optic (OLT)', 'Standard 1'],
    ['06-10-26', 'Berhame Lito', 'Fiber Optic (OLT)', 'Standard 1'],
    ['06-11-26', 'Bayson Boy', 'Fiber Optic (OLT)', 'Standard 1'],
    ['07-02-26', 'Ducks Gose Reactivated', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-15-26', 'Gaga Moring', 'Fiber Optic (OLT)', 'Standard 1'],
    ['08-20-26', 'Rhey Galitguit', 'Fiber Optic (OLT)', 'Standard 1'],
    ['10-22-26', 'Bentot', 'Fiber Optic (Mediacon)', 'Standard 1'],
];

function parseRosterDate(string $mdy): string
{
    $dt = DateTime::createFromFormat('m-d-y', $mdy);
    if (!$dt) {
        throw new RuntimeException("Invalid date: {$mdy}");
    }
    return $dt->format('Y-m-d');
}

function mapMedium(string $label): string
{
    $label = trim($label);
    return match ($label) {
        'Wireless Radio' => 'wireless_radio',
        'Fiber Optic (Mediacon)' => 'fiber_mediacon',
        'Fiber Optic (OLT)' => 'fiber_olt',
        default => throw new RuntimeException("Unknown medium: {$label}"),
    };
}

function mapPlanName(string $label): string
{
    $label = trim($label);
    return match (true) {
        stripos($label, 'Basic') !== false => 'Basic 5Mbps',
        stripos($label, 'Standard') !== false => 'Standard 10Mbps',
        stripos($label, 'Premium') !== false => 'Premium 25Mbps',
        stripos($label, 'Business') !== false => 'Business 35Mbps',
        stripos($label, 'Other') !== false => 'Others (Special Plan)',
        default => throw new RuntimeException("Unknown plan: {$label}"),
    };
}

function slugEmail(string $name, int $seq): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name) ?? 'customer');
    $slug = trim($slug, '.');
    if ($slug === '') {
        $slug = 'customer';
    }
    return $slug . $seq . '@shaven.local';
}

$db = getDB();

$planMap = [];
foreach ($db->query('SELECT id, name FROM service_plans') as $plan) {
    $planMap[$plan['name']] = (int) $plan['id'];
}

foreach (['Basic 5Mbps', 'Standard 10Mbps', 'Premium 25Mbps', 'Business 35Mbps', 'Others (Special Plan)'] as $required) {
    if (!isset($planMap[$required])) {
        fwrite(STDERR, "Missing plan in database: {$required}\n");
        exit(1);
    }
}

$ownerId = (int) ($db->query("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1")->fetchColumn() ?: 1);

echo 'Roster rows: ' . count($roster) . "\n";

$db->beginTransaction();
try {
    // Unlink portal users first
    $db->exec('UPDATE users SET customer_id = NULL WHERE customer_id IS NOT NULL');

    // Clear dependent records that block customer deletes
    $optionalTables = [
        'remittance_payments',
        'payment_batches',
        'sms_notifications',
        'notification_reads',
    ];
    foreach ($optionalTables as $table) {
        $exists = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetchColumn();
        if ($exists) {
            if ($table === 'notification_reads') {
                // keep notification reads; no customer FK
                continue;
            }
            $db->exec("DELETE FROM `{$table}`");
        }
    }

    $db->exec('DELETE FROM payments');
    $db->exec('DELETE FROM bills');
    if ($db->query("SHOW TABLES LIKE 'customer_plan_history'")->fetchColumn()) {
        $db->exec('DELETE FROM customer_plan_history');
    }
    if ($db->query("SHOW TABLES LIKE 'repair_tickets'")->fetchColumn()) {
        $db->exec('DELETE FROM repair_tickets');
    }
    if ($db->query("SHOW TABLES LIKE 'inquiries'")->fetchColumn()) {
        $db->exec('DELETE FROM inquiries');
    }
    if ($db->query("SHOW TABLES LIKE 'batch_invoices'")->fetchColumn()) {
        $db->exec('DELETE FROM batch_invoices');
    }
    if ($db->query("SHOW TABLES LIKE 'advance_payments'")->fetchColumn()) {
        $db->exec('DELETE FROM advance_payments');
    }

    $db->exec('DELETE FROM customers');

    $insert = $db->prepare(
        'INSERT INTO customers (
            account_number, full_name, email, phone, connection_medium,
            address, barangay, city, province, plan_id, installation_date,
            status, advance_balance, notes, created_by
         ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, 0.00, ?, ?
         )'
    );

    $yearCounters = [];
    $inserted = 0;

    foreach ($roster as $row) {
        [$dateRaw, $name, $mediumLabel, $planLabel] = $row;
        $installDate = parseRosterDate($dateRaw);
        $year = substr($installDate, 0, 4);
        $yearCounters[$year] = ($yearCounters[$year] ?? 0) + 1;
        $seq = $yearCounters[$year];
        $account = sprintf('SN-%s-%04d', $year, $seq);

        $planName = mapPlanName($planLabel);
        $planId = $planMap[$planName];
        $medium = mapMedium($mediumLabel);

        $notes = null;
        if (stripos($name, 'reactivated') !== false) {
            $notes = 'Reactivated subscriber';
        }

        $insert->execute([
            $account,
            $name,
            slugEmail($name, $seq),
            sprintf('09%09d', $seq + ((int) $year * 100)),
            $medium,
            'Maralag',
            'Maralag',
            'Dumingag',
            'Zamboanga Del Sur',
            $planId,
            $installDate,
            'active',
            $notes,
            $ownerId,
        ]);
        $inserted++;
    }

    // Relink demo customer portal user to first customer if present
    $firstCustomerId = (int) $db->query('SELECT id FROM customers ORDER BY installation_date ASC, id ASC LIMIT 1')->fetchColumn();
    if ($firstCustomerId) {
        $db->prepare("UPDATE users SET customer_id = ? WHERE username = 'customer1'")->execute([$firstCustomerId]);
    }

    $db->commit();
    echo "Inserted {$inserted} customers.\n";
    echo "Sorted view will show newest installation first.\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$count = (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
echo "customers table now has {$count} rows.\n";

echo "By plan:\n";
foreach ($db->query(
    'SELECT p.name, COUNT(*) AS c
     FROM customers cu JOIN service_plans p ON p.id = cu.plan_id
     GROUP BY p.id ORDER BY c DESC'
) as $row) {
    echo "  {$row['name']}: {$row['c']}\n";
}

echo "By medium:\n";
foreach ($db->query(
    'SELECT connection_medium, COUNT(*) AS c FROM customers GROUP BY connection_medium ORDER BY c DESC'
) as $row) {
    echo "  {$row['connection_medium']}: {$row['c']}\n";
}
