<?php
// api/tourist/itinerary.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';
require_once '../../includes/itinerary_utils.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle php://input for POST/PUT/DELETE
$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

if ($method === 'GET') {
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

            // Fetch days
            $stmtDays = $pdo->prepare("SELECT * FROM itinerary_days WHERE itinerary_id = ? ORDER BY day_number");
            $stmtDays->execute([$itinerary_id]);
            $days = $stmtDays->fetchAll();

            // Fetch items grouped by day
            foreach ($days as &$day) {
                $stmtItems = $pdo->prepare("SELECT * FROM itinerary_items WHERE day_id = ? ORDER BY start_time");
                $stmtItems->execute([$day['id']]);
                $day['items'] = $stmtItems->fetchAll();
            }
            $itinerary['days'] = $days;

            // Add Planning Score and Conflicts
            $itinerary['planning_score'] = calculatePlanningScore($pdo, $itinerary_id);
            $itinerary['conflicts'] = detectConflicts($pdo, $itinerary_id);

            sendJsonResponse(true, 'Itinerary fetched successfully.', $itinerary, 200);

        } else {
            // Fetch all itineraries for user
            $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $itineraries = $stmt->fetchAll();

            // Add basic stats for each card
            foreach ($itineraries as &$it) {
                $it['planning_score'] = calculatePlanningScore($pdo, $it['id']);
            }

            sendJsonResponse(true, 'Itineraries fetched successfully.', $itineraries, 200);
        }

    } catch (\PDOException $e) {
        error_log('Itinerary Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching itineraries.', [], 500);
    }

} elseif ($method === 'POST') {
    $action = isset($data['action']) ? sanitizeInput($data['action']) : 'create_itinerary';

    try {
        if ($action === 'create_itinerary') {
            $trip_name = isset($data['trip_name']) ? sanitizeInput($data['trip_name']) : '';
            $source = isset($data['source']) ? sanitizeInput($data['source']) : '';
            $destination = isset($data['destination']) ? sanitizeInput($data['destination']) : '';
            $start_date = isset($data['start_date']) ? sanitizeInput($data['start_date']) : '';
            $end_date = isset($data['end_date']) ? sanitizeInput($data['end_date']) : '';
            $budget = isset($data['budget']) ? (float)$data['budget'] : 0.00;

            if (empty($trip_name) || empty($destination) || empty($start_date) || empty($end_date)) {
                sendJsonResponse(false, 'Please provide all required itinerary details.', [], 400);
            }

            $pdo->beginTransaction();

            $sql = "INSERT INTO itineraries (user_id, trip_name, source, destination, start_date, end_date, budget) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $trip_name, $source, $destination, $start_date, $end_date, $budget]);
            $itinerary_id = $pdo->lastInsertId();

            // Auto-generate days
            generateItineraryDays($pdo, $itinerary_id, $start_date, $end_date);

            // AUTO-FILL: Scan existing bookings and add them
            autoFillItineraryFromBookings($pdo, $itinerary_id);

            $pdo->commit();

            sendJsonResponse(true, 'Itinerary created successfully.', ['id' => $itinerary_id], 201);

        } elseif ($action === 'add_item') {
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;
            $day_id = isset($data['day_id']) ? (int)$data['day_id'] : 0;
            $item_type = isset($data['item_type']) ? sanitizeInput($data['item_type']) : 'Activity';
            $title = isset($data['title']) ? sanitizeInput($data['title']) : '';
            $start_time = isset($data['start_time']) ? sanitizeInput($data['start_time']) : null;
            $end_time = isset($data['end_time']) ? sanitizeInput($data['end_time']) : null;
            $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : '';
            $cost = isset($data['cost']) ? (float)$data['cost'] : 0.00;

            if ($itinerary_id <= 0 || $day_id <= 0 || empty($title)) {
                sendJsonResponse(false, 'Invalid item parameters.', [], 400);
            }

            // Verify ownership
            $stmtVerify = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND user_id = ?");
            $stmtVerify->execute([$itinerary_id, $user_id]);
            if (!$stmtVerify->fetch()) {
                sendJsonResponse(false, 'Unauthorized or Itinerary not found.', [], 403);
            }

            $sql = "INSERT INTO itinerary_items (itinerary_id, day_id, item_type, title, start_time, end_time, notes, cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itinerary_id, $day_id, $item_type, $title, $start_time, $end_time, $notes, $cost]);

            updateItineraryCost($pdo, $itinerary_id);

            sendJsonResponse(true, 'Item added successfully.', [], 201);

        } elseif ($action === 'delete_item') {
            $item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;

            if ($item_id <= 0 || $itinerary_id <= 0) {
                sendJsonResponse(false, 'Invalid item or itinerary ID.', [], 400);
            }

            // Verify ownership of the itinerary this item belongs to
            $stmtVerify = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND user_id = ?");
            $stmtVerify->execute([$itinerary_id, $user_id]);
            if (!$stmtVerify->fetch()) {
                sendJsonResponse(false, 'Unauthorized.', [], 403);
            }

            $stmt = $pdo->prepare("DELETE FROM itinerary_items WHERE id = ? AND itinerary_id = ?");
            $stmt->execute([$item_id, $itinerary_id]);

            updateItineraryCost($pdo, $itinerary_id);

            sendJsonResponse(true, 'Item removed successfully.', [], 200);

        } elseif ($action === 'delete_itinerary') {
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;

            if ($itinerary_id <= 0) {
                sendJsonResponse(false, 'Invalid Itinerary ID.', [], 400);
            }

            // Verify ownership
            $stmtVerify = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND user_id = ?");
            $stmtVerify->execute([$itinerary_id, $user_id]);
            if (!$stmtVerify->fetch()) {
                sendJsonResponse(false, 'Unauthorized or Itinerary not found.', [], 403);
            }

            $stmt = $pdo->prepare("DELETE FROM itineraries WHERE id = ?");
            $stmt->execute([$itinerary_id]);

            sendJsonResponse(true, 'Itinerary deleted successfully.', [], 200);

        } elseif ($action === 'sync_bookings') {
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;
            if ($itinerary_id <= 0) {
                sendJsonResponse(false, 'Invalid Itinerary ID.', [], 400);
            }

            // Verify ownership
            $stmtVerify = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND user_id = ?");
            $stmtVerify->execute([$itinerary_id, $user_id]);
            if (!$stmtVerify->fetch()) {
                sendJsonResponse(false, 'Unauthorized or Itinerary not found.', [], 403);
            }

            autoFillItineraryFromBookings($pdo, $itinerary_id);
            sendJsonResponse(true, 'Bookings synced successfully.', [], 200);

        } elseif ($action === 'get_suggestions') {
            $itinerary_id = isset($data['itinerary_id']) ? (int)$data['itinerary_id'] : 0;
            
            $stmtIt = $pdo->prepare("SELECT destination, source FROM itineraries WHERE id = ?");
            $stmtIt->execute([$itinerary_id]);
            $it = $stmtIt->fetch();
            
            if (!$it) {
                sendJsonResponse(false, 'Itinerary not found.', [], 404);
            }

            $loc = $it['destination'];
            
            // DYNAMIC AI GENERATOR (No Database Needed)
            // This logic simulates an LLM by generating context-aware activities
            $ai_suggestions = [
                ['activity_name' => "Visit the main City Center in $loc", 'price' => 0.00],
                ['activity_name' => "Guided Walking Tour of $loc", 'price' => 25.00],
                ['activity_name' => "Local Cuisine Tasting Experience", 'price' => 45.00],
                ['activity_name' => "Sunset Photography Session", 'price' => 10.00],
                ['activity_name' => "Visit the $loc Historical Museum", 'price' => 15.00],
                ['activity_name' => "Traditional Souvenir Shopping", 'price' => 30.00],
                ['activity_name' => "Fine Dining Experience in $loc", 'price' => 80.00]
            ];

            // Specific suggestions for known major hubs to make it look "Smarter"
            if (stripos($loc, 'Paris') !== false) {
                array_unshift($ai_suggestions, ['activity_name' => "Eiffel Tower Summit Access", 'price' => 35.00]);
                array_unshift($ai_suggestions, ['activity_name' => "Louvre Museum VIP Entry", 'price' => 22.00]);
            } elseif (stripos($loc, 'London') !== false) {
                array_unshift($ai_suggestions, ['activity_name' => "London Eye Ride", 'price' => 40.00]);
                array_unshift($ai_suggestions, ['activity_name' => "Warner Bros Studio Tour", 'price' => 55.00]);
            } elseif (stripos($loc, 'Rome') !== false) {
                array_unshift($ai_suggestions, ['activity_name' => "Colosseum Guided Tour", 'price' => 50.00]);
                array_unshift($ai_suggestions, ['activity_name' => "Vatican Museum Entry", 'price' => 30.00]);
            }

            sendJsonResponse(true, 'AI Suggestions generated.', $ai_suggestions, 200);

        } else {
            sendJsonResponse(false, 'Invalid action.', [], 400);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Itinerary Post Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred processing the itinerary request.', [], 500);
    }
} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
