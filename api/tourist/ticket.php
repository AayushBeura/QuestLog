<?php
// api/tourist/ticket.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    sendJsonResponse(false, 'Invalid Booking ID.', [], 400);
}

try {
    // Fetch booking details
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendJsonResponse(false, 'Booking not found.', [], 404);
    }

    $details = [];

    if ($booking['type'] === 'Hotel') {
        $stmtEntity = $pdo->prepare("SELECT name, location, image_url FROM hotels WHERE id = ?");
        $stmtEntity->execute([$booking['entity_id']]);
        $details = $stmtEntity->fetch();
    } else {
        $stmtEntity = $pdo->prepare("SELECT type, source, destination, departure_date, departure_time FROM transports WHERE id = ?");
        $stmtEntity->execute([$booking['entity_id']]);
        $details = $stmtEntity->fetch();
    }

    // Fetch payment details
    $stmtPay = $pdo->prepare("SELECT transaction_id, payment_method, created_at FROM payments WHERE booking_id = ?");
    $stmtPay->execute([$booking_id]);
    $payment = $stmtPay->fetch();




    //MY PART
    // [ADDED] Fetch passengers for this booking from booking_passengers table
    $stmtPassengers = $pdo->prepare("SELECT id, passenger_name, passenger_age, passenger_gender, id_type, id_number, seat_number, passenger_status FROM booking_passengers WHERE booking_id = ?");
    $stmtPassengers->execute([$booking_id]);
    $passengers = $stmtPassengers->fetchAll();

    // Recalculate price breakdown for display consistency
    $tax_rate = 0.12;
    $service_fee = 5.00;
    $total_from_db = (float)$booking['total_amount'];
    
    // Back-calculate subtotal from the total
    $subtotal = ($total_from_db - $service_fee) / (1 + $tax_rate);
    $cgst = ($subtotal * $tax_rate) / 2;
    $sgst = $cgst;

    $price_breakdown = [
        'subtotal' => round($subtotal, 2),
        'cgst' => round($cgst, 2),
        'sgst' => round($sgst, 2),
        'service_fee' => round($service_fee, 2),
        'total_amount' => $total_from_db
    ];

    // [MODIFIED] Added passengers array to the response
    sendJsonResponse(true, 'Ticket details fetched.', [
        'booking' => $booking,
        'entity' => $details,
        'payment' => $payment,
        'passengers' => $passengers,      //my part
        'price_breakdown' => $price_breakdown
    ], 200);

} catch (\PDOException $e) {
    error_log('Ticket Fetch Error: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred fetching ticket details.', [], 500);
}
?>
