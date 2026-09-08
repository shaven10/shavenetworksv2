CREATE DATABASE IF NOT EXISTS shaven_isp_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shaven_isp_billing;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    avatar VARCHAR(255) NULL,
    role ENUM('owner', 'technical', 'collector', 'customer') NOT NULL,
    customer_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    approval_status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS service_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    speed_mbps INT NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    connection_medium ENUM('fiber_olt', 'fiber_mediacon', 'wireless_radio') NOT NULL DEFAULT 'fiber_olt',
    address TEXT NOT NULL,
    barangay VARCHAR(100),
    city VARCHAR(100),
    province VARCHAR(100),
    plan_id INT NOT NULL,
    installation_date DATE NOT NULL,
    status ENUM('active', 'suspended', 'disconnected') DEFAULT 'active',
    advance_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_generate_from_year SMALLINT UNSIGNED NULL,
    billing_generate_to_year SMALLINT UNSIGNED NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES service_plans(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS customer_plan_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    plan_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    speed_mbps INT NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    changed_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES service_plans(id),
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_customer_plan_started (customer_id, started_at)
);

CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    bill_number VARCHAR(30) NOT NULL UNIQUE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'partial') DEFAULT 'pending',
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_status (customer_id, status),
    INDEX idx_due_date (due_date)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NULL UNIQUE,
    batch_id INT NULL,
    payment_type ENUM('bill', 'advance', 'advance_applied') NOT NULL DEFAULT 'bill',
    bill_id INT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check', 'advance_credit') DEFAULT 'cash',
    reference_number VARCHAR(50),
    notes TEXT,
    collected_by INT NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS payment_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_invoice_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_count INT NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check') DEFAULT 'cash',
    reference_number VARCHAR(50) NULL,
    notes TEXT NULL,
    collected_by INT NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

ALTER TABLE payments
    ADD CONSTRAINT fk_payments_batch FOREIGN KEY (batch_id) REFERENCES payment_batches(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS remittances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remittance_number VARCHAR(30) NOT NULL UNIQUE,
    submitted_by INT NOT NULL,
    confirmed_by INT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_count INT NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    owner_notes TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (confirmed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS remittance_payments (
    remittance_id INT NOT NULL,
    payment_id INT NOT NULL,
    PRIMARY KEY (remittance_id, payment_id),
    UNIQUE KEY uniq_payment_remittance (payment_id),
    FOREIGN KEY (remittance_id) REFERENCES remittances(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);

ALTER TABLE users
    ADD CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS repair_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('no_internet', 'slow_connection', 'router_issue', 'billing_related', 'other') DEFAULT 'other',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT NULL,
    resolution_notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    category ENUM('account', 'billing', 'service', 'general') DEFAULT 'general',
    status ENUM('open', 'answered', 'closed') DEFAULT 'open',
    response TEXT,
    responded_by INT NULL,
    responded_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_key VARCHAR(80) NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_notification (user_id, notification_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NULL,
    customer_id INT NULL,
    phone VARCHAR(20) NOT NULL,
    notification_type ENUM('due','overdue','test','manual') NOT NULL DEFAULT 'due',
    message TEXT NOT NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    provider_message_id VARCHAR(50) NULL,
    provider_response TEXT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_bill_type (bill_id, notification_type),
    INDEX idx_sms_customer (customer_id),
    INDEX idx_sms_status (status),
    INDEX idx_sms_created (created_at),
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employee_ledgers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    user_id INT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 0,
    in_label VARCHAR(100) NOT NULL DEFAULT 'Hatagon Beben',
    out_label VARCHAR(100) NOT NULL DEFAULT 'Utang',
    balance_as_of DATE NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ledger_user (user_id),
    INDEX idx_ledger_visible (is_visible),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employee_ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ledger_id INT NOT NULL,
    entry_date DATE NULL,
    date_display VARCHAR(100) NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    amount_in DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_out DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    row_type ENUM('entry','year_header','end_marker') NOT NULL DEFAULT 'entry',
    highlight ENUM('none','opening','year') NOT NULL DEFAULT 'none',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ledger_entries_order (ledger_id, sort_order),
    FOREIGN KEY (ledger_id) REFERENCES employee_ledgers(id) ON DELETE CASCADE
);
