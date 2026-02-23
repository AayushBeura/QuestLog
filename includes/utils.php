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
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Handle CORS and preflight options requests.
 * Use carefully in production.
 */
function handleCors() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}
?>
