-- Product Inbound Shipment Counting Record System
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS inbound_shipment_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inbound_shipment_db;

-- Users table (admin and regular users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Admin inbound shipment records
CREATE TABLE IF NOT EXISTS inbound_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inbound_date DATE NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    shipment_number VARCHAR(100) NOT NULL,
    total_quantity INT NOT NULL DEFAULT 0 COMMENT 'Expected carton count',
    quantity INT NOT NULL DEFAULT 0 COMMENT 'Product quantity',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_shipment_number (shipment_number),
    INDEX idx_product_name (product_name),
    INDEX idx_inbound_date (inbound_date)
) ENGINE=InnoDB;

-- User counting records
CREATE TABLE IF NOT EXISTS counting_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(100) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    counting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    completion_time TIME NOT NULL,
    total_counted INT NOT NULL DEFAULT 0 COMMENT 'Counted cartons (admin editable)',
    quantity_counted INT NOT NULL DEFAULT 0 COMMENT 'Counted product quantity',
    counted_by VARCHAR(100) NOT NULL,
    remarks TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_shipment_number (shipment_number),
    INDEX idx_counting_date (counting_date)
) ENGINE=InnoDB;

-- Default admin account (password: admin123)
-- Change password after first login!
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@inbound.local', '$2y$10$.MYp03P7v1ticvPDY95XBuXUO3sNW5GSWoI1yyENpBFDtlFm9IAiq', 'admin')
ON DUPLICATE KEY UPDATE username = username;
