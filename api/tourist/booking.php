<?php
// api/tourist/booking.php

require_once '../../config/db.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

handleCors();
requireLogin();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return all bookings for the logged-in user
    try {
        // Fetch Hotel Bookings
        $sqlHotels = "
            SELECT b.id AS booking_id, b.type, b.booking_date, b.start_date, b.end_date, b.guests_count, b.total_amount, b.booking_status, 
                   h.name AS entity_name, h.location AS entity_details
            FROM bookings b
            JOIN hotels h ON b.entity_id = h.id
            WHERE b.user_id = ? AND b.type = 'Hotel'
            ORDER BY b.booking_date DESC
        ";
        $stmtHotels = $pdo->prepare($sqlHotels);
        $stmtHotels->execute([$user_id]);
        $hotelBookings = $stmtHotels->fetchAll();

        // Fetch Transport Bookings
        $sqlTransports = "
            SELECT b.id AS booking_id, b.type, b.booking_date, b.guests_count, b.total_amount, b.booking_status, 
                   t.type AS transport_mode, CONCAT(t.source, ' to ', t.destination) AS entity_name, t.departure_date AS start_date, t.departure_time AS entity_details
            FROM bookings b
            JOIN transports t ON b.entity_id = t.id
            WHERE b.user_id = ? AND b.type = 'Transport'
            ORDER BY b.booking_date DESC
        ";
        $stmtTrans = $pdo->prepare($sqlTransports);
        $stmtTrans->execute([$user_id]);
        $transBookings = $stmtTrans->fetchAll();

        // Combine and sort
        $allBookings = array_merge($hotelBookings, $transBookings);
        
        // Sort all bookings descending by booking date simply
        usort($allBookings, function($a, $b) {
            return strtotime($b['booking_date']) - strtotime($a['booking_date']);
        });




       //MY PART

        // [ADDED] Fetch passengers for each booking and attach them
        $stmtPassengers = $pdo->prepare("SELECT id, passenger_name, passenger_age, passenger_gender, id_type, id_number, seat_number, passenger_status FROM booking_passengers WHERE booking_id = ?");
        foreach ($allBookings as &$booking) {
            $stmtPassengers->execute([$booking['booking_id']]);
            $booking['passengers'] = $stmtPassengers->fetchAll();
        }
        unset($booking); // break reference




        sendJsonResponse(true, 'Bookings fetched successfully.', [
            'all' => $allBookings,
            'hotels' => $hotelBookings,
            'transports' => $transBookings
        ], 200);

    } catch (\PDOException $e) {
        error_log('Booking Fetch Error: ' . $e->getMessage());
        sendJsonResponse(false, 'An error occurred fetching bookings.', [], 500);
    }

} elseif ($method === 'POST') {
    // Create a new booking
    $content = trim(file_get_contents('php://input'));
    $data = json_decode($content, true) ?: [];

    $type = isset($data['type']) ? sanitizeInput($data['type']) : '';
    $entity_id = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
    $guests_count = isset($data['guests_count']) ? (int)$data['guests_count'] : 1;
    $start_date = isset($data['start_date']) ? sanitizeInput($data['start_date']) : null;
    $end_date = isset($data['end_date']) ? sanitizeInput($data['end_date']) : null;
   
   
    // [ADDED] Accept passengers array from request body(MY PART)
    $passengers = isset($data['passengers']) && is_array($data['passengers']) ? $data['passengers'] : [];

    if (!in_array($type, ['Hotel', 'Transport']) || $entity_id <= 0) {
        sendJsonResponse(false, 'Invalid booking parameters.', [], 400);
    }

    // [ADDED] Validate passengers array matches guests_count
    if (count($passengers) > 0 && count($passengers) !== $guests_count) {
        sendJsonResponse(false, 'Number of passengers must match guests count.', [], 400);
    }
    // [ADDED] Validate each passenger has at least a name
    foreach ($passengers as $p) {
        if (!isset($p['passenger_name']) || trim($p['passenger_name']) === '') {
            sendJsonResponse(false, 'Each passenger must have a name.', [], 400);
        }
    }

    try {
        $pdo->beginTransaction();

        // Check for existing duplicate booking to prevent double booking by the same user
        $dupCheckSql = "SELECT id FROM bookings WHERE user_id = ? AND type = ? AND entity_id = ? AND booking_status != 'Cancelled'";
        $dupParams = [$user_id, $type, $entity_id];

        if ($type === 'Hotel') {
            $dupCheckSql .= " AND start_date = ? AND end_date = ?";
            $dupParams[] = $start_date;
            $dupParams[] = $end_date;
        }

        $stmtDup = $pdo->prepare($dupCheckSql);
        $stmtDup->execute($dupParams);
        if ($stmtDup->fetch()) {
            if (!isset($data['force_duplicate']) || $data['force_duplicate'] !== true) {
                throw new Exception("DUPLICATE_BOOKING");
            }
        }

        $total_amount = 0;
        $tax_rate = 0.12; // 12% Tax
        $service_fee = 5.00; // Flat service fee

        if ($type === 'Hotel') {
            // Verify hotel exists and calculate amount
            if (!$start_date || !$end_date) {
                throw new Exception("Start and End dates are required for hotel bookings.");
            }

            $stmt = $pdo->prepare("SELECT price_per_night, rooms_available FROM hotels WHERE id = ? FOR UPDATE");
            $stmt->execute([$entity_id]);
            $hotel = $stmt->fetch();

            if (!$hotel || $hotel['rooms_available'] < 1) {
                throw new Exception("Hotel not found or no rooms available.");
            }

            // Calculate days
            $datetime1 = new DateTime($start_date);
            $datetime2 = new DateTime($end_date);
            $interval = $datetime1->diff($datetime2);
            $days = $interval->days > 0 ? $interval->days : 1;

            $subtotal = $hotel['price_per_night'] * $days * $guests_count;
            $tax = $subtotal * $tax_rate;
            $cgst = $tax / 2;
            $sgst = $tax / 2;
            $total_amount = $subtotal + $tax + $service_fee;

            // Update availability
            $pdo->prepare("UPDATE hotels SET rooms_available = rooms_available - 1 WHERE id = ?")->execute([$entity_id]);

        } else { // Transport
            $stmt = $pdo->prepare("SELECT price, available_seats FROM transports WHERE id = ? FOR UPDATE");
            $stmt->execute([$entity_id]);
            $transport = $stmt->fetch();

            if (!$transport || $transport['available_seats'] < $guests_count) {
                throw new Exception("Transport not found or insufficient seats available.");
            }

            $subtotal = $transport['price'] * $guests_count;
            $tax = $subtotal * $tax_rate;
            $cgst = $tax / 2;
            $sgst = $tax / 2;
            $total_amount = $subtotal + $tax + $service_fee;

            // Update availability
            $pdo->prepare("UPDATE transports SET available_seats = available_seats - ? WHERE id = ?")->execute([$guests_count, $entity_id]);
        }

        // Insert booking record with 'Pending Payment' status
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, type, entity_id, start_date, end_date, guests_count, total_amount, payment_status, booking_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending Payment')");
        $stmt->execute([$user_id, $type, $entity_id, $start_date, $end_date, $guests_count, $total_amount]);
        $booking_id = $pdo->lastInsertId();

        // --- Auto-assign seats for Transport bookings ---
        // Finds the first block of N consecutive available seats.
        // Cancelled seats are treated as available, so they get reused.
        $seatsPerRow = 5; // A1-A5, B1-B5, C1-C5, ...
        $assignedSeats = [];

        if ($type === 'Transport') {
            // Get all OCCUPIED seat numbers (only confirmed passengers)
            $stmtOccupied = $pdo->prepare(
                "SELECT bp.seat_number FROM booking_passengers bp
                 JOIN bookings b ON bp.booking_id = b.id
                 WHERE b.entity_id = ? AND b.type = 'Transport'
                   AND bp.passenger_status != 'Cancelled'
                   AND bp.seat_number IS NOT NULL"
            );
            $stmtOccupied->execute([$entity_id]);
            $occupiedSeats = array_flip($stmtOccupied->fetchAll(PDO::FETCH_COLUMN));

            $neededSeats = count($passengers);

            // Helper: convert index to seat label
            $seatLabel = function($idx) use ($seatsPerRow) {
                $row = chr(65 + intdiv($idx, $seatsPerRow));
                $col = ($idx % $seatsPerRow) + 1;
                return $row . $col;
            };

            // Scan from seat index 0 to find N consecutive available seats
            $maxScan = 200; // safety limit
            $startIdx = 0;
            while ($startIdx < $maxScan) {
                $block = [];
                $allFree = true;
                for ($j = 0; $j < $neededSeats; $j++) {
                    $label = $seatLabel($startIdx + $j);
                    if (isset($occupiedSeats[$label])) {
                        $allFree = false;
                        $startIdx = $startIdx + $j + 1; // skip past the occupied seat
                        break;
                    }
                    $block[] = $label;
                }
                if ($allFree) {
                    $assignedSeats = $block;
                    break;
                }
            }
        }

        // [ADDED] Insert each passenger into booking_passengers table(MY PART)
        if (count($passengers) > 0) {
            $stmtPass = $pdo->prepare("INSERT INTO booking_passengers (booking_id, passenger_name, passenger_age, passenger_gender, id_type, id_number, seat_number, passenger_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed')");
            foreach ($passengers as $i => $p) {
                $seatNumber = isset($assignedSeats[$i]) ? $assignedSeats[$i] : null;
                $stmtPass->execute([
                    $booking_id,
                    sanitizeInput($p['passenger_name']),
                    isset($p['passenger_age']) ? (int)$p['passenger_age'] : null,
                    isset($p['passenger_gender']) ? sanitizeInput($p['passenger_gender']) : null,
                    isset($p['id_type']) ? sanitizeInput($p['id_type']) : null,
                    isset($p['id_number']) ? sanitizeInput($p['id_number']) : null,
                    $seatNumber
                ]);
            }
        }

        $pdo->commit();

        sendJsonResponse(true, 'Booking initialized. Please proceed to payment.', [
            'booking_id' => $booking_id, 
            'subtotal' => round($subtotal, 2),
            'cgst' => round($cgst, 2),
            'sgst' => round($sgst, 2),
            'service_fee' => $service_fee,
            'total_amount' => round($total_amount, 2),
            'assigned_seats' => $assignedSeats
        ], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Booking Create Error: ' . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), [], 400); // Bad Request normally if logic failed
    }

} else {
    sendJsonResponse(false, 'Method Not Allowed', [], 405);
}
?>
