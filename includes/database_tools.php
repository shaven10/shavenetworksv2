<?php

function getRootPdo(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function runSqlFile(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('SQL file not found: ' . $path);
    }

    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function postInstallSetup(PDO $pdo): void
{
    $pdo->exec('USE `' . DB_NAME . '`');
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ?')->execute([$hash]);

    $exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'customer1'")->fetchColumn();
    if (!$exists) {
        $customerId = $pdo->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetchColumn();
        if ($customerId) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, email, role, customer_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(['customer1', $hash, 'Pedro Santos', 'pedro@email.com', 'customer', $customerId]);
        }
    }
}

function installFreshDatabase(): void
{
    $pdo = getRootPdo();
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    runSqlFile($pdo, __DIR__ . '/../database/schema.sql');
    runSqlFile($pdo, __DIR__ . '/../database/seed.sql');
    postInstallSetup($pdo);
}

function resetDemoPasswords(): void
{
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    getDB()->prepare('UPDATE users SET password_hash = ?')->execute([$hash]);
}

function clearTransactionData(): void
{
    $db = getDB();
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'remittance_payments',
        'activity_logs',
        'payments',
        'payment_batches',
        'remittances',
        'bills',
        'repair_tickets',
        'inquiries',
    ] as $table) {
        $db->exec("TRUNCATE TABLE `{$table}`");
    }
    $db->exec('UPDATE customers SET advance_balance = 0');
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function getDatabaseStats(): array
{
    $db = getDB();
    $tables = [
        'users'           => 'Users',
        'service_plans'   => 'Service Plans',
        'customers'       => 'Customers',
        'bills'           => 'Bills',
        'payments'        => 'Payments',
        'repair_tickets'  => 'Repair Tickets',
        'inquiries'       => 'Inquiries',
        'activity_logs'   => 'Activity Logs',
    ];

    $stats = [];
    $total = 0;

    foreach ($tables as $table => $label) {
        try {
            $count = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $stats[] = ['table' => $table, 'label' => $label, 'count' => $count];
            $total += $count;
        } catch (PDOException) {
            $stats[] = ['table' => $table, 'label' => $label, 'count' => null];
        }
    }

    $dbName = DB_NAME;
    $size = $db->query(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
         FROM information_schema.tables WHERE table_schema = " . $db->quote($dbName)
    )->fetchColumn();

    return [
        'tables'      => $stats,
        'total_rows'  => $total,
        'size_mb'     => $size ?: 0,
        'database'    => $dbName,
        'host'        => DB_HOST,
    ];
}

function verifyOwnerPassword(string $password): bool
{
    $user = currentUser();
    if (!$user || $user['role'] !== 'owner') {
        return false;
    }

    $stmt = getDB()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    return $hash && password_verify($password, $hash);
}

function getDatabaseTableList(): array
{
    return [
        'service_plans',
        'users',
        'customers',
        'bills',
        'payments',
        'activity_logs',
        'repair_tickets',
        'inquiries',
    ];
}

function getBackupDirectory(): string
{
    $dir = __DIR__ . '/../storage/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }

    return $dir;
}

function getRestorePointsDirectory(): string
{
    $dir = __DIR__ . '/../storage/restore_points';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }

    return $dir;
}

function getDefaultRestorePointMetaPath(): string
{
    return getRestorePointsDirectory() . DIRECTORY_SEPARATOR . 'default.json';
}

function getDefaultRestorePointSqlPath(): string
{
    return getRestorePointsDirectory() . DIRECTORY_SEPARATOR . 'default_customers_plans.sql';
}

function sqlQuoteValue(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    return $pdo->quote((string) $value);
}

function exportTableInsertSql(PDO $pdo, string $table): string
{
    $sql = "-- Table: {$table}\n";
    $sql .= "DELETE FROM `{$table}`;\n";

    try {
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        return $sql . "\n";
    }

    foreach ($rows as $row) {
        $columns = array_keys($row);
        $values = [];
        foreach ($row as $value) {
            $values[] = sqlQuoteValue($pdo, $value);
        }
        $sql .= 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
            . implode(', ', $values) . ");\n";
    }

    return $sql . "\n";
}

function exportCustomersAndPlansSnapshot(?string $label = null): string
{
    $pdo = getDB();
    $customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $planCount = (int) $pdo->query('SELECT COUNT(*) FROM service_plans')->fetchColumn();

    $sql = "-- SHAVEN Networks Restore Point\n";
    $sql .= "-- Scope: service_plans + customers\n";
    $sql .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
    $sql .= '-- Label: ' . ($label ?: 'Untitled') . "\n";
    $sql .= "-- Plans: {$planCount}\n";
    $sql .= "-- Customers: {$customerCount}\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    $sql .= exportTableInsertSql($pdo, 'service_plans');
    $sql .= exportTableInsertSql($pdo, 'customers');
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

function saveRestorePointMeta(array $meta): void
{
    file_put_contents(
        getDefaultRestorePointMetaPath(),
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function getDefaultRestorePointMeta(): ?array
{
    $path = getDefaultRestorePointMetaPath();
    $sqlPath = getDefaultRestorePointSqlPath();
    if (!is_file($path) || !is_file($sqlPath)) {
        return null;
    }

    $meta = json_decode((string) file_get_contents($path), true);
    if (!is_array($meta)) {
        return null;
    }

    $meta['size'] = filesize($sqlPath) ?: 0;
    $meta['sql_file'] = basename($sqlPath);
    return $meta;
}

function createDefaultRestorePoint(?string $label = null): array
{
    $label = trim((string) ($label ?: 'Default customers & service plans'));
    $sql = exportCustomersAndPlansSnapshot($label);
    $sqlPath = getDefaultRestorePointSqlPath();
    file_put_contents($sqlPath, $sql);

    $pdo = getDB();
    $meta = [
        'label'           => $label,
        'created_at'      => date('Y-m-d H:i:s'),
        'updated_at'      => date('Y-m-d H:i:s'),
        'plan_count'      => (int) $pdo->query('SELECT COUNT(*) FROM service_plans')->fetchColumn(),
        'customer_count'  => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'is_default'      => true,
        'scope'           => ['service_plans', 'customers'],
    ];
    saveRestorePointMeta($meta);

    return $meta;
}

function ensureDefaultRestorePoint(): array
{
    $meta = getDefaultRestorePointMeta();
    if ($meta) {
        return $meta;
    }
    return createDefaultRestorePoint('Default customers & service plans');
}

function listNamedRestorePoints(): array
{
    $dir = getRestorePointsDirectory();
    $files = glob($dir . DIRECTORY_SEPARATOR . 'restore_*.sql') ?: [];
    rsort($files);

    $points = [];
    foreach ($files as $path) {
        $metaPath = preg_replace('/\.sql$/', '.json', $path);
        $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        $points[] = [
            'filename'       => basename($path),
            'size'           => filesize($path) ?: 0,
            'created'        => filemtime($path) ?: time(),
            'label'          => is_array($meta) ? ($meta['label'] ?? basename($path)) : basename($path),
            'plan_count'     => is_array($meta) ? (int) ($meta['plan_count'] ?? 0) : 0,
            'customer_count' => is_array($meta) ? (int) ($meta['customer_count'] ?? 0) : 0,
        ];
    }

    return $points;
}

function saveNamedRestorePoint(string $label = ''): array
{
    $label = trim($label) ?: ('Restore point ' . date('Y-m-d H:i'));
    $sql = exportCustomersAndPlansSnapshot($label);
    $stamp = date('Ymd_His');
    $filename = "restore_{$stamp}.sql";
    $path = getRestorePointsDirectory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($path, $sql);

    $pdo = getDB();
    $meta = [
        'label'          => $label,
        'created_at'     => date('Y-m-d H:i:s'),
        'plan_count'     => (int) $pdo->query('SELECT COUNT(*) FROM service_plans')->fetchColumn(),
        'customer_count' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'scope'          => ['service_plans', 'customers'],
    ];
    file_put_contents(
        getRestorePointsDirectory() . DIRECTORY_SEPARATOR . "restore_{$stamp}.json",
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    return array_merge($meta, ['filename' => $filename]);
}

function getNamedRestorePointPath(string $filename): string
{
    $filename = basename($filename);
    if (!preg_match('/^restore_[0-9]{8}_[0-9]{6}\.sql$/', $filename)) {
        throw new RuntimeException('Invalid restore point filename.');
    }

    $path = getRestorePointsDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        throw new RuntimeException('Restore point not found.');
    }

    return $path;
}

function cleanupOrphanedCustomerDependents(PDO $pdo): void
{
    // Remove dependent rows for customers that no longer exist after a plans/customers restore.
    $tablesWithCustomerId = [
        'bills',
        'payments',
        'payment_batches',
        'repair_tickets',
        'inquiries',
        'customer_plan_history',
        'sms_notifications',
        'employee_ledgers',
    ];

    foreach ($tablesWithCustomerId as $table) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (PDOException) {
            continue;
        }

        try {
            $pdo->exec(
                "DELETE t FROM `{$table}` t
                 LEFT JOIN customers c ON c.id = t.customer_id
                 WHERE t.customer_id IS NOT NULL AND c.id IS NULL"
            );
        } catch (PDOException) {
            // Table may not have customer_id; skip.
        }
    }

    // Portal users linked to missing customers
    try {
        $pdo->exec(
            "UPDATE users u
             LEFT JOIN customers c ON c.id = u.customer_id
             SET u.customer_id = NULL
             WHERE u.customer_id IS NOT NULL AND c.id IS NULL"
        );
    } catch (PDOException) {
        // ignore
    }
}

function restoreCustomersAndPlansFromSql(string $sql): void
{
    if (trim($sql) === '') {
        throw new RuntimeException('Restore point file is empty.');
    }

    $pdo = getRootPdo();
    $pdo->exec('USE `' . DB_NAME . '`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $sql = stripSqlComments($sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        // Only allow restoring the scoped tables / session flags.
        $normalized = strtoupper(ltrim($statement));
        $allowed = str_starts_with($normalized, 'SET ')
            || str_starts_with($normalized, 'DELETE FROM `SERVICE_PLANS`')
            || str_starts_with($normalized, 'DELETE FROM SERVICE_PLANS')
            || str_starts_with($normalized, 'DELETE FROM `CUSTOMERS`')
            || str_starts_with($normalized, 'DELETE FROM CUSTOMERS')
            || str_starts_with($normalized, 'INSERT INTO `SERVICE_PLANS`')
            || str_starts_with($normalized, 'INSERT INTO SERVICE_PLANS')
            || str_starts_with($normalized, 'INSERT INTO `CUSTOMERS`')
            || str_starts_with($normalized, 'INSERT INTO CUSTOMERS');

        if (!$allowed) {
            continue;
        }

        $pdo->exec($statement);
    }

    cleanupOrphanedCustomerDependents($pdo);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $maxPlan = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM service_plans')->fetchColumn();
    $maxCustomer = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM customers')->fetchColumn();
    $pdo->exec('ALTER TABLE service_plans AUTO_INCREMENT = ' . ($maxPlan + 1));
    $pdo->exec('ALTER TABLE customers AUTO_INCREMENT = ' . ($maxCustomer + 1));
}

function exportDatabaseBackup(): string
{
    $pdo = getDB();
    $tables = getDatabaseTableList();

    $sql = "-- SHAVEN Networks ISP Database Backup\n";
    $sql .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
    $sql .= '-- Database: ' . DB_NAME . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (PDOException) {
            continue;
        }

        $sql .= exportTableInsertSql($pdo, $table);
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

function saveDatabaseBackup(): string
{
    $sql = exportDatabaseBackup();
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $path = getBackupDirectory() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($path, $sql);

    return $filename;
}

function listSavedBackups(): array
{
    $dir = getBackupDirectory();
    $files = glob($dir . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [];
    rsort($files);

    $backups = [];
    foreach ($files as $path) {
        $backups[] = [
            'filename' => basename($path),
            'size'     => filesize($path),
            'created'  => filemtime($path),
        ];
    }

    return $backups;
}

function getBackupFilePath(string $filename): string
{
    $filename = basename($filename);
    if (!preg_match('/^backup_[0-9]{8}_[0-9]{6}\.sql$/', $filename)) {
        throw new RuntimeException('Invalid backup filename.');
    }

    $path = getBackupDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        throw new RuntimeException('Backup file not found.');
    }

    return $path;
}

function stripSqlComments(string $sql): string
{
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $lines[] = $line;
    }

    return implode("\n", $lines);
}

function restoreDatabaseFromSql(string $sql): void
{
    if (trim($sql) === '') {
        throw new RuntimeException('Backup file is empty.');
    }

    $pdo = getRootPdo();
    $pdo->exec('USE `' . DB_NAME . '`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $sql = stripSqlComments($sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function runDatabaseAction(string $action, string $ownerPassword, string $confirmText = '', ?array $extra = null): array
{
    if (!verifyOwnerPassword($ownerPassword)) {
        return ['success' => false, 'message' => 'Incorrect owner password.'];
    }

    try {
        switch ($action) {
            case 'reset_passwords':
                resetDemoPasswords();
                logActivity('db_reset_passwords', 'Reset all user passwords to demo default');
                return ['success' => true, 'message' => 'All user passwords reset to password123.'];

            case 'clear_transactions':
                if (strtoupper(trim($confirmText)) !== 'RESET') {
                    return ['success' => false, 'message' => 'Type RESET to confirm clearing transaction data.'];
                }
                logActivity('db_clear_transactions', 'Cleared bills, payments, batches, remittances, advance credits, tickets, inquiries, logs');
                clearTransactionData();
                return ['success' => true, 'message' => 'Transaction data cleared. Users, plans, and customers kept. Advance balances reset to zero.'];

            case 'reset_demo':
                if (strtoupper(trim($confirmText)) !== 'RESET') {
                    return ['success' => false, 'message' => 'Type RESET to confirm database reset.'];
                }
                installFreshDatabase();
                logActivity('db_reset_demo', 'Full database reset to demo seed');
                return ['success' => true, 'message' => 'Database reset to demo data. All users password: password123'];

            case 'save_backup':
                $filename = saveDatabaseBackup();
                logActivity('db_backup_saved', "Saved backup {$filename}");
                return ['success' => true, 'message' => "Backup saved as {$filename}."];

            case 'save_default_restore_point':
                $meta = createDefaultRestorePoint($extra['label'] ?? null);
                logActivity('db_restore_point_default', 'Updated default customers/plans restore point');
                return [
                    'success' => true,
                    'message' => sprintf(
                        'Default restore point saved (%d plans, %d customers).',
                        $meta['plan_count'],
                        $meta['customer_count']
                    ),
                ];

            case 'save_named_restore_point':
                $point = saveNamedRestorePoint((string) ($extra['label'] ?? ''));
                logActivity('db_restore_point_saved', 'Saved restore point ' . $point['filename']);
                return [
                    'success' => true,
                    'message' => sprintf(
                        'Restore point saved as %s (%d plans, %d customers).',
                        $point['filename'],
                        $point['plan_count'],
                        $point['customer_count']
                    ),
                ];

            case 'restore_default_point':
                if (strtoupper(trim($confirmText)) !== 'RESTORE') {
                    return ['success' => false, 'message' => 'Type RESTORE to confirm restore point recovery.'];
                }
                $meta = ensureDefaultRestorePoint();
                $sql = file_get_contents(getDefaultRestorePointSqlPath());
                if ($sql === false) {
                    return ['success' => false, 'message' => 'Default restore point file is missing.'];
                }
                restoreCustomersAndPlansFromSql($sql);
                logActivity('db_restore_point_default_applied', 'Restored customers & service plans from default restore point');
                return [
                    'success' => true,
                    'message' => sprintf(
                        'Restored default point “%s” (%d plans, %d customers). Related orphan records were cleaned.',
                        $meta['label'] ?? 'Default',
                        $meta['plan_count'] ?? 0,
                        $meta['customer_count'] ?? 0
                    ),
                ];

            case 'restore_named_point':
                if (strtoupper(trim($confirmText)) !== 'RESTORE') {
                    return ['success' => false, 'message' => 'Type RESTORE to confirm restore point recovery.'];
                }
                $filename = (string) ($extra['filename'] ?? '');
                $path = getNamedRestorePointPath($filename);
                $sql = file_get_contents($path);
                if ($sql === false) {
                    return ['success' => false, 'message' => 'Restore point file could not be read.'];
                }
                restoreCustomersAndPlansFromSql($sql);
                logActivity('db_restore_point_applied', "Restored customers & service plans from {$filename}");
                return ['success' => true, 'message' => 'Restored customers and service plans from ' . basename($filename) . '.'];

            case 'restore_upload':
                if (strtoupper(trim($confirmText)) !== 'RESTORE') {
                    return ['success' => false, 'message' => 'Type RESTORE to confirm database restore.'];
                }
                $upload = $extra['upload'] ?? null;
                if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    return ['success' => false, 'message' => 'Please select a valid SQL backup file.'];
                }
                $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                if ($ext !== 'sql') {
                    return ['success' => false, 'message' => 'Only .sql backup files are allowed.'];
                }
                $sql = file_get_contents($upload['tmp_name']);
                logActivity('db_restore_upload', 'Restored database from uploaded backup');
                restoreDatabaseFromSql($sql);
                return ['success' => true, 'message' => 'Database restored from uploaded backup file.'];

            case 'restore_saved':
                if (strtoupper(trim($confirmText)) !== 'RESTORE') {
                    return ['success' => false, 'message' => 'Type RESTORE to confirm database restore.'];
                }
                $filename = $extra['filename'] ?? '';
                $path = getBackupFilePath($filename);
                $sql = file_get_contents($path);
                logActivity('db_restore_saved', "Restored database from {$filename}");
                restoreDatabaseFromSql($sql);
                return ['success' => true, 'message' => 'Database restored from ' . basename($filename) . '.'];

            default:
                return ['success' => false, 'message' => 'Unknown action.'];
        }
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
