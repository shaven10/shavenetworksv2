<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/user_avatar.php';

requireLogin();

$pageTitle = 'My Profile';
$currentPage = 'profile';
$userId = (int) currentUser()['id'];
$errors = [];

$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User account not found.');
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['password'] ?? '';

    if (!$fullName) {
        $errors[] = 'Full name is required.';
    }
    if ($newPassword && strlen($newPassword) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        try {
            $avatar = handleUserAvatarForm($userId, $user['avatar'] ?? null);

            if ($newPassword) {
                $stmt = getDB()->prepare(
                    'UPDATE users SET full_name = ?, email = ?, avatar = ?, password_hash = ? WHERE id = ?'
                );
                $stmt->execute([
                    $fullName,
                    $email ?: null,
                    $avatar,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $userId,
                ]);
            } else {
                $stmt = getDB()->prepare(
                    'UPDATE users SET full_name = ?, email = ?, avatar = ? WHERE id = ?'
                );
                $stmt->execute([$fullName, $email ?: null, $avatar, $userId]);
            }

            logActivity('profile_updated', 'Updated profile details');
            flash('success', 'Profile updated successfully.');
            redirect('/profile.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p>Update your account photo and personal details</p>
    </div>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
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
                <label>Role</label>
                <input type="text" value="<?= e(roleLabel($user['role'])) ?>" disabled>
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" minlength="6" placeholder="Leave blank to keep current">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
