<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$filename = basename($_GET['file'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyOwnerPassword($_POST['owner_password'] ?? '')) {
        flash('danger', 'Owner password required to download saved backup.');
        redirect('/settings/database.php');
    }

    $path = getBackupFilePath($filename);
    $content = file_get_contents($path);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
    redirect('/settings/database.php');
}
