<?php
// api/admin/manage_hotels.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

if ($method === 'GET') {
    // Fetch all hotels
    try {
        $stmt = $pdo->query("SELECT * FROM hotels");
        $hotels = $stmt->fetchAll();

        foreach ($hotels as &$hotel) {
            if ($hotel['amenities']) {
                $hotel['amenities'] = json_decode($hotel['amenities'], true);
            }
        }

        sendJsonResponse(true, 'Hotels fetched successfully.', $hotels, 200);

    } catch (\PDOException $e) {
        error_log('Admin Hotels Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching hotels.', [], 500);
    }

} elseif ($method === 'POST') {
    // Add new hotel
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : (isset($data['name']) ? sanitizeInput($data['name']) : '');
    $location = isset($_POST['location']) ? sanitizeInput($_POST['location']) : (isset($data['location']) ? sanitizeInput($data['location']) : '');
    $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : (isset($data['description']) ? sanitizeInput($data['description']) : '');
    $price = isset($_POST['price_per_night']) ? (float)$_POST['price_per_night'] : (isset($data['price_per_night']) ? (float)$data['price_per_night'] : 0);
    $rooms = isset($_POST['rooms_available']) ? (int)$_POST['rooms_available'] : (isset($data['rooms_available']) ? (int)$data['rooms_available'] : 0);
    
    // Simple amenities handling (expects array or JSON string)
    $amenities = isset($_POST['amenities']) ? $_POST['amenities'] : (isset($data['amenities']) ? $data['amenities'] : '[]');
    if (is_array($amenities)) {
        $amenities = json_encode($amenities);
    }

    if (empty($name) || empty($location) || $price <= 0) {
        sendJsonResponse(false, 'Please provide valid name, location, and price.', [], 400);
    }

    try {
        $sql = "INSERT INTO hotels (name, location, description, price_per_night, amenities, rooms_available) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $location, $description, $price, $amenities, $rooms]);
        
        sendJsonResponse(true, 'Hotel added successfully.', ['id' => $pdo->lastInsertId()], 201);
    } catch (\PDOException $e) {
        error_log('Admin Hotels Add Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred adding the hotel.', [], 500);
    }

} elseif ($method === 'PUT') {
    // Update existing hotel
    $hotel_id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if ($hotel_id <= 0) {
        sendJsonResponse(false, 'Invalid Hotel ID.', [], 400);
    }

    // Build dynamic update query
    $updateFields = [];
    $params = [];

    $allowedTextFields = ['name', 'location', 'description', 'status'];
    $allowedNumericFields = ['price_per_night', 'rooms_available'];

    foreach ($allowedTextFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = sanitizeInput($data[$field]);
        }
    }

    foreach ($allowedNumericFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = is_numeric($data[$field]) ? $data[$field] + 0 : 0;
        }
    }

    if (isset($data['amenities'])) {
        $updateFields[] = "amenities = ?";
        $params[] = is_array($data['amenities']) ? json_encode($data['amenities']) : $data['amenities'];
    }

    if (empty($updateFields)) {
        sendJsonResponse(false, 'No valid fields provided to update.', [], 400);
    }

    $params[] = $hotel_id;

    try {
        $sql = "UPDATE hotels SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendJsonResponse(true, 'Hotel updated successfully.', [], 200);

    } catch (\PDOException $e) {
        error_log('Admin Hotels Update Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred updating the hotel.', [], 500);
    }

} elseif ($method === 'DELETE') {
    // Delete a hotel
    $hotel_id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($hotel_id <= 0) {
        sendJsonResponse(false, 'Invalid Hotel ID.', [], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ?");
        $stmt->execute([$hotel_id]);
        sendJsonResponse(true, 'Hotel deleted successfully.', [], 200);
    } catch (\PDOException $e) {
        error_log('Admin Hotels Delete Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred deleting the hotel.', [], 500);
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
