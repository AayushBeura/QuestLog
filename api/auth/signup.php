<?php
// api/auth/signup.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';

// Allow from any origin (ensure this is restricted in production)
handleCors();

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}

// Get the raw POST JSON data or form data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';

if (strpos($contentType, 'application/json') !== false) {
    $content = trim(file_get_contents('php://input'));
    $decoded = json_decode($content, true);
    $data = is_array($decoded) ? $decoded : [];
} else {
    // Rely on standard form submission data
    $data = $_POST;
}

// Extract and sanitize input
$name = isset($data['name']) ? sanitizeInput($data['name']) : '';
$email = isset($data['email']) ? filter_var(sanitizeInput($data['email']), FILTER_SANITIZE_EMAIL) : '';
$password = isset($data['password']) ? $data['password'] : '';
$address = isset($data['address']) ? sanitizeInput($data['address']) : '';
// The frontend can send the country name explicitly if 'Other' was selected.
$country = isset($data['countryName']) && !empty($data['countryName']) ? sanitizeInput($data['countryName']) : (isset($data['country']) ? sanitizeInput($data['country']) : '');
$state = isset($data['state']) ? sanitizeInput($data['state']) : '';
$mobile = isset($data['mobile']) ? sanitizeInput($data['mobile']) : '';

// Validation
if (empty($name) || empty($email) || empty($password) || empty($country) || empty($mobile)) {
    sendJsonResponse(false, 'Please fill in all required fields.', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Invalid email format.', [], 400);
}

if (strlen($password) < 6) {
    sendJsonResponse(false, 'Password must be at least 6 characters.', [], 400);
}

// Hash the password securely
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'Email is already registered.', [], 409);
    }

    // Insert the new user into the database
    $sql = 'INSERT INTO users (name, email, password_hash, address, country, state, mobile, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, "Tourist", "Active")';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $password_hash, $address, $country, $state, $mobile]);
    
    // Get the newly created user's ID
    $newUserId = $pdo->lastInsertId();

    sendJsonResponse(true, 'Sign up successful! Please log in.', ['user_id' => $newUserId], 201);
} catch (\PDOException $e) {
    // For debugging locally, you could output $e->getMessage()
    error_log('Signup Error: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred during registration. Please try again.', [], 500);
}
?>
