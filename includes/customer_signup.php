<?php

function normalizePhoneNumber(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function findCustomerForSignup(string $accountNumber, string $phone): ?array
{
    $accountNumber = trim($accountNumber);
    $phoneDigits = normalizePhoneNumber($phone);

    if ($accountNumber === '' || $phoneDigits === '') {
        return null;
    }

    $stmt = getDB()->prepare(
        'SELECT c.*, p.name AS plan_name
         FROM customers c
         JOIN service_plans p ON c.plan_id = p.id
         WHERE c.account_number = ? AND c.status != "disconnected"'
    );
    $stmt->execute([$accountNumber]);
    $customer = $stmt->fetch();

    if (!$customer) {
        return null;
    }

    if (normalizePhoneNumber($customer['phone']) !== $phoneDigits) {
        return null;
    }

    return $customer;
}

function customerHasPortalSignup(int $customerId): bool
{
    $stmt = getDB()->prepare(
        'SELECT COUNT(*) FROM users
         WHERE customer_id = ? AND role = "customer"
           AND approval_status IN ("approved", "pending")'
    );
    $stmt->execute([$customerId]);

    return (int) $stmt->fetchColumn() > 0;
}

function registerCustomerSignup(
    string $accountNumber,
    string $phone,
    string $username,
    string $password,
    ?string $email = null
): array {
    $username = trim($username);
    $email = trim($email ?? '') ?: null;

    if (strlen($password) < 6) {
        throw new RuntimeException('Password must be at least 6 characters.');
    }

    $customer = findCustomerForSignup($accountNumber, $phone);
    if (!$customer) {
        throw new RuntimeException('Subscriber account not found or phone number does not match our records.');
    }

    if (customerHasPortalSignup((int) $customer['id'])) {
        throw new RuntimeException('This subscriber account already has a portal signup or active login.');
    }

    $stmt = getDB()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('Username is already taken. Choose a different username.');
    }

    $stmt = getDB()->prepare(
        'INSERT INTO users (username, password_hash, full_name, email, role, customer_id, is_active, approval_status)
         VALUES (?, ?, ?, ?, "customer", ?, 0, "pending")'
    );
    $stmt->execute([
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $customer['full_name'],
        $email ?: ($customer['email'] ?: null),
        (int) $customer['id'],
    ]);

    $userId = (int) getDB()->lastInsertId();

    logActivity('customer_signup_pending', "Portal signup pending for {$customer['account_number']} (user {$username})");

    return [
        'user_id'        => $userId,
        'username'       => $username,
        'customer_id'    => (int) $customer['id'],
        'account_number' => $customer['account_number'],
        'full_name'      => $customer['full_name'],
    ];
}

function getPendingCustomerSignupCount(): int
{
    return (int) getDB()->query(
        'SELECT COUNT(*) FROM users WHERE role = "customer" AND approval_status = "pending"'
    )->fetchColumn();
}

function getPendingCustomerSignups(): array
{
    $stmt = getDB()->query(
        'SELECT u.*, c.account_number, c.phone, c.status AS customer_status, c.city, c.province,
                p.name AS plan_name
         FROM users u
         JOIN customers c ON u.customer_id = c.id
         JOIN service_plans p ON c.plan_id = p.id
         WHERE u.role = "customer" AND u.approval_status = "pending"
         ORDER BY u.created_at ASC'
    );

    return $stmt->fetchAll();
}

function approveCustomerSignup(int $userId): void
{
    $stmt = getDB()->prepare(
        'SELECT u.*, c.account_number, c.full_name
         FROM users u
         LEFT JOIN customers c ON u.customer_id = c.id
         WHERE u.id = ? AND u.role = "customer" AND u.approval_status = "pending"'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Pending signup not found.');
    }

    if (empty($user['customer_id'])) {
        throw new RuntimeException('Signup is not linked to a subscriber record.');
    }

    $stmt = getDB()->prepare(
        'SELECT COUNT(*) FROM users
         WHERE customer_id = ? AND role = "customer" AND approval_status = "approved" AND id != ?'
    );
    $stmt->execute([(int) $user['customer_id'], $userId]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('This subscriber already has an approved portal account.');
    }

    $stmt = getDB()->prepare(
        'UPDATE users SET is_active = 1, approval_status = "approved" WHERE id = ?'
    );
    $stmt->execute([$userId]);

    logActivity(
        'customer_signup_approved',
        "Approved portal signup for {$user['account_number']} ({$user['username']})"
    );
}

function rejectCustomerSignup(int $userId): void
{
    $stmt = getDB()->prepare(
        'SELECT u.*, c.account_number FROM users u
         LEFT JOIN customers c ON u.customer_id = c.id
         WHERE u.id = ? AND u.role = "customer" AND u.approval_status = "pending"'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Pending signup not found.');
    }

    $stmt = getDB()->prepare(
        'UPDATE users SET is_active = 0, approval_status = "rejected" WHERE id = ?'
    );
    $stmt->execute([$userId]);

    logActivity(
        'customer_signup_rejected',
        "Rejected portal signup for {$user['account_number']} ({$user['username']})"
    );
}

function approvalStatusBadge(?string $status): string
{
    return match ($status) {
        'pending'  => '<span class="badge badge-warning">Pending Approval</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-success">Approved</span>',
    };
}
