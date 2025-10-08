<?php
/**
 * Users API Helper
 * api/users.php
 */

require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

// Get all active users (for dropdowns, etc.)
$query = "SELECT id, name, role, email, phone, branch_id FROM users WHERE status = 'active' ORDER BY name ASC";
$result = $conn->query($query);

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

respond(true, 'Users retrieved', ['users' => $users]);
?>
