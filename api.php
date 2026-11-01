<?php
// Langkit University Shop - Unified Backend JSON REST API
// Interacts with MySQL via db.php and enforces session controls

session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");

require_once 'db.php';

// Authentication Guard Helper
function checkAuth($requiredRole = null) {
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized', 'message' => 'Active session required.']);
    exit;
  }
  if ($requiredRole && $_SESSION['user_role'] !== $requiredRole) {
    echo json_encode(['status' => 'forbidden', 'message' => 'Administrative privileges required.']);
    exit;
  }
}

// Log audit helpers
function logAudit($pdo, $action, $details) {
  try {
    $stmt = $pdo->prepare("INSERT INTO `audit_trail` (`user_id`, `username`, `action`, `details`) VALUES (:uid, :uname, :action, :details)");
    $stmt->execute([
      'uid' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system',
      'uname' => isset($_SESSION['user_username']) ? $_SESSION['user_username'] : 'system',
      'action' => $action,
      'details' => $details
    ]);
  } catch (PDOException $e) {
    // Fail silently in audit trail to avoid breaking transactions
  }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
  
  // 1. GET ALL PRODUCTS
  case 'get_products':
    checkAuth();
    try {
      $stmt = $pdo->query("SELECT * FROM `products` ORDER BY `category`, `name` ASC");
      $productsList = $stmt->fetchAll();
      
      // Format data types correctly
      foreach ($productsList as &$p) {
        $p['price'] = (float)$p['price'];
        $p['cost'] = (float)$p['cost'];
        $p['stock'] = (int)$p['stock'];
        $p['threshold'] = (int)$p['threshold'];
      }
      
      echo json_encode(['status' => 'success', 'data' => $productsList]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 2. OWNER: ADD NEW PRODUCT
  case 'add_product':
    checkAuth('owner');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $sku = isset($data['sku']) ? trim(strtoupper($data['sku'])) : '';
    $category = isset($data['category']) ? trim($data['category']) : 'Accessories';
    $price = isset($data['price']) ? (float)$data['price'] : 0.00;
    $cost = isset($data['cost']) ? (float)$data['cost'] : 0.00;
    $stock = isset($data['stock']) ? (int)$data['stock'] : 0;
    $threshold = isset($data['threshold']) ? (int)$data['threshold'] : 20;
    
    // Choose appropriate image based on name/preset
    $image_path = 'assets/lanyard.jpg'; // default
    $lowerName = strtolower($name);
    if (strpos($lowerName, 'mug') !== false) {
      $image_path = 'assets/mug.jpg';
    } else if (strpos($lowerName, 'umbrella') !== false) {
      $image_path = 'assets/umbrella.jpg';
    } else if (strpos($lowerName, 'jacket') !== false) {
      $image_path = 'assets/jacket.jpg';
    } else if (strpos($lowerName, 'shirt') !== false || strpos($lowerName, 'tshirt') !== false) {
      $image_path = 'assets/tshirt.jpg';
    }

    if (empty($name) || empty($sku)) {
      echo json_encode(['status' => 'error', 'message' => 'Product Name and SKU are required.']);
      exit;
    }

    try {
      // Check duplicate SKU
      $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM `products` WHERE `sku` = :sku");
      $skuCheck->execute(['sku' => $sku]);
      if ($skuCheck->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => "SKU Code '{$sku}' is already registered."]);
        exit;
      }

      $id = 'prod-' . uniqid();
      $stmt = $pdo->prepare("INSERT INTO `products` (`id`, `name`, `sku`, `category`, `price`, `cost`, `stock`, `threshold`, `image_path`) VALUES (:id, :name, :sku, :category, :price, :cost, :stock, :threshold, :image_path)");
      $stmt->execute([
        'id' => $id,
        'name' => $name,
        'sku' => $sku,
        'category' => $category,
        'price' => $price,
        'cost' => $cost,
        'stock' => $stock,
        'threshold' => $threshold,
        'image_path' => $image_path
      ]);

      logAudit($pdo, 'product_create', "Created product {$name} (SKU: {$sku}, Price: ₱{$price})");
      echo json_encode(['status' => 'success', 'message' => "Product '{$name}' added successfully."]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 3. OWNER: EDIT PRODUCT
  case 'edit_product':
    checkAuth('owner');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? trim($data['id']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    $sku = isset($data['sku']) ? trim(strtoupper($data['sku'])) : '';
    $category = isset($data['category']) ? trim($data['category']) : '';
    $price = isset($data['price']) ? (float)$data['price'] : 0.00;
    $cost = isset($data['cost']) ? (float)$data['cost'] : 0.00;
    $stock = isset($data['stock']) ? (int)$data['stock'] : 0;
    $threshold = isset($data['threshold']) ? (int)$data['threshold'] : 20;

    if (empty($id) || empty($name) || empty($sku)) {
      echo json_encode(['status' => 'error', 'message' => 'Required fields missing.']);
      exit;
    }

    try {
      // Check SKU duplicate (excluding current ID)
      $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM `products` WHERE `sku` = :sku AND `id` != :id");
      $skuCheck->execute(['sku' => $sku, 'id' => $id]);
      if ($skuCheck->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => "SKU Code '{$sku}' is already in use by another product."]);
        exit;
      }

      // Fetch old info for audit logging
      $oldStmt = $pdo->prepare("SELECT * FROM `products` WHERE `id` = :id");
      $oldStmt->execute(['id' => $id]);
      $oldProd = $oldStmt->fetch();

      $stmt = $pdo->prepare("UPDATE `products` SET `name` = :name, `sku` = :sku, `category` = :category, `price` = :price, `cost` = :cost, `stock` = :stock, `threshold` = :threshold WHERE `id` = :id");
      $stmt->execute([
        'name' => $name,
        'sku' => $sku,
        'category' => $category,
        'price' => $price,
        'cost' => $cost,
        'stock' => $stock,
        'threshold' => $threshold,
        'id' => $id
      ]);

      $auditDetails = "Edited product {$name} (SKU: {$sku}). Stock changed: {$oldProd['stock']} -> {$stock}, Price: ₱{$oldProd['price']} -> ₱{$price}";
      logAudit($pdo, 'product_edit', $auditDetails);
      echo json_encode(['status' => 'success', 'message' => "Product '{$name}' updated successfully."]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 4. OWNER: DELETE PRODUCT
  case 'delete_product':
    checkAuth('owner');
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
      echo json_encode(['status' => 'error', 'message' => 'Product ID required.']);
      exit;
    }

    try {
      // Get product details for audit log
      $stmt = $pdo->prepare("SELECT `name`, `sku` FROM `products` WHERE `id` = :id");
      $stmt->execute(['id' => $id]);
      $prod = $stmt->fetch();
      
      if (!$prod) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        exit;
      }

      $delStmt = $pdo->prepare("DELETE FROM `products` WHERE `id` = :id");
      $delStmt->execute(['id' => $id]);

      logAudit($pdo, 'product_delete', "Deleted product {$prod['name']} (SKU: {$prod['sku']})");
      echo json_encode(['status' => 'success', 'message' => "Product '{$prod['name']}' deleted successfully."]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => 'Cannot delete product: ' . $e->getMessage()]);
    }
    break;

  // 5. GET ACTIVE SHIFT (CASHIER)
  case 'get_active_shift':
    checkAuth();
    try {
      $stmt = $pdo->prepare("SELECT * FROM `shifts` WHERE `user_id` = :uid AND `status` = 'open'");
      $stmt->execute(['uid' => $_SESSION['user_id']]);
      $shift = $stmt->fetch();
      
      if ($shift) {
        $shift['opening_cash'] = (float)$shift['opening_cash'];
        $shift['sales_total'] = (float)$shift['sales_total'];
        echo json_encode(['status' => 'success', 'has_active_shift' => true, 'shift' => $shift]);
      } else {
        echo json_encode(['status' => 'success', 'has_active_shift' => false]);
      }
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 6. OPEN SHIFT (CASHIER)
  case 'open_shift':
    checkAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $opening_cash = isset($data['opening_cash']) ? (float)$data['opening_cash'] : 0.00;

    try {
      // Ensure no current open shift
      $check = $pdo->prepare("SELECT COUNT(*) FROM `shifts` WHERE `user_id` = :uid AND `status` = 'open'");
      $check->execute(['uid' => $_SESSION['user_id']]);
      if ($check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You already have an active open shift.']);
        exit;
      }

      $stmt = $pdo->prepare("INSERT INTO `shifts` (`user_id`, `cashier_name`, `open_time`, `opening_cash`, `status`) VALUES (:uid, :name, NOW(), :cash, 'open')");
      $stmt->execute([
        'uid' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'cash' => $opening_cash
      ]);

      logAudit($pdo, 'shift_open', "Opened sales drawer shift with ₱" . number_format($opening_cash, 2));
      echo json_encode(['status' => 'success', 'message' => 'Shift opened successfully.']);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 7. CLOSE SHIFT (CASHIER)
  case 'close_shift':
    checkAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $closing_cash = isset($data['closing_cash']) ? (float)$data['closing_cash'] : 0.00;

    try {
      // Find active shift
      $stmt = $pdo->prepare("SELECT * FROM `shifts` WHERE `user_id` = :uid AND `status` = 'open'");
      $stmt->execute(['uid' => $_SESSION['user_id']]);
      $shift = $stmt->fetch();

      if (!$shift) {
        echo json_encode(['status' => 'error', 'message' => 'No active shift found to close.']);
        exit;
      }

      // Calculate shift total sales dynamically from database to double-check
      $salesStmt = $pdo->prepare("SELECT IFNULL(SUM(`grand_total`), 0) FROM `transactions` WHERE `cashier_id` = :cid AND `status` = 'completed' AND `timestamp` >= :opentime");
      $salesStmt->execute([
        'cid' => $_SESSION['user_id'],
        'opentime' => $shift['open_time']
      ]);
      $salesTotal = (float)$salesStmt->fetchColumn();
      
      $expectedCash = (float)$shift['opening_cash'] + $salesTotal;
      $discrepancy = $closing_cash - $expectedCash;

      // Update shift
      $updateStmt = $pdo->prepare("UPDATE `shifts` SET `close_time` = NOW(), `closing_cash` = :closing, `actual_cash` = :actual, `sales_total` = :sales, `status` = 'closed' WHERE `id` = :id");
      $updateStmt->execute([
        'closing' => $expectedCash, // expected cash
        'actual' => $closing_cash, // actual cash counted
        'sales' => $salesTotal,
        'id' => $shift['id']
      ]);

      $auditMsg = "Closed shift. Total Sales: ₱" . number_format($salesTotal, 2) . ". Cash Counted: ₱" . number_format($closing_cash, 2) . " (Expected: ₱" . number_format($expectedCash, 2) . "). Discrepancy: ₱" . number_format($discrepancy, 2);
      logAudit($pdo, 'shift_close', $auditMsg);

      // Log discrepancy if significant
      if (abs($discrepancy) > 1.00) {
        logAudit($pdo, 'suspicious_activity', "Shift Cash Discrepancy detected for {$_SESSION['user_name']}! Discrepancy: ₱" . number_format($discrepancy, 2));
      }

      echo json_encode([
        'status' => 'success', 
        'message' => 'Shift closed successfully.',
        'summary' => [
          'sales_total' => $salesTotal,
          'opening_cash' => (float)$shift['opening_cash'],
          'expected_cash' => $expectedCash,
          'actual_cash' => $closing_cash,
          'discrepancy' => $discrepancy
        ]
      ]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 8. CHECKOUT SALE ORDER TRANSACTION (CASHIER/OWNER)
  case 'checkout':
    checkAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $cart = isset($data['cart']) ? $data['cart'] : [];
    $customerType = isset($data['customer_type']) ? trim($data['customer_type']) : 'Walk-in Customer';
    $discountPercent = isset($data['discount_percent']) ? (float)$data['discount_percent'] : 0.00;
    $couponCode = isset($data['coupon_code']) ? trim(strtoupper($data['coupon_code'])) : '';
    $buyerEmail = isset($data['buyer_email']) ? trim($data['buyer_email']) : '';

    if (empty($cart)) {
      echo json_encode(['status' => 'error', 'message' => 'Shopping cart is empty.']);
      exit;
    }

    // Check if cashier has open shift
    if ($_SESSION['user_role'] === 'cashier') {
      $shiftCheck = $pdo->prepare("SELECT COUNT(*) FROM `shifts` WHERE `user_id` = :uid AND `status` = 'open'");
      $shiftCheck->execute(['uid' => $_SESSION['user_id']]);
      if ($shiftCheck->fetchColumn() == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Checkout blocked: You must OPEN A SHIFT drawer first before processing sales.']);
        exit;
      }
    }

    try {
      // 1. Begin SQL ACID Transaction block
      $pdo->beginTransaction();

      $subtotal = 0.00;
      $taxRate = 0.12; // 12% VAT in Philippines
      $itemsToInsert = [];

      // Lock row levels to double check stock before decrement
      $stmtCheckStock = $pdo->prepare("SELECT `id`, `name`, `stock`, `price` FROM `products` WHERE `id` = :id FOR UPDATE");

      foreach ($cart as $item) {
        $itemId = $item['id'];
        $qty = (int)$item['qty'];

        $stmtCheckStock->execute(['id' => $itemId]);
        $prod = $stmtCheckStock->fetch();

        if (!$prod) {
          throw new Exception("Product '{$item['name']}' not found in database.");
        }

        if ($prod['stock'] < $qty) {
          throw new Exception("Insufficient stock for '{$prod['name']}'. Only {$prod['stock']} remaining (Requested: {$qty}).");
        }

        $itemSubtotal = $prod['price'] * $qty;
        $subtotal += $itemSubtotal;

        $itemsToInsert[] = [
          'product_id' => $prod['id'],
          'name' => $prod['name'],
          'qty' => $qty,
          'price' => (float)$prod['price']
        ];
      }

      // Calculations
      $discountAmount = $subtotal * ($discountPercent / 100);
      $taxableAmount = ($subtotal - $discountAmount);
      $taxAmount = $taxableAmount * $taxRate;
      $grandTotal = $taxableAmount + $taxAmount;

      $txId = 'TX-' . mt_rand(10000, 99999) . '-' . mt_rand(1000, 9999);

      // 2. Write Transaction Header
      $txStmt = $pdo->prepare("INSERT INTO `transactions` (`id`, `timestamp`, `cashier_id`, `cashier_name`, `customer_type`, `subtotal`, `discount_percent`, `discount_amount`, `tax`, `grand_total`, `buyer_email`, `status`) VALUES (:id, NOW(), :cid, :cname, :ctype, :sub, :dp, :da, :tax, :total, :email, 'completed')");
      $txStmt->execute([
        'id' => $txId,
        'cid' => $_SESSION['user_id'],
        'cname' => $_SESSION['user_name'],
        'ctype' => $customerType,
        'sub' => $subtotal,
        'dp' => $discountPercent,
        'da' => $discountAmount,
        'tax' => $taxAmount,
        'total' => $grandTotal,
        'email' => empty($buyerEmail) ? null : $buyerEmail
      ]);

      // 3. Write items and decrement inventories
      $itemStmt = $pdo->prepare("INSERT INTO `transaction_items` (`transaction_id`, `product_id`, `name`, `qty`, `price`) VALUES (:txid, :pid, :name, :qty, :price)");
      $updateStockStmt = $pdo->prepare("UPDATE `products` SET `stock` = `stock` - :qty WHERE `id` = :id");

      foreach ($itemsToInsert as $ins) {
        $itemStmt->execute([
          'txid' => $txId,
          'pid' => $ins['product_id'],
          'name' => $ins['name'],
          'qty' => $ins['qty'],
          'price' => $ins['price']
        ]);

        $updateStockStmt->execute([
          'qty' => $ins['qty'],
          'id' => $ins['product_id']
        ]);
      }

      // 4. Update cashier sales total in active shift
      $shiftUpdate = $pdo->prepare("UPDATE `shifts` SET `sales_total` = `sales_total` + :total WHERE `user_id` = :uid AND `status` = 'open'");
      $shiftUpdate->execute(['total' => $grandTotal, 'uid' => $_SESSION['user_id']]);

      // Commit transaction
      $pdo->commit();

      // Audit Logger
      $auditDesc = "Processed checkout {$txId}. Amount Paid: ₱" . number_format($grandTotal, 2) . " (Subtotal: ₱" . number_format($subtotal, 2) . ", Discount: {$discountPercent}%, Tax 12%: ₱" . number_format($taxAmount, 2) . "). Customer: {$customerType}";
      logAudit($pdo, 'checkout', $auditDesc);

      // --- SIMULATED SMTP GMAIL E-RECEIPT LOGGER ---
      $emailLogs = '';
      if (!empty($buyerEmail)) {
        // Create receipts directory
        $receiptDir = __DIR__ . '/e_receipts';
        if (!file_exists($receiptDir)) {
          mkdir($receiptDir, 0777, true);
        }

        // Build HTML Receipt Email body
        $itemsHtml = '';
        foreach ($itemsToInsert as $ins) {
          $itemTotal = $ins['qty'] * $ins['price'];
          $itemsHtml .= "<tr>
            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$ins['name']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$ins['qty']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>₱" . number_format($ins['price'], 2) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>₱" . number_format($itemTotal, 2) . "</td>
          </tr>";
        }

        $emailContent = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333; background-color: #f9f9f9; padding: 20px;'>
          <div style='max-width: 600px; margin: 0 auto; background-color: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
            <div style='background-color: #8B0000; padding: 25px; text-align: center; color: #fff;'>
              <h1 style='margin: 0; font-family: Georgia, serif; color: #D4AF37; font-size: 28px;'>LANGKIT UNIVERSITY SHOP</h1>
              <p style='margin: 5px 0 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Official Digital Sales Invoice</p>
            </div>
            <div style='padding: 30px;'>
              <p>Dear customer, thank you for supporting the <strong>Langkit University Shop</strong>! Below are the details of your digital receipt.</p>
              
              <div style='background-color: #f5f5f5; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                <table style='width: 100%; font-size: 14px;'>
                  <tr><td><strong>Receipt Ref:</strong></td><td>{$txId}</td></tr>
                  <tr><td><strong>Date Processed:</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
                  <tr><td><strong>Served By:</strong></td><td>{$_SESSION['user_name']}</td></tr>
                  <tr><td><strong>Customer Class:</strong></td><td>{$customerType}</td></tr>
                </table>
              </div>

              <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                <thead>
                  <tr style='background-color: #eee;'>
                    <th style='padding: 10px; text-align: left;'>Product</th>
                    <th style='padding: 10px; text-align: center;'>Qty</th>
                    <th style='padding: 10px; text-align: right;'>Unit</th>
                    <th style='padding: 10px; text-align: right;'>Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  {$itemsHtml}
                </tbody>
              </table>

              <div style='margin-top: 20px; font-size: 14px; text-align: right;'>
                <p style='margin: 5px 0;'>Subtotal: ₱" . number_format($subtotal, 2) . "</p>
                " . ($discountPercent > 0 ? "<p style='margin: 5px 0; color: #2E7D32;'>Discount ({$discountPercent}%): -₱" . number_format($discountAmount, 2) . "</p>" : "") . "
                <p style='margin: 5px 0;'>12% VAT: ₱" . number_format($taxAmount, 2) . "</p>
                <h3 style='margin: 10px 0 0 0; color: #8B0000; font-size: 18px;'>Grand Total: ₱" . number_format($grandTotal, 2) . "</h3>
              </div>

              <div style='margin-top: 30px; border-top: 1px dashed #ddd; padding-top: 20px; text-align: center; font-size: 12px; color: #777;'>
                <p>Mindanao State University - Iligan Institute of Technology</p>
                <p>For inquiries, visit our facebook page at MSU IIT Langkit University Shop.</p>
                <p>Copyright &copy; 2026. All Rights Reserved.</p>
              </div>
            </div>
          </div>
        </body>
        </html>";

        // Save simulated email receipt HTML to file
        $receiptPath = $receiptDir . "/receipt_{$txId}.html";
        file_put_contents($receiptPath, $emailContent);
        
        $emailLogs = "[Simulated SMTP Log] E-Receipt HTML compiled successfully! Saved backup copy to assets/e_receipts/receipt_{$txId}.html. Dispatching secure SMTP email payload to gmail MX servers (recipient: {$buyerEmail}, subject: Langkit Shop Invoice {$txId}). Success status: 250 OK.";
        logAudit($pdo, 'email_receipt', "Simulated SMTP e-receipt sent successfully for {$txId} to {$buyerEmail}");
      }

      echo json_encode([
        'status' => 'success',
        'message' => 'Checkout processed successfully.',
        'transaction_id' => $txId,
        'email_log' => $emailLogs,
        'data' => [
          'subtotal' => $subtotal,
          'discount' => $discountAmount,
          'tax' => $taxAmount,
          'total' => $grandTotal
        ]
      ]);

    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 9. OWNER: VOID TRANSACTION
  case 'void_transaction':
    checkAuth('owner');
    $txId = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($txId)) {
      echo json_encode(['status' => 'error', 'message' => 'Transaction ID required.']);
      exit;
    }

    try {
      $pdo->beginTransaction();

      // Find transaction
      $stmt = $pdo->prepare("SELECT * FROM `transactions` WHERE `id` = :id FOR UPDATE");
      $stmt->execute(['id' => $txId]);
      $tx = $stmt->fetch();

      if (!$tx) {
        throw new Exception("Transaction '{$txId}' not found.");
      }

      if ($tx['status'] === 'voided') {
        throw new Exception("Transaction '{$txId}' is already voided.");
      }

      // Restock items
      $itemsStmt = $pdo->prepare("SELECT `product_id`, `qty` FROM `transaction_items` WHERE `transaction_id` = :txid");
      $itemsStmt->execute(['txid' => $txId]);
      $items = $itemsStmt->fetchAll();

      $restockStmt = $pdo->prepare("UPDATE `products` SET `stock` = `stock` + :qty WHERE `id` = :pid");
      foreach ($items as $item) {
        $restockStmt->execute(['qty' => $item['qty'], 'pid' => $item['product_id']]);
      }

      // Update status
      $updateStmt = $pdo->prepare("UPDATE `transactions` SET `status` = 'voided' WHERE `id` = :id");
      $updateStmt->execute(['id' => $txId]);

      // Deduct from cashier shift total
      $shiftUpdate = $pdo->prepare("UPDATE `shifts` SET `sales_total` = GREATEST(0.00, `sales_total` - :total) WHERE `user_id` = :uid AND `status` = 'open'");
      $shiftUpdate->execute(['total' => $tx['grand_total'], 'uid' => $tx['cashier_id']]);

      $pdo->commit();

      logAudit($pdo, 'void_sale', "Voided sales transaction {$txId} (Amount: ₱" . number_format($tx['grand_total'], 2) . "). Quantities restored to inventories.");
      echo json_encode(['status' => 'success', 'message' => "Transaction {$txId} successfully voided."]);
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 10. GET TRANSACTIONS LIST
  case 'get_transactions':
    checkAuth();
    try {
      $stmt = $pdo->query("SELECT * FROM `transactions` ORDER BY `timestamp` DESC");
      $transactions = $stmt->fetchAll();
      
      // Fetch details of items for each transaction
      $itemStmt = $pdo->prepare("SELECT * FROM `transaction_items` WHERE `transaction_id` = :txid");
      
      foreach ($transactions as &$tx) {
        $tx['subtotal'] = (float)$tx['subtotal'];
        $tx['discount_percent'] = (float)$tx['discount_percent'];
        $tx['discount_amount'] = (float)$tx['discount_amount'];
        $tx['tax'] = (float)$tx['tax'];
        $tx['grand_total'] = (float)$tx['grand_total'];
        
        $itemStmt->execute(['txid' => $tx['id']]);
        $tx['items'] = $itemStmt->fetchAll();
        foreach ($tx['items'] as &$it) {
          $it['price'] = (float)$it['price'];
          $it['qty'] = (int)$it['qty'];
        }
      }
      
      echo json_encode(['status' => 'success', 'data' => $transactions]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 11. OWNER: RETRIEVE ADMINISTRATIVE METRICS
  case 'get_owner_metrics':
    checkAuth('owner');
    try {
      // General Inventory valuation and indicators
      $invValPrice = (float)$pdo->query("SELECT SUM(`price` * `stock`) FROM `products`")->fetchColumn();
      $invValCost = (float)$pdo->query("SELECT SUM(`cost` * `stock`) FROM `products`")->fetchColumn();
      $activeCategories = (int)$pdo->query("SELECT COUNT(DISTINCT `category`) FROM `products`")->fetchColumn();
      $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock` <= `threshold` AND `stock` > 0")->fetchColumn();
      $outOfStockCount = (int)$pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock` = 0")->fetchColumn();

      // Low Stock Alert items list
      $lowStockList = $pdo->query("SELECT `name`, `sku`, `stock`, `threshold` FROM `products` WHERE `stock` <= `threshold` ORDER BY `stock` ASC")->fetchAll();

      // Sales Aggregations
      $todaySales = (float)$pdo->query("SELECT IFNULL(SUM(`grand_total`), 0) FROM `transactions` WHERE `status` = 'completed' AND DATE(`timestamp`) = CURDATE()")->fetchColumn();
      $weeklySales = (float)$pdo->query("SELECT IFNULL(SUM(`grand_total`), 0) FROM `transactions` WHERE `status` = 'completed' AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
      $monthlySales = (float)$pdo->query("SELECT IFNULL(SUM(`grand_total`), 0) FROM `transactions` WHERE `status` = 'completed' AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
      
      $totalTransactions = (int)$pdo->query("SELECT COUNT(*) FROM `transactions` WHERE `status` = 'completed'")->fetchColumn();
      $voidsTotal = (float)$pdo->query("SELECT IFNULL(SUM(`grand_total`), 0) FROM `transactions` WHERE `status` = 'voided'")->fetchColumn();
      $discountsTotal = (float)$pdo->query("SELECT IFNULL(SUM(`discount_amount`), 0) FROM `transactions` WHERE `status` = 'completed'")->fetchColumn();
      $taxTotal = (float)$pdo->query("SELECT IFNULL(SUM(`tax`), 0) FROM `transactions` WHERE `status` = 'completed'")->fetchColumn();

      // Expenses Sum
      $expensesTotal = (float)$pdo->query("SELECT IFNULL(SUM(`amount`), 0) FROM `expenses`")->fetchColumn();

      // Dynamic line chart for sales over the last 7 days
      $chartData = $pdo->query("
        SELECT DATE_FORMAT(t.`timestamp`, '%Y-%m-%d') as `day`, SUM(t.`grand_total`) as `total` 
        FROM `transactions` t
        WHERE t.`status` = 'completed' AND t.`timestamp` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(t.`timestamp`)
        ORDER BY `day` ASC
      ")->fetchAll();

      // Best-selling products (limit 5)
      $bestSellers = $pdo->query("
        SELECT ti.`product_id`, ti.`name`, SUM(ti.`qty`) as `units_sold`, SUM(ti.`qty` * ti.`price`) as `revenue`
        FROM `transaction_items` ti
        JOIN `transactions` t ON ti.`transaction_id` = t.`id`
        WHERE t.`status` = 'completed'
        GROUP BY ti.`product_id`
        ORDER BY `units_sold` DESC
        LIMIT 5
      ")->fetchAll();

      // Slow-moving products (low items sold)
      $slowMovers = $pdo->query("
        SELECT p.`name`, p.`sku`, p.`stock`, IFNULL(SUM(ti.`qty`), 0) as `units_sold`
        FROM `products` p
        LEFT JOIN `transaction_items` ti ON p.`id` = ti.`product_id`
        LEFT JOIN `transactions` t ON ti.`transaction_id` = t.`id` AND t.`status` = 'completed'
        GROUP BY p.`id`
        ORDER BY `units_sold` ASC, p.`stock` DESC
        LIMIT 5
      ")->fetchAll();

      // Peak hourly sales
      $peakHours = $pdo->query("
        SELECT HOUR(t.`timestamp`) as `hour`, COUNT(*) as `tx_count`, SUM(t.`grand_total`) as `total_sales`
        FROM `transactions` t
        WHERE t.`status` = 'completed'
        GROUP BY HOUR(t.`timestamp`)
        ORDER BY `tx_count` DESC
        LIMIT 6
      ")->fetchAll();

      // Cashier performance rankings
      $cashierRankings = $pdo->query("
        SELECT t.`cashier_name`, COUNT(*) as `transactions_count`, SUM(t.`grand_total`) as `total_revenue`
        FROM `transactions` t
        WHERE t.`status` = 'completed'
        GROUP BY t.`cashier_id`
        ORDER BY `total_revenue` DESC
      ")->fetchAll();

      // Profit estimation
      $costStmt = $pdo->query("
        SELECT SUM(ti.`qty` * p.`cost`) 
        FROM `transaction_items` ti
        JOIN `transactions` t ON ti.`transaction_id` = t.`id`
        JOIN `products` p ON ti.`product_id` = p.`id`
        WHERE t.`status` = 'completed'
      ");
      $totalCostOfSales = (float)$costStmt->fetchColumn();
      $estimatedProfit = max(0.00, ($subtotal = (float)$pdo->query("SELECT SUM(`subtotal`) FROM `transactions` WHERE `status` = 'completed'")->fetchColumn()) - $totalCostOfSales - $expensesTotal);

      echo json_encode([
        'status' => 'success',
        'metrics' => [
          'sales_today' => $todaySales,
          'sales_weekly' => $weeklySales,
          'sales_monthly' => $monthlySales,
          'total_transactions' => $totalTransactions,
          'inv_val_price' => $invValPrice,
          'inv_val_cost' => $invValCost,
          'active_categories' => $activeCategories,
          'low_stock_count' => $lowStockCount,
          'out_of_stock_count' => $outOfStockCount,
          'voids_total' => $voidsTotal,
          'discounts_total' => $discountsTotal,
          'tax_total' => $taxTotal,
          'expenses_total' => $expensesTotal,
          'profit_estimate' => $estimatedProfit,
          'low_stock_list' => $lowStockList,
          'chart_data' => $chartData,
          'best_sellers' => $bestSellers,
          'slow_movers' => $slowMovers,
          'peak_hours' => $peakHours,
          'cashier_rankings' => $cashierRankings
        ]
      ]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 12. GET EXPENSES LIST (OWNER)
  case 'get_expenses':
    checkAuth('owner');
    try {
      $stmt = $pdo->query("SELECT * FROM `expenses` ORDER BY `timestamp` DESC");
      $expenses = $stmt->fetchAll();
      foreach ($expenses as &$ex) {
        $ex['amount'] = (float)$ex['amount'];
      }
      echo json_encode(['status' => 'success', 'data' => $expenses]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 13. ADD EXPENSE ENTRY (OWNER)
  case 'add_expense':
    checkAuth('owner');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $desc = isset($data['description']) ? trim($data['description']) : '';
    $amount = isset($data['amount']) ? (float)$data['amount'] : 0.00;
    $category = isset($data['category']) ? trim($data['category']) : 'Store Operations';

    if (empty($desc) || $amount <= 0) {
      echo json_encode(['status' => 'error', 'message' => 'Expense description and valid amount are required.']);
      exit;
    }

    try {
      $stmt = $pdo->prepare("INSERT INTO `expenses` (`timestamp`, `description`, `amount`, `category`) VALUES (NOW(), :desc, :amount, :cat)");
      $stmt->execute([
        'desc' => $desc,
        'amount' => $amount,
        'cat' => $category
      ]);

      logAudit($pdo, 'expense_add', "Added expense: {$desc} (Category: {$category}, Amount: ₱" . number_format($amount, 2) . ")");
      echo json_encode(['status' => 'success', 'message' => 'Expense entry recorded successfully.']);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 14. GET SECURITY AUDIT TRAILS (OWNER)
  case 'get_audit_trail':
    checkAuth('owner');
    try {
      $stmt = $pdo->query("SELECT * FROM `audit_trail` ORDER BY `timestamp` DESC LIMIT 100");
      echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 15. GET CASHIER EMPLOYEES CRUD (OWNER)
  case 'get_cashiers':
    checkAuth('owner');
    try {
      $stmt = $pdo->prepare("SELECT `id`, `name`, `username`, `status`, `created_at` FROM `users` WHERE `role` = 'cashier' ORDER BY `created_at` DESC");
      $stmt->execute();
      echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 16. OWNER: ADD CASHIER ACCOUNT
  case 'add_cashier':
    checkAuth('owner');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if (empty($name) || empty($username) || empty($password)) {
      echo json_encode(['status' => 'error', 'message' => 'All employee fields are required.']);
      exit;
    }

    try {
      // Check duplicate username
      $check = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :username");
      $check->execute(['username' => $username]);
      if ($check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username is already taken by another user.']);
        exit;
      }

      $id = 'u-cashier-' . uniqid();
      $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `status`) VALUES (:id, :name, :username, :pass, 'cashier', 'active')");
      $stmt->execute([
        'id' => $id,
        'name' => $name,
        'username' => $username,
        'pass' => password_hash($password, PASSWORD_DEFAULT)
      ]);

      logAudit($pdo, 'employee_create', "Registered new cashier account: {$name} (username: {$username})");
      echo json_encode(['status' => 'success', 'message' => "Cashier account for '{$name}' created successfully."]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 17. OWNER: EDIT CASHIER
  case 'edit_cashier':
    checkAuth('owner');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['status' => 'error', 'message' => 'POST request required.']);
      exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? trim($data['id']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    $status = isset($data['status']) ? trim($data['status']) : 'active';

    if (empty($id) || empty($name) || empty($username)) {
      echo json_encode(['status' => 'error', 'message' => 'ID, Name, and Username are required.']);
      exit;
    }

    try {
      // Check duplicate username
      $check = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :username AND `id` != :id");
      $check->execute(['username' => $username, 'id' => $id]);
      if ($check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username is already taken by another user.']);
        exit;
      }

      if (!empty($password)) {
        $stmt = $pdo->prepare("UPDATE `users` SET `name` = :name, `username` = :username, `password` = :pass, `status` = :status WHERE `id` = :id");
        $stmt->execute([
          'name' => $name,
          'username' => $username,
          'pass' => password_hash($password, PASSWORD_DEFAULT),
          'status' => $status,
          'id' => $id
        ]);
      } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `name` = :name, `username` = :username, `status` = :status WHERE `id` = :id");
        $stmt->execute([
          'name' => $name,
          'username' => $username,
          'status' => $status,
          'id' => $id
        ]);
      }

      logAudit($pdo, 'employee_edit', "Modified cashier credentials for: {$name} (status: {$status})");
      echo json_encode(['status' => 'success', 'message' => 'Employee account details updated successfully.']);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 18. OWNER: DELETE CASHIER
  case 'delete_cashier':
    checkAuth('owner');
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
      echo json_encode(['status' => 'error', 'message' => 'Employee ID required.']);
      exit;
    }

    try {
      $stmt = $pdo->prepare("SELECT `name`, `username` FROM `users` WHERE `id` = :id");
      $stmt->execute(['id' => $id]);
      $user = $stmt->fetch();

      if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Cashier not found.']);
        exit;
      }

      $delStmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = :id");
      $delStmt->execute(['id' => $id]);

      logAudit($pdo, 'employee_delete', "Deleted cashier account: {$user['name']} (username: {$user['username']})");
      echo json_encode(['status' => 'success', 'message' => "Employee account '{$user['name']}' deleted."]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => 'Cannot delete user: ' . $e->getMessage()]);
    }
    break;

  // 19. OWNER: FETCH SHIFTS ATTENDANCE RECORD LIST
  case 'get_all_shifts':
    checkAuth('owner');
    try {
      $stmt = $pdo->query("SELECT * FROM `shifts` ORDER BY `open_time` DESC");
      $shifts = $stmt->fetchAll();
      foreach ($shifts as &$sh) {
        $sh['opening_cash'] = (float)$sh['opening_cash'];
        $sh['closing_cash'] = (float)$sh['closing_cash'];
        $sh['actual_cash'] = (float)$sh['actual_cash'];
        $sh['sales_total'] = (float)$sh['sales_total'];
      }
      echo json_encode(['status' => 'success', 'data' => $shifts]);
    } catch (PDOException $e) {
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  // 20. OWNER: COMPLETE SYSTEM DATA PURGE & RESET HISTORY
  case 'purge_history':
    checkAuth('owner');
    try {
      $pdo->beginTransaction();

      $pdo->exec("DELETE FROM `transaction_items`");
      $pdo->exec("DELETE FROM `transactions`");
      $pdo->exec("DELETE FROM `shifts`");
      $pdo->exec("DELETE FROM `expenses`");
      $pdo->exec("DELETE FROM `audit_trail` WHERE `action` != 'login'");

      // Restore base stock quantities
      $pdo->exec("UPDATE `products` SET `stock` = 120 WHERE `id` = 'prod-lanyard'");
      $pdo->exec("UPDATE `products` SET `stock` = 60 WHERE `id` = 'prod-mug'");
      $pdo->exec("UPDATE `products` SET `stock` = 25 WHERE `id` = 'prod-umbrella'");
      $pdo->exec("UPDATE `products` SET `stock` = 45 WHERE `id` = 'prod-tshirt'");
      $pdo->exec("UPDATE `products` SET `stock` = 15 WHERE `id` = 'prod-jacket'");

      $pdo->commit();

      logAudit($pdo, 'database_purge', "Owner executed full historical transactions reset and stock inventory levels restoration.");
      echo json_encode(['status' => 'success', 'message' => 'All order histories and logs cleared successfully. Stocks restored to original counts.']);
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    break;

  default:
    echo json_encode(['status' => 'error', 'message' => 'Action path not recognized.']);
    break;
}
?>
