<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_avatar.php';
require_once __DIR__ . '/../includes/users.php';

requireRole('owner');



$id = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');

$stmt->execute([$id]);

$user = $stmt->fetch();



if (!$user) {

    flash('danger', 'User not found.');

    redirect('/users/index.php');

}



$pageTitle = 'Edit User';

$currentPage = 'users';

$errors = [];

$customers = getDB()->query('SELECT id, account_number, full_name FROM customers ORDER BY full_name')->fetchAll();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $role = $_POST['role'] ?? '';

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $newPassword = $_POST['password'] ?? '';

    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;



    if (!$fullName) $errors[] = 'Full name is required.';

    if (!in_array($role, ['owner', 'technical', 'collector', 'customer'], true)) $errors[] = 'Invalid role.';

    if ($newPassword && strlen($newPassword) < 6) $errors[] = 'Password must be at least 6 characters.';

    if ($role === 'customer' && !$customerId) $errors[] = 'Customer account link is required.';



    if (empty($errors)) {
        $linkedCustomerId = $role === 'customer' ? $customerId : null;

        try {
            $avatar = handleUserAvatarForm($id, $user['avatar'] ?? null);

            if ($newPassword) {
                $stmt = getDB()->prepare(
                    'UPDATE users SET full_name=?, email=?, avatar=?, role=?, customer_id=?, is_active=?, password_hash=? WHERE id=?'
                );
                $stmt->execute([$fullName, $email ?: null, $avatar, $role, $linkedCustomerId, $isActive, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = getDB()->prepare(
                    'UPDATE users SET full_name=?, email=?, avatar=?, role=?, customer_id=?, is_active=? WHERE id=?'
                );
                $stmt->execute([$fullName, $email ?: null, $avatar, $role, $linkedCustomerId, $isActive, $id]);
            }

            flash('success', 'User updated.');
            redirect('/users/index.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

}



require __DIR__ . '/../includes/header.php';

?>



<div class="page-header">

    <h1>Edit User — <?= e($user['username']) ?></h1>

    <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline">← Back</a>

</div>



<div class="card card-form">

    <?php if ($errors): ?>

    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>

    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="profile-avatar-section">
            <?= renderUserAvatar($user, 'lg') ?>
            <div class="profile-avatar-fields">
                <div class="form-group">
                    <label for="avatar">Profile Photo</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small class="form-hint">JPG, PNG, WebP, or GIF. Max 2 MB.</small>
                </div>
                <?php if (!empty($user['avatar'])): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="remove_avatar" value="1">
                    Remove current photo
                </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-grid">

            <div class="form-group">

                <label>Username</label>

                <input type="text" value="<?= e($user['username']) ?>" disabled>

            </div>

            <div class="form-group">

                <label for="full_name">Full Name *</label>

                <input type="text" id="full_name" name="full_name" required value="<?= e($user['full_name']) ?>">

            </div>

            <div class="form-group">

                <label for="email">Email</label>

                <input type="email" id="email" name="email" value="<?= e($user['email']) ?>">

            </div>

            <div class="form-group">

                <label for="role">Role *</label>

                <select id="role" name="role" required onchange="toggleCustomerLink()">

                    <option value="owner" <?= $user['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>

                    <option value="technical" <?= $user['role'] === 'technical' ? 'selected' : '' ?>>Technical</option>

                    <option value="collector" <?= $user['role'] === 'collector' ? 'selected' : '' ?>>Collector</option>

                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer (Portal)</option>

                </select>

            </div>

            <div class="form-group" id="customer-link-group">

                <label for="customer_id">Linked Customer Account</label>

                <select id="customer_id" name="customer_id">

                    <option value="">Select subscriber account...</option>

                    <?php foreach ($customers as $c): ?>

                    <option value="<?= $c['id'] ?>" <?= ($user['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>

                        <?= e($c['account_number']) ?> — <?= e($c['full_name']) ?>

                    </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="password">New Password</label>

                <input type="password" id="password" name="password" minlength="6" placeholder="Leave blank to keep current">

            </div>

            <div class="form-group">

                <label class="checkbox-label">

                    <input type="checkbox" name="is_active" <?= $user['is_active'] ? 'checked' : '' ?>>

                    Active

                </label>

            </div>

        </div>

        <div class="form-actions">

            <button type="submit" class="btn btn-primary">Save Changes</button>

        </div>

    </form>

</div>



<script>

function toggleCustomerLink() {

    document.getElementById('customer-link-group').style.display =

        document.getElementById('role').value === 'customer' ? 'block' : 'none';

}

toggleCustomerLink();

</script>

<?php renderUserDeleteSection($user); ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

