<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'technical');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare(
    'SELECT c.*, p.name as plan_name, p.monthly_fee FROM customers c
     JOIN service_plans p ON c.plan_id = p.id WHERE c.id = ?'
);
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('/customers/index.php');
}

$pageTitle = $customer['full_name'];
$currentPage = 'customers';
$plans = getDB()->query('SELECT * FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee')->fetchAll();
$errors = [];
$data = normalizeCustomerFormData(array_merge($customer, $_POST));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$data['full_name']) $errors[] = 'Full name is required.';
    if (!$data['phone']) $errors[] = 'Phone is required.';
    if (!$data['plan_id']) $errors[] = 'Service plan is required.';
    $errors = array_merge($errors, validateCustomerAddressFields($data));
    if (hasRole('owner')) {
        $errors = array_merge(
            $errors,
            validateBillingYearRange($data['billing_generate_from_year'], $data['billing_generate_to_year'])
        );
    }

    if (empty($errors)) {
        $oldPlanId = (int) $customer['plan_id'];
        $newPlanId = (int) $data['plan_id'];
        $user = currentUser();

        if (hasRole('owner')) {
            $stmt = getDB()->prepare(
                'UPDATE customers SET full_name=?, email=?, phone=?, connection_medium=?, address=?, barangay=?, city=?, province=?,
                 plan_id=?, installation_date=?, status=?, billing_generate_from_year=?, billing_generate_to_year=?, notes=? WHERE id=?'
            );
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?: null,
                $data['phone'],
                $data['connection_medium'],
                $data['address'],
                $data['barangay'] ?: null,
                $data['city'],
                $data['province'],
                $data['plan_id'],
                $data['installation_date'],
                $data['status'],
                $data['billing_generate_from_year'],
                $data['billing_generate_to_year'],
                $data['notes'] ?: null,
                $id,
            ]);
        } else {
            $stmt = getDB()->prepare(
                'UPDATE customers SET full_name=?, email=?, phone=?, connection_medium=?, address=?, barangay=?, city=?, province=?,
                 plan_id=?, installation_date=?, status=?, notes=? WHERE id=?'
            );
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?: null,
                $data['phone'],
                $data['connection_medium'],
                $data['address'],
                $data['barangay'] ?: null,
                $data['city'],
                $data['province'],
                $data['plan_id'],
                $data['installation_date'],
                $data['status'],
                $data['notes'] ?: null,
                $id,
            ]);
        }

        if ($oldPlanId !== $newPlanId) {
            recordCustomerPlanChange($id, $oldPlanId, $newPlanId, (int) ($user['id'] ?? 0) ?: null, 'Plan changed');
            logActivity(
                'plan_changed',
                "Changed plan for {$customer['account_number']} from plan #{$oldPlanId} to #{$newPlanId}"
            );
        }

        logActivity('customer_updated', "Updated customer {$customer['account_number']}");
        flash('success', 'Customer updated successfully.');
        redirect("/customers/view.php?id={$id}");
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Customer — <?= e($customer['account_number']) ?></h1>
    <a href="<?= APP_URL ?>/customers/view.php?id=<?= $id ?>" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" required value="<?= e($data['full_name']) ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="text" id="phone" name="phone" required value="<?= e($data['phone']) ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($data['email']) ?>">
            </div>
            <div class="form-group">
                <label for="plan_id">Service Plan *</label>
                <select id="plan_id" name="plan_id" required>
                    <?php foreach ($plans as $plan): ?>
                    <option value="<?= $plan['id'] ?>" <?= $data['plan_id'] == $plan['id'] ? 'selected' : '' ?>>
                        <?= e($plan['name']) ?> — <?= formatMoney($plan['monthly_fee']) ?>/mo
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="connection_medium">Medium of Connection *</label>
                <select id="connection_medium" name="connection_medium" required>
                    <?php foreach (connectionMediumOptions() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $data['connection_medium'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="installation_date">Installation Date *</label>
                <input type="date" id="installation_date" name="installation_date" required
                       value="<?= e($data['installation_date']) ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $data['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="disconnected" <?= $data['status'] === 'disconnected' ? 'selected' : '' ?>>Disconnected</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="address">Street / Building Address *</label>
                <textarea id="address" name="address" rows="2" required><?= e($data['address']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="barangay">Barangay</label>
                <input type="text" id="barangay" name="barangay" value="<?= e($data['barangay']) ?>">
            </div>
            <div class="form-group">
                <label for="city">City / Municipality *</label>
                <input type="text" id="city" name="city" required value="<?= e($data['city']) ?>">
            </div>
            <div class="form-group">
                <label for="province">Province *</label>
                <input type="text" id="province" name="province" required value="<?= e($data['province']) ?>">
            </div>
            <div class="form-group full-width">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2"><?= e($data['notes']) ?></textarea>
            </div>
            <?php if (hasRole('owner')): ?>
            <div class="form-group full-width billing-year-range-section">
                <h3 class="form-section-title">Bill Generation Year Range</h3>
                <p class="form-hint">Optional inclusive years for monthly bill generation. Leave both empty to generate all missing periods through the current month.</p>
            </div>
            <div class="form-group">
                <label for="billing_generate_from_year">From Year (inclusive)</label>
                <select id="billing_generate_from_year" name="billing_generate_from_year">
                    <option value="">No limit</option>
                    <?php foreach (billingYearOptions() as $year): ?>
                    <option value="<?= $year ?>" <?= ($data['billing_generate_from_year'] ?? null) == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="billing_generate_to_year">To Year (inclusive)</label>
                <select id="billing_generate_to_year" name="billing_generate_to_year">
                    <option value="">No limit</option>
                    <?php foreach (billingYearOptions() as $year): ?>
                    <option value="<?= $year ?>" <?= ($data['billing_generate_to_year'] ?? null) == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php renderCustomerDeleteSection($customer); ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
