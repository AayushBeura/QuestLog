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
        // Get total rooms
        $stmtTotal = $pdo->prepare("SELECT rooms_available FROM hotels WHERE id = ?");
        $stmtTotal->execute([$id]);
        $total_rooms = $stmtTotal->fetchColumn();

        if ($total_rooms === false) {
            sendJsonResponse(false, 'Hotel not found.', [], 404);
        }
        
        // Count existing bookings that conflict with the requested dates
        // Only consider bookings that are not cancelled or pending payment.
        $stmtConflict = $pdo->prepare("SELECT COUNT(*) FROM bookings 
            WHERE entity_id = ? AND type = 'Hotel' 
            AND booking_status NOT IN ('Cancelled', 'Pending Payment')
            AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))");
        
        $stmtConflict->execute([$id, $end_date, $start_date, $end_date, $start_date]);
        $conflicting_bookings = $stmtConflict->fetchColumn();

        $availability = $total_rooms - $conflicting_bookings;

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
