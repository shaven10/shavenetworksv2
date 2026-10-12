<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$editId = (int) ($_GET['edit_id'] ?? 0);

$stmt = getDB()->prepare(
    'SELECT c.*, p.name AS plan_name
     FROM customers c
     JOIN service_plans p ON p.id = c.plan_id
     WHERE c.id = ?'
);
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('/customers/index.php');
}

$pageTitle = 'Plan History';
$currentPage = 'customers';
$plans = getDB()->query('SELECT * FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee')->fetchAll();
$errors = [];
$history = getCustomerPlanHistory($customerId);
$editRow = $editId ? getCustomerPlanHistoryRow($editId) : null;

if ($editRow && (int) $editRow['customer_id'] !== $customerId) {
    flash('danger', 'Plan history row does not belong to this customer.');
    redirect('/customers/plan_history.php?customer_id=' . $customerId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) (currentUser()['id'] ?? 0) ?: null;

    try {
        if ($action === 'update') {
            $historyId = (int) ($_POST['history_id'] ?? 0);
            updateCustomerPlanHistory(
                $historyId,
                (int) ($_POST['plan_id'] ?? 0),
                (string) ($_POST['started_at'] ?? ''),
                ($_POST['ended_at'] ?? '') !== '' ? (string) $_POST['ended_at'] : null,
                trim((string) ($_POST['notes'] ?? '')),
                $userId
            );
            logActivity('plan_history_updated', "Updated plan history #{$historyId} for {$customer['account_number']}");
            flash('success', 'Plan history updated.');
            redirect('/customers/plan_history.php?customer_id=' . $customerId);
        }

        if ($action === 'add') {
            addCustomerPlanHistory(
                $customerId,
                (int) ($_POST['plan_id'] ?? 0),
                (string) ($_POST['started_at'] ?? ''),
                ($_POST['ended_at'] ?? '') !== '' ? (string) $_POST['ended_at'] : null,
                trim((string) ($_POST['notes'] ?? '')),
                $userId
            );
            logActivity('plan_history_added', "Added plan history for {$customer['account_number']}");
            flash('success', 'Plan history entry added.');
            redirect('/customers/plan_history.php?customer_id=' . $customerId);
        }

        if ($action === 'delete') {
            $historyId = (int) ($_POST['history_id'] ?? 0);
            deleteCustomerPlanHistoryRow($historyId);
            logActivity('plan_history_deleted', "Deleted plan history #{$historyId} for {$customer['account_number']}");
            flash('success', 'Plan history entry deleted.');
            redirect('/customers/plan_history.php?customer_id=' . $customerId);
        }

        $errors[] = 'Unknown action.';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $history = getCustomerPlanHistory($customerId);
        if ($action === 'update') {
            $editId = (int) ($_POST['history_id'] ?? 0);
            $editRow = $editId ? getCustomerPlanHistoryRow($editId) : null;
        }
    }
}

$formPlanId = (int) ($_POST['plan_id'] ?? ($editRow['plan_id'] ?? $customer['plan_id']));
$formStarted = (string) ($_POST['started_at'] ?? (
    $editRow ? substr((string) $editRow['started_at'], 0, 10) : ($customer['installation_date'] ?? date('Y-m-d'))
));
$formEnded = (string) ($_POST['ended_at'] ?? (
    $editRow && !empty($editRow['ended_at']) ? substr((string) $editRow['ended_at'], 0, 10) : ''
));
$formNotes = (string) ($_POST['notes'] ?? ($editRow['notes'] ?? ''));

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Plan History</h1>
        <p>
            <?= e($customer['account_number']) ?> — <?= e($customer['full_name']) ?>
            · Current plan: <?= e($customer['plan_name']) ?>
        </p>
    </div>
    <div class="header-actions">
        <a href="<?= APP_URL ?>/customers/view.php?id=<?= $customerId ?>" class="btn btn-outline">← Customer</a>
        <a href="<?= APP_URL ?>/customers/edit.php?id=<?= $customerId ?>" class="btn btn-outline">Edit Customer</a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $editRow ? 'Edit Plan Period' : 'Add Plan Period' ?></h2>
    </div>
    <p class="form-hint" style="margin-bottom:12px">
        Dates must not overlap. Use half-open periods: one plan ends on the same date the next plan starts.
        Leave <strong>Ended</strong> empty for the current plan.
    </p>
    <form method="POST" class="card-form">
        <input type="hidden" name="customer_id" value="<?= $customerId ?>">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'add' ?>">
        <?php if ($editRow): ?>
        <input type="hidden" name="history_id" value="<?= (int) $editRow['id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="plan_id">Service Plan *</label>
                <select id="plan_id" name="plan_id" required>
                    <?php foreach ($plans as $plan): ?>
                    <option value="<?= (int) $plan['id'] ?>" <?= $formPlanId === (int) $plan['id'] ? 'selected' : '' ?>>
                        <?= e($plan['name']) ?> — <?= formatMoney((float) $plan['monthly_fee']) ?>/mo
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="started_at">Started *</label>
                <input type="date" id="started_at" name="started_at" required
                       min="<?= e($customer['installation_date']) ?>"
                       value="<?= e($formStarted) ?>">
            </div>
            <div class="form-group">
                <label for="ended_at">Ended</label>
                <input type="date" id="ended_at" name="ended_at"
                       min="<?= e($customer['installation_date']) ?>"
                       value="<?= e($formEnded) ?>">
                <small class="form-hint">Leave blank if this is the current plan.</small>
            </div>
            <div class="form-group full-width">
                <label for="notes">Notes</label>
                <input type="text" id="notes" name="notes" value="<?= e($formNotes) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editRow ? 'Save Changes' : 'Add Period' ?></button>
            <?php if ($editRow): ?>
            <a href="<?= APP_URL ?>/customers/plan_history.php?customer_id=<?= $customerId ?>" class="btn btn-outline">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><h2>History Timeline</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Fee</th>
                    <th>Started</th>
                    <th>Ended</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr><td colspan="7" class="text-center text-muted">No plan history.</td></tr>
                <?php else: foreach ($history as $row): ?>
                <tr>
                    <td>
                        <?= e($row['plan_name']) ?><br>
                        <small class="text-muted"><?= (int) $row['speed_mbps'] ?> Mbps</small>
                    </td>
                    <td><?= formatMoney((float) $row['monthly_fee']) ?></td>
                    <td><?= formatDate($row['started_at']) ?></td>
                    <td><?= !empty($row['ended_at']) ? formatDate($row['ended_at']) : '—' ?></td>
                    <td><?= e($row['notes'] ?: '—') ?></td>
                    <td>
                        <?php if (empty($row['ended_at'])): ?>
                        <span class="badge badge-success">Current</span>
                        <?php else: ?>
                        <span class="badge badge-secondary">Previous</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="<?= APP_URL ?>/customers/plan_history.php?customer_id=<?= $customerId ?>&edit_id=<?= (int) $row['id'] ?>"
                           class="btn btn-sm btn-outline">Edit</a>
                        <?php if (count($history) > 1): ?>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete this plan history period?');">
                            <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="history_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
