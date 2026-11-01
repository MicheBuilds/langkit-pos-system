<?php
session_start();
require_once 'db.php';

$error = '';

// If already logged in, redirect
if (isset($_SESSION['user_role'])) {
  if ($_SESSION['user_role'] === 'owner') {
    header('Location: owner.php');
    exit;
  } else {
    header('Location: cashier.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  
  if (empty($username) || empty($password)) {
    $error = 'Please fill in all fields.';
  } else {
    try {
      $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = :username AND `status` = 'active'");
      $stmt->execute(['username' => $username]);
      $user = $stmt->fetch();
      
      if ($user && password_verify($password, $user['password'])) {
        // Log in successfully
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        
        // Log to Audit Trail
        $auditStmt = $pdo->prepare("INSERT INTO `audit_trail` (`user_id`, `username`, `action`, `details`) VALUES (:uid, :uname, 'login', :details)");
        $auditStmt->execute([
          'uid' => $user['id'],
          'uname' => $user['username'],
          'details' => "User logged in successfully as {$user['role']}"
        ]);
        
        // Redirect
        if ($user['role'] === 'owner') {
          header('Location: owner.php');
        } else {
          header('Location: cashier.php');
        }
        exit;
      } else {
        // Failed Login log to audits if user exists
        if ($user) {
          $auditStmt = $pdo->prepare("INSERT INTO `audit_trail` (`user_id`, `username`, `action`, `details`) VALUES (:uid, :uname, 'failed_login', :details)");
          $auditStmt->execute([
            'uid' => $user['id'],
            'uname' => $username,
            'details' => "Failed login attempt (Incorrect Password) for username: {$username}"
          ]);
        }
        $error = 'Invalid username or password.';
      }
    } catch (PDOException $e) {
      $error = 'Database error: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Langkit University Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;900&family=Cinzel:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --primary-red: #8B0000;
      --accent-gold: #D4AF37;
      --bg-primary: #0F0F11;
      --bg-secondary: #16161B;
      --bg-border: #2D2D38;
      --text-primary: #FFFFFF;
      --text-secondary: #A0A0B0;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }
    
    /* Decorative Background Gradients */
    body::before {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(139, 0, 0, 0.3) 0%, transparent 70%);
      top: -100px;
      left: -100px;
      z-index: 0;
    }
    
    body::after {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
      bottom: -150px;
      right: -150px;
      z-index: 0;
    }
    
    .login-container {
      width: 100%;
      max-width: 440px;
      padding: 24px;
      z-index: 10;
    }
    
    .login-card {
      background-color: rgba(22, 22, 27, 0.85);
      border: 1px solid var(--bg-border);
      border-radius: 16px;
      padding: 40px 32px;
      backdrop-filter: blur(12px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5), 0 0 25px rgba(212, 175, 55, 0.05);
      text-align: center;
    }
    
    .brand-section {
      margin-bottom: 30px;
    }
    
    .brand-logo-wrapper {
      width: 80px;
      height: 80px;
      margin: 0 auto 16px auto;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .logo-img {
      max-width: 100%;
      max-height: 100%;
    }
    
    .brand-title {
      font-family: 'Cinzel', serif;
      font-size: 2.1rem;
      color: var(--accent-gold);
      letter-spacing: 1px;
      line-height: 1.1;
    }
    
    .brand-subtitle {
      font-size: 0.85rem;
      color: var(--text-secondary);
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    
    .form-group {
      margin-bottom: 22px;
      text-align: left;
    }
    
    .form-group label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    .input-wrapper i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-secondary);
      font-size: 1.05rem;
      transition: color 0.2s;
    }
    
    .form-control {
      width: 100%;
      padding: 14px 16px 14px 46px;
      background-color: rgba(15, 15, 17, 0.6);
      border: 1px solid var(--bg-border);
      border-radius: 10px;
      color: var(--text-primary);
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    
    .form-control:focus {
      border-color: var(--accent-gold);
      box-shadow: 0 0 10px rgba(212, 175, 55, 0.15);
    }
    
    .form-control:focus + i {
      color: var(--accent-gold);
    }
    
    .login-btn {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--primary-red) 0%, #a80000 100%);
      border: 1px solid rgba(212, 175, 55, 0.3);
      border-radius: 10px;
      color: #FFFFFF;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 10px;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(139, 0, 0, 0.4), 0 0 15px rgba(212, 175, 55, 0.15);
      border-color: var(--accent-gold);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .error-alert {
      background-color: rgba(211, 47, 47, 0.15);
      border: 1px solid #D32F2F;
      border-radius: 8px;
      color: #FF8A8A;
      font-size: 0.85rem;
      padding: 12px;
      margin-bottom: 22px;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .error-alert i {
      font-size: 1.1rem;
    }
    
    .footer-note {
      font-size: 0.75rem;
      color: var(--text-secondary);
      margin-top: 25px;
      border-top: 1px solid rgba(45, 45, 56, 0.4);
      padding-top: 20px;
    }
    
    .footer-note strong {
      color: var(--accent-gold);
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div class="login-card">
      
      <!-- Brand Logo Header -->
      <div class="brand-section">
        <div class="brand-logo-wrapper">
          <!-- Dynamically loads local vector SVG logo -->
          <img src="logo.svg" alt="Langkit Logo" class="logo-img">
        </div>
        <h2 class="brand-title">Langkit</h2>
        <p class="brand-subtitle">University Shop POS</p>
      </div>
      
      <!-- Error Banner -->
      <?php if (!empty($error)): ?>
        <div class="error-alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>
      
      <!-- Authentication Credentials Form -->
      <form action="login.php" method="POST">
        <div class="form-group">
          <label for="username">User Account Username</label>
          <div class="input-wrapper">
            <input type="text" name="username" id="username" class="form-control" placeholder="e.g. cashier / owner" required autocomplete="off">
            <i class="fa-solid fa-user"></i>
          </div>
        </div>
        
        <div class="form-group">
          <label for="password">Account Password</label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            <i class="fa-solid fa-lock"></i>
          </div>
        </div>
        
        <button type="submit" class="login-btn">
          <span>Authorize Portal</span>
          <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </button>
      </form>
      
      <div class="footer-note">
        Official Store Manager Credentials Required.<br>
        Powered by <strong>Langkit Database 1.0</strong>
      </div>
      
    </div>
  </div>

</body>
</html>
