<?php
// includes/auth.php

session_start();

/**
 * Check if the user is currently logged in.
 *
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an Admin.
 *
 * @return bool True if Admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

/**
 * Require the user to be logged in. Sends a 401 response if not.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        require_once 'utils.php';
        sendJsonResponse(false, 'Unauthorized. Please log in.', [], 401);
    }
}

/**
 * Require the user to be an Admin. Sends a 403 response if not.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        require_once 'utils.php';
        sendJsonResponse(false, 'Forbidden. Admin access required.', [], 403);
    }
}
?>
