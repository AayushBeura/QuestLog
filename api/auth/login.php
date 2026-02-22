<?php
// api/auth/login.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php'; // ensure session is started

// Allow from any origin (ensure this is restricted in production)
handleCors();

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}

// Support both JSON and standard form submissions
$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';

if (strpos($contentType, 'application/json') !== false) {
    $content = trim(file_get_contents('php://input'));
    $decoded = json_decode($content, true);
    $data = is_array($decoded) ? $decoded : [];
} else {
    $data = $_POST;
}

$email = isset($data['email']) ? filter_var(sanitizeInput($data['email']), FILTER_SANITIZE_EMAIL) : '';
$password = isset($data['password']) ? $data['password'] : '';

// Validation
if (empty($email) || empty($password)) {
    sendJsonResponse(false, 'Please provide both email and password.', [], 400);
}

try {
    // Fetch user from DB
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        
        // Check if user is blocked
        if ($user['status'] === 'Blocked') {
            sendJsonResponse(false, 'Your account has been blocked. Please contact support.', [], 403);
        }

        // Authentication successful, regenerate session id for security
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        // Don't send the password hash back
        unset($user['password_hash']);

        // Check redirect route based on role
        if ($user['role'] === 'Admin') {
            $redirect = 'admin-dashboard.html'; // Assuming this exists or will exist
        } else {
            $redirect = 'tourist-dashboard.html'; // Assuming this exists or will exist
        }

        sendJsonResponse(true, 'Login successful.', [
            'user' => $user,
            'redirect' => $redirect
        ], 200);

    } else {
        // Invalid credentials
        sendJsonResponse(false, 'Invalid email or password.', [], 401);
    }
} catch (\PDOException $e) {
    error_log('Login Error: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred during login. Please try again.', [], 500);
}
?>
