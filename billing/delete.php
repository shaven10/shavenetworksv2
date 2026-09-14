<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';

requireRole('owner');

$returnPath = billingListReturnPath($_POST);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/billing/index.php');
}

$scope = $_POST['scope'] ?? '';
$confirmText = strtoupper(trim($_POST['confirm_text'] ?? ''));
$billId = (int) ($_POST['bill_id'] ?? 0);
$customerId = (int) ($_POST['customer_id'] ?? 0);

if ($confirmText !== 'DELETE') {
    flash('danger', 'Type DELETE to confirm bill removal.');
    redirect($returnPath);
}

try {
    if ($scope === 'bill') {
        $result = deleteBillRecord($billId);
        logActivity(
            'bill_deleted',
            "Deleted bill {$result['bill_number']} for {$result['account_number']} ({$result['customer_name']})"
        );
        flash(
            'success',
            'Deleted bill ' . $result['bill_number'] . ' for ' . $result['customer_name']
            . '. ' . number_format($result['payment_count']) . ' payment(s) removed.'
        );
    } elseif ($scope === 'customer') {
        $result = deleteCustomerBills($customerId);
        logActivity(
            'customer_bills_deleted',
            "Deleted {$result['bill_count']} bill(s) for {$result['account_number']} ({$result['customer_name']})"
        );
        flash(
            'success',
            'Deleted ' . number_format($result['bill_count']) . ' bill(s) for '
            . $result['account_number'] . ' (' . $result['customer_name'] . '). '
            . number_format($result['payment_count']) . ' payment(s) removed.'
        );
    } else {
        flash('danger', 'Invalid billing delete option.');
    }
} catch (Throwable $e) {
    flash('danger', 'Could not delete billing records: ' . $e->getMessage());
}

redirect($returnPath);
