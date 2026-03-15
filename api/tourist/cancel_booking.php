<?php
// api/tourist/cancel_booking.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';
require_once '../../includes/itinerary_utils.php';

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
// [ADDED] Optional passenger_id for per-passenger cancellation
$passenger_id = isset($data['passenger_id']) ? (int)$data['passenger_id'] : 0;

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

    // [ADDED] Per-passenger cancellation logic
    if ($passenger_id > 0) {
        // Cancel a single passenger instead of the entire booking
        $stmtPass = $pdo->prepare("SELECT id, passenger_status FROM booking_passengers WHERE id = ? AND booking_id = ?");
        $stmtPass->execute([$passenger_id, $booking_id]);
        $passenger = $stmtPass->fetch();

        if (!$passenger) {
            throw new Exception("Passenger not found in this booking.");
        }
        if ($passenger['passenger_status'] === 'Cancelled') {
            throw new Exception("This passenger is already cancelled.");
        }

        // Update passenger status to Cancelled
        $pdo->prepare("UPDATE booking_passengers SET passenger_status = 'Cancelled' WHERE id = ?")->execute([$passenger_id]);

        // Restore 1 seat for transport bookings
        if ($booking['type'] === 'Transport') {
            $pdo->prepare("UPDATE transports SET available_seats = available_seats + 1 WHERE id = ?")->execute([$booking['entity_id']]);
        }

        // Update guests_count on the booking
        $pdo->prepare("UPDATE bookings SET guests_count = guests_count - 1 WHERE id = ?")->execute([$booking_id]);

        // Check if ALL passengers are now cancelled — if so, cancel the whole booking
        $stmtRemaining = $pdo->prepare("SELECT COUNT(*) as active FROM booking_passengers WHERE booking_id = ? AND passenger_status = 'Confirmed'");
        $stmtRemaining->execute([$booking_id]);
        $remaining = $stmtRemaining->fetch();

        if ((int)$remaining['active'] === 0) {
            // All passengers cancelled — cancel entire booking
            if ($booking['type'] === 'Hotel') {
                $pdo->prepare("UPDATE hotels SET rooms_available = rooms_available + 1 WHERE id = ?")->execute([$booking['entity_id']]);
            }
            $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded' WHERE id = ?")->execute([$booking_id]);
            $pdo->commit();

            // Sync to Itinerary
            cancelBookingInItinerary($pdo, $booking_id);

            sendJsonResponse(true, 'All passengers cancelled. Entire booking has been cancelled.', ['full_cancel' => true], 200);
        }

        $pdo->commit();
        sendJsonResponse(true, 'Passenger cancelled successfully. 1 seat restored.', ['full_cancel' => false], 200);

    } else {
        // Original full booking cancellation logic

        // 3. Restore inventory
        if ($booking['type'] === 'Hotel') {
            $pdo->prepare("UPDATE hotels SET rooms_available = rooms_available + 1 WHERE id = ?")->execute([$booking['entity_id']]);
        } else { // Transport
            $pdo->prepare("UPDATE transports SET available_seats = available_seats + ? WHERE id = ?")->execute([$booking['guests_count'], $booking['entity_id']]);
        }

        // 4. Update booking status
        $stmtUpdate = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded' WHERE id = ?");
        $stmtUpdate->execute([$booking_id]);

        // [ADDED] Also mark all passengers as Cancelled
        $pdo->prepare("UPDATE booking_passengers SET passenger_status = 'Cancelled' WHERE booking_id = ?")->execute([$booking_id]);

        // In a real system, you'd also process a refund through the payment gateway here.
        // For now, we'll just mark payment_status as 'Refunded'.

        $pdo->commit();

        // Sync to Itinerary
        cancelBookingInItinerary($pdo, $booking_id);

        sendJsonResponse(true, 'Booking cancelled successfully. Inventory restored.', ['full_cancel' => true], 200);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Cancel Booking Error: ' . $e->getMessage());
    sendJsonResponse(false, $e->getMessage(), [], 400);
}
?>
