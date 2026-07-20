<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/users.php';

requireRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$confirmText = strtoupper(trim($_POST['confirm_text'] ?? ''));

$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'User not found.');
    redirect('/users/index.php');
}

if ($confirmText !== 'DELETE') {
    flash('danger', 'Type DELETE to confirm user removal.');
    redirect("/users/edit.php?id={$id}#remove-user-section");
}

try {
    deleteUserAccount($id);
    logActivity('user_deleted', "Removed user {$user['username']} ({$user['full_name']})");
    flash('success', "User {$user['username']} removed permanently.");
    redirect('/users/index.php');
} catch (Throwable $e) {
    flash('danger', 'Could not remove user: ' . $e->getMessage());
    redirect("/users/edit.php?id={$id}#remove-user-section");
}
