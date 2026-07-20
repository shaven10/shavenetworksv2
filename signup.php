<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/customer_signup.php';
startSession();

if (isLoggedIn()) {
    redirectHome();
}

$error = '';
$success = '';
$data = [
    'account_number' => trim($_POST['account_number'] ?? ''),
    'phone'          => trim($_POST['phone'] ?? ''),
    'username'       => trim($_POST['username'] ?? ''),
    'email'          => trim($_POST['email'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';

    if ($data['account_number'] === '') {
        $error = 'Subscriber account number is required.';
    } elseif ($data['phone'] === '') {
        $error = 'Registered phone number is required.';
    } elseif ($data['username'] === '') {
        $error = 'Username is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            $result = registerCustomerSignup(
                $data['account_number'],
                $data['phone'],
                $data['username'],
                $password,
                $data['email'] ?: null
            );
            $success = 'Signup submitted for ' . $result['full_name']
                . ' (' . $result['account_number'] . '). Your portal login will work after the owner approves your account.';
            $data = [
                'account_number' => '',
                'phone'          => '',
                'username'       => '',
                'email'          => '',
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" <?= renderThemeAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Signup - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style id="app-theme"><?= renderThemeStyles() ?></style>
</head>
<body class="login-page">
    <div class="login-card login-card-wide">
        <div class="login-header">
            <div class="brand-icon lg">SN</div>
            <h1>Customer Portal Signup</h1>
            <p>Link your portal login to your SHAVEN Networks subscriber account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <?php else: ?>
        <form method="POST" class="login-form">
            <div class="info-box signup-info-box">
                <p>Use the <strong>account number</strong> and <strong>phone number</strong> on your subscriber record. Owner approval is required before you can sign in.</p>
            </div>

            <div class="form-group">
                <label for="account_number">Subscriber Account Number *</label>
                <input type="text" id="account_number" name="account_number" required
                       placeholder="e.g. SN-2026-0001"
                       value="<?= e($data['account_number']) ?>">
            </div>

            <div class="form-group">
                <label for="phone">Registered Phone Number *</label>
                <input type="text" id="phone" name="phone" required
                       placeholder="Must match subscriber record"
                       value="<?= e($data['phone']) ?>">
            </div>

            <div class="form-group">
                <label for="username">Choose Username *</label>
                <input type="text" id="username" name="username" required
                       autocomplete="username"
                       value="<?= e($data['username']) ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e($data['email']) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="6"
                       autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password *</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Submit Signup Request</button>
        </form>
        <?php endif; ?>

        <div class="login-demo">
            <p>Already have an approved account? <a href="<?= APP_URL ?>/login.php">Sign in</a></p>
        </div>
    </div>
</body>
</html>
