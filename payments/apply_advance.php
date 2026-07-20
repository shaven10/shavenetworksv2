<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$customerId = (int) ($_POST['customer_id'] ?? 0);

if (!$customerId) {
    flash('danger', 'Customer not found.');
    redirect('/payments/index.php');
}

try {
    $applied = applyCustomerAdvanceToBills($customerId);
    $remaining = getCustomerAdvanceBalance($customerId);

    if ($applied > 0) {
        flash('success', formatMoney($applied) . ' advance credit applied to outstanding bills. Remaining credit: ' . formatMoney($remaining) . '.');
    } else {
        flash('info', 'No advance credit to apply or no outstanding bills.');
    }

    logActivity('advance_applied', "Applied advance credit for customer #{$customerId}: " . formatMoney($applied));
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

$redirect = $_POST['redirect'] ?? '/customers/view.php?id=' . $customerId;
if (!str_starts_with($redirect, '/')) {
    $redirect = '/customers/view.php?id=' . $customerId;
}

redirect($redirect);
