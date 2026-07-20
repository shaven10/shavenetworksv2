USE shaven_isp_billing;

-- Default password for all users: password123
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Owner', 'owner@shaven.net', 'owner'),
('tech1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Technician', 'tech@shaven.net', 'technical'),
('collector1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Collector', 'collector@shaven.net', 'collector');

INSERT INTO service_plans (name, speed_mbps, monthly_fee, description) VALUES
('Basic 10Mbps', 10, 599.00, 'Entry-level home internet'),
('Standard 25Mbps', 25, 899.00, 'Standard family plan'),
('Premium 50Mbps', 50, 1299.00, 'High-speed streaming and gaming'),
('Business 100Mbps', 100, 2499.00, 'Business-grade connectivity');

INSERT INTO customers (account_number, full_name, email, phone, connection_medium, address, barangay, city, province, plan_id, installation_date, status, created_by) VALUES
('SN-2024-0001', 'Pedro Santos', 'pedro@email.com', '09171234567', 'fiber_olt', '123 Rizal St', 'Poblacion', 'Manila', 'Metro Manila', 1, '2024-01-15', 'active', 2),
('SN-2024-0002', 'Ana Reyes', 'ana@email.com', '09181234567', 'wireless_radio', '456 Mabini Ave', 'Central', 'Quezon City', 'Metro Manila', 2, '2024-02-20', 'active', 2),
('SN-2024-0003', 'Carlos Mendoza', 'carlos@email.com', '09191234567', 'fiber_mediacon', '789 Bonifacio Rd', 'San Lorenzo', 'Makati', 'Metro Manila', 3, '2024-03-10', 'active', 2),
('SN-2024-0004', 'Liza Cruz', 'liza@email.com', '09201234567', 'fiber_olt', '321 Aguinaldo St', 'Kapitolyo', 'Pasig', 'Metro Manila', 1, '2024-06-05', 'suspended', 2);
