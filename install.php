<?php
/**
 * Database installer — run via browser or CLI:
 * http://localhost/shaven_networks_v2/install.php
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/database_tools.php';

$messages = [];
$success = false;
$alreadyInstalled = false;

try {
    $pdo = getRootPdo();
    $pdo->exec('USE `' . DB_NAME . '`');
    $alreadyInstalled = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
} catch (PDOException $e) {
    try {
        $pdo = getRootPdo();
        $alreadyInstalled = false;
    } catch (PDOException $e2) {
        $messages[] = 'Could not connect to MySQL: ' . $e2->getMessage();
        $pdo = null;
    }
}

$runInstall = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || php_sapi_name() === 'cli';
$freshInstall = isset($_POST['fresh']) || (php_sapi_name() === 'cli' && in_array('--fresh', $argv ?? [], true));

if ($runInstall && $pdo) {
    try {
        if ($alreadyInstalled && !$freshInstall) {
            resetDemoPasswords();
            $success = true;
            $messages[] = 'Database is already installed.';
            $messages[] = 'Demo passwords were reset to: password123';
            $messages[] = 'Use "Reinstall" below only if you want to wipe and recreate all data.';
        } else {
            installFreshDatabase();
            $success = true;
            $alreadyInstalled = true;
            $messages[] = $freshInstall
                ? 'Database reinstalled successfully! All previous data was removed.'
                : 'Database installed successfully!';
            $messages[] = 'Demo login: owner / tech1 / collector1 / customer1 — password: password123';
        }
    } catch (Throwable $e) {
        $messages[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install - SHAVEN Networks ISP Billing</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card" style="max-width:520px">
        <div class="login-header">
            <div class="brand-icon lg">SN</div>
            <h1>Database Installer</h1>
            <p>SHAVEN Networks ISP Billing System</p>
        </div>

        <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary btn-block">Go to Login</a>
        <?php if ($alreadyInstalled): ?>
        <form method="POST" style="margin-top:12px" onsubmit="return confirm('This will DELETE all existing data and reinstall demo data. Continue?');">
            <input type="hidden" name="fresh" value="1">
            <button type="submit" class="btn btn-outline btn-block">Reinstall Database (Fresh)</button>
        </form>
        <?php endif; ?>
        <?php elseif ($pdo): ?>
        <?php if ($alreadyInstalled): ?>
        <div class="alert alert-info">Database tables already exist. You can log in, or reinstall to reset everything.</div>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary btn-block">Go to Login</a>
        <form method="POST" style="margin-top:12px" onsubmit="return confirm('This will DELETE all existing data and reinstall demo data. Continue?');">
            <input type="hidden" name="fresh" value="1">
            <button type="submit" class="btn btn-outline btn-block">Reinstall Database (Fresh)</button>
        </form>
        <?php else: ?>
        <p style="margin-bottom:16px;font-size:14px;color:#64748b">
            This will create the database, tables, and seed demo data.
            Make sure XAMPP MySQL is running.
        </p>
        <form method="POST">
            <button type="submit" class="btn btn-primary btn-block">Install Database</button>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <p style="font-size:14px;color:#64748b">Start MySQL in XAMPP, then refresh this page.</p>
        <?php endif; ?>
    </div>
</body>
</html>
