<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner', 'technical');

$pageTitle = 'Add Customer';
$currentPage = 'customers';

$plans = getDB()->query('SELECT * FROM service_plans WHERE is_active = 1 ORDER BY monthly_fee')->fetchAll();
$errors = [];
$data = normalizeCustomerFormData($_POST ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$data['full_name']) $errors[] = 'Full name is required.';
    if (!$data['phone']) $errors[] = 'Phone is required.';
    if (!$data['plan_id']) $errors[] = 'Service plan is required.';
    if (!$data['installation_date']) $errors[] = 'Installation date is required.';
    $errors = array_merge($errors, validateCustomerAddressFields($data));

    if (empty($errors)) {
        $accountNumber = generateAccountNumber();
        $user = currentUser();

        $stmt = getDB()->prepare(
            'INSERT INTO customers (account_number, full_name, email, phone, connection_medium, address, barangay, city, province, plan_id, installation_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $accountNumber,
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
            $data['notes'] ?: null,
            $user['id'],
        ]);

        $customerId = (int) getDB()->lastInsertId();
        recordCustomerPlanStart(
            $customerId,
            $data['plan_id'],
            $data['installation_date'] . ' 00:00:00',
            (int) $user['id'],
            'Initial plan'
        );

        logActivity('customer_created', "Created customer {$accountNumber}");
        flash('success', "Customer {$accountNumber} created successfully.");
        redirect('/customers/index.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add Customer</h1>
    <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-outline">← Back</a>
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
                    <option value="">Select plan...</option>
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
                    <option value="">Select medium...</option>
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
                       value="<?= e($data['installation_date'] ?: date('Y-m-d')) ?>">
                <small class="form-hint">Billing cycle is based on this date each month.</small>
            </div>
            <div class="form-group full-width">
                <label for="address">Street / Building Address *</label>
                <textarea id="address" name="address" rows="2" required placeholder="House no., street, subdivision"><?= e($data['address']) ?></textarea>
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
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Customer</button>
            <a href="<?= APP_URL ?>/customers/index.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
