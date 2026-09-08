<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        $stmt = getDB()->prepare('SELECT id, username, full_name, email, avatar, role, customer_id FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;

        if (!$user) {
            logout();
        }
    }

    return $user;
}

function attemptLogin(string $username, string $password): array
{
    $username = trim($username);

    $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return [
            'success' => false,
            'message' => 'Invalid username or password.',
        ];
    }

    $approvalStatus = $user['approval_status'] ?? 'approved';

    if ($approvalStatus === 'pending') {
        return [
            'success' => false,
            'message' => 'Your portal account is pending owner approval. You will be able to sign in once approved.',
        ];
    }

    if ($approvalStatus === 'rejected') {
        return [
            'success' => false,
            'message' => 'Your portal signup was not approved. Please contact SHAVEN Networks support.',
        ];
    }

    if (!(int) $user['is_active']) {
        return [
            'success' => false,
            'message' => 'Your account is inactive. Please contact SHAVEN Networks support.',
        ];
    }

    startSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    logActivity('login', 'User logged in');

    return [
        'success' => true,
        'message' => '',
    ];
}

function login(string $username, string $password): bool
{
    return attemptLogin($username, $password)['success'];
}

function logout(): void
{
    startSession();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function requireLogin(): void
{
    startSession();
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function hasRole(string ...$roles): bool
{
    $user = currentUser();
    return $user && in_array($user['role'], $roles, true);
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!hasRole(...$roles)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

function roleLabel(string $role): string
{
    return match ($role) {
        'owner'     => 'Owner',
        'technical' => 'Technical',
        'collector' => 'Collector',
        'customer'  => 'Customer',
        default     => ucfirst($role),
    };
}

function canAccess(string $module): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    $permissions = [
        'owner' => ['dashboard', 'customers', 'plans', 'billing', 'payments', 'remittances', 'users', 'reports', 'settings', 'tickets', 'inquiries', 'announcements', 'ledgers'],
        'technical' => ['dashboard', 'customers', 'plans', 'tickets', 'inquiries', 'announcements'],
        'collector' => ['dashboard', 'customers', 'billing', 'payments', 'remittances', 'inquiries', 'announcements'],
        'customer' => ['portal', 'my_account', 'repair_tickets', 'inquiries', 'announcements'],
    ];

    return in_array($module, $permissions[$user['role']] ?? [], true);
}

function logActivity(string $action, string $details = ''): void
{
    $user = currentUser();
    $stmt = getDB()->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $user['id'] ?? null,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function redirect(string $path): void
{
    header('Location: ' . APP_URL . $path);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney(float $amount): string
{
    return CURRENCY . number_format($amount, 2);
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date('M d, Y', strtotime($date));
}

function generateAccountNumber(): string
{
    $year = date('Y');
    $stmt = getDB()->query("SELECT COUNT(*) FROM customers WHERE account_number LIKE 'SN-{$year}-%'");
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('SN-%s-%04d', $year, $count);
}

function generateBillNumber(): string
{
    return 'BILL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generateInvoiceNumber(): string
{
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generateBatchInvoiceNumber(): string
{
    return 'BINV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generateAdvanceInvoiceNumber(): string
{
    return 'ADV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/remittances.php';
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/database_tools.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/announcements.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/ledgers.php';
