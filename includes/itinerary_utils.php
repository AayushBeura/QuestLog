<?php
// includes/itinerary_utils.php

/**
 * Automatically updates an itinerary based on a confirmed booking ONLY IF one exists.
 */
function syncBookingToItinerary($pdo, $booking_id) {
    try {
        // Fetch booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();

        if (!$booking || $booking['booking_status'] !== 'Confirmed') return;

        $user_id = $booking['user_id'];
        $type = $booking['type'];
        $entity_id = $booking['entity_id'];

        if ($type === 'Transport') {
            $stmtT = $pdo->prepare("SELECT * FROM transports WHERE id = ?");
            $stmtT->execute([$entity_id]);
            $transport = $stmtT->fetch();
            if (!$transport) return;

            $destination = $transport['destination'];
            $date = $transport['departure_date'];

            // Find existing Itinerary
            $itinerary_id = findOrCreateItinerary($pdo, $user_id, $destination, $date);

            // ONLY add if itinerary exists
            if ($itinerary_id) {
                addTransportToTimeline($pdo, $itinerary_id, $booking_id, $transport);
                updateItineraryCost($pdo, $itinerary_id);
            }

        } elseif ($type === 'Hotel') {
            $stmtH = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
            $stmtH->execute([$entity_id]);
            $hotel = $stmtH->fetch();
            if (!$hotel) return;

            $location = $hotel['location'];
            $start_date = $booking['start_date'];
            $end_date = $booking['end_date'];

            // Find existing Itinerary
            $itinerary_id = findOrCreateItinerary($pdo, $user_id, $location, $start_date, $end_date);

            // ONLY add if itinerary exists
            if ($itinerary_id) {
                addHotelToTimeline($pdo, $itinerary_id, $booking_id, $hotel, $start_date, $end_date);
                updateItineraryCost($pdo, $itinerary_id);
            }
        }

    } catch (Exception $e) {
        error_log("Itinerary Sync Error: " . $e->getMessage());
    }
}

/**
 * Finds an existing itinerary for the destination/dates.
 * Matches based on date range and if the booking destination or source 
 * relates to the itinerary's destination or source.
 */
function findOrCreateItinerary($pdo, $user_id, $location, $start_date, $end_date = null) {
    if (!$end_date) $end_date = $start_date;

    // Check for existing itinerary where the booking date falls within its range
    // And either destination or source matches the location (e.g. London, Paris, Rome)
    $stmt = $pdo->prepare("
        SELECT id FROM itineraries 
        WHERE user_id = ? 
        AND (destination LIKE ? OR source LIKE ?)
        AND ? BETWEEN start_date AND end_date
        LIMIT 1
    ");
    $stmt->execute([$user_id, "%$location%", "%$location%", $start_date]);
    $existing = $stmt->fetch();

    if ($existing) {
        return $existing['id'];
    }

    return null;
}

/**
 * Generates daily slots for an itinerary.
 */
function generateItineraryDays($pdo, $itinerary_id, $start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

    $dayNum = 1;
    foreach ($period as $date) {
        $stmt = $pdo->prepare("INSERT INTO itinerary_days (itinerary_id, day_number, date) VALUES (?, ?, ?)");
        $stmt->execute([$itinerary_id, $dayNum++, $date->format('Y-m-d')]);
    }
}

/**
 * Adds a transport booking to the timeline.
 */
function addTransportToTimeline($pdo, $itinerary_id, $booking_id, $transport) {
    $date = $transport['departure_date'];
    $time = $transport['departure_time'];

    // Find day_id
    $stmtDay = $pdo->prepare("SELECT id FROM itinerary_days WHERE itinerary_id = ? AND date = ?");
    $stmtDay->execute([$itinerary_id, $date]);
    $day = $stmtDay->fetch();
    if (!$day) return;

    $title = $transport['type'] . ": " . $transport['source'] . " to " . $transport['destination'];
    
    // Check if already exists to avoid duplicates
    $stmtCheck = $pdo->prepare("SELECT id FROM itinerary_items WHERE itinerary_id = ? AND booking_id = ? AND item_type = 'Transport'");
    $stmtCheck->execute([$itinerary_id, $booking_id]);
    if ($stmtCheck->fetch()) return;

    // Add Buffer (Airport arrival etc.) - 2 hours before
    // LINK TO booking_id so it deletes together
    $bufferTime = date('H:i:s', strtotime($time . ' - 2 hours'));
    $stmtBuffer = $pdo->prepare("INSERT INTO itinerary_items (itinerary_id, day_id, item_type, booking_id, title, start_time, notes) VALUES (?, ?, 'Buffer', ?, ?, ?, 'Recommended arrival at terminal')");
    $stmtBuffer->execute([$itinerary_id, $day['id'], $booking_id, "Arrival at " . ($transport['type'] == 'Flight' ? 'Airport' : 'Station'), $bufferTime]);

    // Add Transport
    $stmt = $pdo->prepare("INSERT INTO itinerary_items (itinerary_id, day_id, item_type, booking_id, title, start_time, cost) VALUES (?, ?, 'Transport', ?, ?, ?, ?)");
    $stmt->execute([$itinerary_id, $day['id'], $booking_id, $title, $time, $transport['price']]);
}

/**
 * Adds a hotel booking to the timeline.
 */
function addHotelToTimeline($pdo, $itinerary_id, $booking_id, $hotel, $start_date, $end_date) {
    // Check if already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM itinerary_items WHERE itinerary_id = ? AND booking_id = ?");
    $stmtCheck->execute([$itinerary_id, $booking_id]);
    if ($stmtCheck->fetch()) return;

    // Hotel Check-in on day 1 (within the itinerary)
    $stmtDay1 = $pdo->prepare("SELECT id FROM itinerary_days WHERE itinerary_id = ? AND date = ?");
    $stmtDay1->execute([$itinerary_id, $start_date]);
    $day1 = $stmtDay1->fetch();

    if ($day1) {
        $stmt = $pdo->prepare("INSERT INTO itinerary_items (itinerary_id, day_id, item_type, booking_id, title, start_time, notes, cost) VALUES (?, ?, 'Hotel', ?, ?, '14:00:00', 'Check-in at hotel', ?)");
        $stmt->execute([$itinerary_id, $day1['id'], $booking_id, "Check-in: " . $hotel['name'], 0]); 
    }

    // Hotel Check-out on last day (within the itinerary)
    $stmtDayLast = $pdo->prepare("SELECT id FROM itinerary_days WHERE itinerary_id = ? AND date = ?");
    $stmtDayLast->execute([$itinerary_id, $end_date]);
    $dayLast = $stmtDayLast->fetch();

    if ($dayLast) {
        $stmt = $pdo->prepare("INSERT INTO itinerary_items (itinerary_id, day_id, item_type, booking_id, title, start_time, notes) VALUES (?, ?, 'Hotel', ?, ?, '11:00:00', 'Check-out from hotel')");
        $stmt->execute([$itinerary_id, $dayLast['id'], $booking_id, "Check-out: " . $hotel['name']]);
    }
}

/**
 * Updates total cost by summing all items and bookings.
 */
function updateItineraryCost($pdo, $itinerary_id) {
    // Sum from itinerary_items
    $stmtItems = $pdo->prepare("SELECT SUM(cost) as total FROM itinerary_items WHERE itinerary_id = ?");
    $stmtItems->execute([$itinerary_id]);
    $cost = $stmtItems->fetch()['total'] ?: 0;

    $stmtUpdate = $pdo->prepare("UPDATE itineraries SET total_cost = ? WHERE id = ?");
    $stmtUpdate->execute([$cost, $itinerary_id]);
}

/**
 * Calculates a planning score based on components.
 */
function calculatePlanningScore($pdo, $itinerary_id) {
    $score = 0;
    
    // 1. Has Transport? (30%)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itinerary_items WHERE itinerary_id = ? AND item_type = 'Transport'");
    $stmt->execute([$itinerary_id]);
    if ($stmt->fetchColumn() > 0) $score += 30;

    // 2. Has Hotel? (30%)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itinerary_items WHERE itinerary_id = ? AND item_type = 'Hotel'");
    $stmt->execute([$itinerary_id]);
    if ($stmt->fetchColumn() > 0) $score += 30;

    // 3. Has Activities? (20%)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itinerary_items WHERE itinerary_id = ? AND item_type = 'Activity'");
    $stmt->execute([$itinerary_id]);
    if ($stmt->fetchColumn() > 0) $score += 20;

    // 4. Has Budget set? (10%)
    $stmt = $pdo->prepare("SELECT budget FROM itineraries WHERE id = ?");
    $stmt->execute([$itinerary_id]);
    if ($stmt->fetchColumn() > 0) $score += 10;

    // 5. Has Notes? (10%)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itinerary_items WHERE itinerary_id = ? AND notes IS NOT NULL AND notes != ''");
    $stmt->execute([$itinerary_id]);
    if ($stmt->fetchColumn() > 0) $score += 10;

    return $score;
}

/**
 * Detects conflicts in the timeline.
 */
function detectConflicts($pdo, $itinerary_id) {
    $conflicts = [];
    
    // Fetch all items with times
    $stmt = $pdo->prepare("SELECT * FROM itinerary_items WHERE itinerary_id = ? AND start_time IS NOT NULL ORDER BY day_id, start_time");
    $stmt->execute([$itinerary_id]);
    $items = $stmt->fetchAll();

    for ($i = 0; $i < count($items) - 1; $i++) {
        $curr = $items[$i];
        $next = $items[$i+1];

        if ($curr['day_id'] == $next['day_id']) {
            // Simple overlap check if end_time exists
            if ($curr['end_time'] && $curr['end_time'] > $next['start_time']) {
                $conflicts[] = "Conflict between '{$curr['title']}' and '{$next['title']}'.";
            }
            
            // Or if they start at the same time
            if ($curr['start_time'] == $next['start_time']) {
                $conflicts[] = "Multiple activities scheduled at {$curr['start_time']} on Day " . $curr['day_id'];
            }
        }
    }

    return $conflicts;
}

/**
 * Removes or updates itinerary items when a booking is cancelled.
 */
function cancelBookingInItinerary($pdo, $booking_id) {
    try {
        // Find itineraries affected
        $stmt = $pdo->prepare("SELECT DISTINCT itinerary_id FROM itinerary_items WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $itineraries = $stmt->fetchAll();

        // Delete items
        $stmtDel = $pdo->prepare("DELETE FROM itinerary_items WHERE booking_id = ?");
        $stmtDel->execute([$booking_id]);

        // Update costs
        foreach ($itineraries as $it) {
            updateItineraryCost($pdo, $it['itinerary_id']);
        }

    } catch (Exception $e) {
        error_log("Itinerary Cancel Sync Error: " . $e->getMessage());
    }
}

/**
 * Automatically scans a user's bookings and adds relevant ones to a specific itinerary.
 */
function autoFillItineraryFromBookings($pdo, $itinerary_id) {
    try {
        error_log("DEBUG: Starting autoFill for Itinerary ID: $itinerary_id");
        // Fetch itinerary details
        $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id = ?");
        $stmt->execute([$itinerary_id]);
        $it = $stmt->fetch();
        if (!$it) {
            error_log("DEBUG: Itinerary not found");
            return;
        }

        $user_id = $it['user_id'];
        $start = $it['start_date'];
        $end = $it['end_date'];
        error_log("DEBUG: User: $user_id, Start: $start, End: $end");

        // 1. Find ALL transport bookings that fall within the date range
        $stmtT = $pdo->prepare("
            SELECT b.id as booking_id, t.* 
            FROM bookings b
            JOIN transports t ON b.entity_id = t.id
            WHERE b.user_id = ? AND b.type = 'Transport' AND b.booking_status = 'Confirmed'
            AND t.departure_date BETWEEN ? AND ?
        ");
        $stmtT->execute([$user_id, $start, $end]);
        $transports = $stmtT->fetchAll();
        error_log("DEBUG: Found " . count($transports) . " transport bookings");

        foreach ($transports as $tr) {
            error_log("DEBUG: Adding transport booking ID: " . $tr['booking_id']);
            addTransportToTimeline($pdo, $itinerary_id, $tr['booking_id'], $tr);
        }

        // 2. Find ALL hotel bookings that overlap with the itinerary range
        $stmtH = $pdo->prepare("
            SELECT b.id as booking_id, b.start_date as check_in, b.end_date as check_out, h.* 
            FROM bookings b
            JOIN hotels h ON b.entity_id = h.id
            WHERE b.user_id = ? AND b.type = 'Hotel' AND b.booking_status = 'Confirmed'
            AND ((b.start_date BETWEEN ? AND ?) OR (b.end_date BETWEEN ? AND ?))
        ");
        $stmtH->execute([$user_id, $start, $end, $start, $end]);
        $hotels = $stmtH->fetchAll();
        error_log("DEBUG: Found " . count($hotels) . " hotel bookings");

        foreach ($hotels as $ho) {
            error_log("DEBUG: Adding hotel booking ID: " . $ho['booking_id']);
            addHotelToTimeline($pdo, $itinerary_id, $ho['booking_id'], $ho, $ho['check_in'], $ho['check_out']);
        }

        updateItineraryCost($pdo, $itinerary_id);

    } catch (Exception $e) {
        error_log("Itinerary Auto-fill Error: " . $e->getMessage());
    }
}
