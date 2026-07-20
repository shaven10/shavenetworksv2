<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$password = $_POST['owner_password'] ?? '';

if (!verifyOwnerPassword($password)) {
    flash('danger', 'Incorrect owner password. Backup download cancelled.');
    redirect('/settings/database.php');
}

try {
    $sql = exportDatabaseBackup();
    $filename = 'shaven_isp_backup_' . date('Ymd_His') . '.sql';

    logActivity('db_backup_download', 'Downloaded database backup');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, must-revalidate');

    echo $sql;
    exit;
} catch (Throwable $e) {
    flash('danger', 'Backup failed: ' . $e->getMessage());
    redirect('/settings/database.php');
}
