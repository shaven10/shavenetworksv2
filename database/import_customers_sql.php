<?php
/**
 * Import customers from a phpMyAdmin dump into shaven_isp_billing.
 * Usage: D:\SC30\php\php.exe database/import_customers_sql.php "C:\Users\STEVECURRY30\Downloads\customers.sql"
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$source = $argv[1] ?? 'C:\\Users\\STEVECURRY30\\Downloads\\customers.sql';
if (!is_file($source)) {
    fwrite(STDERR, "File not found: {$source}\n");
    exit(1);
}

$sql = file_get_contents($source);
if ($sql === false) {
    fwrite(STDERR, "Unable to read SQL file.\n");
    exit(1);
}

if (!preg_match('/INSERT INTO `customers`\s*\(([^)]+)\)\s*VALUES\s*(.+?);\s*(?:--|\n\n|ALTER)/is', $sql, $m)) {
    // Fallback: catch INSERT ... VALUES ... ; near end before Indexes
    if (!preg_match('/INSERT INTO `customers`\s*\(([^)]+)\)\s*VALUES\s*(.+);/is', $sql, $m)) {
        fwrite(STDERR, "Could not find INSERT INTO customers in dump.\n");
        exit(1);
    }
}

$columns = array_map(static function ($c) {
    return trim($c, " `\t\n\r");
}, explode(',', $m[1]));

$valuesBlock = trim($m[2]);
// Stop before ALTER if captured
if (($pos = stripos($valuesBlock, 'ALTER TABLE')) !== false) {
    $valuesBlock = trim(substr($valuesBlock, 0, $pos));
}
$valuesBlock = rtrim($valuesBlock, "; \t\n\r");

// Split top-level value tuples
$rows = [];
$len = strlen($valuesBlock);
$depth = 0;
$start = null;
$inStr = false;
$strChar = '';
for ($i = 0; $i < $len; $i++) {
    $ch = $valuesBlock[$i];
    $prev = $i > 0 ? $valuesBlock[$i - 1] : '';

    if ($inStr) {
        if ($ch === $strChar && $prev !== '\\') {
            $inStr = false;
        }
        continue;
    }

    if ($ch === "'" || $ch === '"') {
        $inStr = true;
        $strChar = $ch;
        continue;
    }

    if ($ch === '(') {
        if ($depth === 0) {
            $start = $i + 1;
        }
        $depth++;
    } elseif ($ch === ')') {
        $depth--;
        if ($depth === 0 && $start !== null) {
            $rows[] = substr($valuesBlock, $start, $i - $start);
            $start = null;
        }
    }
}

echo 'Parsed rows: ' . count($rows) . "\n";

$db = getDB();

// Ensure referenced plans exist
$planIds = [];
foreach ($db->query('SELECT id FROM service_plans')->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $planIds[(int) $id] = true;
}

$userIds = [];
foreach ($db->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $userIds[(int) $id] = true;
}

$fallbackUserId = (int) ($db->query("SELECT id FROM users WHERE role = 'owner' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
if (!$fallbackUserId) {
    $fallbackUserId = (int) ($db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
}

$neededPlans = [];
foreach ($rows as $rowSql) {
    $vals = parseSqlValueList($rowSql);
    $map = array_combine($columns, $vals);
    $pid = (int) ($map['plan_id'] ?? 0);
    if ($pid && empty($planIds[$pid])) {
        $neededPlans[$pid] = true;
    }
}

foreach (array_keys($neededPlans) as $pid) {
    $stmt = $db->prepare(
        'INSERT INTO service_plans (id, name, speed_mbps, monthly_fee, description, is_active)
         VALUES (?, ?, 25, 0.00, ?, 1)'
    );
    $stmt->execute([$pid, 'Imported Plan ' . $pid, 'Auto-created for customers.sql import (plan_id ' . $pid . ')']);
    $planIds[$pid] = true;
    echo "Created missing service_plans id={$pid}\n";
}

$insertCols = [
    'id', 'account_number', 'full_name', 'email', 'phone', 'connection_medium',
    'address', 'barangay', 'city', 'province', 'plan_id', 'installation_date',
    'status', 'advance_balance', 'billing_generate_from_year', 'billing_generate_to_year',
    'notes', 'created_by', 'created_at', 'updated_at',
];

$placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
$colList = implode(', ', array_map(static fn ($c) => "`{$c}`", $insertCols));
$updateParts = [];
foreach ($insertCols as $c) {
    if ($c === 'id') {
        continue;
    }
    $updateParts[] = "`{$c}` = VALUES(`{$c}`)";
}

$sqlInsert = "INSERT INTO customers ({$colList}) VALUES ({$placeholders})
              ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);

$stmt = $db->prepare($sqlInsert);

$ok = 0;
$fail = 0;
$db->beginTransaction();
try {
    foreach ($rows as $rowSql) {
        $vals = parseSqlValueList($rowSql);
        if (count($vals) !== count($columns)) {
            echo "Skip malformed row (cols mismatch): " . substr($rowSql, 0, 80) . "...\n";
            $fail++;
            continue;
        }
        $map = array_combine($columns, $vals);

        $createdBy = $map['created_by'] ?? null;
        if ($createdBy === null || $createdBy === '' || strtoupper((string) $createdBy) === 'NULL') {
            $createdBy = null;
        } else {
            $createdBy = (int) $createdBy;
            if (empty($userIds[$createdBy])) {
                $createdBy = $fallbackUserId ?: null;
            }
        }

        $planId = (int) $map['plan_id'];
        if (empty($planIds[$planId])) {
            echo "Skip {$map['account_number']}: missing plan_id {$planId}\n";
            $fail++;
            continue;
        }

        $params = [];
        foreach ($insertCols as $c) {
            $v = $map[$c] ?? null;
            if ($c === 'created_by') {
                $params[] = $createdBy;
                continue;
            }
            if ($v === null || strtoupper((string) $v) === 'NULL') {
                $params[] = null;
            } else {
                $params[] = $v;
            }
        }

        $stmt->execute($params);
        $ok++;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$total = (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$maxId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM customers')->fetchColumn();
$db->exec('ALTER TABLE customers AUTO_INCREMENT = ' . ($maxId + 1));

echo "Imported/updated: {$ok}\n";
echo "Failed/skipped: {$fail}\n";
echo "Customers in DB now: {$total}\n";
echo "AUTO_INCREMENT set to " . ($maxId + 1) . "\n";

/**
 * Parse a single SQL VALUES tuple body into PHP values.
 */
function parseSqlValueList(string $rowSql): array
{
    $vals = [];
    $len = strlen($rowSql);
    $buf = '';
    $inStr = false;
    $strChar = '';

    for ($i = 0; $i < $len; $i++) {
        $ch = $rowSql[$i];
        $prev = $i > 0 ? $rowSql[$i - 1] : '';

        if ($inStr) {
            if ($ch === $strChar && $prev !== '\\') {
                $inStr = false;
                $buf .= $ch;
                continue;
            }
            // Unescape common sequences for PHP value
            if ($ch === '\\' && $i + 1 < $len) {
                $next = $rowSql[$i + 1];
                if ($next === "'" || $next === '\\' || $next === '"') {
                    $buf .= $next;
                    $i++;
                    continue;
                }
            }
            $buf .= $ch;
            continue;
        }

        if ($ch === "'" || $ch === '"') {
            $inStr = true;
            $strChar = $ch;
            $buf .= $ch;
            continue;
        }

        if ($ch === ',') {
            $vals[] = normalizeSqlValue(trim($buf));
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }
    if (trim($buf) !== '' || $buf === '0') {
        $vals[] = normalizeSqlValue(trim($buf));
    }

    return $vals;
}

function normalizeSqlValue(string $raw)
{
    if (strtoupper($raw) === 'NULL') {
        return null;
    }
    if (strlen($raw) >= 2 && (($raw[0] === "'" && substr($raw, -1) === "'") || ($raw[0] === '"' && substr($raw, -1) === '"'))) {
        return substr($raw, 1, -1);
    }
    return $raw;
}
