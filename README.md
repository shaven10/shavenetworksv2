# SHAVEN Networks ISP Billing System

A PHP web application for Internet Service Provider billing with installation-date-based billing cycles and Role-Based Access Control (RBAC).

## Features

- **Installation-Date Billing** — Each customer's billing cycle is anchored to their installation date (e.g., installed on the 15th → billed every 15th)
- **RBAC with 3 Roles:**
  - **Owner** — Full access: users, plans, customers, billing, payments, reports
  - **Technical** — Customer management, installations, service status
  - **Collector** — View customers/bills, record payments
- Customer & service plan management
- Automated monthly bill generation
- Payment collection (Cash, GCash, Bank Transfer, Check)
- Revenue & collection reports

## Requirements

- XAMPP (Apache + MySQL/MariaDB + PHP 8.0+)
- PDO MySQL extension enabled

## Installation

1. Place this folder in `C:\xampp\htdocs\shaven_networks_v2`
2. Start **Apache** and **MySQL** in XAMPP Control Panel
3. Open: `http://localhost/shaven_networks_v2/install.php`
4. Click **Install Database**
5. Login at: `http://localhost/shaven_networks_v2/login.php`

## Demo Accounts

| Username   | Role       | Password     |
|-----------|------------|--------------|
| owner     | Owner      | password123  |
| tech1     | Technical  | password123  |
| collector1| Collector  | password123  |

## Billing Logic

Billing periods are calculated from each customer's **installation date**:

- Period start: installation day-of-month in the current/relevant month
- Period end: day before the same day next month
- Due date: 7 days after period end
- Only **active** customers receive bills
- Duplicate bills for the same period are skipped

## Project Structure

```
config/          Database & app configuration
database/        SQL schema and seed data
includes/        Auth, billing engine, layout templates
customers/       Customer CRUD
plans/           Service plan management
billing/         Bill listing & generation
payments/        Payment recording & history
users/           User management (owner only)
reports/         Revenue analytics (owner only)
assets/          CSS & JavaScript
```

## Configuration

Edit `config/database.php` if your MySQL credentials differ from XAMPP defaults (root, no password).

Edit `config/app.php` to change the base URL if deployed under a different path.
