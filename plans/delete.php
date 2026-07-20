<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/plans/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare('SELECT * FROM service_plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();

if (!$plan) {
    flash('danger', 'Plan not found.');
    redirect('/plans/index.php');
}

$countStmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE plan_id = ?');
$countStmt->execute([$id]);
$subscriberCount = (int) $countStmt->fetchColumn();

if ($subscriberCount > 0) {
    flash('danger', 'Cannot delete plan with subscribers. Reassign customers first or mark the plan inactive.');
    redirect('/plans/index.php');
}

$db->prepare('DELETE FROM service_plans WHERE id = ?')->execute([$id]);
logActivity('plan_deleted', "Deleted plan {$plan['name']}");
flash('success', 'Plan deleted successfully.');
redirect('/plans/index.php');
