<?php
// includes/utils.php

/**
 * Send a JSON response and exit the script.
 *
 * @param bool $success Indicates if the request was successful
 * @param string $message A message describing the result
 * @param array $data Any additional data to send payload back
 * @param int $statusCode HTTP status code (default 200)
 */
function sendJsonResponse($success, $message = '', $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Sanitize user input to prevent XSS and SQL injection (partially, mostly use prepared statements).
 *
 * @param string $data The input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    // Note: htmlspecialchars() should only be applied at output/display time, not before DB storage.
    // Prepared statements already prevent SQL injection.
    return $data;
}

/**
 * Handle CORS and preflight options requests.
 * Restricted to same-origin for session-based auth security.
 */
function handleCors() {
    $allowed_origin = 'http://localhost';
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // Allow localhost on any port
        if (preg_match('/^https?:\/\/localhost(:\d+)?$/', $_SERVER['HTTP_ORIGIN'])) {
            $allowed_origin = $_SERVER['HTTP_ORIGIN'];
        }
    }
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

/**
 * Generate a CSRF token and store it in the session.
 * Call this after session_start().
 *
 * @return string The CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from the X-CSRF-Token header.
 * Sends a 403 response if invalid.
 */
function validateCsrfToken() {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(false, 'Invalid or missing CSRF token.', [], 403);
    }
}
?>
