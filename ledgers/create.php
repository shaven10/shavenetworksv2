<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');
ensureEmployeeLedgerTables();

$pageTitle = 'New Employee Ledger';
$currentPage = 'ledgers';
$errors = [];
$users = getLinkableLedgerUsers();

$data = [
    'employee_name' => '',
    'title'         => '',
    'user_id'       => '',
    'is_visible'    => false,
    'in_label'      => 'Hatagon Beben',
    'out_label'     => '',
    'balance_as_of' => '',
    'notes'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $id = createEmployeeLedger($data, (int) currentUser()['id']);
        logActivity('ledger_created', 'Created employee ledger: ' . $data['employee_name']);
        flash('success', 'Employee ledger created. Add entries to match the balance sheet.');
        redirect('/ledgers/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>New Employee Ledger</h1>
        <p>Create a balance sheet for an employee (sweldo / cash advances)</p>
    </div>
    <a href="<?= APP_URL ?>/ledgers/index.php" class="btn btn-outline">Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="POST" class="card card-form">
    <div class="form-grid">
        <div class="form-group">
            <label for="employee_name">Employee Name *</label>
            <input type="text" name="employee_name" id="employee_name" required
                   value="<?= e($data['employee_name']) ?>" placeholder="e.g. Nonoy">
        </div>
        <div class="form-group">
            <label for="title">Ledger Title *</label>
            <input type="text" name="title" id="title"
                   value="<?= e($data['title']) ?>" placeholder="e.g. NONOY BALANSI June 15, 2025-May 2026">
            <span class="form-hint">Leave blank to auto-generate from the employee name.</span>
        </div>
        <div class="form-group">
            <label for="in_label">IN column label</label>
            <input type="text" name="in_label" id="in_label" value="<?= e($data['in_label']) ?>">
            <span class="form-hint">Shown under IN (e.g. Hatagon Beben).</span>
        </div>
        <div class="form-group">
            <label for="out_label">OUT column label</label>
            <input type="text" name="out_label" id="out_label" value="<?= e($data['out_label']) ?>"
                   placeholder="Utang Nonoy">
            <span class="form-hint">Defaults to “Utang {Employee Name}”.</span>
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
            <span class="form-hint">Only approved &amp; active accounts appear here.</span>
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
        </div>
        <div class="form-group full-width">
            <label for="notes">Notes (admin only)</label>
            <textarea name="notes" id="notes" rows="2"><?= e($data['notes']) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create Ledger</button>
        <a href="<?= APP_URL ?>/ledgers/index.php" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
