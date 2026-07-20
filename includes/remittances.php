<?php

function ensureRemittanceTables(): void
{
    getDB()->exec(
        "CREATE TABLE IF NOT EXISTS remittances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            remittance_number VARCHAR(30) NOT NULL UNIQUE,
            submitted_by INT NOT NULL,
            confirmed_by INT NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_count INT NOT NULL DEFAULT 0,
            status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            owner_notes TEXT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (submitted_by) REFERENCES users(id),
            FOREIGN KEY (confirmed_by) REFERENCES users(id)
        )"
    );

    getDB()->exec(
        "CREATE TABLE IF NOT EXISTS remittance_payments (
            remittance_id INT NOT NULL,
            payment_id INT NOT NULL,
            PRIMARY KEY (remittance_id, payment_id),
            UNIQUE KEY uniq_payment_remittance (payment_id),
            FOREIGN KEY (remittance_id) REFERENCES remittances(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_id) REFERENCES payments(id)
        )"
    );
}

function generateRemittanceNumber(): string
{
    return 'REM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function remittanceStatusBadge(string $status): string
{
    return match ($status) {
        'pending'   => '<span class="badge badge-warning">Pending Review</span>',
        'confirmed' => '<span class="badge badge-success">Confirmed</span>',
        'rejected'  => '<span class="badge badge-danger">Rejected</span>',
        default     => '<span class="badge badge-secondary">' . e(ucfirst($status)) . '</span>',
    };
}

function paymentRemittanceSubquery(): string
{
    return 'NOT EXISTS (
        SELECT 1 FROM remittance_payments rp
        JOIN remittances r ON r.id = rp.remittance_id
        WHERE rp.payment_id = p.id AND r.status IN (\'pending\', \'confirmed\')
    )';
}

function getUnremittedPayments(int $collectorId, ?string $from = null, ?string $to = null): array
{
    ensureRemittanceTables();

    $sql = 'SELECT p.*, c.full_name, c.account_number,
                   COALESCE(b.bill_number, "Advance Payment") AS bill_number
            FROM payments p
            JOIN customers c ON p.customer_id = c.id
            LEFT JOIN bills b ON p.bill_id = b.id
            WHERE p.collected_by = ?
            AND p.payment_type IN ("bill", "advance")
            AND ' . paymentRemittanceSubquery();

    $params = [$collectorId];

    if ($from) {
        $sql .= ' AND p.payment_date >= ?';
        $params[] = $from;
    }
    if ($to) {
        $sql .= ' AND p.payment_date <= ?';
        $params[] = $to;
    }

    $sql .= ' ORDER BY p.payment_date ASC, p.created_at ASC';

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getUnremittedPaymentsTotal(int $collectorId): float
{
    ensureRemittanceTables();

    $stmt = getDB()->prepare(
        'SELECT COALESCE(SUM(p.amount), 0)
         FROM payments p
         WHERE p.collected_by = ?
         AND ' . paymentRemittanceSubquery()
    );
    $stmt->execute([$collectorId]);
    return (float) $stmt->fetchColumn();
}

function getUnremittedPaymentCount(int $collectorId): int
{
    ensureRemittanceTables();

    $stmt = getDB()->prepare(
        'SELECT COUNT(*)
         FROM payments p
         WHERE p.collected_by = ?
         AND ' . paymentRemittanceSubquery()
    );
    $stmt->execute([$collectorId]);
    return (int) $stmt->fetchColumn();
}

function createRemittance(int $collectorId, array $paymentIds, ?string $notes): array
{
    ensureRemittanceTables();

    $paymentIds = array_values(array_unique(array_map('intval', $paymentIds)));
    $paymentIds = array_filter($paymentIds, fn ($id) => $id > 0);

    if (empty($paymentIds)) {
        throw new RuntimeException('Select at least one payment to remit.');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $stmt = $db->prepare(
            "SELECT p.id, p.amount
             FROM payments p
             WHERE p.id IN ({$placeholders})
             AND p.collected_by = ?
             AND " . paymentRemittanceSubquery()
        );
        $stmt->execute([...$paymentIds, $collectorId]);
        $payments = $stmt->fetchAll();

        if (count($payments) !== count($paymentIds)) {
            throw new RuntimeException('One or more selected payments are invalid or already remitted.');
        }

        $total = array_sum(array_column($payments, 'amount'));
        $count = count($payments);
        $number = generateRemittanceNumber();

        $stmt = $db->prepare(
            'INSERT INTO remittances (remittance_number, submitted_by, total_amount, payment_count, notes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$number, $collectorId, $total, $count, $notes ?: null]);
        $remittanceId = (int) $db->lastInsertId();

        $link = $db->prepare(
            'INSERT INTO remittance_payments (remittance_id, payment_id) VALUES (?, ?)'
        );
        foreach ($payments as $payment) {
            $link->execute([$remittanceId, $payment['id']]);
        }

        $db->commit();

        return [
            'id'                 => $remittanceId,
            'remittance_number'  => $number,
            'total_amount'       => (float) $total,
            'payment_count'      => $count,
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function getRemittance(int $id): ?array
{
    ensureRemittanceTables();

    $stmt = getDB()->prepare(
        'SELECT r.*, u.full_name AS collector_name, u.username AS collector_username,
                o.full_name AS reviewer_name
         FROM remittances r
         JOIN users u ON r.submitted_by = u.id
         LEFT JOIN users o ON r.confirmed_by = o.id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getRemittancePayments(int $remittanceId): array
{
    ensureRemittanceTables();

    $stmt = getDB()->prepare(
        'SELECT p.*, c.full_name, c.account_number, b.bill_number
         FROM remittance_payments rp
         JOIN payments p ON rp.payment_id = p.id
         JOIN customers c ON p.customer_id = c.id
         JOIN bills b ON p.bill_id = b.id
         WHERE rp.remittance_id = ?
         ORDER BY p.payment_date ASC, p.created_at ASC'
    );
    $stmt->execute([$remittanceId]);
    return $stmt->fetchAll();
}

function confirmRemittance(int $remittanceId, int $ownerId, ?string $ownerNotes): void
{
    reviewRemittance($remittanceId, $ownerId, 'confirmed', $ownerNotes);
}

function rejectRemittance(int $remittanceId, int $ownerId, ?string $ownerNotes): void
{
    if (!$ownerNotes || trim($ownerNotes) === '') {
        throw new RuntimeException('Please provide a reason when rejecting a remittance.');
    }
    reviewRemittance($remittanceId, $ownerId, 'rejected', trim($ownerNotes));
}

function reviewRemittance(int $remittanceId, int $ownerId, string $status, ?string $ownerNotes): void
{
    ensureRemittanceTables();

    if (!in_array($status, ['confirmed', 'rejected'], true)) {
        throw new RuntimeException('Invalid remittance review action.');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare('SELECT * FROM remittances WHERE id = ? FOR UPDATE');
        $stmt->execute([$remittanceId]);
        $remittance = $stmt->fetch();

        if (!$remittance) {
            throw new RuntimeException('Remittance not found.');
        }
        if ($remittance['status'] !== 'pending') {
            throw new RuntimeException('This remittance has already been reviewed.');
        }

        $stmt = $db->prepare(
            'UPDATE remittances
             SET status = ?, confirmed_by = ?, owner_notes = ?, reviewed_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$status, $ownerId, $ownerNotes ?: null, $remittanceId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function countPendingRemittances(): int
{
    ensureRemittanceTables();

    return (int) getDB()->query(
        "SELECT COUNT(*) FROM remittances WHERE status = 'pending'"
    )->fetchColumn();
}

function getCollectorRejectedRemittanceCount(int $collectorId): int
{
    ensureRemittanceTables();

    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM remittances WHERE submitted_by = ? AND status = 'rejected'"
    );
    $stmt->execute([$collectorId]);
    return (int) $stmt->fetchColumn();
}

function canViewRemittance(array $remittance): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }
    if (hasRole('owner')) {
        return true;
    }
    return hasRole('collector') && (int) $remittance['submitted_by'] === (int) $user['id'];
}

function canReviewRemittance(array $remittance): bool
{
    return hasRole('owner') && $remittance['status'] === 'pending';
}
