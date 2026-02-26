<?php
// api/tourist/search.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';

handleCors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}

// Ensure type is provided (hotel or transport)
$type = isset($_GET['type']) ? strtolower(sanitizeInput($_GET['type'])) : '';

if ($type === 'hotel') {
    // Search Hotels
    $location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
    // Optional filters could be added here (price range, etc.)

    try {
        // Base query
        $sql = "SELECT id, name, location, description, price_per_night, image_url, amenities, rooms_available FROM hotels WHERE status = 'Active'";
        $params = [];

        if (!empty($location)) {
            $sql .= " AND location LIKE ?";
            $params[] = "%$location%";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hotels = $stmt->fetchAll();

        // Decode JSON amenities for frontend
        foreach ($hotels as &$hotel) {
            if ($hotel['amenities']) {
                $hotel['amenities'] = json_decode($hotel['amenities'], true);
            }
        }

        sendJsonResponse(true, 'Hotels fetched successfully.', $hotels, 200);

    } catch (\PDOException $e) {
        error_log('Hotel Search Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching hotels.', [], 500);
    }

} elseif (in_array($type, ['flight', 'train', 'bus'])) {
    // Search Transports
    $source = isset($_GET['source']) ? sanitizeInput($_GET['source']) : '';
    $destination = isset($_GET['destination']) ? sanitizeInput($_GET['destination']) : '';
    $date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';

    try {
        $sql = "SELECT id, type, source, destination, departure_date, departure_time, price, available_seats FROM transports WHERE status = 'Active' AND type = ?";
        $params = [ucfirst($type)];

        if (!empty($source)) {
            $sql .= " AND source LIKE ?";
            $params[] = "%$source%";
        }
        if (!empty($destination)) {
            $sql .= " AND destination LIKE ?";
            $params[] = "%$destination%";
        }
        if (!empty($date)) {
            $sql .= " AND departure_date = ?";
            $params[] = $date;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transports = $stmt->fetchAll();

        sendJsonResponse(true, ucfirst($type) . 's fetched successfully.', $transports, 200);

    } catch (\PDOException $e) {
        error_log('Transport Search Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching transports.', [], 500);
    }

} else {
    sendJsonResponse(false, 'Invalid search type. Use hotel, flight, train, or bus.', [], 400);
}
?>
