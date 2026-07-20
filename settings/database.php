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

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Database Tools</h1>
        <p>Owner-only database management, backup, and restore</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/settings/theme.php" class="btn btn-outline btn-sm">Theme Manager</a>
    </div>
</div>

<?php if ($result && !$result['success']): ?>
<div class="alert alert-danger"><?= e($result['message']) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Database Overview</h2></div>
        <dl class="detail-list">
            <dt>Database</dt><dd><?= e($dbStats['database']) ?></dd>
            <dt>Host</dt><dd><?= e($dbStats['host']) ?></dd>
            <dt>Estimated Size</dt><dd><?= e($dbStats['size_mb']) ?> MB</dd>
            <dt>Total Records</dt><dd><?= number_format($dbStats['total_rows']) ?></dd>
        </dl>
        <div class="table-responsive" style="margin-top:16px">
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
                <p>Export the full database as an SQL file to your computer.</p>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="openDbModal('download_backup', 'Download Database Backup', false, 'Download')">
                    Download SQL Backup
                </button>
            </div>
            <div class="tool-action-card">
                <h3>Save Backup on Server</h3>
                <p>Store a backup file in <code>storage/backups/</code> for later restore.</p>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openDbModal('save_backup', 'Save Backup on Server', false, 'Save Backup')">
                    Save Backup
                </button>
            </div>
            <div class="tool-action-card">
                <h3>Restore from Upload</h3>
                <p>Replace current data by uploading a <code>.sql</code> backup file.</p>
                <button type="button" class="btn btn-outline btn-sm" onclick="openRestoreUploadModal()">
                    Upload & Restore
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Saved Backups</h2></div>
    <?php if (empty($backups)): ?>
    <p class="text-muted">No saved backups yet. Use "Save Backup on Server" to create one.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-compact">
            <thead>
                <tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
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

<div class="card">
    <div class="card-header"><h2>Safe Actions</h2></div>
    <div class="tool-actions">
        <div class="tool-action-card">
            <h3>Reset Demo Passwords</h3>
            <p>Set all user passwords to <code>password123</code>. Does not delete any data.</p>
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="openDbModal('reset_passwords', 'Reset Demo Passwords', false, 'Confirm')">
                Reset Passwords
            </button>
        </div>
    </div>
</div>

<div class="card danger-zone">
    <div class="card-header"><h2>Danger Zone — Reset Options</h2></div>
    <p class="danger-intro">These actions modify or delete data. Owner password required. Destructive actions also require typing <strong>RESET</strong>.</p>
    <div class="tool-actions">
        <div class="tool-action-card tool-danger">
            <h3>Clear Transaction Data</h3>
            <p>Removes bills, payments, repair tickets, inquiries, and activity logs.</p>
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="openDbModal('clear_transactions', 'Clear Transaction Data', true, 'Confirm Reset')">
                Clear Transactions
            </button>
        </div>
        <div class="tool-action-card tool-danger">
            <h3>Reset Database to Demo</h3>
            <p>Drops and recreates the entire database with demo seed data.</p>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="openDbModal('reset_demo', 'Reset Database to Demo', true, 'Confirm Reset')">
                Full Reset to Demo
            </button>
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
    var submitBtn = document.getElementById('db-modal-submit');

    var descriptions = {
        reset_passwords: 'This will reset all user passwords to password123.',
        clear_transactions: 'This will permanently delete all bills, payments, tickets, inquiries, and logs.',
        reset_demo: 'This will DROP the entire database and restore demo seed data only.',
        save_backup: 'This will create a new SQL backup file on the server.'
    };

    function closeAllModals() {
        modals.forEach(function (m) { m.hidden = true; });
        document.body.classList.remove('modal-open');
    }

    window.openDbModal = function (action, title, needsConfirm, submitLabel) {
        if (action === 'download_backup') {
            document.getElementById('download-modal').hidden = false;
            document.body.classList.add('modal-open');
            return;
        }

        actionInput.value = action;
        titleEl.textContent = title;
        descEl.textContent = descriptions[action] || '';
        confirmGroup.hidden = !needsConfirm;
        confirmInput.required = needsConfirm;
        confirmInput.value = '';
        document.getElementById('owner_password').value = '';
        submitBtn.className = needsConfirm ? 'btn btn-danger' : 'btn btn-primary';
        submitBtn.textContent = submitLabel || (needsConfirm ? 'Confirm Reset' : 'Confirm');
        dbModal.hidden = false;
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
        if (!confirmGroup.hidden && confirmInput.value.trim().toUpperCase() !== 'RESET') {
            e.preventDefault();
            alert('Please type RESET to confirm this action.');
        }
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
