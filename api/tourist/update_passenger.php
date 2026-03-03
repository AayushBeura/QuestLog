<?php
// api/tourist/update_passenger.php
// Allows the logged-in user to update editable fields of their own passengers

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}

$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

$passenger_id = isset($data['passenger_id']) ? (int)$data['passenger_id'] : 0;

if ($passenger_id <= 0) {
    sendJsonResponse(false, 'Invalid passenger ID.', [], 400);
}

try {
    // Verify ownership: passenger must belong to a booking owned by this user
    $stmt = $pdo->prepare(
        "SELECT bp.id, bp.passenger_status FROM booking_passengers bp
         JOIN bookings b ON bp.booking_id = b.id
         WHERE bp.id = ? AND b.user_id = ?"
    );
    $stmt->execute([$passenger_id, $user_id]);
    $passenger = $stmt->fetch();

    if (!$passenger) {
        sendJsonResponse(false, 'Passenger not found or unauthorized.', [], 404);
    }

    if ($passenger['passenger_status'] === 'Cancelled') {
        sendJsonResponse(false, 'Cannot edit a cancelled passenger.', [], 400);
    }

    // Build dynamic update for allowed fields only
    $allowedFields = ['passenger_name', 'passenger_age', 'passenger_gender', 'id_type', 'id_number'];
    $updates = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            if ($field === 'passenger_age') {
                $params[] = (int)$data[$field];
            } else {
                $params[] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($updates)) {
        sendJsonResponse(false, 'No fields to update.', [], 400);
    }

    $params[] = $passenger_id;
    $sql = "UPDATE booking_passengers SET " . implode(', ', $updates) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);

    sendJsonResponse(true, 'Passenger details updated successfully.', [], 200);

} catch (\PDOException $e) {
    error_log('Update Passenger Error: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred updating passenger details.', [], 500);
}
?>
