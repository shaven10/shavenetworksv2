<?php
/**
 * Create/update the default customers + service_plans restore point.
 * Run: D:\SC30\php\php.exe database/create_default_restore_point.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/database_tools.php';

$meta = createDefaultRestorePoint('Default customers & service plans');
echo "Default restore point saved.\n";
echo "Plans: {$meta['plan_count']}\n";
echo "Customers: {$meta['customer_count']}\n";
echo "File: " . getDefaultRestorePointSqlPath() . "\n";
