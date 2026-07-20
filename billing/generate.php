<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
requireRole('owner');

$pageTitle = 'Generate Bills';
$currentPage = 'billing';

$customers = getDB()->query(
    'SELECT id, account_number, full_name, status, installation_date,
            billing_generate_from_year, billing_generate_to_year
     FROM customers
     WHERE status != "disconnected"
     ORDER BY full_name'
)->fetchAll();

$selectedCustomerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$mode = $_POST['mode'] ?? ($selectedCustomerId ? 'customer' : 'all');
$fromYearInput = trim($_POST['from_year'] ?? $_GET['from_year'] ?? '');
$toYearInput = trim($_POST['to_year'] ?? $_GET['to_year'] ?? '');
$fromYear = $fromYearInput !== '' ? (int) $fromYearInput : null;
$toYear = $toYearInput !== '' ? (int) $toYearInput : null;
$saveYearRange = !empty($_POST['save_year_range']);
$errors = [];
$activeCount = (int) getDB()->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();

$selectedCustomer = null;
if ($selectedCustomerId) {
    foreach ($customers as $customer) {
        if ((int) $customer['id'] === $selectedCustomerId) {
            $selectedCustomer = $customer;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $selectedCustomer && $fromYearInput === '' && $toYearInput === '') {
    $fromYear = $selectedCustomer['billing_generate_from_year'] !== null
        ? (int) $selectedCustomer['billing_generate_from_year'] : null;
    $toYear = $selectedCustomer['billing_generate_to_year'] !== null
        ? (int) $selectedCustomer['billing_generate_to_year'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'all';
    $errors = validateBillingYearRange($fromYear, $toYear);

    if ($mode === 'customer') {
        if (!$selectedCustomerId) {
            $errors[] = 'Please select a customer.';
        }

        if ($selectedCustomer && $selectedCustomer['status'] !== 'active') {
            $errors[] = 'Only active customers can receive new bills.';
        }

        if (empty($errors)) {
            try {
                $result = generateBillsForCustomerId($selectedCustomerId, $fromYear, $toYear, $saveYearRange);
                $rangeLabel = formatBillingYearRange($result['from_year'], $result['to_year']);
                logActivity(
                    'bills_generated',
                    "Generated {$result['generated']} bill(s) for customer #{$selectedCustomerId} ({$rangeLabel})"
                );

                if ($result['generated'] > 0) {
                    flash(
                        'success',
                        "Generated {$result['generated']} new bill(s) for {$result['customer']['full_name']} ({$rangeLabel})."
                    );
                } else {
                    flash(
                        'info',
                        "No new bills to generate for {$result['customer']['full_name']} in {$rangeLabel}. Existing periods may already have bills."
                    );
                }

                redirect('/billing/index.php');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif (empty($errors)) {
        $result = generateAllBills($fromYear, $toYear);
        $rangeLabel = formatBillingYearRange($result['from_year'], $result['to_year']);
        logActivity(
            'bills_generated',
            "Generated {$result['generated']} bill(s) for all active customers ({$rangeLabel})"
        );
        flash(
            'success',
            "Generated {$result['generated']} new bill(s) for {$activeCount} active customer(s) in {$rangeLabel}. {$result['skipped']} customer(s) had no new bills (already up to date or outside the selected years)."
        );
        redirect('/billing/index.php');
    }
}

$yearOptions = billingYearOptions();
$rangeLabel = formatBillingYearRange($fromYear, $toYear);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Generate Monthly Bills</h1>
        <p>Backfill missing periods or limit by year range</p>
    </div>
    <div class="header-actions">
        <?php if (hasRole('owner', 'collector')): ?>
        <a href="<?= APP_URL ?>/billing/generate_current.php" class="btn btn-outline">Current Month Only</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/billing/index.php" class="btn btn-outline">← Back</a>
    </div>
</div>

<div class="card">
    <div class="info-box">
        <h3>How Installation-Date Billing Works</h3>
        <ul>
            <li>Each customer's billing cycle is anchored to their <strong>installation date</strong>.</li>
            <li>Example: Installed on Jan 15 → billing period runs 15th to 14th of the next month.</li>
            <li>Due date is set to <strong>7 days after</strong> the billing period ends.</li>
            <li>Only <strong>active</strong> customers receive bills.</li>
            <li>Generates <strong>missing periods</strong> from installation through the current month.</li>
            <li>Periods that already have a bill are automatically skipped.</li>
            <li>Set an <strong>inclusive year range</strong> to limit which billing periods are generated (based on period start year).</li>
            <li>Leave both years empty to include all missing periods through the current month.</li>
        </ul>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="generate-bills-form" class="generate-bills-form">
        <section class="generate-form-section">
            <h3 class="form-section-title">Generation Scope</h3>
            <div class="mode-toggle generate-scope-toggle">
                <label class="mode-option">
                    <input type="radio" name="mode" value="all" <?= $mode === 'all' ? 'checked' : '' ?>>
                    <span>All active customers<br><small><?= $activeCount ?> subscriber(s)</small></span>
                </label>
                <label class="mode-option">
                    <input type="radio" name="mode" value="customer" <?= $mode === 'customer' ? 'checked' : '' ?>>
                    <span>Specific customer<br><small>One account only</small></span>
                </label>
            </div>
        </section>

        <section id="customer-select-field" class="generate-form-section <?= $mode === 'customer' ? '' : 'hidden' ?>">
            <h3 class="form-section-title">Customer</h3>
            <div class="form-group generate-customer-field">
                <label for="customer_id">Select customer *</label>
                <select id="customer_id" name="customer_id">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?= (int) $customer['id'] ?>"
                            <?= $selectedCustomerId === (int) $customer['id'] ? 'selected' : '' ?>
                            data-from="<?= e($customer['billing_generate_from_year'] ?? '') ?>"
                            data-to="<?= e($customer['billing_generate_to_year'] ?? '') ?>"
                            data-status="<?= e($customer['status']) ?>">
                        <?= e($customer['account_number']) ?> — <?= e($customer['full_name']) ?>
                        <?php if ($customer['status'] !== 'active'): ?> (<?= e(ucfirst($customer['status'])) ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <section class="generate-form-section">
            <h3 class="form-section-title">Inclusive Year Range</h3>
            <p class="form-hint generate-year-intro">
                Limit bill generation by billing period start year. Leave both empty to include all missing periods through the current month.
            </p>

            <div class="form-grid generate-year-grid">
                <div class="form-group">
                    <label for="from_year">From Year (inclusive)</label>
                    <select id="from_year" name="from_year">
                        <option value="">No limit</option>
                        <?php foreach ($yearOptions as $year): ?>
                        <option value="<?= $year ?>" <?= $fromYear === $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="to_year">To Year (inclusive)</label>
                    <select id="to_year" name="to_year">
                        <option value="">No limit</option>
                        <?php foreach ($yearOptions as $year): ?>
                        <option value="<?= $year ?>" <?= $toYear === $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="generate-range-summary" id="year-range-hint">
                Selected range: <strong><?= e($rangeLabel) ?></strong>
                <?php if ($mode === 'customer' && $selectedCustomer): ?>
                · Saved for this customer:
                <strong><?= e(formatBillingYearRange(
                    $selectedCustomer['billing_generate_from_year'] !== null ? (int) $selectedCustomer['billing_generate_from_year'] : null,
                    $selectedCustomer['billing_generate_to_year'] !== null ? (int) $selectedCustomer['billing_generate_to_year'] : null
                )) ?></strong>
                <?php endif; ?>
            </div>

            <div id="save-year-range-field" class="form-group <?= $mode === 'customer' ? '' : 'hidden' ?>">
                <label class="checkbox-label save-year-range-checkbox">
                    <input type="checkbox" name="save_year_range" value="1" <?= $saveYearRange ? 'checked' : '' ?>>
                    Save this year range to the customer profile
                </label>
            </div>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg" id="generate-bills-btn">
                <?= $mode === 'customer' ? 'Generate Bills for Customer' : 'Generate Bills for All Active Customers' ?>
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('generate-bills-form');
    var modeInputs = form.querySelectorAll('input[name="mode"]');
    var customerField = document.getElementById('customer-select-field');
    var saveYearField = document.getElementById('save-year-range-field');
    var customerSelect = document.getElementById('customer_id');
    var fromYear = document.getElementById('from_year');
    var toYear = document.getElementById('to_year');
    var hint = document.getElementById('year-range-hint');
    var submitBtn = document.getElementById('generate-bills-btn');

    function formatRange(fromVal, toVal) {
        if (!fromVal && !toVal) {
            return 'All years (installation through current month)';
        }
        if (fromVal && toVal) {
            return fromVal === toVal ? fromVal : fromVal + ' – ' + toVal + ' (inclusive)';
        }
        if (fromVal) {
            return 'From ' + fromVal + ' onwards';
        }
        return 'Through ' + toVal;
    }

    function updateHint() {
        var mode = form.querySelector('input[name="mode"]:checked').value;
        var selectedRange = formatRange(fromYear.value, toYear.value);
        var html = 'Selected range: <strong>' + selectedRange + '</strong>';

        if (mode === 'customer') {
            var option = customerSelect.options[customerSelect.selectedIndex];
            if (option && option.value) {
                html += ' · Saved for this customer: <strong>' +
                    formatRange(option.dataset.from || '', option.dataset.to || '') + '</strong>';
            }
        }

        hint.innerHTML = html;
    }

    function toggleMode() {
        var mode = form.querySelector('input[name="mode"]:checked').value;
        customerField.classList.toggle('hidden', mode !== 'customer');
        saveYearField.classList.toggle('hidden', mode !== 'customer');
        submitBtn.textContent = mode === 'customer'
            ? 'Generate Bills for Customer'
            : 'Generate Bills for All Active Customers';
        updateHint();
    }

    modeInputs.forEach(function (input) {
        input.addEventListener('change', toggleMode);
    });

    customerSelect.addEventListener('change', function () {
        var option = customerSelect.options[customerSelect.selectedIndex];
        fromYear.value = option && option.dataset.from ? option.dataset.from : '';
        toYear.value = option && option.dataset.to ? option.dataset.to : '';
        updateHint();
    });

    fromYear.addEventListener('change', updateHint);
    toYear.addEventListener('change', updateHint);

    form.addEventListener('submit', function (event) {
        var mode = form.querySelector('input[name="mode"]:checked').value;
        var range = formatRange(fromYear.value, toYear.value);
        var message = mode === 'customer'
            ? 'Generate bills for the selected customer in ' + range + '?'
            : 'Generate bills for all active customers in ' + range + '?';
        if (!confirm(message)) {
            event.preventDefault();
        }
    });

    toggleMode();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
