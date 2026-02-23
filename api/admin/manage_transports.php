<?php
// api/admin/manage_transports.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$content = trim(file_get_contents('php://input'));
$data = json_decode($content, true) ?: [];

if ($method === 'GET') {
    // Fetch all transports
    try {
        $stmt = $pdo->query("SELECT * FROM transports");
        $transports = $stmt->fetchAll();
        sendJsonResponse(true, 'Transports fetched successfully.', $transports, 200);

    } catch (\PDOException $e) {
        error_log('Admin Transports Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching transports.', [], 500);
    }

} elseif ($method === 'POST') {
    // Add new transport
    $type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : (isset($data['type']) ? sanitizeInput($data['type']) : '');
    $source = isset($_POST['source']) ? sanitizeInput($_POST['source']) : (isset($data['source']) ? sanitizeInput($data['source']) : '');
    $destination = isset($_POST['destination']) ? sanitizeInput($_POST['destination']) : (isset($data['destination']) ? sanitizeInput($data['destination']) : '');
    $date = isset($_POST['departure_date']) ? sanitizeInput($_POST['departure_date']) : (isset($data['departure_date']) ? sanitizeInput($data['departure_date']) : '');
    $time = isset($_POST['departure_time']) ? sanitizeInput($_POST['departure_time']) : (isset($data['departure_time']) ? sanitizeInput($data['departure_time']) : '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : (isset($data['price']) ? (float)$data['price'] : 0);
    $total_seats = isset($_POST['total_seats']) ? (int)$_POST['total_seats'] : (isset($data['total_seats']) ? (int)$data['total_seats'] : 0);

    if (!in_array($type, ['Flight', 'Train', 'Bus']) || empty($source) || empty($destination) || $price <= 0 || $total_seats <= 0) {
        sendJsonResponse(false, 'Please provide valid transport details.', [], 400);
    }

    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        sendJsonResponse(false, 'Cannot add transport in the past.', [], 400);
    }

    try {
        $sql = "INSERT INTO transports (type, source, destination, departure_date, departure_time, price, total_seats, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $source, $destination, $date, $time, $price, $total_seats, $total_seats]);
        
        sendJsonResponse(true, 'Transport added successfully.', ['id' => $pdo->lastInsertId()], 201);
    } catch (\PDOException $e) {
        error_log('Admin Transports Add Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred adding the transport.', [], 500);
    }

} elseif ($method === 'PUT') {
    // Update existing transport
    $transport_id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if ($transport_id <= 0) {
        sendJsonResponse(false, 'Invalid Transport ID.', [], 400);
    }

    $updateFields = [];
    $params = [];

    $allowedFields = ['type', 'source', 'destination', 'departure_date', 'departure_time', 'price', 'total_seats', 'available_seats', 'status'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = sanitizeInput($data[$field]);
        }
    }

    if (empty($updateFields)) {
        sendJsonResponse(false, 'No valid fields provided to update.', [], 400);
    }

    $params[] = $transport_id;

    try {
        $sql = "UPDATE transports SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendJsonResponse(true, 'Transport updated successfully.', [], 200);

    } catch (\PDOException $e) {
        error_log('Admin Transports Update Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred updating the transport.', [], 500);
    }

} elseif ($method === 'DELETE') {
    // Delete a transport
    $transport_id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($transport_id <= 0) {
        sendJsonResponse(false, 'Invalid Transport ID.', [], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM transports WHERE id = ?");
        $stmt->execute([$transport_id]);
        sendJsonResponse(true, 'Transport deleted successfully.', [], 200);
    } catch (\PDOException $e) {
        error_log('Admin Transports Delete Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred deleting the transport.', [], 500);
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
