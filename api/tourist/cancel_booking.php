<?php
// api/tourist/cancel_booking.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}

$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

$booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;

if ($booking_id <= 0) {
    sendJsonResponse(false, 'Invalid Booking ID.', [], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Fetch booking details and check ownership
    $stmt = $pdo->prepare("SELECT type, entity_id, guests_count, booking_status FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("Booking not found or unauthorized.");
    }

    // 2. Check if booking is eligible for cancellation
    if (in_array($booking['booking_status'], ['Cancelled', 'Completed'])) {
        throw new Exception("Booking cannot be cancelled as it is already " . $booking['booking_status'] . ".");
    }

    // 3. Restore inventory
    if ($booking['type'] === 'Hotel') {
        $pdo->prepare("UPDATE hotels SET rooms_available = rooms_available + 1 WHERE id = ?")->execute([$booking['entity_id']]);
    } else { // Transport
        $pdo->prepare("UPDATE transports SET available_seats = available_seats + ? WHERE id = ?")->execute([$booking['guests_count'], $booking['entity_id']]);
    }

    // 4. Update booking status
    $stmtUpdate = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded' WHERE id = ?");
    $stmtUpdate->execute([$booking_id]);

    // In a real system, you'd also process a refund through the payment gateway here.
    // For now, we'll just mark payment_status as 'Refunded'.

    $pdo->commit();

    sendJsonResponse(true, 'Booking cancelled successfully. Inventory restored.', [], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Cancel Booking Error: ' . $e->getMessage());
    sendJsonResponse(false, $e->getMessage(), [], 400);
}
?>
