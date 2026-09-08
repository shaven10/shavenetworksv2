<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');
ensureEmployeeLedgerTables();

$pageTitle = 'Employee Ledgers';
$currentPage = 'ledgers';
$ledgers = getEmployeeLedgers();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Employee Ledgers</h1>
        <p>Salary advances and balance sheets (IN / OUT) for employees</p>
    </div>
    <a href="<?= APP_URL ?>/ledgers/create.php" class="btn btn-primary">+ New Ledger</a>
</div>

<div class="card">
    <?php if (empty($ledgers)): ?>
    <p class="text-center text-muted" style="padding:32px 0">No employee ledgers yet. Create one to start tracking advances and sweldo.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Title</th>
                    <th>Linked Account</th>
                    <th>Visibility</th>
                    <th>Entries</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledgers as $row): ?>
                <tr>
                    <td><strong><?= e($row['employee_name']) ?></strong></td>
                    <td><?= e($row['title']) ?></td>
                    <td>
                        <?php if ($row['user_id']): ?>
                        <?= e($row['account_name'] ?? '') ?>
                        <br><small class="text-muted">@<?= e($row['account_username'] ?? '') ?></small>
                        <?php else: ?>
                        <span class="text-muted">Not linked</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $row['is_visible']): ?>
                        <span class="badge badge-success">Visible</span>
                        <?php else: ?>
                        <span class="badge badge-secondary">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $row['entry_count'] ?></td>
                    <td><?= e(formatDate(substr((string) $row['updated_at'], 0, 10))) ?></td>
                    <td class="table-actions">
                        <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary">Open</a>
                        <a href="<?= APP_URL ?>/ledgers/edit.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline">Settings</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
