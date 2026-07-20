<?php

/**
 * Calculate billing period based on customer installation date.
 * Billing cycle runs from installation day-of-month to the day before next month's same day.
 */
function getBillingPeriod(string $installationDate, ?string $referenceDate = null): array
{
    $ref = $referenceDate ? new DateTime($referenceDate) : new DateTime();
    $install = new DateTime($installationDate);
    $billingDay = (int) $install->format('d');

    $periodStart = clone $ref;
    $periodStart->setDate((int) $ref->format('Y'), (int) $ref->format('m'), 1);
    $periodStart->setDate(
        (int) $periodStart->format('Y'),
        (int) $periodStart->format('m'),
        min($billingDay, (int) $periodStart->format('t'))
    );

    if ($periodStart > $ref) {
        $periodStart->modify('-1 month');
        $periodStart->setDate(
            (int) $periodStart->format('Y'),
            (int) $periodStart->format('m'),
            min($billingDay, (int) $periodStart->format('t'))
        );
    }

    $periodEnd = clone $periodStart;
    $periodEnd->modify('+1 month');
    $periodEnd->modify('-1 day');

    $dueDate = clone $periodEnd;
    $dueDate->modify('+7 days');

    return [
        'start'    => $periodStart->format('Y-m-d'),
        'end'      => $periodEnd->format('Y-m-d'),
        'due_date' => $dueDate->format('Y-m-d'),
    ];
}

function getNextBillingDate(string $installationDate, ?string $fromDate = null): string
{
    $period = getBillingPeriod($installationDate, $fromDate);
    $next = new DateTime($period['end']);
    $next->modify('+1 day');
    return $next->format('Y-m-d');
}

function billExists(int $customerId, string $periodStart, string $periodEnd): bool
{
    $stmt = getDB()->prepare(
        'SELECT COUNT(*) FROM bills WHERE customer_id = ? AND billing_period_start = ? AND billing_period_end = ?'
    );
    $stmt->execute([$customerId, $periodStart, $periodEnd]);
    return (int) $stmt->fetchColumn() > 0;
}

function getAllBillingPeriods(string $installationDate, ?string $referenceDate = null): array
{
    $current = getBillingPeriod($installationDate, $referenceDate);
    $currentStart = new DateTime($current['start']);
    $install = new DateTime($installationDate);

    $periods = [];
    $periodStart = clone $install;

    while ($periodStart <= $currentStart) {
        $periodEnd = clone $periodStart;
        $periodEnd->modify('+1 month');
        $periodEnd->modify('-1 day');

        $dueDate = clone $periodEnd;
        $dueDate->modify('+7 days');

        $periods[] = [
            'start'    => $periodStart->format('Y-m-d'),
            'end'      => $periodEnd->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
        ];

        $periodStart = clone $periodEnd;
        $periodStart->modify('+1 day');
    }

    return $periods;
}

function generateBillForCustomer(array $customer): int
{
    if ($customer['status'] !== 'active') {
        return 0;
    }

    $generated = 0;
    $periods = getAllBillingPeriods($customer['installation_date']);

    foreach ($periods as $period) {
        if (billExists($customer['id'], $period['start'], $period['end'])) {
            continue;
        }

        $stmt = getDB()->prepare(
            'INSERT INTO bills (customer_id, bill_number, billing_period_start, billing_period_end, due_date, amount)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customer['id'],
            generateBillNumber(),
            $period['start'],
            $period['end'],
            $period['due_date'],
            $customer['monthly_fee'],
        ]);

        $generated++;
    }

    if ($generated > 0) {
        applyCustomerAdvanceToBills((int) $customer['id'], getDB());
    }

    return $generated;
}

function generateAllBills(): array
{
    $stmt = getDB()->query(
        'SELECT c.*, p.monthly_fee FROM customers c
         JOIN service_plans p ON c.plan_id = p.id
         WHERE c.status = "active"'
    );
    $customers = $stmt->fetchAll();

    $generated = 0;
    $skippedCustomers = 0;

    foreach ($customers as $customer) {
        $count = generateBillForCustomer($customer);
        $generated += $count;
        if ($count === 0) {
            $skippedCustomers++;
        }
    }

    updateOverdueBills();

    return [
        'generated'         => $generated,
        'skipped'           => $skippedCustomers,
        'skipped_customers' => $skippedCustomers,
    ];
}

function updateOverdueBills(): void
{
    getDB()->exec(
        "UPDATE bills SET status = 'overdue'
         WHERE status IN ('pending', 'partial') AND due_date < CURDATE()"
    );
}

function applyBillPayment(PDO $db, int $billId, float $amount, string $method, ?string $reference, ?string $notes, int $collectedBy, ?int $batchId = null): int
{
    $stmt = $db->prepare(
        'SELECT b.*, c.id as customer_id FROM bills b
         JOIN customers c ON b.customer_id = c.id WHERE b.id = ? FOR UPDATE'
    );
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        throw new RuntimeException('Bill not found.');
    }

    $remaining = (float) $bill['amount'] - (float) $bill['paid_amount'];
    if ($amount <= 0 || $amount > $remaining + 0.01) {
        throw new RuntimeException('Invalid payment amount for bill ' . $bill['bill_number'] . '.');
    }

    $invoiceNumber = $batchId ? null : generateInvoiceNumber();

    $stmt = $db->prepare(
        'INSERT INTO payments (invoice_number, batch_id, payment_type, bill_id, customer_id, amount, payment_method, reference_number, notes, collected_by, payment_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
    );
    $stmt->execute([
        $invoiceNumber,
        $batchId,
        'bill',
        $billId,
        $bill['customer_id'],
        $amount,
        $method,
        $reference,
        $notes,
        $collectedBy,
    ]);

    $paymentId = (int) $db->lastInsertId();

    $newPaid = (float) $bill['paid_amount'] + $amount;
    $newStatus = $newPaid >= (float) $bill['amount'] ? 'paid' : 'partial';

    $stmt = $db->prepare('UPDATE bills SET paid_amount = ?, status = ? WHERE id = ?');
    $stmt->execute([$newPaid, $newStatus, $billId]);

    if ($newStatus === 'paid') {
        $stmt = $db->prepare('UPDATE customers SET status = "active" WHERE id = ? AND status = "suspended"');
        $stmt->execute([$bill['customer_id']]);
    }

    return $paymentId;
}

function recordPayment(int $billId, float $amount, string $method, ?string $reference, ?string $notes, int $collectedBy): ?int
{
    $db = getDB();
    $db->beginTransaction();

    try {
        $paymentId = applyBillPayment($db, $billId, $amount, $method, $reference, $notes, $collectedBy);
        $db->commit();
        return $paymentId;
    } catch (Exception) {
        $db->rollBack();
        return null;
    }
}

function getCustomerOutstandingBills(int $customerId): array
{
    applyCustomerAdvanceToBills($customerId);

    $stmt = getDB()->prepare(
        'SELECT b.*, (b.amount - b.paid_amount) as balance
         FROM bills b
         WHERE b.customer_id = ? AND b.status IN ("pending", "partial", "overdue")
         ORDER BY b.billing_period_start ASC, b.due_date ASC'
    );
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function getCustomerAdvanceBalance(int $customerId): float
{
    $stmt = getDB()->prepare('SELECT advance_balance FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    return (float) ($stmt->fetchColumn() ?: 0);
}

function getSystemCollectorId(): int
{
    $id = getDB()->query("SELECT id FROM users WHERE role = 'owner' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    return $id ? (int) $id : 1;
}

function recordAdvancePayment(int $customerId, float $amount, string $method, ?string $reference, ?string $notes, int $collectedBy): array
{
    if ($amount <= 0) {
        throw new RuntimeException('Advance amount must be greater than zero.');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare('SELECT id, full_name, account_number, advance_balance FROM customers WHERE id = ? FOR UPDATE');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();

        if (!$customer) {
            throw new RuntimeException('Customer not found.');
        }

        $invoiceNumber = generateAdvanceInvoiceNumber();

        $stmt = $db->prepare(
            'INSERT INTO payments (invoice_number, payment_type, bill_id, customer_id, amount, payment_method, reference_number, notes, collected_by, payment_date)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->execute([
            $invoiceNumber,
            'advance',
            $customerId,
            $amount,
            $method,
            $reference,
            $notes,
            $collectedBy,
        ]);
        $paymentId = (int) $db->lastInsertId();

        $stmt = $db->prepare('UPDATE customers SET advance_balance = advance_balance + ? WHERE id = ?');
        $stmt->execute([$amount, $customerId]);

        $applied = applyCustomerAdvanceToBills($customerId, $db);

        $db->commit();

        return [
            'payment_id'      => $paymentId,
            'invoice_number'  => $invoiceNumber,
            'amount'          => $amount,
            'applied'         => $applied,
            'remaining_credit'=> getCustomerAdvanceBalance($customerId),
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function applyCustomerAdvanceToBills(int $customerId, ?PDO $db = null): float
{
    $manageTransaction = $db === null;
    if ($manageTransaction) {
        $db = getDB();
        $db->beginTransaction();
    }

    try {
        $stmt = $db->prepare('SELECT advance_balance FROM customers WHERE id = ? FOR UPDATE');
        $stmt->execute([$customerId]);
        $balance = (float) ($stmt->fetchColumn() ?: 0);

        if ($balance <= 0) {
            if ($manageTransaction) {
                $db->commit();
            }
            return 0.0;
        }

        $billStmt = $db->prepare(
            'SELECT id, amount, paid_amount FROM bills
             WHERE customer_id = ? AND status IN ("pending", "partial", "overdue")
             ORDER BY billing_period_start ASC, due_date ASC
             FOR UPDATE'
        );
        $billStmt->execute([$customerId]);
        $bills = $billStmt->fetchAll();

        $totalApplied = 0.0;
        $collectorId = currentUser()['id'] ?? getSystemCollectorId();

        foreach ($bills as $bill) {
            if ($balance <= 0) {
                break;
            }

            $remaining = (float) $bill['amount'] - (float) $bill['paid_amount'];
            if ($remaining <= 0) {
                continue;
            }

            $applyAmount = min($balance, $remaining);
            applyAdvanceCreditToBill($db, $customerId, (int) $bill['id'], $applyAmount, $collectorId);
            $balance -= $applyAmount;
            $totalApplied += $applyAmount;
        }

        if ($manageTransaction) {
            $db->commit();
        }

        return $totalApplied;
    } catch (Throwable $e) {
        if ($manageTransaction) {
            $db->rollBack();
        }
        throw $e;
    }
}

function applyAdvanceCreditToBill(PDO $db, int $customerId, int $billId, float $amount, int $collectedBy): void
{
    if ($amount <= 0) {
        return;
    }

    $stmt = $db->prepare('SELECT advance_balance FROM customers WHERE id = ? FOR UPDATE');
    $stmt->execute([$customerId]);
    $advanceBalance = (float) ($stmt->fetchColumn() ?: 0);

    if ($amount > $advanceBalance + 0.01) {
        throw new RuntimeException('Insufficient advance balance.');
    }

    $stmt = $db->prepare('SELECT * FROM bills WHERE id = ? AND customer_id = ? FOR UPDATE');
    $stmt->execute([$billId, $customerId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        throw new RuntimeException('Bill not found.');
    }

    $remaining = (float) $bill['amount'] - (float) $bill['paid_amount'];
    if ($amount > $remaining + 0.01) {
        throw new RuntimeException('Advance amount exceeds bill balance.');
    }

    $stmt = $db->prepare(
        'INSERT INTO payments (payment_type, bill_id, customer_id, amount, payment_method, notes, collected_by, payment_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())'
    );
    $stmt->execute([
        'advance_applied',
        $billId,
        $customerId,
        $amount,
        'advance_credit',
        'Applied from customer advance balance',
        $collectedBy,
    ]);

    $stmt = $db->prepare('UPDATE customers SET advance_balance = advance_balance - ? WHERE id = ?');
    $stmt->execute([$amount, $customerId]);

    $newPaid = (float) $bill['paid_amount'] + $amount;
    $newStatus = $newPaid >= (float) $bill['amount'] ? 'paid' : 'partial';

    $stmt = $db->prepare('UPDATE bills SET paid_amount = ?, status = ? WHERE id = ?');
    $stmt->execute([$newPaid, $newStatus, $billId]);

    if ($newStatus === 'paid') {
        $stmt = $db->prepare('UPDATE customers SET status = "active" WHERE id = ? AND status = "suspended"');
        $stmt->execute([$customerId]);
    }
}

function paymentTypeLabel(?string $type): string
{
    return match ($type) {
        'advance'         => 'Advance Payment',
        'advance_applied' => 'Advance Applied',
        default           => 'Bill Payment',
    };
}

function getAdvancePaymentInvoice(int $paymentId): ?array
{
    $stmt = getDB()->prepare(
        'SELECT p.*, c.full_name, c.account_number, c.phone, c.address, c.barangay, c.city, c.province,
                c.connection_medium, c.email, c.advance_balance,
                pl.name AS plan_name,
                u.full_name AS collector_name
         FROM payments p
         JOIN customers c ON p.customer_id = c.id
         JOIN service_plans pl ON c.plan_id = pl.id
         JOIN users u ON p.collected_by = u.id
         WHERE p.id = ? AND p.payment_type = ?'
    );
    $stmt->execute([$paymentId, 'advance']);
    return $stmt->fetch() ?: null;
}

function getCustomerOutstandingTotal(int $customerId): float
{
    $stmt = getDB()->prepare(
        'SELECT COALESCE(SUM(amount - paid_amount), 0)
         FROM bills
         WHERE customer_id = ? AND status IN ("pending", "partial", "overdue")'
    );
    $stmt->execute([$customerId]);
    return (float) $stmt->fetchColumn();
}

/**
 * @param array<int, float> $billPayments bill_id => amount
 */
function recordMultiplePayments(int $customerId, array $billPayments, string $method, ?string $reference, ?string $notes, int $collectedBy): array
{
    if (empty($billPayments)) {
        throw new RuntimeException('Select at least one bill to pay.');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $validPayments = [];

        foreach ($billPayments as $billId => $amount) {
            $billId = (int) $billId;
            $amount = (float) $amount;
            if ($amount <= 0) {
                continue;
            }

            $check = $db->prepare(
                'SELECT id FROM bills WHERE id = ? AND customer_id = ? AND status IN ("pending", "partial", "overdue")'
            );
            $check->execute([$billId, $customerId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Invalid or already paid bill selected.');
            }

            $validPayments[$billId] = $amount;
        }

        if (empty($validPayments)) {
            throw new RuntimeException('No valid payment amounts entered.');
        }

        $total = array_sum($validPayments);
        $count = count($validPayments);
        $batchInvoiceNumber = generateBatchInvoiceNumber();

        $stmt = $db->prepare(
            'INSERT INTO payment_batches (batch_invoice_number, customer_id, total_amount, payment_count, payment_method, reference_number, notes, collected_by, payment_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->execute([
            $batchInvoiceNumber,
            $customerId,
            $total,
            $count,
            $method,
            $reference,
            $notes,
            $collectedBy,
        ]);
        $batchId = (int) $db->lastInsertId();

        $recorded = [];
        foreach ($validPayments as $billId => $amount) {
            $paymentId = applyBillPayment($db, $billId, $amount, $method, $reference, $notes, $collectedBy, $batchId);
            $recorded[] = [
                'payment_id' => $paymentId,
                'bill_id'    => $billId,
                'amount'     => $amount,
            ];
        }

        $db->commit();

        return [
            'payments'             => $recorded,
            'total'                => $total,
            'count'                => $count,
            'batch_id'             => $batchId,
            'batch_invoice_number' => $batchInvoiceNumber,
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function getPaymentInvoice(int $paymentId): ?array
{
    $stmt = getDB()->prepare(
        'SELECT p.*, b.bill_number, b.amount as bill_amount, b.paid_amount as bill_paid,
                b.billing_period_start, b.billing_period_end, b.due_date, b.status as bill_status,
                c.full_name, c.account_number, c.phone, c.address, c.barangay, c.city, c.province,
                c.connection_medium, c.email,
                pl.name as plan_name,
                u.full_name as collector_name
         FROM payments p
         JOIN bills b ON p.bill_id = b.id
         JOIN customers c ON p.customer_id = c.id
         JOIN service_plans pl ON c.plan_id = pl.id
         JOIN users u ON p.collected_by = u.id
         WHERE p.id = ?'
    );
    $stmt->execute([$paymentId]);
    return $stmt->fetch() ?: null;
}

function getBatchInvoice(int $batchId): ?array
{
    $stmt = getDB()->prepare(
        'SELECT pb.*,
                c.full_name, c.account_number, c.phone, c.address, c.barangay, c.city, c.province,
                c.connection_medium, c.email,
                pl.name AS plan_name,
                u.full_name AS collector_name
         FROM payment_batches pb
         JOIN customers c ON pb.customer_id = c.id
         JOIN service_plans pl ON c.plan_id = pl.id
         JOIN users u ON pb.collected_by = u.id
         WHERE pb.id = ?'
    );
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        return null;
    }

    $linesStmt = getDB()->prepare(
        'SELECT p.id, p.amount, b.bill_number, b.amount AS bill_amount, b.paid_amount AS bill_paid,
                b.billing_period_start, b.billing_period_end, b.due_date, b.status AS bill_status
         FROM payments p
         JOIN bills b ON p.bill_id = b.id
         WHERE p.batch_id = ?
         ORDER BY b.billing_period_start ASC, b.due_date ASC'
    );
    $linesStmt->execute([$batchId]);
    $batch['lines'] = $linesStmt->fetchAll();

    return $batch;
}

function getDashboardStats(): array
{
    $db = getDB();

    $stats = [
        'total_customers'  => (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'active_customers' => (int) $db->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(),
        'suspended_customers' => (int) $db->query("SELECT COUNT(*) FROM customers WHERE status = 'suspended'")->fetchColumn(),
        'disconnected_customers' => (int) $db->query("SELECT COUNT(*) FROM customers WHERE status = 'disconnected'")->fetchColumn(),
        'total_bills'      => (int) $db->query('SELECT COUNT(*) FROM bills')->fetchColumn(),
        'paid_bills'       => (int) $db->query("SELECT COUNT(*) FROM bills WHERE status = 'paid'")->fetchColumn(),
        'pending_bills'    => (int) $db->query("SELECT COUNT(*) FROM bills WHERE status IN ('pending','partial','overdue')")->fetchColumn(),
        'overdue_bills'    => (int) $db->query("SELECT COUNT(*) FROM bills WHERE status = 'overdue'")->fetchColumn(),
        'total_payments'   => (int) $db->query('SELECT COUNT(*) FROM payments')->fetchColumn(),
        'total_plans'      => (int) $db->query('SELECT COUNT(*) FROM service_plans WHERE is_active = 1')->fetchColumn(),
        'total_users'      => (int) $db->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
        'monthly_revenue'  => (float) $db->query(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())"
        )->fetchColumn(),
        'total_collected'  => (float) $db->query('SELECT COALESCE(SUM(amount), 0) FROM payments')->fetchColumn(),
        'total_billed'     => (float) $db->query('SELECT COALESCE(SUM(amount), 0) FROM bills')->fetchColumn(),
        'outstanding'      => (float) $db->query(
            "SELECT COALESCE(SUM(amount - paid_amount), 0) FROM bills WHERE status IN ('pending','partial','overdue')"
        )->fetchColumn(),
    ];

    $stats['collection_rate'] = $stats['total_billed'] > 0
        ? round(($stats['total_collected'] / $stats['total_billed']) * 100, 1)
        : 0;
    $stats['active_rate'] = $stats['total_customers'] > 0
        ? round(($stats['active_customers'] / $stats['total_customers']) * 100, 1)
        : 0;

    return $stats;
}

function getDashboardAnalytics(): array
{
    $db = getDB();

    $customerStatus = $db->query(
        "SELECT status, COUNT(*) as count FROM customers GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $billStatus = $db->query(
        "SELECT status, COUNT(*) as count FROM bills GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $planSubscribers = $db->query(
        'SELECT p.name, COUNT(c.id) as count
         FROM service_plans p
         LEFT JOIN customers c ON p.id = c.plan_id AND c.status = "active"
         WHERE p.is_active = 1
         GROUP BY p.id, p.name
         ORDER BY count DESC'
    )->fetchAll();

    $monthlyCollections = $db->query(
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month,
                DATE_FORMAT(payment_date, '%b %Y') as label,
                COALESCE(SUM(amount), 0) as total,
                COUNT(*) as count
         FROM payments
         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY month, label
         ORDER BY month"
    )->fetchAll();

    $monthlyCustomers = $db->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                DATE_FORMAT(created_at, '%b %Y') as label,
                COUNT(*) as count
         FROM customers
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY month, label
         ORDER BY month"
    )->fetchAll();

    $paymentMethods = $db->query(
        "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
         FROM payments GROUP BY payment_method ORDER BY total DESC"
    )->fetchAll();

    $installationsByMonth = $db->query(
        "SELECT DATE_FORMAT(installation_date, '%Y-%m') as month,
                DATE_FORMAT(installation_date, '%b %Y') as label,
                COUNT(*) as count
         FROM customers
         WHERE installation_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY month, label
         ORDER BY month"
    )->fetchAll();

    return [
        'customer_status'       => $customerStatus,
        'bill_status'           => $billStatus,
        'plan_subscribers'      => $planSubscribers,
        'monthly_collections'   => $monthlyCollections,
        'monthly_customers'     => $monthlyCustomers,
        'payment_methods'       => $paymentMethods,
        'installations_by_month'=> $installationsByMonth,
    ];
}

function fillMonthlySeries(array $rows, string $valueKey = 'total'): array
{
    $series = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $label = date('M Y', strtotime("-{$i} months"));
        $series[$month] = ['label' => $label, 'value' => 0];
    }

    foreach ($rows as $row) {
        if (isset($series[$row['month']])) {
            $series[$row['month']]['value'] = (float) ($row[$valueKey] ?? $row['count'] ?? 0);
        }
    }

    return array_values($series);
}

function statusBadge(string $status): string
{
    $classes = [
        'active'      => 'badge-success',
        'suspended'   => 'badge-warning',
        'disconnected'=> 'badge-danger',
        'pending'     => 'badge-info',
        'paid'        => 'badge-success',
        'overdue'     => 'badge-danger',
        'partial'     => 'badge-warning',
    ];

    $class = $classes[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function getBillingFiltersFromRequest(): array
{
    return [
        'status'      => $_GET['status'] ?? '',
        'search'      => trim($_GET['search'] ?? ''),
        'due_from'    => $_GET['due_from'] ?? '',
        'due_to'      => $_GET['due_to'] ?? '',
        'customer_id' => (int) ($_GET['customer_id'] ?? 0),
    ];
}

function buildBillingBillFilterSql(array $filters, string $billAlias = 'b', string $customerAlias = 'c'): array
{
    $sql = '';
    $params = [];

    if ($filters['status']) {
        $sql .= " AND {$billAlias}.status = ?";
        $params[] = $filters['status'];
    }

    if ($filters['search']) {
        $sql .= " AND ({$customerAlias}.full_name LIKE ? OR {$customerAlias}.account_number LIKE ? OR {$billAlias}.bill_number LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }

    if ($filters['due_from']) {
        $sql .= " AND {$billAlias}.due_date >= ?";
        $params[] = $filters['due_from'];
    }

    if ($filters['due_to']) {
        $sql .= " AND {$billAlias}.due_date <= ?";
        $params[] = $filters['due_to'];
    }

    if ($filters['customer_id']) {
        $sql .= " AND {$customerAlias}.id = ?";
        $params[] = $filters['customer_id'];
    }

    return ['sql' => $sql, 'params' => $params];
}

function getBillingCustomersGrouped(array $filters, int $page, int $perPage): array
{
    $db = getDB();
    $page = max(1, $page);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $filter = buildBillingBillFilterSql($filters);
    $fromWhere = 'FROM customers c JOIN bills b ON b.customer_id = c.id WHERE 1=1' . $filter['sql'];
    $params = $filter['params'];

    $countStmt = $db->prepare("SELECT COUNT(DISTINCT c.id) {$fromWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $summaryStmt = $db->prepare(
        "SELECT
            COUNT(DISTINCT c.id) AS customer_count,
            COUNT(b.id) AS bill_count,
            COALESCE(SUM(b.amount), 0) AS total_amount,
            COALESCE(SUM(b.paid_amount), 0) AS total_paid,
            COALESCE(SUM(b.amount - b.paid_amount), 0) AS total_balance
         {$fromWhere}"
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch() ?: [
        'customer_count' => 0,
        'bill_count'     => 0,
        'total_amount'   => 0,
        'total_paid'     => 0,
        'total_balance'  => 0,
    ];

    $customerStmt = $db->prepare(
        "SELECT
            c.id,
            c.full_name,
            c.account_number,
            c.status AS customer_status,
            COUNT(b.id) AS bill_count,
            COALESCE(SUM(b.amount), 0) AS total_amount,
            COALESCE(SUM(b.paid_amount), 0) AS total_paid,
            COALESCE(SUM(b.amount - b.paid_amount), 0) AS total_balance,
            SUM(CASE WHEN b.status IN ('pending', 'partial', 'overdue') THEN 1 ELSE 0 END) AS unpaid_count
         {$fromWhere}
         GROUP BY c.id, c.full_name, c.account_number, c.status
         ORDER BY c.full_name ASC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $customerStmt->execute($params);
    $customers = $customerStmt->fetchAll();

    $groups = [];
    if ($customers) {
        $customerIds = array_column($customers, 'id');
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $billParams = array_merge($customerIds, $params);

        $billStmt = $db->prepare(
            "SELECT b.*, c.full_name, c.account_number
             FROM bills b
             JOIN customers c ON b.customer_id = c.id
             WHERE c.id IN ({$placeholders}){$filter['sql']}
             ORDER BY c.full_name ASC, b.billing_period_start ASC, b.due_date ASC"
        );
        $billStmt->execute($billParams);

        $billsByCustomer = [];
        foreach ($billStmt->fetchAll() as $bill) {
            $billsByCustomer[(int) $bill['customer_id']][] = $bill;
        }

        foreach ($customers as $customer) {
            $customerId = (int) $customer['id'];
            $groups[] = [
                'customer' => $customer,
                'bills'    => $billsByCustomer[$customerId] ?? [],
            ];
        }
    }

    return [
        'groups'      => $groups,
        'summary'     => $summary,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'from'        => $total ? $offset + 1 : 0,
        'to'          => min($offset + $perPage, $total),
    ];
}

function getPaymentFiltersFromRequest(): array
{
    return [
        'from'           => $_GET['from'] ?? date('Y-m-01'),
        'to'             => $_GET['to'] ?? date('Y-m-d'),
        'search'         => trim($_GET['search'] ?? ''),
        'payment_method' => $_GET['payment_method'] ?? '',
        'customer_id'    => (int) ($_GET['customer_id'] ?? 0),
    ];
}

function buildPaymentFilterSql(
    array $filters,
    string $paymentAlias = 'p',
    string $customerAlias = 'c',
    string $billAlias = 'b'
): array {
    $sql = " AND {$paymentAlias}.payment_date BETWEEN ? AND ?";
    $params = [$filters['from'], $filters['to']];

    if ($filters['search']) {
        $sql .= " AND ({$customerAlias}.full_name LIKE ? OR {$customerAlias}.account_number LIKE ? OR {$billAlias}.bill_number LIKE ? OR {$paymentAlias}.reference_number LIKE ? OR {$paymentAlias}.invoice_number LIKE ? OR EXISTS (SELECT 1 FROM payment_batches pb2 WHERE pb2.id = {$paymentAlias}.batch_id AND pb2.batch_invoice_number LIKE ?))";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }

    if ($filters['payment_method']) {
        $sql .= " AND {$paymentAlias}.payment_method = ?";
        $params[] = $filters['payment_method'];
    }

    if ($filters['customer_id']) {
        $sql .= " AND {$customerAlias}.id = ?";
        $params[] = $filters['customer_id'];
    }

    return ['sql' => $sql, 'params' => $params];
}

function getPaymentCustomersGrouped(array $filters, int $page, int $perPage): array
{
    $db = getDB();
    $page = max(1, $page);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $filter = buildPaymentFilterSql($filters);
    $fromWhere = 'FROM customers c
                  JOIN payments p ON p.customer_id = c.id
                  LEFT JOIN bills b ON p.bill_id = b.id
                  WHERE p.payment_type IN ("bill", "advance", "advance_applied")' . $filter['sql'];
    $params = $filter['params'];

    $countStmt = $db->prepare("SELECT COUNT(DISTINCT c.id) {$fromWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $summaryStmt = $db->prepare(
        "SELECT
            COUNT(DISTINCT c.id) AS customer_count,
            COUNT(p.id) AS payment_count,
            COALESCE(SUM(p.amount), 0) AS total_collected
         {$fromWhere}"
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch() ?: [
        'customer_count'  => 0,
        'payment_count'   => 0,
        'total_collected' => 0,
    ];

    $customerStmt = $db->prepare(
        "SELECT
            c.id,
            c.full_name,
            c.account_number,
            c.status AS customer_status,
            COUNT(p.id) AS payment_count,
            COALESCE(SUM(p.amount), 0) AS total_collected
         {$fromWhere}
         GROUP BY c.id, c.full_name, c.account_number, c.status
         ORDER BY c.full_name ASC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $customerStmt->execute($params);
    $customers = $customerStmt->fetchAll();

    $groups = [];
    if ($customers) {
        $customerIds = array_column($customers, 'id');
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $paymentParams = array_merge($customerIds, $params);

        $paymentStmt = $db->prepare(
            "SELECT p.*, c.full_name, c.account_number,
                    COALESCE(b.bill_number, 'Advance Payment') AS bill_number,
                    u.full_name AS collector_name,
                    pb.id AS batch_invoice_id, pb.batch_invoice_number
             FROM payments p
             JOIN customers c ON p.customer_id = c.id
             LEFT JOIN bills b ON p.bill_id = b.id
             JOIN users u ON p.collected_by = u.id
             LEFT JOIN payment_batches pb ON p.batch_id = pb.id
             WHERE c.id IN ({$placeholders}){$filter['sql']}
             ORDER BY c.full_name ASC, p.payment_date DESC, p.created_at DESC"
        );
        $paymentStmt->execute($paymentParams);

        $paymentsByCustomer = [];
        foreach ($paymentStmt->fetchAll() as $payment) {
            $paymentsByCustomer[(int) $payment['customer_id']][] = $payment;
        }

        foreach ($customers as $customer) {
            $customerId = (int) $customer['id'];
            $groups[] = [
                'customer' => $customer,
                'payments' => $paymentsByCustomer[$customerId] ?? [],
            ];
        }
    }

    return [
        'groups'      => $groups,
        'summary'     => $summary,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'from'        => $total ? $offset + 1 : 0,
        'to'          => min($offset + $perPage, $total),
    ];
}
