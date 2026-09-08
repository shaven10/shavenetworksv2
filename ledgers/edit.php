<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');
ensureEmployeeLedgerTables();

$id = (int) ($_GET['id'] ?? 0);
$ledger = getEmployeeLedger($id);
if (!$ledger) {
    flash('danger', 'Ledger not found.');
    redirect('/ledgers/index.php');
}

$pageTitle = 'Ledger Settings';
$currentPage = 'ledgers';
$errors = [];
$users = getLinkableLedgerUsers();

$data = [
    'employee_name' => $ledger['employee_name'],
    'title'         => $ledger['title'],
    'user_id'       => $ledger['user_id'] ?? '',
    'is_visible'    => (int) $ledger['is_visible'] === 1,
    'in_label'      => $ledger['in_label'],
    'out_label'     => $ledger['out_label'],
    'balance_as_of' => $ledger['balance_as_of'] ?? '',
    'notes'         => $ledger['notes'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        deleteEmployeeLedger($id);
        logActivity('ledger_deleted', 'Deleted employee ledger #' . $id);
        flash('success', 'Employee ledger deleted.');
        redirect('/ledgers/index.php');
    }

    $data = [
        'employee_name' => trim($_POST['employee_name'] ?? ''),
        'title'         => trim($_POST['title'] ?? ''),
        'user_id'       => $_POST['user_id'] ?? '',
        'is_visible'    => !empty($_POST['is_visible']),
        'in_label'      => trim($_POST['in_label'] ?? 'Hatagon Beben'),
        'out_label'     => trim($_POST['out_label'] ?? ''),
        'balance_as_of' => trim($_POST['balance_as_of'] ?? ''),
        'notes'         => trim($_POST['notes'] ?? ''),
    ];

    try {
        updateEmployeeLedger($id, $data);
        logActivity('ledger_updated', 'Updated employee ledger #' . $id);
        flash('success', 'Ledger settings saved.');
        redirect('/ledgers/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Ledger Settings</h1>
        <p><?= e($ledger['title']) ?></p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= $id ?>" class="btn btn-outline">Open Ledger</a>
        <a href="<?= APP_URL ?>/ledgers/index.php" class="btn btn-outline">All Ledgers</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="POST" class="card card-form">
    <input type="hidden" name="action" value="save">
    <div class="form-grid">
        <div class="form-group">
            <label for="employee_name">Employee Name *</label>
            <input type="text" name="employee_name" id="employee_name" required value="<?= e($data['employee_name']) ?>">
        </div>
        <div class="form-group">
            <label for="title">Ledger Title *</label>
            <input type="text" name="title" id="title" required value="<?= e($data['title']) ?>">
        </div>
        <div class="form-group">
            <label for="in_label">IN column label</label>
            <input type="text" name="in_label" id="in_label" value="<?= e($data['in_label']) ?>">
        </div>
        <div class="form-group">
            <label for="out_label">OUT column label</label>
            <input type="text" name="out_label" id="out_label" value="<?= e($data['out_label']) ?>">
        </div>
        <div class="form-group">
            <label for="user_id">Link activated account</label>
            <select name="user_id" id="user_id">
                <option value="">— None —</option>
                <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>" <?= (string) $data['user_id'] === (string) $user['id'] ? 'selected' : '' ?>>
                    <?= e($user['full_name']) ?> (@<?= e($user['username']) ?>) — <?= e(roleLabel($user['role'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="balance_as_of">Balance as of</label>
            <input type="date" name="balance_as_of" id="balance_as_of" value="<?= e($data['balance_as_of']) ?>">
        </div>
        <div class="form-group full-width">
            <label class="checkbox-label">
                <input type="checkbox" name="is_visible" value="1" <?= $data['is_visible'] ? 'checked' : '' ?>>
                Make visible (read-only) on the linked activated account
            </label>
            <span class="form-hint">When enabled, the linked user sees this ledger under “My Ledger” with no edit access.</span>
        </div>
        <div class="form-group full-width">
            <label for="notes">Notes (admin only)</label>
            <textarea name="notes" id="notes" rows="2"><?= e($data['notes']) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>

<form method="POST" class="card" style="margin-top:16px;border-color:#fecaca"
      onsubmit="return confirm('Delete this entire ledger and all entries? This cannot be undone.');">
    <input type="hidden" name="action" value="delete">
    <div class="card-header"><h2>Danger Zone</h2></div>
    <p class="form-hint" style="margin-bottom:12px">Permanently delete this employee ledger and every line item.</p>
    <button type="submit" class="btn btn-outline" style="color:#991b1b;border-color:#fecaca">Delete Ledger</button>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
