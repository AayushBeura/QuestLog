<?php
// api/admin/users.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all users (with optional search)
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    
    try {
        $sql = "SELECT id, name, email, role, status, created_at FROM users";
        $params = [];

        if (!empty($search)) {
            $sql .= " WHERE name LIKE ? OR email LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        sendJsonResponse(true, 'Users fetched successfully.', $users, 200);

    } catch (\PDOException $e) {
        error_log('Admin Users Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching users.', [], 500);
    }

} elseif ($method === 'PUT') {
    // Update user status (Block/Unblock) or Role
    $content = trim(file_get_contents('php://input'));
    $data = json_decode($content, true) ?: [];

    $target_user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $status = isset($data['status']) ? sanitizeInput($data['status']) : null;
    $role = isset($data['role']) ? sanitizeInput($data['role']) : null;

    if ($target_user_id <= 0) {
        sendJsonResponse(false, 'Invalid User ID.', [], 400);
    }

    if ($target_user_id === (int)$_SESSION['user_id']) {
        sendJsonResponse(false, 'You cannot modify your own role or status.', [], 403);
    }

    $updateFields = [];
    $params = [];

    if ($status !== null && in_array($status, ['Active', 'Blocked'])) {
        $updateFields[] = "status = ?";
        $params[] = $status;
    }
    
    if ($role !== null && in_array($role, ['Tourist', 'Admin'])) {
        $updateFields[] = "role = ?";
        $params[] = $role;
    }

    if (empty($updateFields)) {
        sendJsonResponse(false, 'No valid fields to update.', [], 400);
    }

    $params[] = $target_user_id;

    try {
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendJsonResponse(true, 'User updated successfully.', [], 200);

    } catch (\PDOException $e) {
        error_log('Admin Users Update Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred updating the user.', [], 500);
    }

} elseif ($method === 'DELETE') {
    // Delete user
    $content = trim(file_get_contents('php://input'));
    $data = json_decode($content, true) ?: [];
    
    // Also allow DELETE request with query params fallback
    $target_user_id = isset($data['user_id']) ? (int)$data['user_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);

    if ($target_user_id <= 0) {
        sendJsonResponse(false, 'Invalid User ID.', [], 400);
    }

    if ($target_user_id === (int)$_SESSION['user_id']) {
        sendJsonResponse(false, 'You cannot delete yourself.', [], 403);
    }

    try {
        // Cascading deletes handled by foreign keys in schema where applicable
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);

        sendJsonResponse(true, 'User deleted successfully.', [], 200);

    } catch (\PDOException $e) {
        error_log('Admin Users Delete Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred deleting the user.', [], 500);
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
