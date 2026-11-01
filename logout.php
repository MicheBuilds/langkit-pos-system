<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
  try {
    // Record to Audit Trail
    $auditStmt = $pdo->prepare("INSERT INTO `audit_trail` (`user_id`, `username`, `action`, `details`) VALUES (:uid, :uname, 'logout', :details)");
    $auditStmt->execute([
      'uid' => $_SESSION['user_id'],
      'uname' => $_SESSION['user_username'],
      'details' => "User logged out successfully from " . $_SESSION['user_role'] . " portal"
    ]);
  } catch (PDOException $e) {
    // Fail silently in audit logger on logout to ensure session destroys cleanly
  }
}

// Clear all sessions
$_SESSION = array();
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}
session_destroy();

header('Location: login.php');
exit;
?>
