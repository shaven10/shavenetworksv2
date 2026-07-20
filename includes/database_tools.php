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

        $sql .= "-- Table: {$table}\n";
        $sql .= "DELETE FROM `{$table}`;\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = [];
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
            }
            $sql .= 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
                . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
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
