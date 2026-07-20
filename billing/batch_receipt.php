<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'collector');

$batchId = (int) ($_GET['id'] ?? $_SESSION['batch_payment_result']['batch_id'] ?? 0);
unset($_SESSION['batch_payment_result']);

if (!$batchId) {
    flash('info', 'No batch payment to display.');
    redirect('/billing/collect.php');
}

redirect('/billing/batch_invoice.php?id=' . $batchId);
