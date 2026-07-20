<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_avatar.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/customer_signup.php';

requireRole('owner');



$pageTitle = 'Users';

$currentPage = 'users';



$search = trim($_GET['search'] ?? '');

$role = $_GET['role'] ?? '';

$active = $_GET['is_active'] ?? '';
$approval = $_GET['approval_status'] ?? '';

$listPage = getListPage();

$perPage = getListPerPage();



$fromWhere = 'FROM users WHERE 1=1';

$params = [];



if ($search) {

    $fromWhere .= ' AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)';

    $params[] = "%{$search}%";

    $params[] = "%{$search}%";

    $params[] = "%{$search}%";

}

if ($role) {

    $fromWhere .= ' AND role = ?';

    $params[] = $role;

}

if ($active !== '') {

    $fromWhere .= ' AND is_active = ?';

    $params[] = (int) $active;

}

if ($approval) {

    $fromWhere .= ' AND approval_status = ?';

    $params[] = $approval;

}



$result = paginatedSelect(
    'SELECT id, username, full_name, email, avatar, role, is_active, approval_status, customer_id, created_at',

    $fromWhere,

    $params,

    'ORDER BY role, full_name',

    $listPage,

    $perPage

);

$users = $result['data'];



$filterParams = paginationQuery([

    'search'    => $search,

    'role'      => $role,

    'is_active' => $active,

    'approval_status' => $approval,

    'per_page'  => $perPage,

]);

$pendingSignupCount = getPendingCustomerSignupCount();



require __DIR__ . '/../includes/header.php';

?>



<div class="page-header">

    <div>

        <h1>User Management</h1>

        <p>Manage system users and role assignments</p>

    </div>

    <div class="header-actions">
        <?php if ($pendingSignupCount > 0): ?>
        <a href="<?= APP_URL ?>/users/pending_signups.php" class="btn btn-warning">
            Pending Signups (<?= $pendingSignupCount ?>)
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/signup.php" class="btn btn-outline" target="_blank" rel="noopener">Signup Link</a>
        <a href="<?= APP_URL ?>/users/create.php" class="btn btn-primary">+ Add User</a>
    </div>

</div>



<div class="card">

    <form method="GET" class="filter-bar">

        <input type="text" name="search" placeholder="Search username, name, email..." value="<?= e($search) ?>">

        <select name="role">

            <option value="">All Roles</option>

            <option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Owner</option>

            <option value="technical" <?= $role === 'technical' ? 'selected' : '' ?>>Technical</option>

            <option value="collector" <?= $role === 'collector' ? 'selected' : '' ?>>Collector</option>
            <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>

        </select>

        <select name="is_active">

            <option value="">All Status</option>

            <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option>

            <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option>

        </select>

        <select name="approval_status">
            <option value="">All Approval</option>
            <option value="pending" <?= $approval === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $approval === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $approval === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>

        <select name="per_page">

            <?php foreach (perPageOptions() as $option): ?>

            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> / page</option>

            <?php endforeach; ?>

        </select>

        <button type="submit" class="btn btn-outline">Filter</button>

        <?php if ($search || $role || $active !== '' || $approval): ?>

        <a href="<?= APP_URL ?>/users/index.php" class="btn btn-outline">Clear</a>

        <?php endif; ?>

    </form>



    <?= renderPagination($result, $filterParams) ?>



    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>
                    <th></th>
                    <th>Username</th>

                    <th>Full Name</th>

                    <th>Email</th>

                    <th>Role</th>

                    <th>Status</th>

                    <th>Approval</th>

                    <th>Created</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($users)): ?>

                <tr><td colspan="9" class="text-center text-muted">No users found.</td></tr>

                <?php else: foreach ($users as $u): ?>

                <tr>
                    <td><?= renderUserAvatar($u, 'sm') ?></td>
                    <td><strong><?= e($u['username']) ?></strong></td>

                    <td><?= e($u['full_name']) ?></td>

                    <td><?= e($u['email'] ?: '—') ?></td>

                    <td><span class="role-tag role-<?= e($u['role']) ?>"><?= roleLabel($u['role']) ?></span></td>

                    <td><?= $u['is_active'] ? statusBadge('active') : '<span class="badge badge-secondary">Inactive</span>' ?></td>

                    <td><?= approvalStatusBadge($u['approval_status'] ?? 'approved') ?></td>

                    <td><?= formatDate($u['created_at']) ?></td>

                    <td>
                        <a href="<?= APP_URL ?>/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <?php if (getUserDeleteStatus((int) $u['id'])['allowed']): ?>
                        <a href="<?= APP_URL ?>/users/edit.php?id=<?= $u['id'] ?>#remove-user-section" class="btn btn-sm btn-danger">Remove</a>
                        <?php endif; ?>
                    </td>

                </tr>

                <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>



    <?= renderPagination($result, $filterParams) ?>

</div>



<div class="card">

    <h3>Role Permissions</h3>

    <div class="permissions-grid">

        <div>

            <h4><span class="role-tag role-owner">Owner</span></h4>

            <ul>

                <li>Full system access</li>

                <li>Manage users, plans, customers</li>

                <li>Generate bills & view reports</li>

                <li>Record payments</li>

            </ul>

        </div>

        <div>

            <h4><span class="role-tag role-technical">Technical</span></h4>

            <ul>

                <li>View dashboard</li>

                <li>Add/edit customers & installations</li>

                <li>View service plans</li>

                <li>Update customer status</li>

            </ul>

        </div>

        <div>

            <h4><span class="role-tag role-collector">Collector</span></h4>

            <ul>

                <li>View dashboard & customers</li>

                <li>View billing statements</li>

                <li>Record customer payments</li>

                <li>View collection history</li>

            </ul>

        </div>

    </div>

</div>



<?php require __DIR__ . '/../includes/footer.php'; ?>

