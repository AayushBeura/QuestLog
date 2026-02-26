<?php
// api/tourist/availability.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';

handleCors();

$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : null;

if ($id <= 0 || !in_array($type, ['hotel', 'transport'])) {
    sendJsonResponse(false, 'Invalid parameters.', [], 400);
}

try {
    $availability = null;
    if ($type === 'hotel') {
        // Use rooms_available directly, matching booking.php logic
        $stmtTotal = $pdo->prepare("SELECT rooms_available FROM hotels WHERE id = ? AND status = 'Active'");
        $stmtTotal->execute([$id]);
        $availability = $stmtTotal->fetchColumn();

        if ($availability === false) {
            sendJsonResponse(false, 'Hotel not found.', [], 404);
        }

    } else { // transport
        $stmt = $pdo->prepare("SELECT available_seats FROM transports WHERE id = ?");
        $stmt->execute([$id]);
        $availability = $stmt->fetchColumn();
    }

    if ($availability !== false) {
        sendJsonResponse(true, 'Availability fetched.', ['available' => $availability], 200);
    } else {
        sendJsonResponse(false, 'Entity not found.', [], 404);
    }
} catch (PDOException $e) {
    error_log('Availability Check Error: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred.', [], 500);
}
?>
