<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_avatar.php';
require_once __DIR__ . '/../includes/customer_signup.php';

requireRole('owner');



$pageTitle = 'Add User';

$currentPage = 'users';

$errors = [];

$customers = getDB()->query('SELECT id, account_number, full_name FROM customers ORDER BY full_name')->fetchAll();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';

    $fullName = trim($_POST['full_name'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $role = $_POST['role'] ?? '';

    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;



    if (!$username) $errors[] = 'Username is required.';

    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (!$fullName) $errors[] = 'Full name is required.';

    if (!in_array($role, ['owner', 'technical', 'collector', 'customer'], true)) $errors[] = 'Invalid role.';

    if ($role === 'customer' && !$customerId) $errors[] = 'Customer account link is required for customer role.';



    $stmt = getDB()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');

    $stmt->execute([$username]);

    if ($stmt->fetchColumn() > 0) $errors[] = 'Username already exists.';



    if ($role === 'customer' && $customerId) {

        if (customerHasPortalSignup($customerId)) {
            $errors[] = 'This customer account already has a portal user or pending signup.';
        }

    }



    if (empty($errors)) {
        try {
            $stmt = getDB()->prepare(
                'INSERT INTO users (username, password_hash, full_name, email, role, customer_id, is_active, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, 1, "approved")'
            );
            $stmt->execute([
                $username, password_hash($password, PASSWORD_DEFAULT), $fullName,
                $email ?: null, $role, $role === 'customer' ? $customerId : null,
            ]);

            $newUserId = (int) getDB()->lastInsertId();
            $avatar = handleUserAvatarForm($newUserId, null);

            if ($avatar) {
                $stmt = getDB()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                $stmt->execute([$avatar, $newUserId]);
            }

            logActivity('user_created', "Created user: {$username} ({$role})");
            flash('success', 'User created successfully.');
            redirect('/users/index.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

}



require __DIR__ . '/../includes/header.php';

?>



<div class="page-header">

    <h1>Add User</h1>

    <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline">← Back</a>

</div>



<div class="card card-form">

    <?php if ($errors): ?>

    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>

    <?php endif; ?>

    <form method="POST" id="user-form" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="avatar">Profile Photo</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
                <small class="form-hint">Optional. JPG, PNG, WebP, or GIF. Max 2 MB.</small>
            </div>

            <div class="form-group">

                <label for="username">Username *</label>

                <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">

            </div>

            <div class="form-group">

                <label for="password">Password *</label>

                <input type="password" id="password" name="password" required minlength="6">

            </div>

            <div class="form-group">

                <label for="full_name">Full Name *</label>

                <input type="text" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">

            </div>

            <div class="form-group">

                <label for="email">Email</label>

                <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">

            </div>

            <div class="form-group">

                <label for="role">Role *</label>

                <select id="role" name="role" required onchange="toggleCustomerLink()">

                    <option value="">Select role...</option>

                    <option value="owner" <?= ($_POST['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner</option>

                    <option value="technical" <?= ($_POST['role'] ?? '') === 'technical' ? 'selected' : '' ?>>Technical</option>

                    <option value="collector" <?= ($_POST['role'] ?? '') === 'collector' ? 'selected' : '' ?>>Collector</option>

                    <option value="customer" <?= ($_POST['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer (Portal)</option>

                </select>

            </div>

            <div class="form-group" id="customer-link-group" style="display:none">

                <label for="customer_id">Linked Customer Account *</label>

                <select id="customer_id" name="customer_id">

                    <option value="">Select subscriber account...</option>

                    <?php foreach ($customers as $c): ?>

                    <option value="<?= $c['id'] ?>" <?= ($_POST['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>

                        <?= e($c['account_number']) ?> — <?= e($c['full_name']) ?>

                    </option>

                    <?php endforeach; ?>

                </select>

                <small class="form-hint">Links portal login to a subscriber record for account access.</small>

            </div>

        </div>

        <div class="form-actions">

            <button type="submit" class="btn btn-primary">Create User</button>

        </div>

    </form>

</div>



<script>

function toggleCustomerLink() {

    var role = document.getElementById('role').value;

    var group = document.getElementById('customer-link-group');

    group.style.display = role === 'customer' ? 'block' : 'none';

}

toggleCustomerLink();

</script>



<?php require __DIR__ . '/../includes/footer.php'; ?>

