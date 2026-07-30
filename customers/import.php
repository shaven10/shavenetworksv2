<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscriber_import.php';
requireRole('owner');

$pageTitle = 'Import Subscribers';
$currentPage = 'customers';

$plans = getDB()->query('SELECT id, name FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee, name')->fetchAll();
$plansByName = getActivePlansByName();
$errors = [];
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['import_file'] ?? null;

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
        } else {
            try {
                $spreadsheet = readSubscriberSpreadsheet($file['tmp_name'], $originalName);
                $rows = $spreadsheet['rows'];

                if (!$rows) {
                    $errors[] = 'No subscriber rows found in the file.';
                } else {
                    $user = currentUser();
                    $createdBy = (int) $user['id'];
                    $imported = [];
                    $failed = [];
                    $skipped = 0;

                    foreach ($rows as $rowNum => $row) {
                        $prepared = prepareSubscriberImportRow($row, $plansByName);
                        if (!$prepared['ok']) {
                            $failed[] = [
                                'row'     => $rowNum,
                                'preview' => $prepared['preview'],
                                'errors'  => $prepared['errors'],
                            ];
                            continue;
                        }

                        try {
                            $accountNumber = insertImportedSubscriber($prepared['data'], $createdBy);
                            $imported[] = [
                                'row'            => $rowNum,
                                'account_number' => $accountNumber,
                                'full_name'      => $prepared['data']['full_name'],
                                'plan'           => $prepared['preview']['plan'],
                            ];
                        } catch (Throwable $e) {
                            $failed[] = [
                                'row'     => $rowNum,
                                'preview' => $prepared['preview'],
                                'errors'  => ['Database error: ' . $e->getMessage()],
                            ];
                        }
                    }

                    $importResult = [
                        'imported' => $imported,
                        'failed'   => $failed,
                        'skipped'  => $skipped,
                        'total'    => count($rows),
                    ];

                    if ($imported) {
                        logActivity(
                            'subscribers_imported',
                            'Imported ' . count($imported) . ' subscriber(s) from Excel'
                        );
                    }
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Import Subscribers</h1>
        <p>Upload an Excel spreadsheet to create subscriber accounts in bulk</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/customers/import_template.php?format=xlsx" class="btn btn-primary">Download Excel Template</a>
        <a href="<?= APP_URL ?>/customers/import_template.php?format=csv" class="btn btn-outline">Download CSV</a>
        <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-outline">← Back</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($importResult): ?>
<div class="alert <?= $importResult['failed'] ? 'alert-warning' : 'alert-success' ?>">
    Imported <strong><?= count($importResult['imported']) ?></strong> of
    <strong><?= (int) $importResult['total'] ?></strong> row(s).
    <?php if ($importResult['failed']): ?>
    <?= count($importResult['failed']) ?> row(s) failed validation or insert.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card card-form">
        <div class="card-header"><h2>Upload File</h2></div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="import_file">Excel / CSV file *</label>
                <input type="file" id="import_file" name="import_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                <p class="form-hint">Accepted formats: .xlsx (Excel) or .csv. Max 5 MB.</p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Import Subscribers</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h2>Column Guide</h2></div>
        <p class="form-hint" style="margin-bottom:12px">
            Use the template headers exactly. Plan names must match an active service plan.
        </p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Column</th><th>Required</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>full_name</code></td><td>Yes</td><td>Subscriber name</td></tr>
                    <tr><td><code>phone</code></td><td>Yes</td><td>Contact number</td></tr>
                    <tr><td><code>email</code></td><td>No</td><td>Optional email</td></tr>
                    <tr><td><code>plan</code></td><td>Yes</td><td>Exact or matching plan name</td></tr>
                    <tr><td><code>connection_medium</code></td><td>Yes</td><td><code>fiber_olt</code>, <code>fiber_mediacon</code>, <code>wireless_radio</code>, or OLT / Mediacon / Wireless Radio</td></tr>
                    <tr><td><code>installation_date</code></td><td>Yes</td><td>YYYY-MM-DD preferred</td></tr>
                    <tr><td><code>address</code></td><td>Yes</td><td>Street / building</td></tr>
                    <tr><td><code>barangay</code></td><td>No</td><td>Optional</td></tr>
                    <tr><td><code>city</code></td><td>Yes</td><td>City / municipality</td></tr>
                    <tr><td><code>province</code></td><td>Yes</td><td>Province</td></tr>
                    <tr><td><code>notes</code></td><td>No</td><td>Optional notes</td></tr>
                    <tr><td><code>status</code></td><td>No</td><td>active, suspended, or disconnected (default active)</td></tr>
                </tbody>
            </table>
        </div>
        <?php if ($plans): ?>
        <p class="form-hint" style="margin-top:12px"><strong>Active plans:</strong>
            <?= e(implode(', ', array_column($plans, 'name'))) ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($importResult && $importResult['imported']): ?>
<div class="card">
    <div class="card-header"><h2>Imported (<?= count($importResult['imported']) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Row</th><th>Account</th><th>Name</th><th>Plan</th></tr>
            </thead>
            <tbody>
                <?php foreach ($importResult['imported'] as $row): ?>
                <tr>
                    <td><?= (int) $row['row'] ?></td>
                    <td><?= e($row['account_number']) ?></td>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['plan']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($importResult && $importResult['failed']): ?>
<div class="card">
    <div class="card-header"><h2>Failed Rows (<?= count($importResult['failed']) ?>)</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Row</th><th>Name</th><th>Phone</th><th>Plan</th><th>Errors</th></tr>
            </thead>
            <tbody>
                <?php foreach ($importResult['failed'] as $row): ?>
                <tr>
                    <td><?= (int) $row['row'] ?></td>
                    <td><?= e($row['preview']['full_name'] ?: '—') ?></td>
                    <td><?= e($row['preview']['phone'] ?: '—') ?></td>
                    <td><?= e($row['preview']['plan'] ?: '—') ?></td>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
