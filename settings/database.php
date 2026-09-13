<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$pageTitle = 'Database Tools';
$currentPage = 'database_tools';
$result = null;
$backups = listSavedBackups();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $password = $_POST['owner_password'] ?? '';
    $confirmText = $_POST['confirm_text'] ?? '';

    if ($action === 'restore_upload') {
        $result = runDatabaseAction($action, $password, $confirmText, [
            'upload' => $_FILES['backup_file'] ?? null,
        ]);
    } elseif ($action === 'restore_saved') {
        $result = runDatabaseAction($action, $password, $confirmText, [
            'filename' => $_POST['backup_filename'] ?? '',
        ]);
    } elseif ($action === 'restore_named_point') {
        $result = runDatabaseAction($action, $password, $confirmText, [
            'filename' => $_POST['restore_filename'] ?? '',
        ]);
    } elseif (in_array($action, ['save_default_restore_point', 'save_named_restore_point'], true)) {
        $result = runDatabaseAction($action, $password, $confirmText, [
            'label' => $_POST['restore_label'] ?? '',
        ]);
    } else {
        $result = runDatabaseAction($action, $password, $confirmText);
    }

    if ($result['success']) {
        flash('success', $result['message']);
        redirect('/settings/database.php');
    }
}

$dbStats = getDatabaseStats();
$backups = listSavedBackups();
$defaultRestore = ensureDefaultRestorePoint();
$namedRestorePoints = listNamedRestorePoints();

require __DIR__ . '/../includes/header.php';
?>

<div class="db-tools-page">
<div class="page-header">
    <div>
        <h1>Database Tools</h1>
        <p>Owner-only database management, backup, and restore</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/settings/api.php" class="btn btn-outline btn-sm">API Settings</a>
        <a href="<?= APP_URL ?>/settings/theme.php" class="btn btn-outline btn-sm">Theme Manager</a>
    </div>
</div>

<?php if ($result && !$result['success']): ?>
<div class="alert alert-danger"><?= e($result['message']) ?></div>
<?php endif; ?>

<div class="db-tools-sections">
    <div class="card">
        <div class="card-header"><h2>Database Overview</h2></div>
        <div class="db-tools-stats">
            <div class="db-tools-stat">
                <span>Database</span>
                <strong><?= e($dbStats['database']) ?></strong>
            </div>
            <div class="db-tools-stat">
                <span>Host</span>
                <strong><?= e($dbStats['host']) ?></strong>
            </div>
            <div class="db-tools-stat">
                <span>Size</span>
                <strong><?= e($dbStats['size_mb']) ?> MB</strong>
            </div>
            <div class="db-tools-stat">
                <span>Records</span>
                <strong><?= number_format($dbStats['total_rows']) ?></strong>
            </div>
        </div>
        <div class="table-responsive db-tools-table-wrap">
            <table class="table table-compact">
                <thead><tr><th>Table</th><th>Records</th></tr></thead>
                <tbody>
                    <?php foreach ($dbStats['tables'] as $row): ?>
                    <tr>
                        <td><?= e($row['label']) ?></td>
                        <td><?= $row['count'] !== null ? number_format($row['count']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Backup & Restore</h2></div>
        <div class="tool-actions">
            <div class="tool-action-card">
                <h3>Download Backup</h3>
                <p>Export full database SQL to your computer.</p>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="openDbModal('download_backup', 'Download Database Backup', false, 'Download')">
                    Download SQL
                </button>
            </div>
            <div class="tool-action-card">
                <h3>Save on Server</h3>
                <p>Store a backup in <code>storage/backups/</code>.</p>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openDbModal('save_backup', 'Save Backup on Server', false, 'Save Backup')">
                    Save Backup
                </button>
            </div>
            <div class="tool-action-card">
                <h3>Restore Upload</h3>
                <p>Replace data from an uploaded <code>.sql</code> file.</p>
                <button type="button" class="btn btn-outline btn-sm" onclick="openRestoreUploadModal()">
                    Upload & Restore
                </button>
            </div>
        </div>
    </div>
</div>

<div class="db-tools-sections">
    <div class="card">
        <div class="card-header"><h2>Restore Points</h2></div>
        <p class="form-hint">
            Snapshot <strong>service_plans</strong> + <strong>customers</strong> only. Orphaned billing rows are cleaned on restore.
        </p>

        <div class="tool-actions">
            <div class="tool-action-card">
                <h3>Default Restore Point</h3>
                <?php if ($defaultRestore): ?>
                <p>
                    <strong><?= e($defaultRestore['label'] ?? 'Default') ?></strong><br>
                    <?= (int) ($defaultRestore['plan_count'] ?? 0) ?> plans ·
                    <?= (int) ($defaultRestore['customer_count'] ?? 0) ?> customers ·
                    <?= e(formatDate(substr((string) ($defaultRestore['updated_at'] ?? $defaultRestore['created_at'] ?? ''), 0, 10))) ?>
                </p>
                <?php else: ?>
                <p class="text-muted">No default restore point yet.</p>
                <?php endif; ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    <button type="button" class="btn btn-primary btn-sm"
                            onclick="openDbModal('restore_default_point', 'Restore Default Point', true, 'Restore', 'RESTORE')">
                        Restore Default
                    </button>
                    <button type="button" class="btn btn-outline btn-sm"
                            onclick="openSaveRestorePointModal('save_default_restore_point', 'Update Default Restore Point')">
                        Update Default
                    </button>
                </div>
            </div>

            <div class="tool-action-card">
                <h3>Save New Point</h3>
                <p>Keep an extra dated snapshot of current customers and plans.</p>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openSaveRestorePointModal('save_named_restore_point', 'Save Named Restore Point')">
                    Save Point
                </button>
            </div>
        </div>

        <?php if (!empty($namedRestorePoints)): ?>
        <div class="table-responsive db-tools-table-wrap" style="margin-top:12px">
            <table class="table table-compact">
                <thead>
                    <tr><th>Label</th><th>Plans</th><th>Customers</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($namedRestorePoints as $point): ?>
                    <tr>
                        <td>
                            <?= e($point['label']) ?><br>
                            <small class="text-muted"><code><?= e($point['filename']) ?></code></small>
                        </td>
                        <td><?= number_format((int) $point['plan_count']) ?></td>
                        <td><?= number_format((int) $point['customer_count']) ?></td>
                        <td><?= date('M d, Y h:i A', (int) $point['created']) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="openRestoreNamedPointModal('<?= e($point['filename']) ?>', '<?= e($point['label']) ?>')">
                                Restore
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h2>Saved Backups</h2></div>
        <?php if (empty($backups)): ?>
        <p class="text-muted">No saved backups yet.</p>
        <?php else: ?>
        <div class="table-responsive db-tools-table-wrap">
            <table class="table table-compact">
                <thead>
                    <tr><th>Filename</th><th>Size</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td><code><?= e($backup['filename']) ?></code></td>
                        <td><?= formatFileSize((int) $backup['size']) ?></td>
                        <td><?= date('M d, Y h:i A', $backup['created']) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-outline"
                                    onclick="openDownloadSavedModal('<?= e($backup['filename']) ?>')">Download</button>
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="openRestoreSavedModal('<?= e($backup['filename']) ?>')">Restore</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="db-tools-sections">
    <div class="card">
        <div class="card-header"><h2>Safe Actions</h2></div>
        <div class="tool-actions">
            <div class="tool-action-card">
                <h3>Reset Demo Passwords</h3>
                <p>Set all user passwords to <code>password123</code>.</p>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openDbModal('reset_passwords', 'Reset Demo Passwords', false, 'Confirm')">
                    Reset Passwords
                </button>
            </div>
        </div>
    </div>

    <div class="card danger-zone">
        <div class="card-header"><h2>Danger Zone</h2></div>
        <p class="danger-intro">Destructive actions require owner password and typing <strong>RESET</strong>.</p>
        <div class="tool-actions">
            <div class="tool-action-card tool-danger">
                <h3>Clear Transactions</h3>
                <p>Remove bills, payments, remittances, tickets, inquiries, and logs.</p>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openDbModal('clear_transactions', 'Clear Transaction Data', true, 'Confirm Reset')">
                    Clear Transactions
                </button>
            </div>
            <div class="tool-action-card tool-danger">
                <h3>Full Reset to Demo</h3>
                <p>Drop and recreate the database with demo seed data.</p>
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="openDbModal('reset_demo', 'Reset Database to Demo', true, 'Confirm Reset')">
                    Full Reset
                </button>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Standard action modal -->
<div class="modal-overlay" id="db-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2 id="db-modal-title">Confirm Action</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" id="db-modal-form" action="<?= APP_URL ?>/settings/database.php">
            <input type="hidden" name="action" id="db-modal-action">
            <div class="modal-body">
                <p id="db-modal-desc" class="modal-intro"></p>
                <div class="form-group">
                    <label for="owner_password">Your Owner Password *</label>
                    <input type="password" id="owner_password" name="owner_password" required autocomplete="current-password">
                </div>
                <div class="form-group" id="confirm-text-group" hidden>
                    <label for="confirm_text">Type RESET to confirm *</label>
                    <input type="text" id="confirm_text" name="confirm_text" placeholder="RESET" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary" id="db-modal-submit">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Download backup modal -->
<div class="modal-overlay" id="download-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Download Database Backup</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/settings/backup_download.php">
            <div class="modal-body">
                <p class="modal-intro">Enter your owner password to download a full SQL backup.</p>
                <div class="form-group">
                    <label for="download_password">Your Owner Password *</label>
                    <input type="password" id="download_password" name="owner_password" required autocomplete="current-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Download</button>
            </div>
        </form>
    </div>
</div>

<!-- Download saved backup modal -->
<div class="modal-overlay" id="download-saved-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Download Saved Backup</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" id="download-saved-form" action="">
            <div class="modal-body">
                <p class="modal-intro">Downloading: <strong id="download-saved-name"></strong></p>
                <div class="form-group">
                    <label for="download_saved_password">Your Owner Password *</label>
                    <input type="password" id="download_saved_password" name="owner_password" required autocomplete="current-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Download</button>
            </div>
        </form>
    </div>
</div>

<!-- Restore upload modal -->
<div class="modal-overlay" id="restore-upload-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Restore from Upload</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="<?= APP_URL ?>/settings/database.php">
            <input type="hidden" name="action" value="restore_upload">
            <div class="modal-body">
                <p class="modal-intro">This will replace current database records with the uploaded backup. Type RESTORE to confirm.</p>
                <div class="form-group">
                    <label for="backup_file">SQL Backup File *</label>
                    <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                </div>
                <div class="form-group">
                    <label for="restore_upload_password">Your Owner Password *</label>
                    <input type="password" id="restore_upload_password" name="owner_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="restore_upload_confirm">Type RESTORE to confirm *</label>
                    <input type="text" id="restore_upload_confirm" name="confirm_text" placeholder="RESTORE" required autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Restore Database</button>
            </div>
        </form>
    </div>
</div>

<!-- Restore saved modal -->
<div class="modal-overlay" id="restore-saved-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Restore Saved Backup</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/settings/database.php">
            <input type="hidden" name="action" value="restore_saved">
            <input type="hidden" name="backup_filename" id="restore_saved_filename">
            <div class="modal-body">
                <p class="modal-intro">Restoring: <strong id="restore-saved-name"></strong>. This replaces all current data. Type RESTORE to confirm.</p>
                <div class="form-group">
                    <label for="restore_saved_password">Your Owner Password *</label>
                    <input type="password" id="restore_saved_password" name="owner_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="restore_saved_confirm">Type RESTORE to confirm *</label>
                    <input type="text" id="restore_saved_confirm" name="confirm_text" placeholder="RESTORE" required autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Restore Database</button>
            </div>
        </form>
    </div>
</div>

<!-- Restore named restore-point modal -->
<div class="modal-overlay" id="restore-named-point-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2>Restore Named Point</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/settings/database.php">
            <input type="hidden" name="action" value="restore_named_point">
            <input type="hidden" name="restore_filename" id="restore_named_filename">
            <div class="modal-body">
                <p class="modal-intro">
                    Restoring customers &amp; plans from <strong id="restore-named-label"></strong>.
                    Type RESTORE to confirm.
                </p>
                <div class="form-group">
                    <label for="restore_named_password">Your Owner Password *</label>
                    <input type="password" id="restore_named_password" name="owner_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="restore_named_confirm">Type RESTORE to confirm *</label>
                    <input type="text" id="restore_named_confirm" name="confirm_text" placeholder="RESTORE" required autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Restore Point</button>
            </div>
        </form>
    </div>
</div>

<!-- Save restore point modal -->
<div class="modal-overlay" id="save-restore-point-modal" hidden>
    <div class="modal-dialog" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h2 id="save-restore-point-title">Save Restore Point</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/settings/database.php">
            <input type="hidden" name="action" id="save-restore-point-action" value="save_named_restore_point">
            <div class="modal-body">
                <p class="modal-intro" id="save-restore-point-desc">
                    Capture the current customers and service plans snapshot.
                </p>
                <div class="form-group">
                    <label for="restore_label">Label</label>
                    <input type="text" id="restore_label" name="restore_label"
                           placeholder="e.g. Maralag customers baseline" maxlength="120">
                </div>
                <div class="form-group">
                    <label for="save_restore_password">Your Owner Password *</label>
                    <input type="password" id="save_restore_password" name="owner_password" required autocomplete="current-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Snapshot</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modals = document.querySelectorAll('.modal-overlay');
    var dbModal = document.getElementById('db-modal');
    var dbForm = document.getElementById('db-modal-form');
    var actionInput = document.getElementById('db-modal-action');
    var titleEl = document.getElementById('db-modal-title');
    var descEl = document.getElementById('db-modal-desc');
    var confirmGroup = document.getElementById('confirm-text-group');
    var confirmInput = document.getElementById('confirm_text');
    var confirmLabel = confirmGroup ? confirmGroup.querySelector('label') : null;
    var submitBtn = document.getElementById('db-modal-submit');
    var expectedConfirmWord = 'RESET';

    var descriptions = {
        reset_passwords: 'This will reset all user passwords to password123.',
        clear_transactions: 'This will permanently delete all bills, payments, batch invoices, advance payments, remittances, tickets, inquiries, and logs. Customer advance credit balances will be reset to zero.',
        reset_demo: 'This will DROP the entire database and restore demo seed data only.',
        save_backup: 'This will create a new SQL backup file on the server.',
        restore_default_point: 'This will replace current service plans and customers with the default restore point snapshot. Related orphan billing rows may be cleaned.'
    };

    function closeAllModals() {
        modals.forEach(function (m) { m.hidden = true; });
        document.body.classList.remove('modal-open');
    }

    window.openDbModal = function (action, title, needsConfirm, submitLabel, confirmWord) {
        if (action === 'download_backup') {
            document.getElementById('download-modal').hidden = false;
            document.body.classList.add('modal-open');
            return;
        }

        expectedConfirmWord = (confirmWord || 'RESET').toUpperCase();
        actionInput.value = action;
        titleEl.textContent = title;
        descEl.textContent = descriptions[action] || '';
        confirmGroup.hidden = !needsConfirm;
        confirmInput.required = needsConfirm;
        confirmInput.value = '';
        confirmInput.placeholder = expectedConfirmWord;
        if (confirmLabel) {
            confirmLabel.textContent = 'Type ' + expectedConfirmWord + ' to confirm *';
        }
        document.getElementById('owner_password').value = '';
        submitBtn.className = needsConfirm ? 'btn btn-danger' : 'btn btn-primary';
        submitBtn.textContent = submitLabel || (needsConfirm ? 'Confirm Reset' : 'Confirm');
        dbModal.hidden = false;
        document.body.classList.add('modal-open');
    };

    window.openSaveRestorePointModal = function (action, title) {
        document.getElementById('save-restore-point-action').value = action;
        document.getElementById('save-restore-point-title').textContent = title;
        document.getElementById('save-restore-point-desc').textContent =
            action === 'save_default_restore_point'
                ? 'Overwrite the default restore point with the current customers and service plans.'
                : 'Save an extra dated snapshot of the current customers and service plans.';
        document.getElementById('restore_label').value =
            action === 'save_default_restore_point'
                ? 'Default customers & service plans'
                : '';
        document.getElementById('save_restore_password').value = '';
        document.getElementById('save-restore-point-modal').hidden = false;
        document.body.classList.add('modal-open');
    };

    window.openRestoreNamedPointModal = function (filename, label) {
        document.getElementById('restore_named_filename').value = filename;
        document.getElementById('restore-named-label').textContent = label || filename;
        document.getElementById('restore_named_password').value = '';
        document.getElementById('restore_named_confirm').value = '';
        document.getElementById('restore-named-point-modal').hidden = false;
        document.body.classList.add('modal-open');
    };

    window.openRestoreUploadModal = function () {
        document.getElementById('restore-upload-modal').hidden = false;
        document.body.classList.add('modal-open');
    };

    window.openRestoreSavedModal = function (filename) {
        document.getElementById('restore_saved_filename').value = filename;
        document.getElementById('restore-saved-name').textContent = filename;
        document.getElementById('restore-saved-modal').hidden = false;
        document.body.classList.add('modal-open');
    };

    window.openDownloadSavedModal = function (filename) {
        document.getElementById('download-saved-name').textContent = filename;
        document.getElementById('download-saved-form').action =
            '<?= APP_URL ?>/settings/backup_file.php?file=' + encodeURIComponent(filename);
        document.getElementById('download-saved-modal').hidden = false;
        document.body.classList.add('modal-open');
    };

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeAllModals);
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeAllModals();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllModals();
    });

    dbForm.addEventListener('submit', function (e) {
        if (!confirmGroup.hidden && confirmInput.value.trim().toUpperCase() !== expectedConfirmWord) {
            e.preventDefault();
            alert('Please type ' + expectedConfirmWord + ' to confirm this action.');
        }
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
