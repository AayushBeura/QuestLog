<?php
require_once 'config/db.php';
try {
    $hotels = $pdo->query("SELECT COUNT(*) FROM hotels")->fetchColumn();
    $transports = $pdo->query("SELECT COUNT(*) FROM transports")->fetchColumn();
    echo "Hotels in DB: $hotels
";
    echo "Transports in DB: $transports
";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
