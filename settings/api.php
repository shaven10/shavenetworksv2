<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$pageTitle = 'API Settings';
$currentPage = 'api_settings';
ensureSmsNotificationsTable();

$settings = getApiSettings();
$actionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        persistApiSettings([
            'semaphore_api_key'     => $_POST['semaphore_api_key'] ?? '',
            'semaphore_sender_name' => $_POST['semaphore_sender_name'] ?? '',
            'sms_enabled'           => !empty($_POST['sms_enabled']),
            'notify_days_before'    => (int) ($_POST['notify_days_before'] ?? 3),
            'notify_on_due'         => !empty($_POST['notify_on_due']),
            'notify_on_overdue'     => !empty($_POST['notify_on_overdue']),
            'auto_send'             => !empty($_POST['auto_send']),
            'message_due'           => $_POST['message_due'] ?? '',
            'message_overdue'       => $_POST['message_overdue'] ?? '',
            'clear_api_key'         => !empty($_POST['clear_api_key']),
        ]);
        logActivity('api_settings_update', 'Updated Semaphore SMS API settings');
        flash('success', 'API settings saved successfully.');
        redirect('/settings/api.php');
    }

    if ($action === 'test_sms') {
        $result = sendTestSms(
            (string) ($_POST['test_phone'] ?? ''),
            (string) ($_POST['test_message'] ?? '')
        );
        logActivity('sms_test', $result['message']);
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('/settings/api.php');
    }

    if ($action === 'send_due_now') {
        $settings = getApiSettings();
        if (empty($settings['sms_enabled'])) {
            flash('danger', 'Enable SMS notifications before sending.');
            redirect('/settings/api.php');
        }

        $forceResend = !empty($_POST['force_resend']);
        $result = processDuePaymentSms($forceResend);
        updateApiSettingsMeta(['last_auto_run' => date('Y-m-d')]);

        $summary = sprintf(
            'Due-payment SMS finished: %d sent, %d failed, %d skipped.',
            $result['sent'],
            $result['failed'],
            $result['skipped']
        );
        logActivity('sms_due_send', $summary);

        if ($result['sent'] > 0 && $result['failed'] === 0) {
            flash('success', $summary);
        } elseif ($result['sent'] > 0) {
            flash('warning', $summary . (!empty($result['errors']) ? ' ' . $result['errors'][0] : ''));
        } elseif (!empty($result['errors'])) {
            flash('danger', $summary . ' ' . $result['errors'][0]);
        } else {
            flash('info', $summary . ' No new recipients needed a reminder.');
        }
        redirect('/settings/api.php');
    }
}

$settings = getApiSettings();
$stats = getSmsNotificationStats();
$logs = getSmsNotificationLogs(40);
$pendingCount = 0;
try {
    $pendingCount = countPendingSmsTargets();
} catch (Throwable $e) {
    $pendingCount = 0;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>API Settings</h1>
        <p>Configure Semaphore SMS and automatic due-payment notifications</p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/settings/theme.php" class="btn btn-outline btn-sm">Theme Manager</a>
        <a href="<?= APP_URL ?>/settings/database.php" class="btn btn-outline btn-sm">Database Tools</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-label">SMS Status</div>
        <div class="stat-value" style="font-size:1.25rem"><?= !empty($settings['sms_enabled']) ? 'Enabled' : 'Disabled' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Recipients</div>
        <div class="stat-value"><?= number_format($pendingCount) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sent Today</div>
        <div class="stat-value"><?= number_format($stats['today']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Sent</div>
        <div class="stat-value"><?= number_format($stats['sent']) ?></div>
    </div>
</div>

<div class="grid-2">
    <form method="POST" class="card card-form" style="max-width:none">
        <input type="hidden" name="action" value="save">
        <div class="card-header"><h2>Semaphore API</h2></div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="sms_enabled" value="1" <?= !empty($settings['sms_enabled']) ? 'checked' : '' ?>>
                Enable SMS notifications
            </label>
            <span class="form-hint">When enabled, due/overdue reminders can be sent to subscriber mobile numbers.</span>
        </div>

        <div class="form-group">
            <label for="semaphore_api_key">API Key</label>
            <input type="password" name="semaphore_api_key" id="semaphore_api_key"
                   placeholder="<?= $settings['semaphore_api_key'] !== '' ? e(maskApiKey($settings['semaphore_api_key'])) : 'Paste your Semaphore API key' ?>"
                   autocomplete="off">
            <span class="form-hint">
                Leave blank to keep the current key.
                <?php if ($settings['semaphore_api_key'] !== ''): ?>
                Current: <?= e(maskApiKey($settings['semaphore_api_key'])) ?>
                <?php endif; ?>
                Get your key from <a href="https://semaphore.co" target="_blank" rel="noopener">semaphore.co</a>.
            </span>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="clear_api_key" value="1">
                Clear saved API key
            </label>
        </div>

        <div class="form-group">
            <label for="semaphore_sender_name">Sender Name</label>
            <input type="text" name="semaphore_sender_name" id="semaphore_sender_name"
                   value="<?= e($settings['semaphore_sender_name']) ?>" maxlength="11"
                   placeholder="SHAVEN">
            <span class="form-hint">Must match an approved Sender Name in your Semaphore account (max 11 characters).</span>
        </div>

        <div class="card-header" style="margin-top:8px"><h2>Due Payment Rules</h2></div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="auto_send" value="1" <?= !empty($settings['auto_send']) ? 'checked' : '' ?>>
                Automatically send SMS once per day
            </label>
            <span class="form-hint">
                Runs when staff open the app (once daily) or via the cron script.
                Last auto-run: <?= $settings['last_auto_run'] ? e(formatDate($settings['last_auto_run'])) : 'Never' ?>
            </span>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="notify_on_due" value="1" <?= !empty($settings['notify_on_due']) ? 'checked' : '' ?>>
                Remind before / on due date
            </label>
        </div>

        <div class="form-group">
            <label for="notify_days_before">Days before due date</label>
            <input type="number" name="notify_days_before" id="notify_days_before"
                   min="0" max="30" value="<?= (int) $settings['notify_days_before'] ?>">
            <span class="form-hint">0 = only on the due date. Example: 3 sends when due within the next 3 days.</span>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="notify_on_overdue" value="1" <?= !empty($settings['notify_on_overdue']) ? 'checked' : '' ?>>
                Notify overdue unpaid bills
            </label>
        </div>

        <div class="card-header" style="margin-top:8px"><h2>Message Templates</h2></div>
        <p class="form-hint" style="margin-bottom:12px">
            Placeholders: <code>{name}</code> <code>{account}</code> <code>{amount}</code>
            <code>{due_date}</code> <code>{bill_number}</code> <code>{period}</code> <code>{balance}</code>
        </p>

        <div class="form-group">
            <label for="message_due">Due reminder message</label>
            <textarea name="message_due" id="message_due" rows="3" required><?= e($settings['message_due']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="message_overdue">Overdue message</label>
            <textarea name="message_overdue" id="message_overdue" rows="3" required><?= e($settings['message_overdue']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>

    <div>
        <form method="POST" class="card" style="margin-bottom:16px">
            <input type="hidden" name="action" value="test_sms">
            <div class="card-header"><h2>Send Test SMS</h2></div>
            <div class="form-group">
                <label for="test_phone">Mobile number</label>
                <input type="text" name="test_phone" id="test_phone" placeholder="09171234567" required>
            </div>
            <div class="form-group">
                <label for="test_message">Message (optional)</label>
                <textarea name="test_message" id="test_message" rows="2" placeholder="Leave blank for default test message"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-outline">Send Test</button>
            </div>
        </form>

        <form method="POST" class="card" style="margin-bottom:16px"
              onsubmit="return confirm('Send due-payment SMS to eligible subscribers now?');">
            <input type="hidden" name="action" value="send_due_now">
            <div class="card-header"><h2>Send Due Payment SMS</h2></div>
            <p class="form-hint" style="margin-bottom:12px">
                Sends to active subscribers with a valid PH mobile number and unpaid bills
                matching your rules. Already-notified bills are skipped unless you force resend.
                Currently pending: <strong><?= number_format($pendingCount) ?></strong>
            </p>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="force_resend" value="1">
                    Force resend (even if already sent for this bill)
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= empty($settings['sms_enabled']) ? 'disabled' : '' ?>>
                    Send Now
                </button>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h2>Cron (optional)</h2></div>
            <p class="form-hint" style="margin-bottom:8px">
                For reliable daily sending on a schedule, point a Windows Task Scheduler or cron job to:
            </p>
            <code style="display:block;word-break:break-all;font-size:12px;padding:10px;background:var(--panel-bg,#f8fafc);border-radius:6px">
                php <?= e(str_replace('\\', '/', realpath(__DIR__ . '/../cron/sms_due_payments.php') ?: (__DIR__ . '/../cron/sms_due_payments.php'))) ?>
            </code>
        </div>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">
        <h2>SMS Log</h2>
        <span class="form-hint">
            Sent <?= number_format($stats['sent']) ?> ·
            Failed <?= number_format($stats['failed']) ?> ·
            Skipped <?= number_format($stats['skipped']) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-compact">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Subscriber</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-muted">No SMS messages logged yet.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(formatDate(substr((string) $log['created_at'], 0, 10))) ?><br>
                        <small class="text-muted"><?= e(substr((string) $log['created_at'], 11, 8)) ?></small>
                    </td>
                    <td>
                        <?php if (!empty($log['account_number'])): ?>
                        <?= e($log['full_name'] ?? '') ?><br>
                        <small class="text-muted"><?= e($log['account_number']) ?></small>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($log['phone']) ?></td>
                    <td><span class="badge"><?= e(ucfirst($log['notification_type'])) ?></span></td>
                    <td>
                        <?php
                        $statusClass = match ($log['status']) {
                            'sent' => 'badge-success',
                            'failed' => 'badge-danger',
                            'skipped' => 'badge-warning',
                            default => '',
                        };
                        ?>
                        <span class="badge <?= $statusClass ?>"><?= e(ucfirst($log['status'])) ?></span>
                        <?php if (!empty($log['error_message'])): ?>
                        <br><small class="text-muted"><?= e($log['error_message']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:280px">
                        <small><?php
                            $msg = (string) $log['message'];
                            echo e(strlen($msg) > 120 ? substr($msg, 0, 117) . '…' : $msg);
                        ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
