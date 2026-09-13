<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user_avatar.php';
$user = currentUser();
$flash = getFlash();
$isCustomer = hasRole('customer');
$notifications = getHeaderNotifications();
if (!$isCustomer && function_exists('maybeAutoSendDuePaymentSms')) {
    maybeAutoSendDuePaymentSms();
}
?>
<!DOCTYPE html>
<html lang="en" <?= renderThemeAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= e(APP_NAME) ?></title>
    <?= renderFontLinks() ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style id="app-theme"><?= renderThemeStyles() ?></style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <div class="app-layout">
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">SN</div>
                <div class="sidebar-brand-text">
                    <strong>SHAVEN Networks</strong>
                    <small><?= $isCustomer ? 'Customer Portal' : 'ISP Billing System' ?></small>
                </div>
                <button type="button" class="sidebar-collapse-btn" id="sidebar-collapse-btn"
                        aria-label="Collapse sidebar" title="Collapse menu">
                    <svg class="sidebar-collapse-icon sidebar-collapse-icon-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                    <svg class="sidebar-collapse-icon sidebar-collapse-icon-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M8.59 16.59 10 18l6-6-6-6-1.41 1.41L13.17 12z"/>
                    </svg>
                </button>
            </div>
            <nav class="sidebar-nav" id="sidebar-nav">
                <div class="nav-toolbar">
                    <span class="nav-toolbar-label">Menu</span>
                    <div class="nav-toolbar-actions">
                        <button type="button" class="nav-bulk-btn" id="nav-expand-all"
                                title="Expand all sections" aria-label="Expand all menu sections">
                            <svg class="nav-bulk-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                <path fill="currentColor" d="M7.41 3.41 12 7.99l4.59-4.58L18 4.82l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                            <span class="nav-bulk-text">Expand</span>
                        </button>
                        <button type="button" class="nav-bulk-btn" id="nav-collapse-all"
                                title="Collapse all sections" aria-label="Collapse all menu sections">
                            <svg class="nav-bulk-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M7.41 15.41 12 10.83l4.59 4.58L18 14l-6-6-6 6 1.41 1.41z"/>
                                <path fill="currentColor" d="M7.41 20.59 12 16.01l4.59 4.58L18 19.18l-6-6-6 6 1.41 1.41z"/>
                            </svg>
                            <span class="nav-bulk-text">Collapse</span>
                        </button>
                    </div>
                </div>
                <?php if ($isCustomer): ?>
                <div class="nav-group" data-nav-group="account">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">👤</span>
                            <span class="nav-group-title">Account</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/portal/index.php" class="nav-link <?= ($currentPage ?? '') === 'portal' ? 'active' : '' ?>">
                        <span class="nav-icon">🏠</span> <span class="nav-link-text">My Dashboard</span>
                    </a>
                    <a href="<?= APP_URL ?>/portal/account.php" class="nav-link <?= ($currentPage ?? '') === 'my_account' ? 'active' : '' ?>">
                        <span class="nav-icon">👤</span> <span class="nav-link-text">My Account</span>
                    </a>
                    <?php if (userHasVisibleLedger()): ?>
                    <a href="<?= APP_URL ?>/ledgers/my.php" class="nav-link <?= ($currentPage ?? '') === 'my_ledger' ? 'active' : '' ?>">
                        <span class="nav-icon">📒</span> <span class="nav-link-text">My Ledger</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/profile.php" class="nav-link <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                        <span class="nav-icon">🖼</span> <span class="nav-link-text">Profile Photo</span>
                    </a>
                    </div>
                </div>
                <div class="nav-group" data-nav-group="support">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">🛟</span>
                            <span class="nav-group-title">Support</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/portal/tickets/index.php" class="nav-link <?= ($currentPage ?? '') === 'repair_tickets' ? 'active' : '' ?>">
                        <span class="nav-icon">🔧</span> <span class="nav-link-text">Repair Tickets</span>
                    </a>
                    <a href="<?= APP_URL ?>/portal/inquiries/index.php" class="nav-link <?= ($currentPage ?? '') === 'inquiries' ? 'active' : '' ?>">
                        <span class="nav-icon">💬</span> <span class="nav-link-text">Inquiries</span>
                    </a>
                    </div>
                </div>
                <div class="nav-group" data-nav-group="updates">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">📢</span>
                            <span class="nav-group-title">Updates</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/announcements/index.php" class="nav-link <?= ($currentPage ?? '') === 'announcements' ? 'active' : '' ?>">
                        <span class="nav-icon">📢</span> <span class="nav-link-text">Announcements</span>
                    </a>
                    </div>
                </div>
                <?php else: ?>
                <?php if (canAccess('dashboard')): ?>
                <div class="nav-group" data-nav-group="overview">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">🏠</span>
                            <span class="nav-group-title">Overview</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/index.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <span class="nav-icon">📊</span> <span class="nav-link-text">Dashboard</span>
                    </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (canAccess('customers') || canAccess('plans')): ?>
                <div class="nav-group" data-nav-group="subscribers">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">👥</span>
                            <span class="nav-group-title">Subscribers</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <?php if (canAccess('customers')): ?>
                    <a href="<?= APP_URL ?>/customers/index.php" class="nav-link <?= ($currentPage ?? '') === 'customers' ? 'active' : '' ?>">
                        <span class="nav-icon">👥</span> <span class="nav-link-text">Customers</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('plans')): ?>
                    <a href="<?= APP_URL ?>/plans/index.php" class="nav-link <?= ($currentPage ?? '') === 'plans' ? 'active' : '' ?>">
                        <span class="nav-icon">📡</span> <span class="nav-link-text">Service Plans</span>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (canAccess('billing') || canAccess('payments') || canAccess('remittances')): ?>
                <div class="nav-group" data-nav-group="finance">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">💰</span>
                            <span class="nav-group-title">Finance</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <?php if (canAccess('billing')): ?>
                    <a href="<?= APP_URL ?>/billing/index.php" class="nav-link <?= ($currentPage ?? '') === 'billing' ? 'active' : '' ?>">
                        <span class="nav-icon">📄</span> <span class="nav-link-text">Billing</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('payments')): ?>
                    <a href="<?= APP_URL ?>/payments/index.php" class="nav-link <?= ($currentPage ?? '') === 'payments' ? 'active' : '' ?>">
                        <span class="nav-icon">💰</span> <span class="nav-link-text">Payments</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('remittances')): ?>
                    <a href="<?= APP_URL ?>/remittances/index.php" class="nav-link <?= ($currentPage ?? '') === 'remittances' ? 'active' : '' ?>">
                        <span class="nav-icon">🏦</span> <span class="nav-link-text">Remittances</span>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (canAccess('tickets') || canAccess('inquiries') || canAccess('announcements')): ?>
                <div class="nav-group" data-nav-group="support">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">🛟</span>
                            <span class="nav-group-title">Support</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <?php if (canAccess('tickets')): ?>
                    <a href="<?= APP_URL ?>/support/tickets/index.php" class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">
                        <span class="nav-icon">🔧</span> <span class="nav-link-text">Repair Tickets</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('inquiries')): ?>
                    <a href="<?= APP_URL ?>/support/inquiries/index.php" class="nav-link <?= ($currentPage ?? '') === 'staff_inquiries' ? 'active' : '' ?>">
                        <span class="nav-icon">💬</span> <span class="nav-link-text">Inquiries</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('announcements')): ?>
                    <a href="<?= APP_URL ?>/announcements/index.php" class="nav-link <?= ($currentPage ?? '') === 'announcements' ? 'active' : '' ?>">
                        <span class="nav-icon">📢</span> <span class="nav-link-text">Announcements</span>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (canAccess('reports')): ?>
                <div class="nav-group" data-nav-group="insights">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">📈</span>
                            <span class="nav-group-title">Insights</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/reports/index.php" class="nav-link <?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>">
                        <span class="nav-icon">📈</span> <span class="nav-link-text">Reports</span>
                    </a>
                    </div>
                </div>
                <?php endif; ?>
                <div class="nav-group" data-nav-group="account">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">👤</span>
                            <span class="nav-group-title">Account</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <a href="<?= APP_URL ?>/profile.php" class="nav-link <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                        <span class="nav-icon">👤</span> <span class="nav-link-text">My Profile</span>
                    </a>
                    <?php if (userHasVisibleLedger()): ?>
                    <a href="<?= APP_URL ?>/ledgers/my.php" class="nav-link <?= ($currentPage ?? '') === 'my_ledger' ? 'active' : '' ?>">
                        <span class="nav-icon">📒</span> <span class="nav-link-text">My Ledger</span>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
                <?php if (canAccess('settings') || canAccess('users') || canAccess('ledgers')): ?>
                <div class="nav-group" data-nav-group="system">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-heading">
                            <span class="nav-group-icon" aria-hidden="true">⚙️</span>
                            <span class="nav-group-title">System</span>
                        </span>
                        <span class="nav-chevron" aria-hidden="true">
                            <svg viewBox="0 0 20 20" width="12" height="12" focusable="false">
                                <path fill="currentColor" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/>
                            </svg>
                        </span>
                    </button>
                    <div class="nav-group-links">
                    <?php if (canAccess('ledgers')): ?>
                    <a href="<?= APP_URL ?>/ledgers/index.php" class="nav-link <?= ($currentPage ?? '') === 'ledgers' ? 'active' : '' ?>">
                        <span class="nav-icon">📒</span> <span class="nav-link-text">Employee Ledgers</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('settings')): ?>
                    <a href="<?= APP_URL ?>/settings/api.php" class="nav-link <?= ($currentPage ?? '') === 'api_settings' ? 'active' : '' ?>">
                        <span class="nav-icon">📱</span> <span class="nav-link-text">API Settings</span>
                    </a>
                    <a href="<?= APP_URL ?>/settings/theme.php" class="nav-link <?= ($currentPage ?? '') === 'theme_manager' ? 'active' : '' ?>">
                        <span class="nav-icon">🎨</span> <span class="nav-link-text">Theme Manager</span>
                    </a>
                    <a href="<?= APP_URL ?>/settings/database.php" class="nav-link <?= ($currentPage ?? '') === 'database_tools' ? 'active' : '' ?>">
                        <span class="nav-icon">🗄</span> <span class="nav-link-text">Database Tools</span>
                    </a>
                    <?php endif; ?>
                    <?php if (canAccess('users')): ?>
                    <a href="<?= APP_URL ?>/users/index.php" class="nav-link <?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>">
                        <span class="nav-icon">🔐</span> <span class="nav-link-text">Users</span>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
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
