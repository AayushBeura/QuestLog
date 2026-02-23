<?php
// api/tourist/profile.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

// Allow from any origin (ensure this is restricted in production)
handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch user profile
    try {
        $stmt = $pdo->prepare('SELECT name, email, address, country, state, mobile, role, status FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            sendJsonResponse(true, 'Profile fetched successfully.', $user, 200);
        } else {
            sendJsonResponse(false, 'User not found.', [], 404);
        }
    } catch (\PDOException $e) {
        error_log('Profile Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching profile.', [], 500);
    }
} elseif ($method === 'PUT') {
    // Update user profile
    $content = trim(file_get_contents('php://input'));
    $data = json_decode($content, true) ?: [];

    // Allow updating specific fields (excluding email, role, etc. for security)
    $name = isset($data['name']) ? sanitizeInput($data['name']) : null;
    $address = isset($data['address']) ? sanitizeInput($data['address']) : null;
    $country = isset($data['country']) ? sanitizeInput($data['country']) : null;
    $state = isset($data['state']) ? sanitizeInput($data['state']) : null;
    $mobile = isset($data['mobile']) ? sanitizeInput($data['mobile']) : null;

    $updateFields = [];
    $params = [];

    if ($name !== null) { $updateFields[] = "name = ?"; $params[] = $name; }
    if ($address !== null) { $updateFields[] = "address = ?"; $params[] = $address; }
    if ($country !== null) { $updateFields[] = "country = ?"; $params[] = $country; }
    if ($state !== null) { $updateFields[] = "state = ?"; $params[] = $state; }
    if ($mobile !== null) { $updateFields[] = "mobile = ?"; $params[] = $mobile; }

    if (empty($updateFields)) {
        sendJsonResponse(false, 'No valid fields provided for update.', [], 400);
    }

    $params[] = $user_id; // Add user_id for WHERE clause

    try {
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Update session name if changed
        if ($name !== null) {
            $_SESSION['name'] = $name;
        }

        sendJsonResponse(true, 'Profile updated successfully.', [], 200);
    } catch (\PDOException $e) {
        error_log('Profile Update Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred updating profile.', [], 500);
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
