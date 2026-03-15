<?php
// api/tourist/payment.php

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
$payment_method = isset($data['payment_method']) ? sanitizeInput($data['payment_method']) : 'Credit Card';

if ($booking_id <= 0) {
    sendJsonResponse(false, 'Invalid Booking ID.', [], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Verify booking exists and belongs to the user
    $stmt = $pdo->prepare("SELECT total_amount, payment_status FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("Booking not found or unauthorized.");
    }

    if ($booking['payment_status'] === 'Completed') {
        throw new Exception("This booking has already been paid for.");
    }

    // 2. Verify amount matches (passed from frontend, or re-fetched)
    $amount_from_frontend = isset($data['amount']) ? (float)$data['amount'] : 0;
    $amount_from_db = (float)$booking['total_amount'];

    if ($amount_from_frontend !== $amount_from_db) {
        throw new Exception("Price mismatch detected. Please try booking again.");
    }
    
    // 3. Simulate Payment Processing (e.g., contacting Stripe/PayPal API)
    // This is where you'd integrate with a real payment gateway.
    $transaction_id = 'TXN-' . strtoupper(bin2hex(random_bytes(10)));
    
    // 4. Record the payment in our database
    $stmtPay = $pdo->prepare("INSERT INTO payments (booking_id, user_id, amount, payment_method, payment_status, transaction_id) VALUES (?, ?, ?, ?, 'Success', ?)");
    $stmtPay->execute([$booking_id, $user_id, $amount_from_db, $payment_method, $transaction_id]);

    // 5. Update the booking status to 'Confirmed'
    $stmtUpdate = $pdo->prepare("UPDATE bookings SET payment_status = 'Completed', booking_status = 'Confirmed' WHERE id = ?");
    $stmtUpdate->execute([$booking_id]);

    // 6. Send email confirmation (placeholder)
    // In a real application, you would use a library like PHPMailer or an email service API.
    // error_log("Sending confirmation email for booking #$booking_id to user #$user_id with transaction #$transaction_id");

    $pdo->commit();

    // Trigger Itinerary Sync
    syncBookingToItinerary($pdo, $booking_id);

    sendJsonResponse(true, 'Payment successful!', [
        'transaction_id' => $transaction_id,
        'amount' => $amount_from_db,
        'booking_id' => $booking_id
    ], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Payment Error: ' . $e->getMessage());
    sendJsonResponse(false, $e->getMessage(), [], 400);
}
?>
