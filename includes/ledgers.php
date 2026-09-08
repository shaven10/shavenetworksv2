<?php

function ensureEmployeeLedgerTables(): void
{
    $db = getDB();

    $db->exec(
        "CREATE TABLE IF NOT EXISTS employee_ledgers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            user_id INT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 0,
            in_label VARCHAR(100) NOT NULL DEFAULT 'Hatagon Beben',
            out_label VARCHAR(100) NOT NULL DEFAULT 'Utang',
            balance_as_of DATE NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ledger_user (user_id),
            INDEX idx_ledger_visible (is_visible),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS employee_ledger_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ledger_id INT NOT NULL,
            entry_date DATE NULL,
            date_display VARCHAR(100) NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            amount_in DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            amount_out DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            row_type ENUM('entry','year_header','end_marker') NOT NULL DEFAULT 'entry',
            highlight ENUM('none','opening','year') NOT NULL DEFAULT 'none',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ledger_entries_order (ledger_id, sort_order),
            FOREIGN KEY (ledger_id) REFERENCES employee_ledgers(id) ON DELETE CASCADE
        )"
    );

    // Older installs may lack date_display
    try {
        $cols = $db->query('SHOW COLUMNS FROM employee_ledger_entries LIKE "date_display"')->fetch();
        if (!$cols) {
            $db->exec('ALTER TABLE employee_ledger_entries ADD COLUMN date_display VARCHAR(100) NULL AFTER entry_date');
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function getLinkableLedgerUsers(): array
{
    $stmt = getDB()->query(
        "SELECT id, username, full_name, role, approval_status, is_active
         FROM users
         WHERE role IN ('technical', 'collector', 'customer', 'owner')
           AND approval_status = 'approved'
           AND is_active = 1
         ORDER BY full_name ASC"
    );
    return $stmt->fetchAll() ?: [];
}

function getEmployeeLedgers(): array
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->query(
        "SELECT l.*, u.full_name AS account_name, u.username AS account_username,
                (SELECT COUNT(*) FROM employee_ledger_entries e WHERE e.ledger_id = l.id AND e.row_type = 'entry') AS entry_count
         FROM employee_ledgers l
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.updated_at DESC, l.id DESC"
    );
    return $stmt->fetchAll() ?: [];
}

function getEmployeeLedger(int $id): ?array
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->prepare(
        "SELECT l.*, u.full_name AS account_name, u.username AS account_username,
                u.role AS account_role, u.is_active AS account_active, u.approval_status AS account_approval
         FROM employee_ledgers l
         LEFT JOIN users u ON u.id = l.user_id
         WHERE l.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getVisibleLedgerForUser(int $userId): ?array
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->prepare(
        "SELECT l.*, u.full_name AS account_name, u.username AS account_username
         FROM employee_ledgers l
         LEFT JOIN users u ON u.id = l.user_id
         WHERE l.user_id = ? AND l.is_visible = 1
         ORDER BY l.updated_at DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function userHasVisibleLedger(?int $userId = null): bool
{
    $userId = $userId ?? (int) (currentUser()['id'] ?? 0);
    if (!$userId) {
        return false;
    }
    return getVisibleLedgerForUser($userId) !== null;
}

function getLedgerEntries(int $ledgerId): array
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->prepare(
        "SELECT * FROM employee_ledger_entries
         WHERE ledger_id = ?
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute([$ledgerId]);
    return $stmt->fetchAll() ?: [];
}

function getLedgerTotals(array $entries): array
{
    $in = 0.0;
    $out = 0.0;
    foreach ($entries as $entry) {
        if (($entry['row_type'] ?? 'entry') !== 'entry') {
            continue;
        }
        $in += (float) $entry['amount_in'];
        $out += (float) $entry['amount_out'];
    }
    return [
        'total_in'  => $in,
        'total_out' => $out,
        'balance'   => $out - $in,
    ];
}

function nextLedgerSortOrder(int $ledgerId): int
{
    $stmt = getDB()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM employee_ledger_entries WHERE ledger_id = ?');
    $stmt->execute([$ledgerId]);
    return (int) $stmt->fetchColumn();
}

function createEmployeeLedger(array $data, int $createdBy): int
{
    ensureEmployeeLedgerTables();

    $employeeName = trim((string) ($data['employee_name'] ?? ''));
    $title = trim((string) ($data['title'] ?? ''));
    if ($employeeName === '') {
        throw new InvalidArgumentException('Employee name is required.');
    }
    if ($title === '') {
        $title = strtoupper($employeeName) . ' BALANSI';
    }

    $userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;
    if ($userId) {
        assertLinkableLedgerUser($userId);
    }

    $outLabel = trim((string) ($data['out_label'] ?? ''));
    if ($outLabel === '') {
        $outLabel = 'Utang ' . $employeeName;
    }

    $inLabel = trim((string) ($data['in_label'] ?? '')) ?: 'Hatagon Beben';
    $balanceAsOf = trim((string) ($data['balance_as_of'] ?? '')) ?: null;
    $isVisible = !empty($data['is_visible']) ? 1 : 0;
    if ($isVisible && !$userId) {
        throw new InvalidArgumentException('Link an activated account before making the ledger visible.');
    }

    $stmt = getDB()->prepare(
        'INSERT INTO employee_ledgers
            (employee_name, title, user_id, is_visible, in_label, out_label, balance_as_of, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $employeeName,
        $title,
        $userId,
        $isVisible,
        $inLabel,
        $outLabel,
        $balanceAsOf,
        trim((string) ($data['notes'] ?? '')) ?: null,
        $createdBy,
    ]);

    return (int) getDB()->lastInsertId();
}

function updateEmployeeLedger(int $id, array $data): void
{
    ensureEmployeeLedgerTables();
    $ledger = getEmployeeLedger($id);
    if (!$ledger) {
        throw new RuntimeException('Ledger not found.');
    }

    $employeeName = trim((string) ($data['employee_name'] ?? ''));
    $title = trim((string) ($data['title'] ?? ''));
    if ($employeeName === '') {
        throw new InvalidArgumentException('Employee name is required.');
    }
    if ($title === '') {
        $title = strtoupper($employeeName) . ' BALANSI';
    }

    $userId = array_key_exists('user_id', $data)
        ? (!empty($data['user_id']) ? (int) $data['user_id'] : null)
        : ($ledger['user_id'] ? (int) $ledger['user_id'] : null);

    if ($userId) {
        assertLinkableLedgerUser($userId);
    }

    $outLabel = trim((string) ($data['out_label'] ?? ''));
    if ($outLabel === '') {
        $outLabel = 'Utang ' . $employeeName;
    }
    $inLabel = trim((string) ($data['in_label'] ?? '')) ?: 'Hatagon Beben';
    $balanceAsOf = trim((string) ($data['balance_as_of'] ?? '')) ?: null;
    $isVisible = !empty($data['is_visible']) ? 1 : 0;
    if ($isVisible && !$userId) {
        throw new InvalidArgumentException('Link an activated account before making the ledger visible.');
    }

    $stmt = getDB()->prepare(
        'UPDATE employee_ledgers
         SET employee_name = ?, title = ?, user_id = ?, is_visible = ?,
             in_label = ?, out_label = ?, balance_as_of = ?, notes = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $employeeName,
        $title,
        $userId,
        $isVisible,
        $inLabel,
        $outLabel,
        $balanceAsOf,
        trim((string) ($data['notes'] ?? '')) ?: null,
        $id,
    ]);
}

function assertLinkableLedgerUser(int $userId): void
{
    $stmt = getDB()->prepare(
        "SELECT id FROM users
         WHERE id = ? AND approval_status = 'approved' AND is_active = 1"
    );
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Selected account must be activated (approved and active).');
    }
}

function deleteEmployeeLedger(int $id): void
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->prepare('DELETE FROM employee_ledgers WHERE id = ?');
    $stmt->execute([$id]);
}

function addLedgerEntry(int $ledgerId, array $data): int
{
    ensureEmployeeLedgerTables();
    if (!getEmployeeLedger($ledgerId)) {
        throw new RuntimeException('Ledger not found.');
    }

    $rowType = $data['row_type'] ?? 'entry';
    if (!in_array($rowType, ['entry', 'year_header', 'end_marker'], true)) {
        $rowType = 'entry';
    }

    $highlight = $data['highlight'] ?? 'none';
    if (!in_array($highlight, ['none', 'opening', 'year'], true)) {
        $highlight = 'none';
    }
    if ($rowType === 'year_header') {
        $highlight = 'year';
    }

    $description = trim((string) ($data['description'] ?? ''));
    if ($rowType === 'end_marker' && $description === '') {
        $description = 'x-x-x-nothing follows-x-x-x';
    }
    if ($rowType === 'year_header' && $description === '') {
        throw new InvalidArgumentException('Year header description is required (e.g. Year 2026).');
    }
    if ($rowType === 'entry' && $description === '') {
        throw new InvalidArgumentException('Description is required.');
    }

    $entryDate = trim((string) ($data['entry_date'] ?? '')) ?: null;
    $dateDisplay = trim((string) ($data['date_display'] ?? '')) ?: null;
    if ($rowType !== 'entry') {
        $entryDate = null;
        if ($rowType === 'year_header' && $dateDisplay === null) {
            $dateDisplay = $description;
        }
    }

    $amountIn = $rowType === 'entry' ? max(0, (float) ($data['amount_in'] ?? 0)) : 0;
    $amountOut = $rowType === 'entry' ? max(0, (float) ($data['amount_out'] ?? 0)) : 0;

    if ($rowType === 'entry' && $amountIn <= 0 && $amountOut <= 0) {
        throw new InvalidArgumentException('Enter an IN or OUT amount.');
    }

    $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : nextLedgerSortOrder($ledgerId);

    $stmt = getDB()->prepare(
        'INSERT INTO employee_ledger_entries
            (ledger_id, entry_date, date_display, description, amount_in, amount_out, row_type, highlight, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ledgerId,
        $entryDate,
        $dateDisplay,
        $description,
        $amountIn,
        $amountOut,
        $rowType,
        $highlight,
        $sortOrder,
    ]);

    touchEmployeeLedger($ledgerId);
    return (int) getDB()->lastInsertId();
}

function updateLedgerEntry(int $entryId, array $data): void
{
    ensureEmployeeLedgerTables();
    $entry = getLedgerEntry($entryId);
    if (!$entry) {
        throw new RuntimeException('Entry not found.');
    }

    $rowType = $data['row_type'] ?? $entry['row_type'];
    if (!in_array($rowType, ['entry', 'year_header', 'end_marker'], true)) {
        $rowType = 'entry';
    }

    $highlight = $data['highlight'] ?? $entry['highlight'];
    if (!in_array($highlight, ['none', 'opening', 'year'], true)) {
        $highlight = 'none';
    }
    if ($rowType === 'year_header') {
        $highlight = 'year';
    }

    $description = trim((string) ($data['description'] ?? $entry['description']));
    if ($rowType === 'end_marker' && $description === '') {
        $description = 'x-x-x-nothing follows-x-x-x';
    }
    if ($description === '') {
        throw new InvalidArgumentException('Description is required.');
    }

    $entryDate = array_key_exists('entry_date', $data)
        ? (trim((string) $data['entry_date']) ?: null)
        : $entry['entry_date'];
    $dateDisplay = array_key_exists('date_display', $data)
        ? (trim((string) $data['date_display']) ?: null)
        : ($entry['date_display'] ?? null);
    if ($rowType !== 'entry') {
        $entryDate = null;
    }

    $amountIn = $rowType === 'entry' ? max(0, (float) ($data['amount_in'] ?? $entry['amount_in'])) : 0;
    $amountOut = $rowType === 'entry' ? max(0, (float) ($data['amount_out'] ?? $entry['amount_out'])) : 0;

    $stmt = getDB()->prepare(
        'UPDATE employee_ledger_entries
         SET entry_date = ?, date_display = ?, description = ?, amount_in = ?, amount_out = ?, row_type = ?, highlight = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $entryDate,
        $dateDisplay,
        $description,
        $amountIn,
        $amountOut,
        $rowType,
        $highlight,
        $entryId,
    ]);

    touchEmployeeLedger((int) $entry['ledger_id']);
}

function getLedgerEntry(int $entryId): ?array
{
    ensureEmployeeLedgerTables();
    $stmt = getDB()->prepare('SELECT * FROM employee_ledger_entries WHERE id = ?');
    $stmt->execute([$entryId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function deleteLedgerEntry(int $entryId): void
{
    ensureEmployeeLedgerTables();
    $entry = getLedgerEntry($entryId);
    if (!$entry) {
        return;
    }
    $stmt = getDB()->prepare('DELETE FROM employee_ledger_entries WHERE id = ?');
    $stmt->execute([$entryId]);
    touchEmployeeLedger((int) $entry['ledger_id']);
}

function touchEmployeeLedger(int $ledgerId): void
{
    getDB()->prepare('UPDATE employee_ledgers SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$ledgerId]);
}

function formatLedgerMoney(float $amount): string
{
    if (abs($amount) < 0.005) {
        return '';
    }
    return number_format($amount, 2);
}

function formatLedgerDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    return date('F j, Y', strtotime($date));
}

function formatLedgerDateCell(array $entry): string
{
    $display = trim((string) ($entry['date_display'] ?? ''));
    if ($display !== '') {
        return $display;
    }
    return formatLedgerDate($entry['entry_date'] ?? null);
}

function renderEmployeeLedgerSheet(array $ledger, array $entries, bool $editable = false): void
{
    $totals = getLedgerTotals($entries);
    $employee = $ledger['employee_name'];
    $inLabel = $ledger['in_label'] ?: 'Hatagon Beben';
    $outLabel = $ledger['out_label'] ?: ('Utang ' . $employee);
    $colCount = $editable ? 5 : 4;
    ?>
    <div class="ledger-sheet">
        <div class="ledger-sheet-title"><?= e($ledger['title']) ?></div>
        <div class="table-responsive ledger-table-wrap">
            <table class="ledger-table<?= $editable ? ' is-editable' : '' ?>">
                <colgroup>
                    <col class="ledger-col-date">
                    <col class="ledger-col-desc">
                    <col class="ledger-col-amt">
                    <col class="ledger-col-amt">
                    <?php if ($editable): ?><col class="ledger-col-actions"><?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>DESCRIPTION</th>
                        <th>
                            <div>IN</div>
                            <div class="ledger-subhead"><?= e($inLabel) ?></div>
                        </th>
                        <th>
                            <div>OUT</div>
                            <div class="ledger-subhead"><?= e($outLabel) ?></div>
                        </th>
                        <?php if ($editable): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="<?= $colCount ?>" class="text-center text-muted" style="padding:24px">
                            No ledger entries yet.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($entries as $entry): ?>
                    <?php
                    $type = $entry['row_type'] ?? 'entry';
                    $rowClass = match ($entry['highlight'] ?? 'none') {
                        'opening' => 'ledger-row-opening',
                        'year' => 'ledger-row-year',
                        default => '',
                    };
                    if ($type === 'end_marker') {
                        $rowClass = 'ledger-row-end';
                    }
                    echo '<tr class="' . e($rowClass) . '">';

                    if ($type === 'year_header') {
                        echo '<td colspan="4" class="ledger-year-cell">' . e($entry['description']) . '</td>';
                        if ($editable) {
                            echo '<td class="ledger-actions">';
                            renderLedgerEntryActions((int) $ledger['id'], (int) $entry['id']);
                            echo '</td>';
                        }
                    } elseif ($type === 'end_marker') {
                        echo '<td colspan="4" class="ledger-end-cell">'
                            . e($entry['description'] ?: 'x-x-x-nothing follows-x-x-x')
                            . '</td>';
                        if ($editable) {
                            echo '<td class="ledger-actions">';
                            renderLedgerEntryActions((int) $ledger['id'], (int) $entry['id']);
                            echo '</td>';
                        }
                    } else {
                        echo '<td>' . e(formatLedgerDateCell($entry)) . '</td>';
                        echo '<td>' . e($entry['description']) . '</td>';
                        echo '<td class="ledger-num">' . e(formatLedgerMoney((float) $entry['amount_in'])) . '</td>';
                        echo '<td class="ledger-num">' . e(formatLedgerMoney((float) $entry['amount_out'])) . '</td>';
                        if ($editable) {
                            echo '<td class="ledger-actions">';
                            renderLedgerEntryActions((int) $ledger['id'], (int) $entry['id']);
                            echo '</td>';
                        }
                    }

                    echo '</tr>';
                    ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="ledger-total-row">
                        <td></td>
                        <td><strong>TOTAL</strong></td>
                        <td class="ledger-num"><strong><?= e(number_format($totals['total_in'], 2)) ?></strong></td>
                        <td class="ledger-num"><strong><?= e(number_format($totals['total_out'], 2)) ?></strong></td>
                        <?php if ($editable): ?><td></td><?php endif; ?>
                    </tr>
                    <tr class="ledger-grand-row">
                        <td></td>
                        <td><strong>GRAND TOTAL</strong></td>
                        <td class="ledger-num">
                            <div class="ledger-subhead">Beben Hatagon</div>
                            <strong><?= e(number_format($totals['total_in'], 2)) ?></strong>
                        </td>
                        <td class="ledger-num">
                            <div class="ledger-subhead"><?= e($employee) ?> Utang</div>
                            <strong><?= e(number_format($totals['total_out'], 2)) ?></strong>
                        </td>
                        <?php if ($editable): ?><td></td><?php endif; ?>
                    </tr>
                    <tr class="ledger-balance-row">
                        <td colspan="<?= $colCount ?>">
                            <strong>UTANGAN <?= e(strtoupper($employee)) ?>:</strong>
                            <?= e(number_format($totals['balance'], 2)) ?>
                            <?php if (!empty($ledger['balance_as_of'])): ?>
                            <span class="ledger-as-of">as of <?= e(formatLedgerDate($ledger['balance_as_of'])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
}

function renderLedgerEntryActions(int $ledgerId, int $entryId): void
{
    ?>
    <div class="ledger-action-btns">
        <a href="?id=<?= $ledgerId ?>&edit_entry=<?= $entryId ?>#entry-form" class="btn btn-sm btn-outline">Edit</a>
        <form method="POST" class="ledger-delete-form" onsubmit="return confirm('Delete this entry?');">
            <input type="hidden" name="action" value="delete_entry">
            <input type="hidden" name="entry_id" value="<?= $entryId ?>">
            <button type="submit" class="btn btn-sm btn-outline">Delete</button>
        </form>
    </div>
    <?php
}
