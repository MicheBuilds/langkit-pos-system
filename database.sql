-- Langkit University Shop - Database Setup Schema
-- Suitable for phpMyAdmin Import

CREATE DATABASE IF NOT EXISTS `langkit_shop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `langkit_shop`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(50) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` VARCHAR(50) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(50) UNIQUE NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `cost` DECIMAL(10, 2) NOT NULL,
  `stock` INT NOT NULL,
  `threshold` INT NOT NULL DEFAULT 20,
  `image_path` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` VARCHAR(50) PRIMARY KEY,
  `timestamp` DATETIME NOT NULL,
  `cashier_id` VARCHAR(50) NOT NULL,
  `cashier_name` VARCHAR(100) NOT NULL,
  `customer_type` VARCHAR(50) NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `discount_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(10, 2) NOT NULL,
  `grand_total` DECIMAL(10, 2) NOT NULL,
  `buyer_email` VARCHAR(150) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Transaction Items Table
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` VARCHAR(50) NOT NULL,
  `product_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Shifts Table
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL,
  `cashier_name` VARCHAR(100) NOT NULL,
  `open_time` DATETIME NOT NULL,
  `close_time` DATETIME DEFAULT NULL,
  `opening_cash` DECIMAL(10, 2) NOT NULL,
  `closing_cash` DECIMAL(10, 2) DEFAULT 0.00,
  `actual_cash` DECIMAL(10, 2) DEFAULT 0.00,
  `sales_total` DECIMAL(10, 2) DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Audit Trail Table
CREATE TABLE IF NOT EXISTS `audit_trail` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `user_id` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Expenses Table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `timestamp` DATETIME NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `category` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default user hashes (owner123 / cashier123)
-- (Real database setups will automatically seed these via db.php script)
INSERT IGNORE INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES 
('u-owner-1', 'Store Owner Manager', 'owner', '$2y$10$tZ2cQ.2R/XGv830C895Y9Om2b2ZqS3y0F7rA7U0/n.7mCgV8B/Zbe', 'owner'),
('u-cashier-1', 'Shift Cashier', 'cashier', '$2y$10$wIex66B8aU4Q5Zk2K4dOFeA54H68H94O3aB4m4F8k8n.VwzFv6rB.', 'cashier');
