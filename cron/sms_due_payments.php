<?php
/**
 * CLI / scheduled task: send due-payment SMS via Semaphore.
 *
 * Usage:
 *   php cron/sms_due_payments.php
 *
 * Optional Windows Task Scheduler example (daily 8:00 AM):
 *   php.exe D:\SC30\htdocs\shavenetworksv2\cron\sms_due_payments.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/sms.php';

ensureSmsNotificationsTable();
updateOverdueBills();

$settings = getApiSettings();
if (empty($settings['sms_enabled'])) {
    echo '[' . date('Y-m-d H:i:s') . "] SMS disabled — nothing to do.\n";
    exit(0);
}

$result = processDuePaymentSms(false);
updateApiSettingsMeta(['last_auto_run' => date('Y-m-d')]);

echo '[' . date('Y-m-d H:i:s') . '] ';
echo sprintf(
    "sent=%d failed=%d skipped=%d\n",
    $result['sent'],
    $result['failed'],
    $result['skipped']
);

if (!empty($result['errors'])) {
    foreach (array_slice($result['errors'], 0, 10) as $err) {
        echo "  - {$err}\n";
    }
}

exit($result['failed'] > 0 && $result['sent'] === 0 ? 1 : 0);
