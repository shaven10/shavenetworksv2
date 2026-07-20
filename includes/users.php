<?php

require_once __DIR__ . '/user_avatar.php';

function userDeleteTableExists(string $table): bool
{
    static $cache = [];

    if (!isset($cache[$table])) {
        $stmt = getDB()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
    }

    return $cache[$table];
}

function userReferenceCount(string $table, string $column, int $userId): int
{
    if (!userDeleteTableExists($table)) {
        return 0;
    }

    try {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function ensureUserDeleteDependencies(): void
{
    require_once __DIR__ . '/remittances.php';
    require_once __DIR__ . '/notifications.php';

    ensureRemittanceTables();
    ensureNotificationReadsTable();

    if (!userDeleteTableExists('payment_batches')) {
        getDB()->exec(
            "CREATE TABLE IF NOT EXISTS payment_batches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                batch_invoice_number VARCHAR(30) NOT NULL UNIQUE,
                customer_id INT NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                payment_count INT NOT NULL,
                payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check') DEFAULT 'cash',
                reference_number VARCHAR(50) NULL,
                notes TEXT NULL,
                collected_by INT NOT NULL,
                payment_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id),
                FOREIGN KEY (collected_by) REFERENCES users(id)
            )"
        );
    }
}

function runUserDeleteStatement(PDO $db, string $sql, array $params, ?string $table = null): void
{
    if ($table !== null && !userDeleteTableExists($table)) {
        return;
    }

    $db->prepare($sql)->execute($params);
}

function countActiveOwners(?int $excludeUserId = null): int
{
    $sql = "SELECT COUNT(*) FROM users WHERE role = 'owner' AND is_active = 1";
    $params = [];

    if ($excludeUserId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeUserId;
    }

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function getUserReassignTargetId(int $excludeUserId): ?int
{
    $stmt = getDB()->prepare(
        "SELECT id FROM users WHERE role = 'owner' AND is_active = 1 AND id != ? ORDER BY id LIMIT 1"
    );
    $stmt->execute([$excludeUserId]);
    $ownerId = $stmt->fetchColumn();
    if ($ownerId) {
        return (int) $ownerId;
    }

    $stmt = getDB()->prepare(
        'SELECT id FROM users WHERE is_active = 1 AND id != ? ORDER BY id LIMIT 1'
    );
    $stmt->execute([$excludeUserId]);
    $userId = $stmt->fetchColumn();

    return $userId ? (int) $userId : null;
}

function getUserDeleteStatus(int $userId): array
{
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'allowed' => false,
            'reason'  => 'User not found.',
            'user'    => null,
        ];
    }

    $current = currentUser();
    if ($current && (int) $current['id'] === $userId) {
        return [
            'allowed' => false,
            'reason'  => 'You cannot remove your own account while logged in.',
            'user'    => $user,
        ];
    }

    if ($user['role'] === 'owner' && countActiveOwners($userId) === 0) {
        return [
            'allowed' => false,
            'reason'  => 'Cannot remove the last active owner account.',
            'user'    => $user,
        ];
    }

    if (getTotalUserCount($userId) === 0) {
        return [
            'allowed' => false,
            'reason'  => 'Cannot remove the only remaining user account.',
            'user'    => $user,
        ];
    }

    return [
        'allowed' => true,
        'reason'  => '',
        'user'    => $user,
    ];
}

function getTotalUserCount(?int $excludeUserId = null): int
{
    $sql = 'SELECT COUNT(*) FROM users';
    $params = [];

    if ($excludeUserId) {
        $sql .= ' WHERE id != ?';
        $params[] = $excludeUserId;
    }

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function getUserDeleteSummary(int $userId): array
{
    ensureUserDeleteDependencies();

    return [
        'payments'              => userReferenceCount('payments', 'collected_by', $userId),
        'payment_batches'       => userReferenceCount('payment_batches', 'collected_by', $userId),
        'remittances_submitted' => userReferenceCount('remittances', 'submitted_by', $userId),
        'remittances_confirmed' => userReferenceCount('remittances', 'confirmed_by', $userId),
        'customers_created'     => userReferenceCount('customers', 'created_by', $userId),
        'tickets_created'       => userReferenceCount('repair_tickets', 'created_by', $userId),
        'tickets_assigned'      => userReferenceCount('repair_tickets', 'assigned_to', $userId),
        'inquiries_created'     => userReferenceCount('inquiries', 'created_by', $userId),
        'inquiries_responded'   => userReferenceCount('inquiries', 'responded_by', $userId),
        'activity_logs'         => userReferenceCount('activity_logs', 'user_id', $userId),
    ];
}

function deleteUserAccount(int $userId): void
{
    $status = getUserDeleteStatus($userId);
    if (!$status['allowed']) {
        throw new RuntimeException($status['reason']);
    }

    $user = $status['user'];
    $reassignTo = getUserReassignTargetId($userId);

    if (!$reassignTo) {
        throw new RuntimeException('No active user is available to reassign historical records.');
    }

    ensureUserDeleteDependencies();

    $db = getDB();
    $db->beginTransaction();

    try {
        runUserDeleteStatement($db, 'UPDATE payments SET collected_by = ? WHERE collected_by = ?', [$reassignTo, $userId]);
        runUserDeleteStatement($db, 'UPDATE payment_batches SET collected_by = ? WHERE collected_by = ?', [$reassignTo, $userId], 'payment_batches');
        runUserDeleteStatement($db, 'UPDATE remittances SET submitted_by = ? WHERE submitted_by = ?', [$reassignTo, $userId], 'remittances');
        runUserDeleteStatement($db, 'UPDATE repair_tickets SET created_by = ? WHERE created_by = ?', [$reassignTo, $userId], 'repair_tickets');
        runUserDeleteStatement($db, 'UPDATE inquiries SET created_by = ? WHERE created_by = ?', [$reassignTo, $userId], 'inquiries');

        runUserDeleteStatement($db, 'UPDATE remittances SET confirmed_by = NULL WHERE confirmed_by = ?', [$userId], 'remittances');
        runUserDeleteStatement($db, 'UPDATE customers SET created_by = NULL WHERE created_by = ?', [$userId]);
        runUserDeleteStatement($db, 'UPDATE repair_tickets SET assigned_to = NULL WHERE assigned_to = ?', [$userId], 'repair_tickets');
        runUserDeleteStatement($db, 'UPDATE inquiries SET responded_by = NULL WHERE responded_by = ?', [$userId], 'inquiries');
        runUserDeleteStatement($db, 'UPDATE activity_logs SET user_id = NULL WHERE user_id = ?', [$userId]);
        runUserDeleteStatement($db, 'DELETE FROM notification_reads WHERE user_id = ?', [$userId], 'notification_reads');

        removeUserAvatarFile($user['avatar'] ?? null);

        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();

        if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
            throw new RuntimeException(
                'This user is still linked to system records that could not be reassigned automatically. Please contact support.',
                0,
                $e
            );
        }

        throw new RuntimeException('Database error while removing user: ' . $e->getMessage(), 0, $e);
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function renderUserDeleteSection(array $user): void
{
    $userId = (int) $user['id'];
    $status = getUserDeleteStatus($userId);

    if (!$status['allowed'] && $status['reason'] === 'User not found.') {
        return;
    }

    $summary = [];
    if ($status['allowed']) {
        try {
            $summary = getUserDeleteSummary($userId);
        } catch (Throwable $e) {
            $summary = [];
        }
    }
    ?>
<div class="card danger-zone" id="remove-user-section">
    <div class="card-header"><h2>Remove User</h2></div>
    <?php if (!$status['allowed']): ?>
    <p class="danger-intro"><?= e($status['reason']) ?></p>
    <?php if ($status['reason'] === 'Cannot remove the last active owner account.'): ?>
    <p class="text-muted">Create or activate another owner account first, then try again.</p>
    <?php endif; ?>
    <?php else: ?>
    <p class="danger-intro">
        Permanently remove <strong><?= e($user['username']) ?></strong> — <?= e($user['full_name']) ?>.
        Historical records such as payments and remittances will be reassigned to another owner account.
    </p>
    <ul class="delete-summary">
        <?php if ($summary['payments']): ?><li><?= number_format($summary['payments']) ?> payment record(s)</li><?php endif; ?>
        <?php if ($summary['payment_batches']): ?><li><?= number_format($summary['payment_batches']) ?> batch payment(s)</li><?php endif; ?>
        <?php if ($summary['remittances_submitted']): ?><li><?= number_format($summary['remittances_submitted']) ?> remittance(s) submitted</li><?php endif; ?>
        <?php if ($summary['customers_created']): ?><li><?= number_format($summary['customers_created']) ?> customer(s) created</li><?php endif; ?>
        <?php if ($summary['tickets_created'] || $summary['tickets_assigned']): ?>
        <li><?= number_format($summary['tickets_created'] + $summary['tickets_assigned']) ?> support ticket link(s)</li>
        <?php endif; ?>
        <?php if ($summary['inquiries_created'] || $summary['inquiries_responded']): ?>
        <li><?= number_format($summary['inquiries_created'] + $summary['inquiries_responded']) ?> inquiry link(s)</li>
        <?php endif; ?>
        <?php if ($summary['activity_logs']): ?><li><?= number_format($summary['activity_logs']) ?> activity log(s)</li><?php endif; ?>
    </ul>
    <form method="POST" action="<?= APP_URL ?>/users/delete.php" class="delete-account-form">
        <input type="hidden" name="id" value="<?= $userId ?>">
        <div class="form-group">
            <label for="delete_confirm_user_<?= $userId ?>">Type DELETE to confirm *</label>
            <input type="text" id="delete_confirm_user_<?= $userId ?>" name="confirm_text"
                   placeholder="DELETE" autocomplete="off" required>
        </div>
        <button type="submit" class="btn btn-danger">Remove User</button>
    </form>
    <?php endif; ?>
</div>
    <?php
}
