-- =====================================================
-- INVENTORY LEDGER MANAGEMENT SYSTEM
-- Database: inventory_system
-- MySQL 8+ Compatible
-- =====================================================

CREATE DATABASE IF NOT EXISTS inventory_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE inventory_system;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT '',
    role VARCHAR(20) DEFAULT 'admin',
    dark_mode BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- PASSWORD HISTORY TABLE
-- =====================================================
CREATE TABLE password_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

-- =====================================================
-- PRODUCTS TABLE
-- =====================================================
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    category VARCHAR(100) DEFAULT '',
    unit VARCHAR(50) DEFAULT 'pcs',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product_category (category)
) ENGINE=InnoDB;

-- =====================================================
-- PRODUCT VARIETIES TABLE
-- =====================================================
CREATE TABLE product_varieties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    current_stock DECIMAL(15,3) DEFAULT 0.000,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_product_variety (product_id, name),
    INDEX idx_variety_product (product_id)
) ENGINE=InnoDB;

-- =====================================================
-- LEDGER TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE ledger_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    variety_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    customer VARCHAR(255) DEFAULT '',
    invoice_number VARCHAR(100) DEFAULT '',
    delivery_note VARCHAR(100) DEFAULT '',
    stock_in DECIMAL(15,3) DEFAULT 0.000,
    stock_out DECIMAL(15,3) DEFAULT 0.000,
    remarks TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (variety_id) REFERENCES product_varieties(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_trans_variety_date (variety_id, transaction_date),
    INDEX idx_trans_date (transaction_date),
    INDEX idx_trans_customer (customer),
    INDEX idx_trans_invoice (invoice_number)
) ENGINE=InnoDB;

-- =====================================================
-- ACTIVITY LOGS TABLE
-- =====================================================
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT '',
    ip_address VARCHAR(45) DEFAULT '',
    user_agent TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_user (user_id),
    INDEX idx_log_action (action),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- SETTINGS TABLE
-- =====================================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT '',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Inventory Ledger System'),
('company_logo', ''),
('currency', 'USD'),
('currency_symbol', '$'),
('date_format', 'Y-m-d'),
('rows_per_page', '25'),
('dark_mode', '0');

-- =====================================================
-- BACKUPS TABLE
-- =====================================================
CREATE TABLE backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    size_bytes BIGINT DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_backup_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TRIGGER: Update current_stock on new transaction
-- =====================================================
DELIMITER //

CREATE TRIGGER trg_after_transaction_insert
AFTER INSERT ON ledger_transactions
FOR EACH ROW
BEGIN
    DECLARE new_balance DECIMAL(15,3);
    SET new_balance = (
        SELECT COALESCE(SUM(stock_in - stock_out), 0)
        FROM ledger_transactions
        WHERE variety_id = NEW.variety_id
    );
    UPDATE product_varieties
    SET current_stock = new_balance
    WHERE id = NEW.variety_id;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: Update current_stock on transaction update
-- =====================================================
DELIMITER //

CREATE TRIGGER trg_after_transaction_update
AFTER UPDATE ON ledger_transactions
FOR EACH ROW
BEGIN
    DECLARE new_balance DECIMAL(15,3);
    SET new_balance = (
        SELECT COALESCE(SUM(stock_in - stock_out), 0)
        FROM ledger_transactions
        WHERE variety_id = NEW.variety_id
    );
    UPDATE product_varieties
    SET current_stock = new_balance
    WHERE id = NEW.variety_id;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: Update current_stock on transaction delete
-- =====================================================
DELIMITER //

CREATE TRIGGER trg_after_transaction_delete
AFTER DELETE ON ledger_transactions
FOR EACH ROW
BEGIN
    DECLARE new_balance DECIMAL(15,3);
    SET new_balance = (
        SELECT COALESCE(SUM(stock_in - stock_out), 0)
        FROM ledger_transactions
        WHERE variety_id = OLD.variety_id
    );
    UPDATE product_varieties
    SET current_stock = new_balance
    WHERE id = OLD.variety_id;
END //

DELIMITER ;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Default user: Amir / 14620267
INSERT INTO users (username, password_hash, full_name, email, role, dark_mode) VALUES
('Amir', '$2y$10$84pa83Qb88sraYNmMf1C3ep8aE.FErHyKlyrn/..2zxvBOM4lqkNe', 'Amir Administrator', 'amir@example.com', 'admin', FALSE);

-- Add password history (Amir's old passwords)
INSERT INTO password_history (user_id, password_hash) VALUES
(1, '$2y$10$LYIpD2l1lTG.C2p0iEzlMuW9um1S8XCWlwqIGVzNFIidx5U2H.WWa'), -- oldpass1
(1, '$2y$10$wAxJ49lbyKcAh8VbgqwwUe59.DwV1x121nKaBGodEgLOhoGk6JOgS'); -- oldpass2

-- Sample Products
INSERT INTO products (name, description, category, unit) VALUES
('Paint', 'Premium quality paint for walls and surfaces', 'Home Improvement', 'liter'),
('Cement', 'Portland cement for construction', 'Construction', 'bag'),
('Steel Rod', 'Galvanized steel rods for reinforcement', 'Construction', 'piece'),
('Electrical Wire', 'Copper electrical wiring', 'Electrical', 'meter'),
('PVC Pipe', 'Polyvinyl chloride pipes for plumbing', 'Plumbing', 'piece');

-- Sample Varieties
INSERT INTO product_varieties (product_id, name, current_stock) VALUES
(1, 'White', 150.000),
(1, 'Black', 80.000),
(1, 'Blue', 45.000),
(1, 'Green', 30.000),
(1, 'Red', 60.000),
(2, 'OPC 50kg', 200.000),
(2, 'PPC 50kg', 150.000),
(3, '12mm', 500.000),
(3, '16mm', 350.000),
(3, '20mm', 200.000),
(4, '2.5mm', 1000.000),
(4, '4mm', 800.000),
(4, '6mm', 500.000),
(5, '1 inch', 250.000),
(5, '2 inch', 180.000),
(5, '3 inch', 100.000);

-- Sample Transactions for last 30 days
INSERT INTO ledger_transactions (variety_id, transaction_date, customer, invoice_number, delivery_note, stock_in, stock_out, remarks, created_by) VALUES
-- Paint White
(1, CURDATE() - INTERVAL 25 DAY, 'ABC Construction', 'INV-001', 'DN-001', 0, 50, 'Sold to ABC Construction', 1),
(1, CURDATE() - INTERVAL 20 DAY, 'BuildRight Ltd', 'INV-002', 'DN-002', 100, 0, 'Stock Purchased', 1),
(1, CURDATE() - INTERVAL 15 DAY, 'City Developers', 'INV-003', 'DN-003', 0, 30, 'Sold to City Developers', 1),
(1, CURDATE() - INTERVAL 10 DAY, 'Home Depot', 'INV-004', 'DN-004', 0, 20, 'Sold to Home Depot', 1),
(1, CURDATE() - INTERVAL 5 DAY, 'Green Builders', 'INV-005', 'DN-005', 0, 15, 'Sold to Green Builders', 1),
(1, CURDATE() - INTERVAL 2 DAY, 'Paint Supply Co', 'INV-006', 'DN-006', 200, 0, 'Wholesale Purchase', 1),
(1, CURDATE(), 'Quick Renovations', 'INV-007', 'DN-007', 0, 35, 'Daily Sale', 1),

-- Paint Black
(2, CURDATE() - INTERVAL 28 DAY, 'XYZ Contractors', 'INV-008', 'DN-008', 0, 40, 'Initial Sale', 1),
(2, CURDATE() - INTERVAL 18 DAY, 'BuildRight Ltd', 'INV-009', 'DN-009', 50, 0, 'Restock', 1),
(2, CURDATE() - INTERVAL 8 DAY, 'ABC Construction', 'INV-010', 'DN-010', 0, 20, 'Sale', 1),
(2, CURDATE(), 'Home Depot', 'INV-011', 'DN-011', 0, 10, 'Daily Sale', 1),

-- Paint Blue
(3, CURDATE() - INTERVAL 22 DAY, 'Blue Sky Designs', 'INV-012', 'DN-012', 0, 25, 'Sale', 1),
(3, CURDATE() - INTERVAL 12 DAY, 'City Painters', 'INV-013', 'DN-013', 80, 0, 'Purchase', 1),
(3, CURDATE() - INTERVAL 5 DAY, 'Home Decor Plus', 'INV-014', 'DN-014', 0, 10, 'Sale', 1),
(3, CURDATE(), 'Quick Renovations', 'INV-015', 'DN-015', 0, 15, 'Sale', 1),

-- Steel Rod 12mm
(8, CURDATE() - INTERVAL 30 DAY, 'Steel Works Inc', 'INV-016', 'DN-016', 200, 0, 'Bulk Purchase', 1),
(8, CURDATE() - INTERVAL 20 DAY, 'BuildRight Ltd', 'INV-017', 'DN-017', 0, 150, 'Construction Supply', 1),
(8, CURDATE() - INTERVAL 10 DAY, 'Iron Masters', 'INV-018', 'DN-018', 0, 100, 'Sale', 1),
(8, CURDATE() - INTERVAL 3 DAY, 'City Developers', 'INV-019', 'DN-019', 0, 50, 'Stock Issue', 1),
(8, CURDATE(), 'ABC Construction', 'INV-020', 'DN-020', 0, 75, 'Daily Stock Out', 1),

-- Cement OPC 50kg
(6, CURDATE() - INTERVAL 27 DAY, 'BuildRight Ltd', 'INV-021', 'DN-021', 300, 0, 'Cement Purchase', 1),
(6, CURDATE() - INTERVAL 17 DAY, 'ABC Construction', 'INV-022', 'DN-022', 0, 200, 'Stock Out', 1),
(6, CURDATE() - INTERVAL 7 DAY, 'Home Depot', 'INV-023', 'DN-023', 0, 100, 'Sale', 1),
(6, CURDATE(), 'Green Builders', 'INV-024', 'DN-024', 0, 150, 'Stock Issue', 1);

-- =====================================================
-- SAMPLE ACTIVITY LOGS
-- =====================================================
INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES
(1, 'login', 'User logged in successfully', '127.0.0.1', NOW() - INTERVAL 1 HOUR),
(1, 'password_change', 'Password was changed', '127.0.0.1', NOW() - INTERVAL 2 DAY),
(1, 'product_add', 'Added product: Paint', '127.0.0.1', NOW() - INTERVAL 3 DAY),
(1, 'product_edit', 'Edited product: Cement', '127.0.0.1', NOW() - INTERVAL 5 DAY),
(1, 'stock_in', 'Stock In: White Paint (+200)', '127.0.0.1', NOW() - INTERVAL 2 DAY),
(1, 'stock_out', 'Stock Out: White Paint (-35)', '127.0.0.1', NOW()),
(1, 'report_generated', 'Generated Daily Report', '127.0.0.1', NOW() - INTERVAL 10 HOUR),
(1, 'backup', 'Database backup created', '127.0.0.1', NOW() - INTERVAL 1 WEEK);

SELECT 'Database and sample data created successfully!' AS message;
