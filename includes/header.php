<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user_avatar.php';
$user = currentUser();
$flash = getFlash();
$isCustomer = hasRole('customer');
$notifications = getHeaderNotifications();
?>
<!DOCTYPE html>
<html lang="en" <?= renderThemeAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style id="app-theme"><?= renderThemeStyles() ?></style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <div class="app-layout">
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">SN</div>
                <div>
                    <strong>SHAVEN Networks</strong>
                    <small><?= $isCustomer ? 'Customer Portal' : 'ISP Billing System' ?></small>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php if ($isCustomer): ?>
                <a href="<?= APP_URL ?>/portal/index.php" class="nav-link <?= ($currentPage ?? '') === 'portal' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> My Dashboard
                </a>
                <a href="<?= APP_URL ?>/portal/account.php" class="nav-link <?= ($currentPage ?? '') === 'my_account' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span> My Account
                </a>
                <a href="<?= APP_URL ?>/profile.php" class="nav-link <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                    <span class="nav-icon">🖼</span> Profile Photo
                </a>
                <a href="<?= APP_URL ?>/portal/tickets/index.php" class="nav-link <?= ($currentPage ?? '') === 'repair_tickets' ? 'active' : '' ?>">
                    <span class="nav-icon">🔧</span> Repair Tickets
                </a>
                <a href="<?= APP_URL ?>/portal/inquiries/index.php" class="nav-link <?= ($currentPage ?? '') === 'inquiries' ? 'active' : '' ?>">
                    <span class="nav-icon">💬</span> Inquiries
                </a>
                <a href="<?= APP_URL ?>/announcements/index.php" class="nav-link <?= ($currentPage ?? '') === 'announcements' ? 'active' : '' ?>">
                    <span class="nav-icon">📢</span> Announcements
                </a>
                <?php else: ?>
                <?php if (canAccess('dashboard')): ?>
                <a href="<?= APP_URL ?>/index.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <?php endif; ?>
                <?php if (canAccess('customers')): ?>
                <a href="<?= APP_URL ?>/customers/index.php" class="nav-link <?= ($currentPage ?? '') === 'customers' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> Customers
                </a>
                <?php endif; ?>
                <?php if (canAccess('plans')): ?>
                <a href="<?= APP_URL ?>/plans/index.php" class="nav-link <?= ($currentPage ?? '') === 'plans' ? 'active' : '' ?>">
                    <span class="nav-icon">📡</span> Service Plans
                </a>
                <?php endif; ?>
                <?php if (canAccess('billing')): ?>
                <a href="<?= APP_URL ?>/billing/index.php" class="nav-link <?= ($currentPage ?? '') === 'billing' ? 'active' : '' ?>">
                    <span class="nav-icon">📄</span> Billing
                </a>
                <?php endif; ?>
                <?php if (canAccess('payments')): ?>
                <a href="<?= APP_URL ?>/payments/index.php" class="nav-link <?= ($currentPage ?? '') === 'payments' ? 'active' : '' ?>">
                    <span class="nav-icon">💰</span> Payments
                </a>
                <?php endif; ?>
                <?php if (canAccess('remittances')): ?>
                <a href="<?= APP_URL ?>/remittances/index.php" class="nav-link <?= ($currentPage ?? '') === 'remittances' ? 'active' : '' ?>">
                    <span class="nav-icon">🏦</span> Remittances
                </a>
                <?php endif; ?>
                <?php if (canAccess('tickets')): ?>
                <a href="<?= APP_URL ?>/support/tickets/index.php" class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">
                    <span class="nav-icon">🔧</span> Repair Tickets
                </a>
                <?php endif; ?>
                <?php if (canAccess('inquiries')): ?>
                <a href="<?= APP_URL ?>/support/inquiries/index.php" class="nav-link <?= ($currentPage ?? '') === 'staff_inquiries' ? 'active' : '' ?>">
                    <span class="nav-icon">💬</span> Inquiries
                </a>
                <?php endif; ?>
                <?php if (canAccess('announcements')): ?>
                <a href="<?= APP_URL ?>/announcements/index.php" class="nav-link <?= ($currentPage ?? '') === 'announcements' ? 'active' : '' ?>">
                    <span class="nav-icon">📢</span> Announcements
                </a>
                <?php endif; ?>
                <?php if (canAccess('reports')): ?>
                <a href="<?= APP_URL ?>/reports/index.php" class="nav-link <?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>">
                    <span class="nav-icon">📈</span> Reports
                </a>
                <?php endif; ?>
                <?php if (!$isCustomer): ?>
                <a href="<?= APP_URL ?>/profile.php" class="nav-link <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span> My Profile
                </a>
                <?php endif; ?>
                <?php if (canAccess('settings')): ?>
                <a href="<?= APP_URL ?>/settings/theme.php" class="nav-link <?= ($currentPage ?? '') === 'theme_manager' ? 'active' : '' ?>">
                    <span class="nav-icon">🎨</span> Theme Manager
                </a>
                <a href="<?= APP_URL ?>/settings/database.php" class="nav-link <?= ($currentPage ?? '') === 'database_tools' ? 'active' : '' ?>">
                    <span class="nav-icon">🗄</span> Database Tools
                </a>
                <?php endif; ?>
                <?php if (canAccess('users')): ?>
                <a href="<?= APP_URL ?>/users/index.php" class="nav-link <?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>">
                    <span class="nav-icon">🔐</span> Users
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
            <div class="main-topbar">
                <div class="topbar-left">
                    <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="app-sidebar">
                        <span class="sidebar-toggle-bar"></span>
                        <span class="sidebar-toggle-bar"></span>
                        <span class="sidebar-toggle-bar"></span>
                    </button>
                    <div class="topbar-title">
                        <span class="topbar-app"><?= e(APP_NAME) ?></span>
                        <?php if (!empty($pageTitle)): ?>
                        <span class="topbar-page"><?= e($pageTitle) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="topbar-actions">
                <div class="notification-wrapper">
                    <button type="button"
                            class="notification-trigger <?= $notifications['total'] > 0 ? 'has-unread' : '' ?>"
                            id="notification-toggle"
                            aria-expanded="false"
                            aria-controls="notification-panel"
                            aria-label="Notifications<?= $notifications['total'] > 0 ? ', ' . $notifications['total'] . ' unread' : '' ?>">
                        <svg class="notification-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                            <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z"/>
                        </svg>
                        <span class="notification-badge <?= $notifications['total'] > 0 ? '' : 'is-hidden' ?>"
                              id="notification-badge"
                              aria-live="polite"><?= $notifications['total'] > 99 ? '99+' : $notifications['total'] ?></span>
                    </button>
                    <div class="notification-panel" id="notification-panel" hidden>
                        <div class="notification-panel-header">
                            <div class="notification-panel-title">
                                <h3>Notifications</h3>
                                <span class="notification-count <?= $notifications['total'] > 0 ? '' : 'is-hidden' ?>"
                                      id="notification-count"><?= $notifications['total'] ?> unread</span>
                            </div>
                            <button type="button"
                                    class="notification-mark-all <?= $notifications['total'] > 0 ? '' : 'is-hidden' ?>"
                                    id="notification-mark-all">Mark all read</button>
                        </div>
                        <div class="notification-panel-body" id="notification-panel-body">
                            <?= renderNotificationGroupsHtml($notifications['groups']) ?>
                        </div>
                        <?php if (!$isCustomer && canAccess('dashboard')): ?>
                        <div class="notification-panel-footer">
                            <a href="<?= APP_URL ?>/index.php">Go to Dashboard</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="topbar-user">
                    <a href="<?= APP_URL ?>/profile.php" class="topbar-user-link" title="My Profile">
                        <?= renderUserAvatar($user, 'md') ?>
                        <div class="topbar-user-info">
                            <strong><?= e($user['full_name']) ?></strong>
                            <span class="role-tag role-<?= e($user['role']) ?>"><?= roleLabel($user['role']) ?></span>
                        </div>
                    </a>
                    <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline btn-sm topbar-logout">Logout</a>
                </div>
                </div>
            </div>
            <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>
            <?php endif; ?>
