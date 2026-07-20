<?php

function connectionMediumOptions(): array
{
    return [
        'fiber_olt'      => 'Fiber Optic (OLT)',
        'fiber_mediacon' => 'Fiber Optic (Mediacon)',
        'wireless_radio' => 'Wireless Radio',
    ];
}

function connectionMediumLabel(?string $medium): string
{
    if (!$medium) {
        return '—';
    }

    return connectionMediumOptions()[$medium] ?? ucfirst(str_replace('_', ' ', $medium));
}

function connectionMediumBadge(?string $medium): string
{
    if (!$medium) {
        return '<span class="badge badge-secondary">—</span>';
    }

    $classes = [
        'fiber_olt'      => 'badge-info',
        'fiber_mediacon' => 'badge-success',
        'wireless_radio' => 'badge-warning',
    ];
    $class = $classes[$medium] ?? 'badge-secondary';

    return '<span class="badge ' . $class . '">' . e(connectionMediumLabel($medium)) . '</span>';
}

function formatCustomerAddress(array $customer): string
{
    $parts = array_filter([
        trim($customer['address'] ?? ''),
        trim($customer['barangay'] ?? '') !== '' ? 'Brgy. ' . trim($customer['barangay']) : '',
        trim($customer['city'] ?? ''),
        trim($customer['province'] ?? ''),
    ]);

    return $parts ? implode(', ', $parts) : '—';
}

function validateCustomerAddressFields(array $data): array
{
    $errors = [];

    if (trim($data['address'] ?? '') === '') {
        $errors[] = 'Street / building address is required.';
    }
    if (trim($data['city'] ?? '') === '') {
        $errors[] = 'City / municipality is required.';
    }
    if (trim($data['province'] ?? '') === '') {
        $errors[] = 'Province is required.';
    }

    $medium = $data['connection_medium'] ?? '';
    if (!array_key_exists($medium, connectionMediumOptions())) {
        $errors[] = 'Medium of connection is required.';
    }

    return $errors;
}

function normalizeCustomerFormData(array $post): array
{
    return [
        'full_name'          => trim($post['full_name'] ?? ''),
        'email'              => trim($post['email'] ?? ''),
        'phone'              => trim($post['phone'] ?? ''),
        'connection_medium'  => trim($post['connection_medium'] ?? ''),
        'address'            => trim($post['address'] ?? ''),
        'barangay'           => trim($post['barangay'] ?? ''),
        'city'               => trim($post['city'] ?? ''),
        'province'           => trim($post['province'] ?? ''),
        'plan_id'            => (int) ($post['plan_id'] ?? 0),
        'installation_date'  => $post['installation_date'] ?? '',
        'status'             => $post['status'] ?? 'active',
        'notes'              => trim($post['notes'] ?? ''),
    ];
}

function deleteCustomerAccount(int $customerId): void
{
    $db = getDB();

    $db->beginTransaction();

    try {
        $db->prepare('DELETE FROM payments WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('DELETE FROM bills WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('UPDATE users SET customer_id = NULL WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('DELETE FROM customers WHERE id = ?')->execute([$customerId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function getCustomerDeleteSummary(int $customerId): array
{
    $db = getDB();

    $billStmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE customer_id = ?');
    $billStmt->execute([$customerId]);
    $bills = (int) $billStmt->fetchColumn();

    $payStmt = $db->prepare('SELECT COUNT(*) FROM payments WHERE customer_id = ?');
    $payStmt->execute([$customerId]);
    $payments = (int) $payStmt->fetchColumn();

    $ticketStmt = $db->prepare('SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ?');
    $ticketStmt->execute([$customerId]);
    $tickets = (int) $ticketStmt->fetchColumn();

    $inquiryStmt = $db->prepare('SELECT COUNT(*) FROM inquiries WHERE customer_id = ?');
    $inquiryStmt->execute([$customerId]);
    $inquiries = (int) $inquiryStmt->fetchColumn();

    return [
        'bills'     => $bills,
        'payments'  => $payments,
        'tickets'   => $tickets,
        'inquiries' => $inquiries,
    ];
}

function renderCustomerDeleteSection(array $customer): void
{
    if (!hasRole('owner', 'technical')) {
        return;
    }

    $summary = getCustomerDeleteSummary((int) $customer['id']);
    $customerId = (int) $customer['id'];
    ?>
<div class="card danger-zone">
    <div class="card-header"><h2>Delete Account</h2></div>
    <p class="danger-intro">
        Permanently remove <strong><?= e($customer['account_number']) ?></strong> — <?= e($customer['full_name']) ?>.
        This will also delete related billing and support records.
    </p>
    <ul class="delete-summary">
        <li><?= number_format($summary['bills']) ?> bill(s)</li>
        <li><?= number_format($summary['payments']) ?> payment(s)</li>
        <li><?= number_format($summary['tickets']) ?> repair ticket(s)</li>
        <li><?= number_format($summary['inquiries']) ?> inquiry/inquiries</li>
    </ul>
    <form method="POST" action="<?= APP_URL ?>/customers/delete.php" class="delete-account-form"
          onsubmit="return confirm(<?= json_encode('Permanently delete ' . $customer['account_number'] . '?') ?>)">
        <input type="hidden" name="id" value="<?= $customerId ?>">
        <div class="form-group">
            <label for="delete_confirm_<?= $customerId ?>">Type DELETE to confirm *</label>
            <input type="text" id="delete_confirm_<?= $customerId ?>" name="confirm_text"
                   placeholder="DELETE" autocomplete="off" required>
        </div>
        <button type="submit" class="btn btn-danger">Delete Account</button>
    </form>
</div>
    <?php
}
