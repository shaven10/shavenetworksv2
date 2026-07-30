<?php
/**
 * Generates static template files under assets/templates/
 * Run: php database/generate_import_template.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscriber_import.php';

$dir = __DIR__ . '/../assets/templates';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Unable to create assets/templates\n");
    exit(1);
}

$planNames = getDB()->query(
    'SELECT name FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee, name'
)->fetchAll(PDO::FETCH_COLUMN);

$xlsx = buildSubscriberImportTemplateXlsx($planNames);
$csv = buildSubscriberImportTemplateCsv($planNames);

$xlsxPath = $dir . '/subscribers_import_template.xlsx';
$csvPath = $dir . '/subscribers_import_template.csv';

file_put_contents($xlsxPath, $xlsx);
file_put_contents($csvPath, $csv);

echo "Wrote {$xlsxPath} (" . strlen($xlsx) . " bytes)\n";
echo "Wrote {$csvPath} (" . strlen($csv) . " bytes)\n";
echo 'Plans: ' . implode(', ', $planNames) . "\n";
