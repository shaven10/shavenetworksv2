<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'My Dashboard';
$currentPage = 'portal';
$customer = getLinkedCustomer();

$db = getDB();
$stmt = $db->prepare("SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ? AND status IN ('open','in_progress')");
$stmt->execute([$customer['id']]);
$openTickets = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE customer_id = ? AND status = 'open'");
$stmt->execute([$customer['id']]);
$openInquiries = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT COUNT(*) FROM bills WHERE customer_id = ? AND status IN ('pending','partial','overdue')"
);
$stmt->execute([$customer['id']]);
$unpaidBills = (int) $stmt->fetchColumn();

$period = getBillingPeriod($customer['installation_date']);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Welcome, <?= e($customer['full_name']) ?></h1>
        <p>Account <?= e($customer['account_number']) ?> · <?= statusBadge($customer['status']) ?></p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card stat-info">
        <div class="stat-value"><?= e($customer['plan_name']) ?></div>
        <div class="stat-label">Current Plan (<?= $customer['speed_mbps'] ?> Mbps)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= formatMoney($customer['monthly_fee']) ?></div>
        <div class="stat-label">Monthly Fee</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-value"><?= $unpaidBills ?></div>
        <div class="stat-label">Unpaid Bills</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-value"><?= $openTickets ?></div>
        <div class="stat-label">Open Repair Tickets</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $openInquiries ?></div>
        <div class="stat-label">Open Inquiries</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="quick-actions">
            <a href="<?= APP_URL ?>/portal/tickets/create.php" class="btn btn-primary">Report Connection Issue</a>
            <a href="<?= APP_URL ?>/portal/inquiries/create.php" class="btn btn-outline">Submit Inquiry</a>
            <a href="<?= APP_URL ?>/portal/account.php" class="btn btn-outline">View My Account</a>
        </div>
    </div>
    <div class="info-card">
        <h3>Billing Cycle</h3>
        <dl>
            <dt>Current Period</dt>
            <dd><?= formatDate($period['start']) ?> — <?= formatDate($period['end']) ?></dd>
            <dt>Due Date</dt>
            <dd><?= formatDate($period['due_date']) ?></dd>
            <dt>Installation Date</dt>
            <dd><?= formatDate($customer['installation_date']) ?></dd>
        </dl>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Recent Repair Tickets</h2>
            <a href="<?= APP_URL ?>/portal/tickets/index.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Ticket #</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $stmt = $db->prepare('SELECT * FROM repair_tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5');
                    $stmt->execute([$customer['id']]);
                    $tickets = $stmt->fetchAll();
                    if (empty($tickets)):
                    ?>
                    <tr><td colspan="3" class="text-muted text-center">No repair tickets yet.</td></tr>
                    <?php else: foreach ($tickets as $t): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/portal/tickets/view.php?id=<?= $t['id'] ?>"><?= e($t['ticket_number']) ?></a></td>
                        <td><?= e($t['subject']) ?></td>
                        <td><?= ticketStatusBadge($t['status']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Recent Inquiries</h2>
            <a href="<?= APP_URL ?>/portal/inquiries/index.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Inquiry #</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $stmt = $db->prepare('SELECT * FROM inquiries WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5');
                    $stmt->execute([$customer['id']]);
                    $inquiries = $stmt->fetchAll();
                    if (empty($inquiries)):
                    ?>
                    <tr><td colspan="3" class="text-muted text-center">No inquiries yet.</td></tr>
                    <?php else: foreach ($inquiries as $inq): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/portal/inquiries/view.php?id=<?= $inq['id'] ?>"><?= e($inq['inquiry_number']) ?></a></td>
                        <td><?= e($inq['subject']) ?></td>
                        <td><?= inquiryStatusBadge($inq['status']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
