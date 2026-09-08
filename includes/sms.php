<?php

/**
 * Semaphore SMS integration for due-payment notifications.
 */

function getApiSettingsFilePath(): string
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir . '/api_settings.json';
}

function getDefaultApiSettings(): array
{
    return [
        'semaphore_api_key'     => '',
        'semaphore_sender_name' => 'SHAVEN',
        'sms_enabled'           => false,
        'notify_days_before'    => 3,
        'notify_on_due'         => true,
        'notify_on_overdue'     => true,
        'auto_send'             => true,
        'message_due'           => 'Hi {name}, your SHAVEN Networks bill {bill_number} of {amount} is due on {due_date}. Please settle to avoid interruption. Thank you.',
        'message_overdue'       => 'Hi {name}, your SHAVEN Networks bill {bill_number} of {amount} is OVERDUE (due {due_date}). Please pay ASAP to avoid disconnection. Thank you.',
        'last_auto_run'         => null,
    ];
}

function getApiSettings(): array
{
    if (!array_key_exists('_api_settings_cache', $GLOBALS) || $GLOBALS['_api_settings_cache'] === null) {
        $defaults = getDefaultApiSettings();
        $path = getApiSettingsFilePath();

        if (is_file($path)) {
            $saved = json_decode((string) file_get_contents($path), true);
            if (is_array($saved)) {
                $GLOBALS['_api_settings_cache'] = array_merge($defaults, $saved);
                return $GLOBALS['_api_settings_cache'];
            }
        }

        $GLOBALS['_api_settings_cache'] = $defaults;
    }

    return $GLOBALS['_api_settings_cache'];
}

function persistApiSettings(array $data): array
{
    $defaults = getDefaultApiSettings();
    $current = getApiSettings();

    $apiKey = trim((string) ($data['semaphore_api_key'] ?? ''));
    if (!empty($data['clear_api_key'])) {
        $apiKey = '';
    } elseif ($apiKey === '' && !empty($current['semaphore_api_key'])) {
        $apiKey = $current['semaphore_api_key'];
    }

    $settings = [
        'semaphore_api_key'     => $apiKey,
        'semaphore_sender_name' => trim((string) ($data['semaphore_sender_name'] ?? $defaults['semaphore_sender_name'])) ?: $defaults['semaphore_sender_name'],
        'sms_enabled'           => !empty($data['sms_enabled']),
        'notify_days_before'    => max(0, min(30, (int) ($data['notify_days_before'] ?? $defaults['notify_days_before']))),
        'notify_on_due'         => !empty($data['notify_on_due']),
        'notify_on_overdue'     => !empty($data['notify_on_overdue']),
        'auto_send'             => !empty($data['auto_send']),
        'message_due'           => trim((string) ($data['message_due'] ?? $defaults['message_due'])) ?: $defaults['message_due'],
        'message_overdue'       => trim((string) ($data['message_overdue'] ?? $defaults['message_overdue'])) ?: $defaults['message_overdue'],
        'last_auto_run'         => $current['last_auto_run'] ?? null,
    ];

    file_put_contents(
        getApiSettingsFilePath(),
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    $GLOBALS['_api_settings_cache'] = $settings;
    return $settings;
}

function updateApiSettingsMeta(array $patch): void
{
    $settings = array_merge(getApiSettings(), $patch);
    file_put_contents(
        getApiSettingsFilePath(),
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    $GLOBALS['_api_settings_cache'] = $settings;
}

function maskApiKey(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }
    $len = strlen($key);
    if ($len <= 8) {
        return str_repeat('•', $len);
    }
    return substr($key, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($key, -4);
}

function ensureSmsNotificationsTable(): void
{
    getDB()->exec(
        "CREATE TABLE IF NOT EXISTS sms_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bill_id INT NULL,
            customer_id INT NULL,
            phone VARCHAR(20) NOT NULL,
            notification_type ENUM('due','overdue','test','manual') NOT NULL DEFAULT 'due',
            message TEXT NOT NULL,
            status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            provider_message_id VARCHAR(50) NULL,
            provider_response TEXT NULL,
            error_message TEXT NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sms_bill_type (bill_id, notification_type),
            INDEX idx_sms_customer (customer_id),
            INDEX idx_sms_status (status),
            INDEX idx_sms_created (created_at),
            FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        )"
    );
}

/**
 * Normalize Philippine mobile numbers for Semaphore (09XXXXXXXXX).
 */
function formatSemaphorePhone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return null;
    }

    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        $digits = '0' . substr($digits, 2);
    }

    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }

    return null;
}

function isValidSubscriberPhone(string $phone): bool
{
    return formatSemaphorePhone($phone) !== null;
}

function buildSmsMessage(string $template, array $bill): string
{
    $balance = max(0, (float) $bill['amount'] - (float) $bill['paid_amount']);

    $replacements = [
        '{name}'        => $bill['full_name'] ?? '',
        '{account}'     => $bill['account_number'] ?? '',
        '{amount}'      => formatMoney($balance),
        '{balance}'     => formatMoney($balance),
        '{due_date}'    => formatDate($bill['due_date'] ?? null),
        '{bill_number}' => $bill['bill_number'] ?? '',
        '{period}'      => trim(
            formatDate($bill['billing_period_start'] ?? null) . ' – ' . formatDate($bill['billing_period_end'] ?? null),
            ' –'
        ),
    ];

    return strtr($template, $replacements);
}

/**
 * Send one SMS via Semaphore API.
 *
 * @return array{success:bool,message_id:?string,response:?string,error:?string}
 */
function sendSemaphoreSms(string $number, string $message, ?string $apiKey = null, ?string $senderName = null): array
{
    $settings = getApiSettings();
    $apiKey = $apiKey ?? ($settings['semaphore_api_key'] ?? '');
    $senderName = $senderName ?? ($settings['semaphore_sender_name'] ?? '');

    $number = formatSemaphorePhone($number);
    if ($number === null) {
        return [
            'success'    => false,
            'message_id' => null,
            'response'   => null,
            'error'      => 'Invalid Philippine mobile number.',
        ];
    }

    if (trim($apiKey) === '') {
        return [
            'success'    => false,
            'message_id' => null,
            'response'   => null,
            'error'      => 'Semaphore API key is not configured.',
        ];
    }

    $message = trim($message);
    if ($message === '') {
        return [
            'success'    => false,
            'message_id' => null,
            'response'   => null,
            'error'      => 'Message is empty.',
        ];
    }

    // Semaphore silently ignores messages that start with "TEST"
    if (preg_match('/^\s*TEST\b/i', $message)) {
        $message = 'Notice: ' . $message;
    }

    $params = [
        'apikey'  => $apiKey,
        'number'  => $number,
        'message' => $message,
    ];
    if (trim((string) $senderName) !== '') {
        $params['sendername'] = trim((string) $senderName);
    }

    if (!function_exists('curl_init')) {
        return [
            'success'    => false,
            'message_id' => null,
            'response'   => null,
            'error'      => 'PHP cURL extension is required to send SMS.',
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return [
            'success'    => false,
            'message_id' => null,
            'response'   => null,
            'error'      => 'cURL error: ' . ($curlError ?: 'unknown'),
        ];
    }

    $decoded = json_decode($raw, true);
    $responseStr = is_string($raw) ? $raw : null;

    if (is_array($decoded)) {
        if (isset($decoded['message']) && !isset($decoded[0]) && $httpCode >= 400) {
            return [
                'success'    => false,
                'message_id' => null,
                'response'   => $responseStr,
                'error'      => (string) $decoded['message'],
            ];
        }
        if (isset($decoded['error'])) {
            $err = is_array($decoded['error']) ? json_encode($decoded['error']) : (string) $decoded['error'];
            return [
                'success'    => false,
                'message_id' => null,
                'response'   => $responseStr,
                'error'      => $err,
            ];
        }

        $first = isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : $decoded;
        $status = strtolower((string) ($first['status'] ?? ''));
        $messageId = isset($first['message_id']) ? (string) $first['message_id'] : null;

        if ($status === 'failed' || $httpCode >= 400) {
            return [
                'success'    => false,
                'message_id' => $messageId,
                'response'   => $responseStr,
                'error'      => (string) ($first['message'] ?? $first['network'] ?? 'Semaphore rejected the message.'),
            ];
        }

        return [
            'success'    => true,
            'message_id' => $messageId,
            'response'   => $responseStr,
            'error'      => null,
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success'    => true,
            'message_id' => null,
            'response'   => $responseStr,
            'error'      => null,
        ];
    }

    return [
        'success'    => false,
        'message_id' => null,
        'response'   => $responseStr,
        'error'      => 'Unexpected Semaphore response (HTTP ' . $httpCode . ').',
    ];
}

function logSmsNotification(array $data): int
{
    ensureSmsNotificationsTable();

    $stmt = getDB()->prepare(
        'INSERT INTO sms_notifications
            (bill_id, customer_id, phone, notification_type, message, status, provider_message_id, provider_response, error_message, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $status = $data['status'] ?? 'pending';
    $sentAt = in_array($status, ['sent', 'failed', 'skipped'], true) ? date('Y-m-d H:i:s') : null;

    $stmt->execute([
        $data['bill_id'] ?? null,
        $data['customer_id'] ?? null,
        $data['phone'],
        $data['notification_type'] ?? 'due',
        $data['message'],
        $status,
        $data['provider_message_id'] ?? null,
        $data['provider_response'] ?? null,
        $data['error_message'] ?? null,
        $sentAt,
    ]);

    return (int) getDB()->lastInsertId();
}

function hasSuccessfulSmsForBill(int $billId, string $type): bool
{
    ensureSmsNotificationsTable();

    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM sms_notifications
         WHERE bill_id = ? AND notification_type = ? AND status = 'sent'"
    );
    $stmt->execute([$billId, $type]);

    return (int) $stmt->fetchColumn() > 0;
}

function hasSkippedSmsForBill(int $billId, string $type): bool
{
    ensureSmsNotificationsTable();

    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM sms_notifications
         WHERE bill_id = ? AND notification_type = ? AND status = 'skipped'"
    );
    $stmt->execute([$billId, $type]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Find unpaid bills that need SMS (due soon or overdue).
 */
function getBillsNeedingSms(?array $settings = null): array
{
    $settings = $settings ?? getApiSettings();
    $daysBefore = (int) ($settings['notify_days_before'] ?? 3);
    $rows = [];

    if (!empty($settings['notify_on_due'])) {
        $stmt = getDB()->prepare(
            "SELECT b.id, b.bill_number, b.amount, b.paid_amount, b.due_date, b.status,
                    b.billing_period_start, b.billing_period_end, b.customer_id,
                    c.full_name, c.account_number, c.phone, c.status AS customer_status
             FROM bills b
             JOIN customers c ON c.id = b.customer_id
             WHERE b.status IN ('pending', 'partial')
               AND c.status = 'active'
               AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY b.due_date ASC, c.full_name ASC"
        );
        $stmt->execute([$daysBefore]);
        foreach ($stmt->fetchAll() as $row) {
            $row['_sms_type'] = 'due';
            $rows[] = $row;
        }
    }

    if (!empty($settings['notify_on_overdue'])) {
        $stmt = getDB()->query(
            "SELECT b.id, b.bill_number, b.amount, b.paid_amount, b.due_date, b.status,
                    b.billing_period_start, b.billing_period_end, b.customer_id,
                    c.full_name, c.account_number, c.phone, c.status AS customer_status
             FROM bills b
             JOIN customers c ON c.id = b.customer_id
             WHERE b.status IN ('pending', 'partial', 'overdue')
               AND c.status = 'active'
               AND b.due_date < CURDATE()
             ORDER BY b.due_date ASC, c.full_name ASC"
        );
        foreach ($stmt->fetchAll() as $row) {
            $row['_sms_type'] = 'overdue';
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Send due/overdue SMS notifications. Skips bills already notified successfully.
 *
 * @return array{sent:int,failed:int,skipped:int,errors:string[]}
 */
function processDuePaymentSms(bool $forceResend = false, ?int $limit = null): array
{
    ensureSmsNotificationsTable();
    if (function_exists('updateOverdueBills')) {
        updateOverdueBills();
    }

    $settings = getApiSettings();
    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

    if (empty($settings['sms_enabled'])) {
        $result['errors'][] = 'SMS notifications are disabled in API settings.';
        return $result;
    }

    if (trim((string) ($settings['semaphore_api_key'] ?? '')) === '') {
        $result['errors'][] = 'Semaphore API key is not configured.';
        return $result;
    }

    $bills = getBillsNeedingSms($settings);
    $processed = 0;

    foreach ($bills as $bill) {
        if ($limit !== null && $processed >= $limit) {
            break;
        }

        $type = $bill['_sms_type'] ?? 'due';
        $billId = (int) $bill['id'];
        $customerId = (int) $bill['customer_id'];
        $phoneRaw = (string) $bill['phone'];
        $phone = formatSemaphorePhone($phoneRaw);

        if ($phone === null) {
            if (!hasSkippedSmsForBill($billId, $type)) {
                logSmsNotification([
                    'bill_id'           => $billId,
                    'customer_id'       => $customerId,
                    'phone'             => $phoneRaw,
                    'notification_type' => $type,
                    'message'           => '(skipped — invalid phone)',
                    'status'            => 'skipped',
                    'error_message'     => 'Invalid or missing phone number.',
                ]);
            }
            $result['skipped']++;
            $processed++;
            continue;
        }

        if (!$forceResend && hasSuccessfulSmsForBill($billId, $type)) {
            $result['skipped']++;
            continue;
        }

        $template = $type === 'overdue'
            ? ($settings['message_overdue'] ?? '')
            : ($settings['message_due'] ?? '');
        $message = buildSmsMessage($template, $bill);

        $send = sendSemaphoreSms($phone, $message);
        $processed++;

        if ($send['success']) {
            $result['sent']++;
            logSmsNotification([
                'bill_id'             => $billId,
                'customer_id'         => $customerId,
                'phone'               => $phone,
                'notification_type'   => $type,
                'message'             => $message,
                'status'              => 'sent',
                'provider_message_id' => $send['message_id'],
                'provider_response'   => $send['response'],
            ]);
        } else {
            $result['failed']++;
            $err = $send['error'] ?? 'Unknown error';
            $result['errors'][] = ($bill['account_number'] ?? '') . ': ' . $err;
            logSmsNotification([
                'bill_id'             => $billId,
                'customer_id'         => $customerId,
                'phone'               => $phone,
                'notification_type'   => $type,
                'message'             => $message,
                'status'              => 'failed',
                'provider_message_id' => $send['message_id'],
                'provider_response'   => $send['response'],
                'error_message'       => $err,
            ]);
        }

        // Stay under Semaphore's 120 requests/minute limit
        usleep(100000);
    }

    return $result;
}

/**
 * Auto-run once per calendar day when enabled (request-driven; also usable from cron).
 * Caps volume on web requests so page loads stay responsive.
 */
function maybeAutoSendDuePaymentSms(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $settings = getApiSettings();
    if (empty($settings['sms_enabled']) || empty($settings['auto_send'])) {
        return;
    }
    if (trim((string) ($settings['semaphore_api_key'] ?? '')) === '') {
        return;
    }

    $today = date('Y-m-d');
    $lastRun = $settings['last_auto_run'] ?? null;
    if ($lastRun === $today) {
        return;
    }

    // Mark run date first to avoid duplicate bursts on concurrent requests
    updateApiSettingsMeta(['last_auto_run' => $today]);

    try {
        // Limit web-triggered sends; use cron for full daily batches
        processDuePaymentSms(false, 15);
    } catch (Throwable $e) {
        error_log('SMS auto-send failed: ' . $e->getMessage());
    }
}

function getSmsNotificationLogs(int $limit = 50, int $offset = 0): array
{
    ensureSmsNotificationsTable();
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $stmt = getDB()->prepare(
        "SELECT s.*, c.full_name, c.account_number
         FROM sms_notifications s
         LEFT JOIN customers c ON c.id = s.customer_id
         ORDER BY s.created_at DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function getSmsNotificationStats(): array
{
    ensureSmsNotificationsTable();
    $row = getDB()->query(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'sent') AS sent,
            SUM(status = 'failed') AS failed,
            SUM(status = 'skipped') AS skipped,
            SUM(DATE(created_at) = CURDATE()) AS today
         FROM sms_notifications"
    )->fetch() ?: [];

    return [
        'total'   => (int) ($row['total'] ?? 0),
        'sent'    => (int) ($row['sent'] ?? 0),
        'failed'  => (int) ($row['failed'] ?? 0),
        'skipped' => (int) ($row['skipped'] ?? 0),
        'today'   => (int) ($row['today'] ?? 0),
    ];
}

function countPendingSmsTargets(): int
{
    $settings = getApiSettings();
    $bills = getBillsNeedingSms($settings);
    $count = 0;

    foreach ($bills as $bill) {
        $type = $bill['_sms_type'] ?? 'due';
        if (!isValidSubscriberPhone((string) $bill['phone'])) {
            continue;
        }
        if (hasSuccessfulSmsForBill((int) $bill['id'], $type)) {
            continue;
        }
        $count++;
    }

    return $count;
}

function sendTestSms(string $phone, ?string $customMessage = null): array
{
    $formatted = formatSemaphorePhone($phone);
    if ($formatted === null) {
        return ['success' => false, 'message' => 'Enter a valid Philippine mobile number (e.g. 09171234567).'];
    }

    $message = trim((string) $customMessage);
    if ($message === '') {
        $message = 'SHAVEN Networks SMS test: your Semaphore API settings are working correctly.';
    }

    $send = sendSemaphoreSms($formatted, $message);

    $customerId = null;
    $stmt = getDB()->prepare('SELECT id FROM customers WHERE REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") LIKE ? LIMIT 1');
    $stmt->execute(['%' . substr($formatted, -10)]);
    $customerId = $stmt->fetchColumn() ?: null;

    logSmsNotification([
        'bill_id'             => null,
        'customer_id'         => $customerId ? (int) $customerId : null,
        'phone'               => $formatted,
        'notification_type'   => 'test',
        'message'             => $message,
        'status'              => $send['success'] ? 'sent' : 'failed',
        'provider_message_id' => $send['message_id'],
        'provider_response'   => $send['response'],
        'error_message'       => $send['error'],
    ]);

    if ($send['success']) {
        return ['success' => true, 'message' => 'Test SMS sent to ' . $formatted . '.'];
    }

    return ['success' => false, 'message' => $send['error'] ?? 'Failed to send test SMS.'];
}
