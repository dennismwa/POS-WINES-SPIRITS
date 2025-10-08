<?php
// ========== LOGOUT.PHP ==========
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Update session logout time
    $conn->query("UPDATE sessions SET logout_time = NOW() WHERE user_id = $userId AND logout_time IS NULL");
    
    // Log activity
    logActivity('LOGOUT', 'User logged out');
    
    // Destroy session
    session_unset();
    session_destroy();
}

header('Location: /index.php');
exit;
?>