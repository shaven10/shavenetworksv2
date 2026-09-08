<?php
/**
 * Run once: php database/migrate_ledgers.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ledgers.php';

ensureEmployeeLedgerTables();
echo "employee_ledgers and employee_ledger_entries tables ready.\n";
