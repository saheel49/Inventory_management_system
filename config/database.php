<?php
require_once __DIR__ . '/app.php';

class Database {
    private $conn;

    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->conn->connect_error) {
                throw new Exception('Connection failed: ' . $this->conn->connect_error);
            }
            $this->conn->set_charset('utf8mb4');
            $this->ensureSchema();
        } catch (Exception $e) {
            die('Database Error: ' . $e->getMessage());
        }
    }

    private function ensureSchema() {
        $this->conn->query("CREATE TABLE IF NOT EXISTS customers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            address TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $this->conn->query("CREATE TABLE IF NOT EXISTS suppliers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            address TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $this->conn->query("CREATE TABLE IF NOT EXISTS customer_transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            customer_id INT NOT NULL,
            variety_id INT NOT NULL,
            ledger_id INT NULL,
            quantity DECIMAL(15,3) DEFAULT 0.000,
            unit_price DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            transaction_date DATE NOT NULL,
            notes TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (variety_id) REFERENCES product_varieties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        $this->conn->query("CREATE TABLE IF NOT EXISTS supplier_transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            supplier_id INT NOT NULL,
            variety_id INT NOT NULL,
            ledger_id INT NULL,
            quantity DECIMAL(15,3) DEFAULT 0.000,
            unit_price DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            transaction_date DATE NOT NULL,
            notes TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            FOREIGN KEY (variety_id) REFERENCES product_varieties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        $this->conn->query("ALTER TABLE product_varieties ADD COLUMN IF NOT EXISTS unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00");
        $this->conn->query("ALTER TABLE product_varieties ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT ''");
    }

    public function getConnection() {
        return $this->conn;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
}

function getDB() {
    return (new Database())->getConnection();
}

