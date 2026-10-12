<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ledger_io.php';
requireRole('owner');
ensureEmployeeLedgerTables();

$pageTitle = 'Import Ledger';
$currentPage = 'ledgers';

$targetId = (int) ($_GET['id'] ?? $_POST['ledger_id'] ?? 0);
$targetLedger = $targetId ? getEmployeeLedger($targetId) : null;
$allLedgers = getEmployeeLedgers();
$errors = [];
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? 'new');
    $replace = ($_POST['replace_entries'] ?? '1') === '1';
    $file = $_FILES['import_file'] ?? null;

    $formMeta = [
        'employee_name' => trim((string) ($_POST['employee_name'] ?? '')),
        'title'         => trim((string) ($_POST['title'] ?? '')),
        'in_label'      => trim((string) ($_POST['in_label'] ?? '')),
        'out_label'     => trim((string) ($_POST['out_label'] ?? '')),
        'balance_as_of' => trim((string) ($_POST['balance_as_of'] ?? '')),
        'notes'         => trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($mode === 'existing') {
        $targetId = (int) ($_POST['ledger_id'] ?? 0);
        $targetLedger = $targetId ? getEmployeeLedger($targetId) : null;
        if (!$targetLedger) {
            $errors[] = 'Select an existing ledger to import into.';
        }
    }

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose an Excel (.xlsx) or CSV file to upload.';
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } elseif (($file['size'] ?? 0) <= 0) {
        $errors[] = 'The uploaded file is empty.';
    } elseif (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = 'File is too large. Maximum size is 5 MB.';
    } else {
        $originalName = (string) ($file['name'] ?? 'upload.xlsx');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            $errors[] = 'Only .xlsx or .csv files are allowed.';
        }
    }

    if (!$errors) {
        try {
            $spreadsheet = readLedgerSpreadsheet($file['tmp_name'], $originalName);
            $fileMeta = $spreadsheet['meta'] ?? [];
            $rows = $spreadsheet['rows'] ?? [];

            if (!$rows) {
                $errors[] = 'No ledger entry rows found in the file.';
            } else {
                $prepared = [];
                $failed = [];

                foreach ($rows as $rowNum => $row) {
                    $result = prepareLedgerImportRow($row);
                    if (!$result['ok']) {
                        $failed[] = [
                            'row'     => $rowNum,
                            'preview' => $result['preview'],
                            'errors'  => $result['errors'],
                        ];
                        continue;
                    }
                    $prepared[] = $result['data'];
                }

                if (!$prepared) {
                    $errors[] = 'No valid entry rows to import.';
                } else {
                    $user = currentUser();
                    $createdBy = (int) $user['id'];
                    $ledgerId = 0;

                    if ($mode === 'existing' && $targetLedger) {
                        $ledgerId = (int) $targetLedger['id'];
                        // Optional meta updates from form/file when provided
                        $update = [
                            'employee_name' => $formMeta['employee_name'] !== ''
                                ? $formMeta['employee_name']
                                : ($fileMeta['employee_name'] ?? $targetLedger['employee_name']),
                            'title' => $formMeta['title'] !== ''
                                ? $formMeta['title']
                                : ($fileMeta['title'] ?? $targetLedger['title']),
                            'in_label' => $formMeta['in_label'] !== ''
                                ? $formMeta['in_label']
                                : ($fileMeta['in_label'] ?? $targetLedger['in_label']),
                            'out_label' => $formMeta['out_label'] !== ''
                                ? $formMeta['out_label']
                                : ($fileMeta['out_label'] ?? $targetLedger['out_label']),
                            'balance_as_of' => $formMeta['balance_as_of'] !== ''
                                ? $formMeta['balance_as_of']
                                : ($fileMeta['balance_as_of'] ?? $targetLedger['balance_as_of']),
                            'notes' => $formMeta['notes'] !== ''
                                ? $formMeta['notes']
                                : ($fileMeta['notes'] ?? $targetLedger['notes']),
                            'user_id'    => $targetLedger['user_id'],
                            'is_visible' => (int) $targetLedger['is_visible'],
                        ];
                        updateEmployeeLedger($ledgerId, $update);
                        $count = importLedgerEntries($ledgerId, $prepared, $replace);
                    } else {
                        $employeeName = $formMeta['employee_name'] !== ''
                            ? $formMeta['employee_name']
                            : trim((string) ($fileMeta['employee_name'] ?? ''));
                        if ($employeeName === '') {
                            throw new InvalidArgumentException('Employee name is required (form or file Meta).');
                        }

                        $ledgerId = createEmployeeLedger([
                            'employee_name' => $employeeName,
                            'title'         => $formMeta['title'] !== ''
                                ? $formMeta['title']
                                : ($fileMeta['title'] ?? ''),
                            'in_label'      => $formMeta['in_label'] !== ''
                                ? $formMeta['in_label']
                                : ($fileMeta['in_label'] ?? 'Hatagon Beben'),
                            'out_label'     => $formMeta['out_label'] !== ''
                                ? $formMeta['out_label']
                                : ($fileMeta['out_label'] ?? ''),
                            'balance_as_of' => $formMeta['balance_as_of'] !== ''
                                ? $formMeta['balance_as_of']
                                : ($fileMeta['balance_as_of'] ?? ''),
                            'notes'         => $formMeta['notes'] !== ''
                                ? $formMeta['notes']
                                : ($fileMeta['notes'] ?? 'Imported from CSV/Excel'),
                            'is_visible'    => false,
                        ], $createdBy);

                        $count = importLedgerEntries($ledgerId, $prepared, true);
                    }

                    $importResult = [
                        'ledger_id' => $ledgerId,
                        'imported'  => $count,
                        'failed'    => $failed,
                        'total'     => count($rows),
                        'mode'      => $mode,
                        'replace'   => $replace,
                    ];

                    logActivity(
                        'ledger_imported',
                        "Imported {$count} ledger entr" . ($count === 1 ? 'y' : 'ies')
                        . " into ledger #{$ledgerId} from {$originalName}"
                    );
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Import Ledger</h1>
        <p>Upload Excel or CSV to create a ledger or replace/append entries</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/ledgers/import_template.php?format=xlsx" class="btn btn-primary">Download Excel Template</a>
        <a href="<?= APP_URL ?>/ledgers/import_template.php?format=csv" class="btn btn-outline">Download CSV</a>
        <a href="<?= APP_URL ?>/ledgers/index.php" class="btn btn-outline">← Back</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($importResult): ?>
<div class="alert <?= $importResult['failed'] ? 'alert-warning' : 'alert-success' ?>">
    Imported <strong><?= (int) $importResult['imported'] ?></strong> of
    <strong><?= (int) $importResult['total'] ?></strong> row(s)
    into ledger #<?= (int) $importResult['ledger_id'] ?>.
    <?php if ($importResult['failed']): ?>
    <?= count($importResult['failed']) ?> row(s) failed validation.
    <?php endif; ?>
    <a href="<?= APP_URL ?>/ledgers/view.php?id=<?= (int) $importResult['ledger_id'] ?>">Open ledger →</a>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card card-form">
        <div class="card-header"><h2>Upload File</h2></div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="mode">Import mode</label>
                <select name="mode" id="mode">
                    <option value="new" <?= ($targetLedger ? '' : 'selected') ?>>Create new ledger</option>
                    <option value="existing" <?= ($targetLedger ? 'selected' : '') ?>>Import into existing ledger</option>
                </select>
            </div>

            <div class="form-group" id="existing-ledger-group">
                <label for="ledger_id">Existing ledger</label>
                <select name="ledger_id" id="ledger_id">
                    <option value="">— Select —</option>
                    <?php foreach ($allLedgers as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= $targetId === (int) $row['id'] ? 'selected' : '' ?>>
                        <?= e($row['employee_name'] . ' — ' . $row['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="replace-group">
                <label for="replace_entries">Existing entries</label>
                <select name="replace_entries" id="replace_entries">
                    <option value="1" selected>Replace all entries</option>
                    <option value="0">Append to existing entries</option>
                </select>
            </div>

            <div class="form-group">
                <label for="import_file">Excel / CSV file *</label>
                <input type="file" id="import_file" name="import_file"
                       accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                       required>
                <p class="form-hint">Accepted: .xlsx or .csv. Max 5 MB.</p>
            </div>

            <div class="card-header" style="padding-left:0"><h2>Ledger details (optional overrides)</h2></div>
            <p class="form-hint" style="margin-top:0">Used when creating a new ledger, or to override Meta from the file.</p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="employee_name">Employee name</label>
                    <input type="text" name="employee_name" id="employee_name"
                           value="<?= e($targetLedger['employee_name'] ?? '') ?>"
                           placeholder="Required for new ledger if not in file">
                </div>
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title"
                           value="<?= e($targetLedger['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="in_label">IN label</label>
                    <input type="text" name="in_label" id="in_label"
                           value="<?= e($targetLedger['in_label'] ?? '') ?>" placeholder="Hatagon Beben">
                </div>
                <div class="form-group">
                    <label for="out_label">OUT label</label>
                    <input type="text" name="out_label" id="out_label"
                           value="<?= e($targetLedger['out_label'] ?? '') ?>" placeholder="Utang …">
                </div>
                <div class="form-group">
                    <label for="balance_as_of">Balance as of</label>
                    <input type="date" name="balance_as_of" id="balance_as_of"
                           value="<?= e($targetLedger['balance_as_of'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <input type="text" name="notes" id="notes"
                           value="<?= e($targetLedger['notes'] ?? '') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Import Ledger</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h2>Column Guide</h2></div>
        <div class="table-responsive">
            <table class="table table-compact">
                <thead>
                    <tr><th>Column</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <tr><td>Date</td><td>YYYY-MM-DD (optional if Date Label is set)</td></tr>
                    <tr><td>Date Label</td><td>e.g. Month of July 2025</td></tr>
                    <tr><td>Description</td><td>Required for transactions</td></tr>
                    <tr><td>IN</td><td>Salary / sweldo amount</td></tr>
                    <tr><td>OUT</td><td>Cash advance / utang amount</td></tr>
                    <tr><td>Row Type</td><td><code>entry</code>, <code>year_header</code>, or <code>end_marker</code></td></tr>
                    <tr><td>Highlight</td><td><code>none</code>, <code>opening</code>, or <code>year</code></td></tr>
                </tbody>
            </table>
        </div>
        <p style="padding:0 16px 16px;margin:0" class="text-muted">
            Excel: put rows on the <strong>Entries</strong> sheet; optional <strong>Meta</strong> sheet for employee name, title, labels.
            CSV: optional lines like <code># employee_name: Nonoy Balansi</code> above the header row.
        </p>
    </div>
</div>

<?php if ($importResult && $importResult['failed']): ?>
<div class="card" style="margin-top:16px">
    <div class="card-header"><h2>Failed Rows (<?= count($importResult['failed']) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Description</th>
                    <th>IN</th>
                    <th>OUT</th>
                    <th>Errors</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($importResult['failed'] as $row): ?>
                <tr>
                    <td><?= (int) $row['row'] ?></td>
                    <td><?= e($row['preview']['description'] ?? '') ?></td>
                    <td><?= e($row['preview']['in'] ?? '') ?></td>
                    <td><?= e($row['preview']['out'] ?? '') ?></td>
                    <td>
                        <ul class="import-error-list">
                            <?php foreach ($row['errors'] as $err): ?>
                            <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var mode = document.getElementById('mode');
    var existing = document.getElementById('existing-ledger-group');
    var replace = document.getElementById('replace-group');
    function sync() {
        var isExisting = mode.value === 'existing';
        existing.style.display = isExisting ? '' : 'none';
        replace.style.display = isExisting ? '' : 'none';
    }
    mode.addEventListener('change', sync);
    sync();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
