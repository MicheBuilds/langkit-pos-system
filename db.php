<?php
// Langkit University Shop - MySQL Database Connection & Auto-Setup
// Compatible with XAMPP (Host: localhost, Username: root, Password: "")

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'langkit_shop';

try {
  // 1. First connect to MySQL without selecting a database to check existence
  $pdo = new PDO("mysql:host=$host", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  // Create database if not exists
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  
  // 2. Re-connect specifically to the langkit_shop database
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  
  // 3. Auto-Setup Migration Tables
  setupDatabaseTables($pdo);

} catch (PDOException $e) {
  // Graceful failure return for JSON APIs
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'error',
    'message' => 'Database connection failed: ' . $e->getMessage()
  ]);
  exit;
}

function setupDatabaseTables($pdo) {
  // 1. Create Users Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL, /* 'owner' or 'cashier' */
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 2. Create Products Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `sku` VARCHAR(50) UNIQUE NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `cost` DECIMAL(10, 2) NOT NULL,
    `stock` INT NOT NULL,
    `threshold` INT NOT NULL DEFAULT 20,
    `image_path` VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 3. Create Transactions Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (
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
    `status` VARCHAR(20) NOT NULL DEFAULT 'completed' /* 'completed' or 'voided' */
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 4. Create Transaction Items Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` VARCHAR(50) NOT NULL,
    `product_id` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `qty` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 5. Create Shifts Table (Attendance/Cashier accountability logs)
  $pdo->exec("CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `cashier_name` VARCHAR(100) NOT NULL,
    `open_time` DATETIME NOT NULL,
    `close_time` DATETIME DEFAULT NULL,
    `opening_cash` DECIMAL(10, 2) NOT NULL,
    `closing_cash` DECIMAL(10, 2) DEFAULT 0.00,
    `actual_cash` DECIMAL(10, 2) DEFAULT 0.00,
    `sales_total` DECIMAL(10, 2) DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'open' /* 'open' or 'closed' */
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 6. Create Security Audit Trail Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_trail` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `user_id` VARCHAR(50) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // 7. Create Shop Expenses Table
  $pdo->exec("CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` DATETIME NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `category` VARCHAR(50) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");


  // --- SEEDING CORE ACCOUNTS ---
  $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
  if ($userCount == 0) {
    $seedUsers = [
      [
        'id' => 'u-owner-1',
        'name' => 'Store Owner Manager',
        'username' => 'owner',
        'password' => password_hash('owner123', PASSWORD_DEFAULT),
        'role' => 'owner'
      ],
      [
        'id' => 'u-cashier-1',
        'name' => 'Shift Cashier',
        'username' => 'cashier',
        'password' => password_hash('cashier123', PASSWORD_DEFAULT),
        'role' => 'cashier'
      ]
    ];

    $userStmt = $pdo->prepare("INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES (:id, :name, :username, :password, :role)");
    foreach ($seedUsers as $u) {
      $userStmt->execute($u);
    }
  }


  // --- SEEDING PRODUCT CATALOG ---
  $prodCount = $pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
  if ($prodCount == 0) {
    $seedProducts = [
      [
        'id' => 'prod-lanyard',
        'name' => 'Reversible Lanyard',
        'sku' => 'LKT-LYD-001',
        'category' => 'Accessories',
        'price' => 150.00,
        'cost' => 60.00,
        'stock' => 120,
        'threshold' => 40,
        'image_path' => 'assets/lanyard.jpg'
      ],
      [
        'id' => 'prod-mug',
        'name' => 'Insulated Mug',
        'sku' => 'LKT-MUG-002',
        'category' => 'Drinkware',
        'price' => 350.00,
        'cost' => 150.00,
        'stock' => 60,
        'threshold' => 25,
        'image_path' => 'assets/mug.jpg'
      ],
      [
        'id' => 'prod-umbrella',
        'name' => 'Premium Umbrella',
        'sku' => 'LKT-UMB-003',
        'category' => 'Accessories',
        'price' => 450.00,
        'cost' => 200.00,
        'stock' => 25,
        'threshold' => 15,
        'image_path' => 'assets/umbrella.jpg'
      ],
      [
        'id' => 'prod-tshirt',
        'name' => 'Sports T-Shirt',
        'sku' => 'LKT-TSH-004',
        'category' => 'Apparel',
        'price' => 250.00,
        'cost' => 110.00,
        'stock' => 45,
        'threshold' => 20,
        'image_path' => 'assets/tshirt.jpg'
      ],
      [
        'id' => 'prod-jacket',
        'name' => 'Varsity Jacket Maroon',
        'sku' => 'LKT-VJK-005',
        'category' => 'Apparel',
        'price' => 1500.00,
        'cost' => 700.00,
        'stock' => 15,
        'threshold' => 10,
        'image_path' => 'assets/jacket.jpg'
      ]
    ];

    $prodStmt = $pdo->prepare("INSERT INTO `products` (`id`, `name`, `sku`, `category`, `price`, `cost`, `stock`, `threshold`, `image_path`) VALUES (:id, :name, :sku, :category, :price, :cost, :stock, :threshold, :image_path)");
    foreach ($seedProducts as $p) {
      $prodStmt->execute($p);
    }
  }
}
?>
