<?php

function connectionMediumOptions(): array
{
    return [
        'fiber_olt'      => 'Fiber Optic (OLT)',
        'fiber_mediacon' => 'Fiber Optic (Mediacon)',
        'wireless_radio' => 'Wireless Radio',
    ];
}

function connectionMediumLabel(?string $medium): string
{
    if (!$medium) {
        return '—';
    }

    return connectionMediumOptions()[$medium] ?? ucfirst(str_replace('_', ' ', $medium));
}

function connectionMediumBadge(?string $medium): string
{
    if (!$medium) {
        return '<span class="badge badge-secondary">—</span>';
    }

    $classes = [
        'fiber_olt'      => 'badge-info',
        'fiber_mediacon' => 'badge-success',
        'wireless_radio' => 'badge-warning',
    ];
    $class = $classes[$medium] ?? 'badge-secondary';

    return '<span class="badge ' . $class . '">' . e(connectionMediumLabel($medium)) . '</span>';
}

function formatCustomerAddress(array $customer): string
{
    $parts = array_filter([
        trim($customer['address'] ?? ''),
        trim($customer['barangay'] ?? '') !== '' ? 'Brgy. ' . trim($customer['barangay']) : '',
        trim($customer['city'] ?? ''),
        trim($customer['province'] ?? ''),
    ]);

    return $parts ? implode(', ', $parts) : '—';
}

function validateCustomerAddressFields(array $data): array
{
    $errors = [];

    if (trim($data['address'] ?? '') === '') {
        $errors[] = 'Street / building address is required.';
    }
    if (trim($data['city'] ?? '') === '') {
        $errors[] = 'City / municipality is required.';
    }
    if (trim($data['province'] ?? '') === '') {
        $errors[] = 'Province is required.';
    }

    $medium = $data['connection_medium'] ?? '';
    if (!array_key_exists($medium, connectionMediumOptions())) {
        $errors[] = 'Medium of connection is required.';
    }

    return $errors;
}

function normalizeCustomerFormData(array $post): array
{
    $fromYear = trim($post['billing_generate_from_year'] ?? '');
    $toYear = trim($post['billing_generate_to_year'] ?? '');

    return [
        'full_name'                  => trim($post['full_name'] ?? ''),
        'email'                      => trim($post['email'] ?? ''),
        'phone'                      => trim($post['phone'] ?? ''),
        'connection_medium'          => trim($post['connection_medium'] ?? ''),
        'address'                    => trim($post['address'] ?? ''),
        'barangay'                   => trim($post['barangay'] ?? ''),
        'city'                       => trim($post['city'] ?? ''),
        'province'                   => trim($post['province'] ?? ''),
        'plan_id'                    => (int) ($post['plan_id'] ?? 0),
        'installation_date'          => $post['installation_date'] ?? '',
        'status'                     => $post['status'] ?? 'active',
        'notes'                      => trim($post['notes'] ?? ''),
        'billing_generate_from_year' => $fromYear !== '' ? (int) $fromYear : null,
        'billing_generate_to_year'   => $toYear !== '' ? (int) $toYear : null,
    ];
}

function validateBillingYearRange(?int $fromYear, ?int $toYear): array
{
    $errors = [];
    $currentYear = (int) date('Y');
    $minYear = 2000;
    $maxYear = $currentYear + 5;

    if ($fromYear !== null && ($fromYear < $minYear || $fromYear > $maxYear)) {
        $errors[] = "Bill generation from year must be between {$minYear} and {$maxYear}.";
    }

    if ($toYear !== null && ($toYear < $minYear || $toYear > $maxYear)) {
        $errors[] = "Bill generation to year must be between {$minYear} and {$maxYear}.";
    }

    if ($fromYear !== null && $toYear !== null && $fromYear > $toYear) {
        $errors[] = 'Bill generation from year cannot be later than the to year.';
    }

    return $errors;
}

function billingYearOptions(int $yearsBack = 10, int $yearsForward = 2): array
{
    $currentYear = (int) date('Y');
    $years = [];

    for ($year = $currentYear - $yearsBack; $year <= $currentYear + $yearsForward; $year++) {
        $years[] = $year;
    }

    return $years;
}

function ensureCustomerPlanHistoryTable(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    getDB()->exec(
        "CREATE TABLE IF NOT EXISTS customer_plan_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            plan_id INT NOT NULL,
            plan_name VARCHAR(100) NOT NULL,
            speed_mbps INT NOT NULL,
            monthly_fee DECIMAL(10,2) NOT NULL,
            started_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            changed_by INT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_plan_started (customer_id, started_at)
        )"
    );
    $ready = true;
}

function getPlanSnapshot(int $planId): ?array
{
    $stmt = getDB()->prepare('SELECT id, name, speed_mbps, monthly_fee FROM service_plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    return $plan ?: null;
}

function normalizePlanEffectiveAt(?string $effectiveAt): string
{
    if ($effectiveAt === null || trim($effectiveAt) === '') {
        return date('Y-m-d 00:00:00');
    }

    $ts = strtotime($effectiveAt);
    if ($ts === false) {
        throw new RuntimeException('Invalid plan effective date.');
    }

    return date('Y-m-d 00:00:00', $ts);
}

function ensureCustomerHasPlanHistory(int $customerId): void
{
    ensureCustomerPlanHistoryTable();
    $db = getDB();

    $stmt = $db->prepare('SELECT COUNT(*) FROM customer_plan_history WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $customerStmt = $db->prepare(
        'SELECT c.plan_id, c.installation_date, c.created_by, c.created_at,
                p.name, p.speed_mbps, p.monthly_fee
         FROM customers c
         JOIN service_plans p ON p.id = c.plan_id
         WHERE c.id = ?'
    );
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer) {
        return;
    }

    $startedAt = !empty($customer['installation_date'])
        ? $customer['installation_date'] . ' 00:00:00'
        : ($customer['created_at'] ?? date('Y-m-d H:i:s'));

    $db->prepare(
        'INSERT INTO customer_plan_history
            (customer_id, plan_id, plan_name, speed_mbps, monthly_fee, started_at, ended_at, changed_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)'
    )->execute([
        $customerId,
        (int) $customer['plan_id'],
        $customer['name'],
        (int) $customer['speed_mbps'],
        $customer['monthly_fee'],
        $startedAt,
        $customer['created_by'] !== null ? (int) $customer['created_by'] : null,
        'Backfilled from current plan',
    ]);
}

function getCustomerPlanRateForDate(int $customerId, string $asOfDate, float $fallbackFee): float
{
    ensureCustomerHasPlanHistory($customerId);

    $asOf = date('Y-m-d 00:00:00', strtotime($asOfDate) ?: time());
    $stmt = getDB()->prepare(
        'SELECT monthly_fee
         FROM customer_plan_history
         WHERE customer_id = ?
           AND started_at <= ?
           AND (ended_at IS NULL OR ended_at > ?)
         ORDER BY started_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([$customerId, $asOf, $asOf]);
    $fee = $stmt->fetchColumn();

    return $fee !== false ? (float) $fee : $fallbackFee;
}

function recordCustomerPlanStart(
    int $customerId,
    int $planId,
    ?string $startedAt = null,
    ?int $changedBy = null,
    ?string $notes = null
): void {
    ensureCustomerPlanHistoryTable();
    $plan = getPlanSnapshot($planId);
    if (!$plan) {
        throw new RuntimeException('Service plan not found.');
    }

    $stmt = getDB()->prepare(
        'INSERT INTO customer_plan_history
            (customer_id, plan_id, plan_name, speed_mbps, monthly_fee, started_at, ended_at, changed_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)'
    );
    $stmt->execute([
        $customerId,
        $plan['id'],
        $plan['name'],
        $plan['speed_mbps'],
        $plan['monthly_fee'],
        $startedAt ?: date('Y-m-d H:i:s'),
        $changedBy,
        $notes,
    ]);
}

function recordCustomerPlanChange(
    int $customerId,
    int $oldPlanId,
    int $newPlanId,
    ?int $changedBy = null,
    ?string $notes = null,
    ?string $effectiveAt = null
): void {
    if ($oldPlanId === $newPlanId) {
        return;
    }

    $db = getDB();
    ensureCustomerPlanHistoryTable();
    ensureCustomerHasPlanHistory($customerId);

    $effective = normalizePlanEffectiveAt($effectiveAt);

    $customerStmt = $db->prepare('SELECT installation_date FROM customers WHERE id = ?');
    $customerStmt->execute([$customerId]);
    $installationDate = $customerStmt->fetchColumn();
    if ($installationDate && strtotime($effective) < strtotime($installationDate . ' 00:00:00')) {
        throw new RuntimeException('Plan effective date cannot be before the installation date.');
    }

    $openStmt = $db->prepare(
        'SELECT id, plan_id, started_at FROM customer_plan_history
         WHERE customer_id = ? AND ended_at IS NULL
         ORDER BY started_at DESC, id DESC LIMIT 1'
    );
    $openStmt->execute([$customerId]);
    $open = $openStmt->fetch();

    if ($open) {
        if (strtotime((string) $open['started_at']) > strtotime($effective)) {
            throw new RuntimeException(
                'Plan effective date must be on or after the current plan start ('
                . formatDate((string) $open['started_at']) . ').'
            );
        }

        if (strtotime((string) $open['started_at']) === strtotime($effective)) {
            $plan = getPlanSnapshot($newPlanId);
            if (!$plan) {
                throw new RuntimeException('Service plan not found.');
            }
            $db->prepare(
                'UPDATE customer_plan_history
                 SET plan_id = ?, plan_name = ?, speed_mbps = ?, monthly_fee = ?, changed_by = ?, notes = ?
                 WHERE id = ?'
            )->execute([
                $plan['id'],
                $plan['name'],
                $plan['speed_mbps'],
                $plan['monthly_fee'],
                $changedBy,
                $notes ?: 'Plan updated',
                (int) $open['id'],
            ]);
            return;
        }

        $db->prepare('UPDATE customer_plan_history SET ended_at = ? WHERE id = ?')
            ->execute([$effective, (int) $open['id']]);
    } else {
        $oldPlan = getPlanSnapshot($oldPlanId);
        if ($oldPlan) {
            $startPrior = $installationDate
                ? $installationDate . ' 00:00:00'
                : $effective;
            assertPlanHistoryNoOverlap($customerId, $startPrior, $effective, null);
            $db->prepare(
                'INSERT INTO customer_plan_history
                    (customer_id, plan_id, plan_name, speed_mbps, monthly_fee, started_at, ended_at, changed_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $customerId,
                $oldPlan['id'],
                $oldPlan['name'],
                $oldPlan['speed_mbps'],
                $oldPlan['monthly_fee'],
                $startPrior,
                $effective,
                $changedBy,
                'Previous plan (no prior history)',
            ]);
        }
    }

    assertPlanHistoryNoOverlap($customerId, $effective, null, null);

    recordCustomerPlanStart(
        $customerId,
        $newPlanId,
        $effective,
        $changedBy,
        $notes ?: 'Plan changed'
    );
}

function getCustomerPlanHistory(int $customerId): array
{
    ensureCustomerHasPlanHistory($customerId);

    $stmt = getDB()->prepare(
        'SELECT h.*, u.full_name as changed_by_name
         FROM customer_plan_history h
         LEFT JOIN users u ON h.changed_by = u.id
         WHERE h.customer_id = ?
         ORDER BY h.started_at DESC, h.id DESC'
    );
    $stmt->execute([$customerId]);

    return $stmt->fetchAll();
}

function getCustomerPlanHistoryRow(int $historyId): ?array
{
    ensureCustomerPlanHistoryTable();
    $stmt = getDB()->prepare(
        'SELECT h.*, c.installation_date, c.account_number, c.full_name AS customer_name
         FROM customer_plan_history h
         JOIN customers c ON c.id = h.customer_id
         WHERE h.id = ?'
    );
    $stmt->execute([$historyId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Half-open intervals [started_at, ended_at) must not overlap.
 * A NULL ended_at means open-ended (current plan).
 */
function assertPlanHistoryNoOverlap(
    int $customerId,
    string $startedAt,
    ?string $endedAt,
    ?int $excludeId = null
): void {
    $startTs = strtotime($startedAt);
    if ($startTs === false) {
        throw new RuntimeException('Invalid plan start date.');
    }

    $endTs = null;
    if ($endedAt !== null && $endedAt !== '') {
        $endTs = strtotime($endedAt);
        if ($endTs === false) {
            throw new RuntimeException('Invalid plan end date.');
        }
        if ($endTs <= $startTs) {
            throw new RuntimeException('Plan end date must be after the start date.');
        }
    }

    $stmt = getDB()->prepare(
        'SELECT id, plan_name, started_at, ended_at
         FROM customer_plan_history
         WHERE customer_id = ?'
        . ($excludeId ? ' AND id <> ?' : '')
        . ' ORDER BY started_at ASC, id ASC'
    );
    $params = [$customerId];
    if ($excludeId) {
        $params[] = $excludeId;
    }
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $openCount = 0;
    foreach ($rows as $row) {
        if (empty($row['ended_at'])) {
            $openCount++;
        }
    }
    if ($endTs === null) {
        $openCount++;
    }
    if ($openCount > 1) {
        throw new RuntimeException('Only one current (open-ended) plan history row is allowed.');
    }

    foreach ($rows as $row) {
        $otherStart = strtotime((string) $row['started_at']);
        $otherEnd = !empty($row['ended_at']) ? strtotime((string) $row['ended_at']) : null;

        // Overlap for half-open ranges: start < otherEnd AND otherStart < end
        $leftOk = ($otherEnd === null) ? true : ($startTs < $otherEnd);
        $rightOk = ($endTs === null) ? true : ($otherStart < $endTs);

        if ($leftOk && $rightOk) {
            $otherLabel = formatDate((string) $row['started_at'])
                . ' – '
                . (!empty($row['ended_at']) ? formatDate((string) $row['ended_at']) : 'current');
            throw new RuntimeException(
                'Plan dates overlap with “' . $row['plan_name'] . '” (' . $otherLabel . '). '
                . 'Use non-overlapping ranges where one plan ends when the next starts.'
            );
        }
    }
}

function syncCustomerPlanFromHistory(int $customerId): void
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT plan_id FROM customer_plan_history
         WHERE customer_id = ? AND ended_at IS NULL
         ORDER BY started_at DESC, id DESC LIMIT 1'
    );
    $stmt->execute([$customerId]);
    $planId = $stmt->fetchColumn();
    if ($planId) {
        $db->prepare('UPDATE customers SET plan_id = ? WHERE id = ?')
            ->execute([(int) $planId, $customerId]);
        return;
    }

    // Fallback: latest closed segment
    $latest = $db->prepare(
        'SELECT plan_id FROM customer_plan_history
         WHERE customer_id = ?
         ORDER BY started_at DESC, id DESC LIMIT 1'
    );
    $latest->execute([$customerId]);
    $planId = $latest->fetchColumn();
    if ($planId) {
        $db->prepare('UPDATE customers SET plan_id = ? WHERE id = ?')
            ->execute([(int) $planId, $customerId]);
    }
}

function updateCustomerPlanHistory(
    int $historyId,
    int $planId,
    string $startedDate,
    ?string $endedDate,
    ?string $notes,
    ?int $changedBy = null
): void {
    if (!hasRole('owner')) {
        throw new RuntimeException('Only the owner can update plan history.');
    }

    $row = getCustomerPlanHistoryRow($historyId);
    if (!$row) {
        throw new RuntimeException('Plan history row not found.');
    }

    $plan = getPlanSnapshot($planId);
    if (!$plan) {
        throw new RuntimeException('Service plan not found.');
    }

    $startedAt = normalizePlanEffectiveAt($startedDate);
    $endedAt = ($endedDate !== null && trim($endedDate) !== '')
        ? normalizePlanEffectiveAt($endedDate)
        : null;

    $installation = (string) ($row['installation_date'] ?? '');
    if ($installation !== '' && strtotime($startedAt) < strtotime($installation . ' 00:00:00')) {
        throw new RuntimeException('Plan start date cannot be before the installation date.');
    }

    assertPlanHistoryNoOverlap((int) $row['customer_id'], $startedAt, $endedAt, $historyId);

    $db = getDB();
    $db->prepare(
        'UPDATE customer_plan_history
         SET plan_id = ?, plan_name = ?, speed_mbps = ?, monthly_fee = ?,
             started_at = ?, ended_at = ?, changed_by = ?, notes = ?
         WHERE id = ?'
    )->execute([
        $plan['id'],
        $plan['name'],
        $plan['speed_mbps'],
        $plan['monthly_fee'],
        $startedAt,
        $endedAt,
        $changedBy,
        $notes !== null && trim($notes) !== '' ? trim($notes) : $row['notes'],
        $historyId,
    ]);

    syncCustomerPlanFromHistory((int) $row['customer_id']);
}

function addCustomerPlanHistory(
    int $customerId,
    int $planId,
    string $startedDate,
    ?string $endedDate,
    ?string $notes,
    ?int $changedBy = null
): int {
    if (!hasRole('owner')) {
        throw new RuntimeException('Only the owner can add plan history.');
    }

    ensureCustomerPlanHistoryTable();
    $plan = getPlanSnapshot($planId);
    if (!$plan) {
        throw new RuntimeException('Service plan not found.');
    }

    $customerStmt = getDB()->prepare('SELECT installation_date FROM customers WHERE id = ?');
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch();
    if (!$customer) {
        throw new RuntimeException('Customer not found.');
    }

    $startedAt = normalizePlanEffectiveAt($startedDate);
    $endedAt = ($endedDate !== null && trim($endedDate) !== '')
        ? normalizePlanEffectiveAt($endedDate)
        : null;

    if (!empty($customer['installation_date'])
        && strtotime($startedAt) < strtotime($customer['installation_date'] . ' 00:00:00')) {
        throw new RuntimeException('Plan start date cannot be before the installation date.');
    }

    assertPlanHistoryNoOverlap($customerId, $startedAt, $endedAt, null);

    $db = getDB();
    $db->prepare(
        'INSERT INTO customer_plan_history
            (customer_id, plan_id, plan_name, speed_mbps, monthly_fee, started_at, ended_at, changed_by, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $customerId,
        $plan['id'],
        $plan['name'],
        $plan['speed_mbps'],
        $plan['monthly_fee'],
        $startedAt,
        $endedAt,
        $changedBy,
        $notes !== null && trim($notes) !== '' ? trim($notes) : 'Manual plan history entry',
    ]);

    $newId = (int) $db->lastInsertId();
    syncCustomerPlanFromHistory($customerId);

    return $newId;
}

function deleteCustomerPlanHistoryRow(int $historyId): void
{
    if (!hasRole('owner')) {
        throw new RuntimeException('Only the owner can delete plan history.');
    }

    $row = getCustomerPlanHistoryRow($historyId);
    if (!$row) {
        throw new RuntimeException('Plan history row not found.');
    }

    $customerId = (int) $row['customer_id'];
    $db = getDB();

    $countStmt = $db->prepare('SELECT COUNT(*) FROM customer_plan_history WHERE customer_id = ?');
    $countStmt->execute([$customerId]);
    if ((int) $countStmt->fetchColumn() <= 1) {
        throw new RuntimeException('Cannot delete the only plan history row. Add another period first.');
    }

    $db->prepare('DELETE FROM customer_plan_history WHERE id = ?')->execute([$historyId]);
    syncCustomerPlanFromHistory($customerId);
}

function deleteCustomerAccount(int $customerId): void
{
    if (!hasRole('owner')) {
        throw new RuntimeException('Only the owner can delete customer accounts.');
    }

    $db = getDB();

    $db->beginTransaction();

    try {
        $db->prepare('DELETE FROM customer_plan_history WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('DELETE FROM payments WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('DELETE FROM bills WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('UPDATE users SET customer_id = NULL WHERE customer_id = ?')->execute([$customerId]);
        $db->prepare('DELETE FROM customers WHERE id = ?')->execute([$customerId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function getCustomerDeleteSummary(int $customerId): array
{
    $db = getDB();

    $billStmt = $db->prepare('SELECT COUNT(*) FROM bills WHERE customer_id = ?');
    $billStmt->execute([$customerId]);
    $bills = (int) $billStmt->fetchColumn();

    $payStmt = $db->prepare('SELECT COUNT(*) FROM payments WHERE customer_id = ?');
    $payStmt->execute([$customerId]);
    $payments = (int) $payStmt->fetchColumn();

    $ticketStmt = $db->prepare('SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ?');
    $ticketStmt->execute([$customerId]);
    $tickets = (int) $ticketStmt->fetchColumn();

    $inquiryStmt = $db->prepare('SELECT COUNT(*) FROM inquiries WHERE customer_id = ?');
    $inquiryStmt->execute([$customerId]);
    $inquiries = (int) $inquiryStmt->fetchColumn();

    return [
        'bills'     => $bills,
        'payments'  => $payments,
        'tickets'   => $tickets,
        'inquiries' => $inquiries,
    ];
}

function renderCustomerDeleteSection(array $customer): void
{
    if (!hasRole('owner')) {
        return;
    }

    $summary = getCustomerDeleteSummary((int) $customer['id']);
    $customerId = (int) $customer['id'];
    ?>
<div class="card danger-zone">
    <div class="card-header"><h2>Delete Account</h2></div>
    <p class="danger-intro">
        Permanently remove <strong><?= e($customer['account_number']) ?></strong> — <?= e($customer['full_name']) ?>.
        This will also delete related billing and support records.
    </p>
    <ul class="delete-summary">
        <li><?= number_format($summary['bills']) ?> bill(s)</li>
        <li><?= number_format($summary['payments']) ?> payment(s)</li>
        <li><?= number_format($summary['tickets']) ?> repair ticket(s)</li>
        <li><?= number_format($summary['inquiries']) ?> inquiry/inquiries</li>
    </ul>
    <form method="POST" action="<?= APP_URL ?>/customers/delete.php" class="delete-account-form"
          onsubmit='return confirm(<?= json_encode('Permanently delete ' . $customer['account_number'] . '?') ?>)'>
        <input type="hidden" name="id" value="<?= $customerId ?>">
        <div class="form-group">
            <label for="delete_confirm_<?= $customerId ?>">Type DELETE to confirm *</label>
            <input type="text" id="delete_confirm_<?= $customerId ?>" name="confirm_text"
                   placeholder="DELETE" autocomplete="off" required>
        </div>
        <button type="submit" class="btn btn-danger">Delete Account</button>
    </form>
</div>
    <?php
}

function buildCustomerListFilters(string $search = '', string $status = '', int $planId = 0): array
{
    $fromWhere = 'FROM customers c JOIN service_plans p ON c.plan_id = p.id WHERE 1=1';
    $params = [];

    $search = trim($search);
    if ($search !== '') {
        $fromWhere .= ' AND (c.full_name LIKE ? OR c.account_number LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($status !== '' && in_array($status, ['active', 'suspended', 'disconnected'], true)) {
        $fromWhere .= ' AND c.status = ?';
        $params[] = $status;
    }

    if ($planId > 0) {
        $fromWhere .= ' AND c.plan_id = ?';
        $params[] = $planId;
    }

    return [
        'from_where' => $fromWhere,
        'params'     => $params,
        'search'     => $search,
        'status'     => $status,
        'plan_id'    => $planId,
    ];
}

function getCustomersForExport(string $search = '', string $status = '', int $planId = 0): array
{
    $filters = buildCustomerListFilters($search, $status, $planId);
    $sql = 'SELECT c.*, p.name AS plan_name, p.monthly_fee, p.speed_mbps
            ' . $filters['from_where'] . '
            ORDER BY c.installation_date DESC, c.account_number ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($filters['params']);
    return $stmt->fetchAll() ?: [];
}

function describeCustomerExportFilters(string $search, string $status, int $planId): string
{
    $parts = [];
    if ($search !== '') {
        $parts[] = 'Search: ' . $search;
    }
    if ($status !== '') {
        $parts[] = 'Status: ' . ucfirst($status);
    }
    if ($planId > 0) {
        $stmt = getDB()->prepare('SELECT name FROM service_plans WHERE id = ?');
        $stmt->execute([$planId]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $parts[] = 'Plan: ' . $name;
        }
    }
    return $parts ? implode(' · ', $parts) : 'All customers';
}

function buildCustomersExcel(array $customers, array $meta = []): string
{
    require_once __DIR__ . '/reports.php';

    $rows = [[
        'Account Number',
        'Full Name',
        'Phone',
        'Email',
        'Plan',
        'Speed (Mbps)',
        'Monthly Fee',
        'Connection',
        'Status',
        'Installation Date',
        'Next Billing',
        'Address',
        'Barangay',
        'City',
        'Province',
        'Advance Balance',
        'Notes',
    ]];

    foreach ($customers as $c) {
        $rows[] = [
            (string) $c['account_number'],
            (string) $c['full_name'],
            (string) $c['phone'],
            (string) ($c['email'] ?? ''),
            (string) ($c['plan_name'] ?? ''),
            (int) ($c['speed_mbps'] ?? 0),
            (float) ($c['monthly_fee'] ?? 0),
            connectionMediumLabel($c['connection_medium'] ?? null),
            ucfirst((string) ($c['status'] ?? '')),
            (string) ($c['installation_date'] ?? ''),
            function_exists('getNextBillingDate')
                ? (string) getNextBillingDate($c['installation_date'])
                : '',
            (string) ($c['address'] ?? ''),
            (string) ($c['barangay'] ?? ''),
            (string) ($c['city'] ?? ''),
            (string) ($c['province'] ?? ''),
            (float) ($c['advance_balance'] ?? 0),
            (string) ($c['notes'] ?? ''),
        ];
    }

    $summary = [
        ['SHAVEN Networks — Customer List'],
        ['Generated', $meta['generated_at'] ?? date('Y-m-d H:i:s')],
        ['Filters', $meta['filters_label'] ?? 'All customers'],
        ['Total Records', count($customers)],
    ];

    return buildWorkbookXlsx([
        'Customers' => buildXlsxSheetXml($rows),
        'Summary'   => buildXlsxSheetXml($summary),
    ]);
}

function buildCustomersCsv(array $customers): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        throw new RuntimeException('Unable to build CSV export.');
    }

    fputcsv($out, [
        'Account Number', 'Full Name', 'Phone', 'Email', 'Plan', 'Speed (Mbps)', 'Monthly Fee',
        'Connection', 'Status', 'Installation Date', 'Next Billing', 'Address', 'Barangay',
        'City', 'Province', 'Advance Balance', 'Notes',
    ]);

    foreach ($customers as $c) {
        fputcsv($out, [
            $c['account_number'],
            $c['full_name'],
            $c['phone'],
            $c['email'] ?? '',
            $c['plan_name'] ?? '',
            $c['speed_mbps'] ?? '',
            $c['monthly_fee'] ?? '',
            connectionMediumLabel($c['connection_medium'] ?? null),
            ucfirst((string) ($c['status'] ?? '')),
            $c['installation_date'] ?? '',
            function_exists('getNextBillingDate') ? getNextBillingDate($c['installation_date']) : '',
            $c['address'] ?? '',
            $c['barangay'] ?? '',
            $c['city'] ?? '',
            $c['province'] ?? '',
            $c['advance_balance'] ?? 0,
            $c['notes'] ?? '',
        ]);
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    if ($csv === false) {
        throw new RuntimeException('Unable to read CSV export.');
    }

    return "\xEF\xBB\xBF" . $csv;
}

function renderCustomersPrintDocument(array $customers, string $filtersLabel, string $generatedAt): void
{
    $active = 0;
    $suspended = 0;
    $disconnected = 0;
    foreach ($customers as $c) {
        match ($c['status'] ?? '') {
            'active' => $active++,
            'suspended' => $suspended++,
            'disconnected' => $disconnected++,
            default => null,
        };
    }
    ?>
    <div class="report-document customers-print-doc">
        <div class="report-doc-header">
            <div class="report-brand">
                <div class="brand-icon">SN</div>
                <div>
                    <strong>SHAVEN Networks</strong>
                    <small>ISP Billing System</small>
                </div>
            </div>
            <div class="report-doc-meta">
                <h1>Customer List</h1>
                <p><?= e($filtersLabel) ?></p>
                <small>
                    <?= number_format(count($customers)) ?> subscribers ·
                    Generated <?= e(date('M d, Y g:i A', strtotime($generatedAt))) ?>
                </small>
            </div>
        </div>

        <div class="stats-grid report-stats">
            <div class="stat-card">
                <div class="stat-value"><?= number_format(count($customers)) ?></div>
                <div class="stat-label">Total Listed</div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-value"><?= number_format($active) ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-value"><?= number_format($suspended) ?></div>
                <div class="stat-label">Suspended</div>
            </div>
            <div class="stat-card stat-danger">
                <div class="stat-value"><?= number_format($disconnected) ?></div>
                <div class="stat-label">Disconnected</div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-compact customers-export-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Account / Installed</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Connection</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr><td colspan="9" class="text-center text-muted">No customers found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($customers as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <?= e($c['account_number']) ?><br>
                                <small><?= e(formatDate($c['installation_date'] ?? null)) ?></small>
                            </td>
                            <td><?= e($c['full_name']) ?></td>
                            <td><?= e($c['phone']) ?></td>
                            <td><?= e($c['plan_name'] ?? '') ?></td>
                            <td><?= e(connectionMediumLabel($c['connection_medium'] ?? null)) ?></td>
                            <td><?= formatMoney((float) ($c['monthly_fee'] ?? 0)) ?></td>
                            <td><?= e(ucfirst((string) ($c['status'] ?? ''))) ?></td>
                            <td><?= e(formatCustomerAddress($c)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
