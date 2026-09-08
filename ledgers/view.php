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

$pageTitle = $ledger['employee_name'] . ' Ledger';
$currentPage = 'ledgers';
$errors = [];
$editEntry = null;
$editEntryId = (int) ($_GET['edit_entry'] ?? 0);
if ($editEntryId) {
    $editEntry = getLedgerEntry($editEntryId);
    if (!$editEntry || (int) $editEntry['ledger_id'] !== $id) {
        $editEntry = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete_entry') {
            $entryId = (int) ($_POST['entry_id'] ?? 0);
            $entry = getLedgerEntry($entryId);
            if (!$entry || (int) $entry['ledger_id'] !== $id) {
                throw new RuntimeException('Entry not found.');
            }
            deleteLedgerEntry($entryId);
            logActivity('ledger_entry_deleted', "Deleted ledger entry #{$entryId}");
            flash('success', 'Entry deleted.');
            redirect('/ledgers/view.php?id=' . $id);
        }

        if ($action === 'save_entry') {
            $payload = [
                'entry_date'   => $_POST['entry_date'] ?? '',
                'date_display' => $_POST['date_display'] ?? '',
                'description'  => $_POST['description'] ?? '',
                'amount_in'    => $_POST['amount_in'] ?? 0,
                'amount_out'   => $_POST['amount_out'] ?? 0,
                'row_type'     => $_POST['row_type'] ?? 'entry',
                'highlight'    => $_POST['highlight'] ?? 'none',
            ];

            $entryId = (int) ($_POST['entry_id'] ?? 0);
            if ($entryId) {
                $existing = getLedgerEntry($entryId);
                if (!$existing || (int) $existing['ledger_id'] !== $id) {
                    throw new RuntimeException('Entry not found.');
                }
                updateLedgerEntry($entryId, $payload);
                logActivity('ledger_entry_updated', "Updated ledger entry #{$entryId}");
                flash('success', 'Entry updated.');
            } else {
                addLedgerEntry($id, $payload);
                logActivity('ledger_entry_added', "Added entry to ledger #{$id}");
                flash('success', 'Entry added.');
            }
            redirect('/ledgers/view.php?id=' . $id);
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        if (($action ?? '') === 'save_entry' && !empty($_POST['entry_id'])) {
            $editEntry = [
                'id'           => (int) $_POST['entry_id'],
                'entry_date'   => $_POST['entry_date'] ?? '',
                'date_display' => $_POST['date_display'] ?? '',
                'description'  => $_POST['description'] ?? '',
                'amount_in'    => $_POST['amount_in'] ?? 0,
                'amount_out'   => $_POST['amount_out'] ?? 0,
                'row_type'     => $_POST['row_type'] ?? 'entry',
                'highlight'    => $_POST['highlight'] ?? 'none',
            ];
        }
    }
}

$ledger = getEmployeeLedger($id);
$entries = getLedgerEntries($id);
$form = $editEntry ?: [
    'id'           => 0,
    'entry_date'   => '',
    'date_display' => '',
    'description'  => '',
    'amount_in'    => '',
    'amount_out'   => '',
    'row_type'     => 'entry',
    'highlight'    => 'none',
];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1><?= e($ledger['title']) ?></h1>
        <p>
            Employee ledger
            <?php if ((int) $ledger['is_visible'] && $ledger['user_id']): ?>
            · <span class="badge badge-success">Visible to <?= e($ledger['account_name'] ?? 'account') ?></span>
            <?php elseif ($ledger['user_id']): ?>
            · <span class="badge badge-secondary">Linked (hidden)</span>
            <?php else: ?>
            · <span class="badge badge-secondary">Not linked</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Ledger</button>
        <a href="<?= APP_URL ?>/ledgers/edit.php?id=<?= $id ?>" class="btn btn-outline">Settings</a>
        <a href="<?= APP_URL ?>/ledgers/index.php" class="btn btn-outline">All Ledgers</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger no-print"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="card ledger-print-card" style="margin-bottom:16px;overflow:hidden">
    <?php renderEmployeeLedgerSheet($ledger, $entries, true); ?>
</div>

<form method="POST" class="card card-form no-print" id="entry-form" style="max-width:none">
    <input type="hidden" name="action" value="save_entry">
    <input type="hidden" name="entry_id" value="<?= (int) ($form['id'] ?? 0) ?>">
    <div class="card-header">
        <h2><?= !empty($form['id']) ? 'Edit Entry' : 'Add Entry' ?></h2>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label for="row_type">Row type</label>
            <select name="row_type" id="row_type">
                <option value="entry" <?= ($form['row_type'] ?? '') === 'entry' ? 'selected' : '' ?>>Transaction</option>
                <option value="year_header" <?= ($form['row_type'] ?? '') === 'year_header' ? 'selected' : '' ?>>Year header</option>
                <option value="end_marker" <?= ($form['row_type'] ?? '') === 'end_marker' ? 'selected' : '' ?>>Nothing follows</option>
            </select>
        </div>
        <div class="form-group">
            <label for="highlight">Highlight</label>
            <select name="highlight" id="highlight">
                <option value="none" <?= ($form['highlight'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                <option value="opening" <?= ($form['highlight'] ?? '') === 'opening' ? 'selected' : '' ?>>Opening balance</option>
                <option value="year" <?= ($form['highlight'] ?? '') === 'year' ? 'selected' : '' ?>>Year (yellow)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="entry_date">Date</label>
            <input type="date" name="entry_date" id="entry_date" value="<?= e($form['entry_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="date_display">Date label (optional)</label>
            <input type="text" name="date_display" id="date_display"
                   value="<?= e($form['date_display'] ?? '') ?>"
                   placeholder="e.g. Month of July 2025">
            <span class="form-hint">Overrides the date column text (used for monthly sweldo rows).</span>
        </div>
        <div class="form-group">
            <label for="description">Description *</label>
            <input type="text" name="description" id="description" required
                   value="<?= e($form['description'] ?? '') ?>"
                   placeholder="Cash Advance / MONTLY SWELDO / Year 2026">
        </div>
        <div class="form-group">
            <label for="amount_in">IN amount</label>
            <input type="number" name="amount_in" id="amount_in" step="0.01" min="0"
                   value="<?= e((string) ($form['amount_in'] ?? '')) ?>" placeholder="0.00">
        </div>
        <div class="form-group">
            <label for="amount_out">OUT amount</label>
            <input type="number" name="amount_out" id="amount_out" step="0.01" min="0"
                   value="<?= e((string) ($form['amount_out'] ?? '')) ?>" placeholder="0.00">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= !empty($form['id']) ? 'Update Entry' : 'Add Entry' ?></button>
        <?php if (!empty($form['id'])): ?>
        <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= $id ?>" class="btn btn-outline">Cancel Edit</a>
        <?php endif; ?>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
