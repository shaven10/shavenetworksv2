<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscriber_import.php';
requireRole('owner');

$format = strtolower(trim($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'csv'], true)) {
    $format = 'xlsx';
}

$planNames = getDB()->query(
    'SELECT name FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee, name'
)->fetchAll(PDO::FETCH_COLUMN);

try {
    if ($format === 'csv') {
        $bytes = buildSubscriberImportTemplateCsv($planNames);
        $filename = 'subscribers_import_template.csv';
        $contentType = 'text/csv; charset=UTF-8';
    } else {
        $bytes = buildSubscriberImportTemplateXlsx($planNames);
        $filename = 'subscribers_import_template.xlsx';
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
} catch (Throwable $e) {
    flash('danger', 'Unable to generate template: ' . $e->getMessage());
    redirect('/customers/import.php');
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $bytes;
exit;
