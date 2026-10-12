<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ledger_io.php';
requireRole('owner');

$format = strtolower(trim($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'csv'], true)) {
    $format = 'xlsx';
}

try {
    if ($format === 'csv') {
        $bytes = buildLedgerImportTemplateCsv();
        $filename = 'ledger_import_template.csv';
        $contentType = 'text/csv; charset=UTF-8';
    } else {
        $bytes = buildLedgerImportTemplateXlsx();
        $filename = 'ledger_import_template.xlsx';
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
} catch (Throwable $e) {
    flash('danger', 'Unable to generate template: ' . $e->getMessage());
    redirect('/ledgers/import.php');
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $bytes;
exit;
