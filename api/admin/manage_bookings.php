<?php
// api/admin/manage_bookings.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

if ($method === 'GET') {
    // Fetch all bookings with user details
    try {
        $sql = "
            SELECT b.id AS booking_id, b.type, b.booking_date, b.guests_count, b.total_amount, b.payment_status, b.booking_status, 
                   u.name AS user_name, u.email AS user_email,
                   CASE 
                       WHEN b.type = 'Hotel' THEN (SELECT name FROM hotels WHERE id = b.entity_id)
                       WHEN b.type = 'Transport' THEN (SELECT CONCAT(source, ' to ', destination) FROM transports WHERE id = b.entity_id)
                   END AS entity_details
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            ORDER BY b.booking_date DESC
        ";
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll();

        sendJsonResponse(true, 'Bookings fetched successfully.', $bookings, 200);

    } catch (\PDOException $e) {
        error_log('Admin Bookings Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching bookings.', [], 500);
    }

} elseif ($method === 'PUT') {
    // Update booking status (e.g., Cancel, Complete)
    $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
    $status = isset($data['status']) ? sanitizeInput($data['status']) : '';
    
    if ($booking_id <= 0 || !in_array($status, ['Confirmed', 'Cancelled', 'Completed'])) {
        sendJsonResponse(false, 'Invalid booking update parameters.', [], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmtVerify = $pdo->prepare("SELECT type, entity_id, guests_count, booking_status FROM bookings WHERE id = ? FOR UPDATE");
        $stmtVerify->execute([$booking_id]);
        $booking = $stmtVerify->fetch();

        if (!$booking) {
            throw new Exception("Booking not found.");
        }

        if ($booking['booking_status'] === $status) {
             throw new Exception("Booking is already marked as $status.");
        }

        // Logic to restore inventory if cancelled
        if ($status === 'Cancelled' && $booking['booking_status'] !== 'Cancelled') {
            if ($booking['type'] === 'Hotel') {
                 // Simplistic room restore logic
                 $pdo->prepare("UPDATE hotels SET rooms_available = rooms_available + 1 WHERE id = ?")->execute([$booking['entity_id']]);
            } elseif ($booking['type'] === 'Transport') {
                 $pdo->prepare("UPDATE transports SET available_seats = available_seats + ? WHERE id = ?")->execute([$booking['guests_count'], $booking['entity_id']]);
            }
        }

        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
        $stmt->execute([$status, $booking_id]);
        
        $pdo->commit();
        sendJsonResponse(true, 'Booking status updated to '.$status.'.', [], 200);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Admin Bookings Update Error: ' . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), [], 400);
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
