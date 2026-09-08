<?php
/**
 * Seed NONOY BALANSI ledger from the paper template (June 15, 2025 – May 2026).
 * Run: D:\SC30\php\php.exe database/seed_nonoy_ledger.php
 *
 * Re-running replaces an existing "NONOY BALANSI" ledger of the same title.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ledgers.php';

ensureEmployeeLedgerTables();

$entries = [
    ['date' => '2025-06-15', 'date_display' => null, 'description' => 'Utang Nonoy (Prev balansi balance nonoy)', 'in' => 0, 'out' => 11950, 'type' => 'entry', 'highlight' => 'opening'],
    ['date' => '2025-07-03', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 2000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-07-23', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of July 2025', 'description' => 'MONTLY SWELDO (increased)', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-08-01', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-08-24', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 5000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-08-26', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of August 2025', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-09-02', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 4000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-09-04', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 200, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-09-08', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-09-20', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of September 2025', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-10-01', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-10-13', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-10-22', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of October 2025', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-11-01', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 4000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-11-19', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 6000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of November 2025', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-12-06', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-12-09', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-12-14', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-12-17', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2025-12-24', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 2000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of December 2025', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => null, 'description' => 'Year 2026', 'in' => 0, 'out' => 0, 'type' => 'year_header', 'highlight' => 'year'],
    ['date' => '2026-01-01', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-01-08', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of January 2026', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-02-03', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-02-21', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 3500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of February 2026', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-03-01', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-03-15', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1500, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-03-17', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of March 2026', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-04-02', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-04-18', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 2000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => 'Month of April 2026', 'description' => 'MONTLY SWELDO', 'in' => 5000, 'out' => 0, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-05-02', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-05-05', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => '2026-05-18', 'date_display' => null, 'description' => 'Cash Advance', 'in' => 0, 'out' => 1000, 'type' => 'entry', 'highlight' => 'none'],
    ['date' => null, 'date_display' => null, 'description' => 'x-x-x-nothing follows-x-x-x', 'in' => 0, 'out' => 0, 'type' => 'end_marker', 'highlight' => 'none'],
];

$db = getDB();
$title = 'NONOY BALANSI June 15, 2025-May 2026';

$ownerId = (int) $db->query(
    "SELECT id FROM users WHERE role = 'owner' AND is_active = 1 ORDER BY id ASC LIMIT 1"
)->fetchColumn();

if (!$ownerId) {
    fwrite(STDERR, "No owner user found.\n");
    exit(1);
}

$existing = $db->prepare('SELECT id FROM employee_ledgers WHERE title = ? OR (employee_name = ? AND title LIKE ?)');
$existing->execute(['NONOY', 'Nonoy', 'NONOY BALANSI%']);
$existingId = (int) ($existing->fetchColumn() ?: 0);

// Prefer exact title match
$byTitle = $db->prepare('SELECT id FROM employee_ledgers WHERE title = ? LIMIT 1');
$byTitle->execute([$title]);
$existingId = (int) ($byTitle->fetchColumn() ?: $existingId);

try {
    if ($existingId) {
        $db->prepare('DELETE FROM employee_ledgers WHERE id = ?')->execute([$existingId]);
        echo "Replaced existing Nonoy ledger #{$existingId}\n";
    }

    $ledgerId = createEmployeeLedger([
        'employee_name' => 'Nonoy',
        'title'         => $title,
        'in_label'      => 'Hatagon Beben',
        'out_label'     => 'Utang Nonoy',
        'balance_as_of' => '2026-05-05',
        'is_visible'    => false,
        'notes'         => 'Imported from paper BALANSI template.',
    ], $ownerId);

    $order = 1;
    foreach ($entries as $row) {
        addLedgerEntry($ledgerId, [
            'entry_date'   => $row['date'],
            'date_display' => $row['date_display'],
            'description'  => $row['description'],
            'amount_in'    => $row['in'],
            'amount_out'   => $row['out'],
            'row_type'     => $row['type'],
            'highlight'    => $row['highlight'],
            'sort_order'   => $order++,
        ]);
    }

    $loaded = getLedgerEntries($ledgerId);
    $totals = getLedgerTotals($loaded);

    echo "Created ledger #{$ledgerId}: {$title}\n";
    echo sprintf(
        "Entries: %d | IN: %s | OUT: %s | UTANGAN: %s\n",
        count($loaded),
        number_format($totals['total_in'], 2),
        number_format($totals['total_out'], 2),
        number_format($totals['balance'], 2)
    );
    echo "Open: /ledgers/view.php?id={$ledgerId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
