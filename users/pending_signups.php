<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer_signup.php';
require_once __DIR__ . '/../includes/user_avatar.php';

requireRole('owner');

$pageTitle = 'Pending Portal Signups';
$currentPage = 'users';

$pending = getPendingCustomerSignups();
$signupUrl = APP_URL . '/signup.php';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Pending Portal Signups</h1>
        <p>Review customer self-signup requests before they can access the portal</p>
    </div>
    <div class="header-actions">
        <a href="<?= e($signupUrl) ?>" class="btn btn-outline" target="_blank" rel="noopener">Open Signup Link</a>
        <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline">All Users</a>
    </div>
</div>

<div class="card">
    <div class="info-box signup-info-box">
        <p>Share this signup link with customers: <strong><a href="<?= e($signupUrl) ?>" target="_blank" rel="noopener"><?= e($signupUrl) ?></a></strong></p>
        <p>Each signup is verified against the subscriber account number and phone number, then linked to that customer record upon approval.</p>
    </div>

    <?php if (empty($pending)): ?>
    <p class="text-muted text-center" style="padding: 24px 0;">No pending signup requests.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th></th>
                    <th>Username</th>
                    <th>Subscriber</th>
                    <th>Account #</th>
                    <th>Phone</th>
                    <th>Plan</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $u): ?>
                <tr>
                    <td><?= renderUserAvatar($u, 'sm') ?></td>
                    <td>
                        <strong><?= e($u['username']) ?></strong><br>
                        <small class="text-muted"><?= e($u['email'] ?: 'No email') ?></small>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/customers/view.php?id=<?= (int) $u['customer_id'] ?>">
                            <?= e($u['full_name']) ?>
                        </a>
                    </td>
                    <td><?= e($u['account_number']) ?></td>
                    <td><?= e($u['phone']) ?></td>
                    <td><?= e($u['plan_name']) ?></td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td>
                        <form method="POST" action="<?= APP_URL ?>/users/signup_review.php" class="inline-actions">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Reject this portal signup?');">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
