# Inventory Ledger Management System

A complete PHP 8+ / MySQL inventory management system with ledger sheets, reporting, and activity tracking. Runs locally on XAMPP.

## Features

- **Authentication**: Single-user login with 30-minute session timeout
- **Dashboard**: Real-time stats cards, recent activity, quick actions
- **Product Management**: CRUD products with unlimited varieties per product
- **Ledger Sheets**: Excel-like transaction records per variety with sticky headers
- **Auto-calculations**: Running balance, current stock, stock in/out totals computed automatically
- **Filtering**: Date range, customer, invoice, delivery note, transaction type
- **Reports**: Daily, weekly, monthly, yearly, product, variety, customer, stock movement, current stock, low stock
- **Export**: CSV export for reports
- **Activity Log**: Automatic tracking of all actions
- **Settings**: Company info, currency, date format, rows per page, dark mode, database backup
- **Dark Mode**: Toggle across entire application with localStorage persistence
- **Responsive**: Works on desktop, tablet, and mobile

## Requirements

- XAMPP (Apache + MySQL)
- PHP 8.0 or higher
- MySQL 8.0 or higher

## Installation

1. Copy the `inventory_system` folder to `C:\xampp\htdocs\`
2. Start Apache and MySQL from the XAMPP Control Panel
3. Open phpMyAdmin: `http://localhost/phpmyadmin`
4. Create a database named `inventory_system` (or import the SQL file)
5. Import `database/inventory_system.sql` into the database
6. Open the application: `http://localhost/inventory_system`

## Default Credentials

- **Username**: `Amir`
- **Password**: `14620267`

## File Structure

```
inventory_system/
├── api/
│   └── search.php              # Global search API
├── backups/                    # Database backup files
├── config/
│   ├── app.php                 # App constants and session config
│   └── database.php            # Database connection class
├── css/
│   └── style.css               # Complete application styles
├── database/
│   └── inventory_system.sql    # Full database schema + sample data
├── images/                     # Logo and favicon uploads
├── includes/
│   ├── footer.php              # Shared footer
│   ├── functions.php           # Helper functions
│   ├── header.php              # Shared header + top nav
│   └── sidebar.php             # Sidebar navigation
├── js/
│   └── main.js                 # Global JavaScript
├── uploads/                    # Company logo uploads
├── dashboard.php               # Main dashboard
├── forgot_password.php         # Password reset page
├── login.php                   # Login page
├── logout.php                  # Session destroy
├── pages/                      # Shared page templates
├── products/
│   └── index.php               # Product & variety management
├── reports/
│   └── index.php               # Reporting & CSV export
├── ledger/
│   └── index.php               # Ledger transaction sheets
├── settings/
│   └── index.php               # Settings & database backup
└── activity_logs/
    └── index.php               # System activity log
```

## Usage

### Products
1. Go to **Products** from the sidebar
2. Click **Add Product** to create a new product
3. Add varieties (e.g., Paint → White, Black, Blue)
4. Each variety automatically gets its own ledger sheet

### Ledgers
1. Click any variety chip or use the **Ledgers** submenu
2. Add transactions with Date, Customer, Invoice, Stock In/Out
3. Balance is calculated automatically
4. Use filters to narrow down by date, customer, type, etc.

### Reports
1. Go to **Reports** from the sidebar
2. Select a report type
3. Use filters (date range, product) and click **Export CSV**

### Settings
1. Go to **Settings**
2. Update company name, currency, date format
3. Toggle dark mode
4. Download database backup

## Database Schema

- `users` - Single admin user
- `password_history` - Last 10 passwords
- `products` - Product master data
- `product_varieties` - Varieties with auto stock
- `ledger_transactions` - All IN/OUT transactions
- `activity_logs` - System actions
- `settings` - Application settings
- `backups` - Backup record history

Triggers automatically update `product_varieties.current_stock` on every transaction insert/update/delete.

## Password Reset

1. Go to **Forgot Password** from the login page
2. Enter username and any previous password (current or from history)
3. Set a new password
4. Login with the new password

## Security

- Prepared statements everywhere (no SQL injection)
- Output escaping for all user data
- Password hashing with `password_hash()` and `password_verify()`
- Session timeout after 30 minutes of inactivity
- Password history prevents reuse of last 10 passwords

## License

MIT License
