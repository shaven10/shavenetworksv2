<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'technical');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/customers/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$confirmText = strtoupper(trim($_POST['confirm_text'] ?? ''));

$stmt = getDB()->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('/customers/index.php');
}

if ($confirmText !== 'DELETE') {
    flash('danger', 'Type DELETE to confirm account removal.');
    redirect("/customers/view.php?id={$id}");
}

try {
    deleteCustomerAccount($id);
    logActivity('customer_deleted', "Deleted customer {$customer['account_number']} ({$customer['full_name']})");
    flash('success', "Customer {$customer['account_number']} deleted permanently.");
    redirect('/customers/index.php');
} catch (Throwable $e) {
    flash('danger', 'Could not delete customer: ' . $e->getMessage());
    redirect("/customers/view.php?id={$id}");
}
