<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer_signup.php';

requireRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/pending_signups.php');
}

$userId = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

try {
    if ($action === 'approve') {
        approveCustomerSignup($userId);
        flash('success', 'Portal signup approved. The customer can now sign in.');
    } elseif ($action === 'reject') {
        rejectCustomerSignup($userId);
        flash('success', 'Portal signup rejected.');
    } else {
        flash('danger', 'Invalid action.');
    }
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect('/users/pending_signups.php');
