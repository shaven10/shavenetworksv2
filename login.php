<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    redirectHome();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password && login($username, $password)) {
        redirectHome();
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en" <?= renderThemeAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style id="app-theme"><?= renderThemeStyles() ?></style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon lg">SN</div>
            <h1>SHAVEN Networks</h1>
            <p>ISP Billing System</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
        <div class="login-demo">
            <p><strong>Demo Accounts</strong></p>
            <small>owner / tech1 / collector1 / customer1 — password: <code>password123</code></small>
        </div>
    </div>
</body>
</html>
