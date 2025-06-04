<?php
// Correct path based on your file structure
require_once __DIR__ . '/../config.php';  // This is correct since auth_check.php is in includes folder

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['error'] = 'Please log in to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Optional: Check if user account is still active
try {
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['status'] !== 'active') {
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?error=account_inactive');
        exit();
    }
} catch(PDOException $e) {
    error_log("Error checking user status: " . $e->getMessage());
    // Continue with the request even if status check fails
}
?>