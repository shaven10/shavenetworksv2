<?php

function generateTicketNumber(): string
{
    return 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generateInquiryNumber(): string
{
    return 'INQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function getLinkedCustomer(): ?array
{
    $user = currentUser();
    if (!$user || empty($user['customer_id'])) {
        return null;
    }

    static $customer = null;
    if ($customer === null) {
        $stmt = getDB()->prepare(
            'SELECT c.*, p.name as plan_name, p.speed_mbps, p.monthly_fee
             FROM customers c JOIN service_plans p ON c.plan_id = p.id
             WHERE c.id = ?'
        );
        $stmt->execute([$user['customer_id']]);
        $customer = $stmt->fetch() ?: null;
    }

    return $customer;
}

function requireLinkedCustomer(): array
{
    requireRole('customer');
    $customer = getLinkedCustomer();
    if (!$customer) {
        http_response_code(403);
        die('Your account is not linked to a subscriber record. Contact support.');
    }
    return $customer;
}

function ticketCategoryLabel(string $category): string
{
    return match ($category) {
        'no_internet'      => 'No Internet',
        'slow_connection'  => 'Slow Connection',
        'router_issue'     => 'Router / Equipment',
        'billing_related'  => 'Billing Related',
        default            => 'Other',
    };
}

function inquiryCategoryLabel(string $category): string
{
    return match ($category) {
        'account'  => 'Account',
        'billing'  => 'Billing',
        'service'  => 'Service',
        default    => 'General',
    };
}

function ticketStatusBadge(string $status): string
{
    $classes = [
        'open'        => 'badge-info',
        'in_progress' => 'badge-warning',
        'resolved'    => 'badge-success',
        'closed'      => 'badge-secondary',
    ];
    $class = $classes[$status] ?? 'badge-secondary';
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function inquiryStatusBadge(string $status): string
{
    return match ($status) {
        'open'     => '<span class="badge badge-info">Open</span>',
        'answered' => '<span class="badge badge-success">Answered</span>',
        'closed'   => '<span class="badge badge-secondary">Closed</span>',
        default    => '<span class="badge badge-secondary">' . e(ucfirst($status)) . '</span>',
    };
}

function priorityBadge(string $priority): string
{
    $classes = ['low' => 'badge-secondary', 'medium' => 'badge-warning', 'high' => 'badge-danger'];
    $class = $classes[$priority] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . e(ucfirst($priority)) . '</span>';
}

function homePath(): string
{
    return hasRole('customer') ? '/portal/index.php' : '/index.php';
}

function redirectHome(): void
{
    redirect(homePath());
}

function getHeaderNotifications(bool $filterRead = true): array
{
    $user = currentUser();
    if (!$user) {
        return ['total' => 0, 'groups' => []];
    }

    $db = getDB();
    $groups = [];

    if (hasRole('customer')) {
        $customer = getLinkedCustomer();
        if ($customer) {
            $ticketItems = [];
            $stmt = $db->prepare(
                "SELECT id, ticket_number, subject, status, created_at FROM repair_tickets
                 WHERE customer_id = ? AND status IN ('open','in_progress')
                 ORDER BY created_at DESC LIMIT 5"
            );
            $stmt->execute([$customer['id']]);
            foreach ($stmt->fetchAll() as $row) {
                $ticketItems[] = [
                    'key'      => 'ticket:' . $row['id'],
                    'title'    => $row['ticket_number'],
                    'message'  => $row['subject'],
                    'meta'     => ucfirst(str_replace('_', ' ', $row['status'])),
                    'time'     => $row['created_at'],
                    'url'      => APP_URL . '/portal/tickets/view.php?id=' . $row['id'],
                    'severity' => $row['status'] === 'open' ? 'info' : 'warning',
                ];
            }
            if ($ticketItems) {
                $groups[] = [
                    'type'  => 'tickets',
                    'label' => 'My Open Tickets',
                    'url'   => APP_URL . '/portal/tickets/index.php',
                    'items' => $ticketItems,
                ];
            }

            $billItems = [];
            $stmt = $db->prepare(
                "SELECT id, bill_number, amount, paid_amount, due_date, status FROM bills
                 WHERE customer_id = ? AND status IN ('pending','partial','overdue')
                 ORDER BY due_date ASC LIMIT 5"
            );
            $stmt->execute([$customer['id']]);
            foreach ($stmt->fetchAll() as $row) {
                $balance = $row['amount'] - $row['paid_amount'];
                $billItems[] = [
                    'key'      => 'bill:' . $row['id'],
                    'title'    => $row['bill_number'],
                    'message'  => 'Balance due: ' . formatMoney($balance),
                    'meta'     => 'Due ' . formatDate($row['due_date']),
                    'time'     => $row['due_date'],
                    'url'      => APP_URL . '/portal/account.php',
                    'severity' => $row['status'] === 'overdue' ? 'danger' : 'warning',
                ];
            }
            if ($billItems) {
                $groups[] = [
                    'type'  => 'billing',
                    'label' => 'Unpaid Bills',
                    'url'   => APP_URL . '/portal/account.php',
                    'items' => $billItems,
                ];
            }
        }
    } else {
        if (canAccess('tickets')) {
            $ticketItems = [];
            $rows = $db->query(
                "SELECT t.id, t.ticket_number, t.subject, t.status, t.priority, t.created_at, c.full_name
                 FROM repair_tickets t
                 JOIN customers c ON t.customer_id = c.id
                 WHERE t.status IN ('open','in_progress')
                 ORDER BY FIELD(t.priority,'high','medium','low'), t.created_at DESC
                 LIMIT 6"
            )->fetchAll();
            foreach ($rows as $row) {
                $ticketItems[] = [
                    'key'      => 'ticket:' . $row['id'],
                    'title'    => $row['ticket_number'],
                    'message'  => $row['subject'],
                    'meta'     => $row['full_name'] . ' · ' . ucfirst(str_replace('_', ' ', $row['status'])),
                    'time'     => $row['created_at'],
                    'url'      => APP_URL . '/support/tickets/view.php?id=' . $row['id'],
                    'severity' => $row['priority'] === 'high' ? 'danger' : ($row['status'] === 'open' ? 'info' : 'warning'),
                ];
            }
            if ($ticketItems) {
                $groups[] = [
                    'type'  => 'tickets',
                    'label' => 'Incoming Repair Tickets',
                    'url'   => APP_URL . '/support/tickets/index.php?status=open',
                    'items' => $ticketItems,
                ];
            }
        }

        if (canAccess('billing')) {
            updateOverdueBills();
            $billItems = [];

            $overdue = $db->query(
                "SELECT b.id, b.bill_number, b.amount, b.paid_amount, b.due_date,
                        c.full_name, c.account_number
                 FROM bills b JOIN customers c ON b.customer_id = c.id
                 WHERE b.status = 'overdue'
                 ORDER BY b.due_date ASC LIMIT 5"
            )->fetchAll();
            foreach ($overdue as $row) {
                $balance = $row['amount'] - $row['paid_amount'];
                $billItems[] = [
                    'key'      => 'bill:' . $row['id'],
                    'title'    => $row['bill_number'],
                    'message'  => $row['full_name'] . ' — ' . formatMoney($balance) . ' overdue',
                    'meta'     => 'Due ' . formatDate($row['due_date']),
                    'time'     => $row['due_date'],
                    'url'      => APP_URL . '/billing/index.php?status=overdue',
                    'severity' => 'danger',
                ];
            }

            $pending = $db->query(
                "SELECT b.id, b.bill_number, b.amount, b.paid_amount, b.due_date,
                        c.full_name
                 FROM bills b JOIN customers c ON b.customer_id = c.id
                 WHERE b.status IN ('pending','partial') AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                 ORDER BY b.due_date ASC LIMIT 5"
            )->fetchAll();
            foreach ($pending as $row) {
                $balance = $row['amount'] - $row['paid_amount'];
                $billItems[] = [
                    'key'      => 'bill:' . $row['id'],
                    'title'    => $row['bill_number'],
                    'message'  => $row['full_name'] . ' — ' . formatMoney($balance) . ' due soon',
                    'meta'     => 'Due ' . formatDate($row['due_date']),
                    'time'     => $row['due_date'],
                    'url'      => APP_URL . '/billing/index.php?status=pending',
                    'severity' => 'warning',
                ];
            }

            if ($billItems) {
                $groups[] = [
                    'type'  => 'billing',
                    'label' => 'Billing Alerts',
                    'url'   => APP_URL . '/billing/index.php',
                    'items' => array_slice($billItems, 0, 6),
                ];
            }
        }

        if (canAccess('remittances')) {
            ensureRemittanceTables();
            $remittanceItems = [];

            if (hasRole('owner')) {
                $rows = $db->query(
                    "SELECT r.id, r.remittance_number, r.total_amount, r.payment_count, r.submitted_at, u.full_name
                     FROM remittances r
                     JOIN users u ON r.submitted_by = u.id
                     WHERE r.status = 'pending'
                     ORDER BY r.submitted_at ASC
                     LIMIT 6"
                )->fetchAll();

                foreach ($rows as $row) {
                    $remittanceItems[] = [
                        'key'      => 'remittance:' . $row['id'],
                        'title'    => $row['remittance_number'],
                        'message'  => $row['full_name'] . ' submitted ' . formatMoney((float) $row['total_amount']),
                        'meta'     => $row['payment_count'] . ' payment(s)',
                        'time'     => $row['submitted_at'],
                        'url'      => APP_URL . '/remittances/view.php?id=' . $row['id'],
                        'severity' => 'warning',
                    ];
                }

                if ($remittanceItems) {
                    $groups[] = [
                        'type'  => 'remittances',
                        'label' => 'Pending Remittances',
                        'url'   => APP_URL . '/remittances/index.php?status=pending',
                        'items' => $remittanceItems,
                    ];
                }
            }

            if (hasRole('collector')) {
                $stmt = $db->prepare(
                    "SELECT r.id, r.remittance_number, r.total_amount, r.reviewed_at, r.owner_notes
                     FROM remittances r
                     WHERE r.submitted_by = ? AND r.status = 'rejected'
                     ORDER BY r.reviewed_at DESC
                     LIMIT 5"
                );
                $stmt->execute([$user['id']]);
                $rejectedItems = [];

                foreach ($stmt->fetchAll() as $row) {
                    $rejectedItems[] = [
                        'key'      => 'remittance_rejected:' . $row['id'],
                        'title'    => $row['remittance_number'] . ' rejected',
                        'message'  => $row['owner_notes'] ?: 'Resubmit after reviewing owner notes.',
                        'meta'     => formatMoney((float) $row['total_amount']),
                        'time'     => $row['reviewed_at'] ?? date('Y-m-d H:i:s'),
                        'url'      => APP_URL . '/remittances/view.php?id=' . $row['id'],
                        'severity' => 'danger',
                    ];
                }

                if ($rejectedItems) {
                    $groups[] = [
                        'type'  => 'remittances',
                        'label' => 'Rejected Remittances',
                        'url'   => APP_URL . '/remittances/index.php?status=rejected',
                        'items' => $rejectedItems,
                    ];
                }
            }
        }
    }

    if ($filterRead) {
        $groups = filterUnreadNotifications($groups, getReadNotificationKeys());
    }

    $total = array_sum(array_map(fn($g) => count($g['items']), $groups));

    return ['total' => $total, 'groups' => $groups];
}

function notificationTimeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return formatDate($datetime);
}
