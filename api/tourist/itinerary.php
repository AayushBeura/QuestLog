<?php
// api/tourist/itinerary.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle php://input for POST/PUT/DELETE
$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

if ($method === 'GET') {
    // Determine if we are fetching all itineraries or a specific one with its items
    $itinerary_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    try {
        if ($itinerary_id > 0) {
            // Fetch specific itinerary
            $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id = ? AND user_id = ?");
            $stmt->execute([$itinerary_id, $user_id]);
            $itinerary = $stmt->fetch();

            if (!$itinerary) {
                sendJsonResponse(false, 'Itinerary not found.', [], 404);
            }

            // Fetch attached items
            $stmtItems = $pdo->prepare("SELECT * FROM itinerary_items WHERE itinerary_id = ?");
            $stmtItems->execute([$itinerary_id]);
            $itinerary['items'] = $stmtItems->fetchAll();

            sendJsonResponse(true, 'Itinerary fetched successfully.', $itinerary, 200);

        } else {
            // Fetch all itineraries for user
            $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $itineraries = $stmt->fetchAll();

            sendJsonResponse(true, 'Itineraries fetched successfully.', $itineraries, 200);
        }

    } catch (\PDOException $e) {
        error_log('Itinerary Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching itineraries.', [], 500);
    }

} elseif ($method === 'POST') {
    // Check if creating an overarching itinerary or adding an item to one
    $action = isset($data['action']) ? sanitizeInput($data['action']) : 'create_itinerary';

    try {
        if ($action === 'create_itinerary') {
            $trip_name = isset($data['trip_name']) ? sanitizeInput($data['trip_name']) : '';
            $destination = isset($data['destination']) ? sanitizeInput($data['destination']) : '';
            $start_date = isset($data['start_date']) ? sanitizeInput($data['start_date']) : '';
            $end_date = isset($data['end_date']) ? sanitizeInput($data['end_date']) : '';

            if (empty($trip_name) || empty($destination) || empty($start_date) || empty($end_date)) {
                sendJsonResponse(false, 'Please provide all required itinerary details.', [], 400);
            }

            $sql = "INSERT INTO itineraries (user_id, trip_name, destination, start_date, end_date) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $trip_name, $destination, $start_date, $end_date]);

            sendJsonResponse(true, 'Itinerary created successfully.', ['id' => $pdo->lastInsertId()], 201);

        } elseif ($action === 'add_item') {
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;
            $item_type = isset($data['item_type']) ? sanitizeInput($data['item_type']) : '';
            $item_id = isset($data['item_id']) && $data['item_id'] ? (int)$data['item_id'] : null;
            $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : '';
            $cost = isset($data['cost']) ? (float)$data['cost'] : 0.00;

            if ($itinerary_id <= 0 || !in_array($item_type, ['Transport', 'Hotel', 'Activity'])) {
                sendJsonResponse(false, 'Invalid item parameters.', [], 400);
            }

            // Verify itinerary belongs to user
            $stmtVerify = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND user_id = ?");
            $stmtVerify->execute([$itinerary_id, $user_id]);
            if (!$stmtVerify->fetch()) {
                sendJsonResponse(false, 'Unauthorized or Itinerary not found.', [], 403);
            }

            $pdo->beginTransaction();

            // Add Item
            $sql = "INSERT INTO itinerary_items (itinerary_id, item_type, item_id, notes, cost) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itinerary_id, $item_type, $item_id, $notes, $cost]);

            // Update Total Cost of Itinerary
            $sqlUpdateCost = "UPDATE itineraries SET total_cost = total_cost + ? WHERE id = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdateCost);
            $stmtUpdate->execute([$cost, $itinerary_id]);

            $pdo->commit();

            sendJsonResponse(true, 'Item added to itinerary successfully.', [], 201);

        } else {
            sendJsonResponse(false, 'Invalid action.', [], 400);
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Itinerary Post Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred processing the itinerary request.', [], 500);
    }
} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
