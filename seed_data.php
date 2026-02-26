<?php
require_once 'config/db.php';

$hotels = [
    ['Sunset Resort', 'Bali', 'Luxury beachfront villa.', 250.00, 10],
    ['The Grand Palace', 'Paris', 'Classical elegance in the heart of Paris.', 450.00, 5],
    ['Skyline Suites', 'New York', 'Modern stays with city views.', 300.00, 15],
    ['Heritage Inn', 'Mumbai', 'Experience royal hospitality.', 120.00, 20]
];

$transports = [
    ['Flight', 'New York', 'Los Angeles', '2026-03-15', '10:00:00', 350.00, 150],
    ['Flight', 'London', 'Paris', '2026-03-15', '14:00:00', 120.00, 80],
    ['Train', 'Mumbai', 'Delhi', '2026-03-16', '08:00:00', 50.00, 200]
];

try {
    foreach ($hotels as $h) {
        $stmt = $pdo->prepare("INSERT INTO hotels (name, location, description, price_per_night, rooms_available) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($h);
    }
    foreach ($transports as $t) {
        $stmt = $pdo->prepare("INSERT INTO transports (type, source, destination, departure_date, departure_time, price, total_seats, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[6]]);
    }
    echo "Sample data seeded successfully!";
} catch (Exception $e) {
    echo "Error seeding data: " . $e->getMessage();
}
?>
