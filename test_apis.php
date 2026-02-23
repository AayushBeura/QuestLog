<?php
// test_apis.php
// Run this script via command line: php test_apis.php

$baseUrl = "http://localhost/QuestLog/api"; // Adjust depending on XAMPP folder setup, we will test this locally though.

echo "Starting API Tests...\n";
echo "Note: These tests assume your XAMPP Apache is running and serving this folder at /QuestLog.\n";
echo "If the server is running on a different port or folder, adjustments may be needed.\n\n";

// Function to make cURL requests
function makeRequest($url, $method = 'GET', $data = null, $cookies = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    // For handling session cookies during tests
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    curl_setopt($ch, CURLOPT_HEADER, 1); 

    if ($data !== null) {
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    // Extract cookies to maintain session
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
    $new_cookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $new_cookies = array_merge($new_cookies, $cookie);
    }
    
    $cookie_str = '';
    foreach($new_cookies as $key => $val) {
        $cookie_str .= "$key=$val; ";
    }

    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($body, true),
        'cookies' => rtrim($cookie_str, '; ')
    ];
}

// 1. Test Signup (Should fail if already exists, else 201)
echo "1. Testing Tourist Signup...\n";
$signupData = [
    'name' => 'Test Tourist',
    'email' => 'tourist@test.com',
    'password' => 'password123',
    'country' => 'US',
    'mobile' => '1234567890'
];
$res = makeRequest("$baseUrl/auth/signup.php", 'POST', $signupData);
echo "   Status: " . $res['code'] . " - " . ($res['body']['message'] ?? 'No message') . "\n";

// 2. Test Login (Tourist)
echo "\n2. Testing Tourist Login...\n";
$loginData = ['email' => 'tourist@test.com', 'password' => 'password123'];
$resLogin = makeRequest("$baseUrl/auth/login.php", 'POST', $loginData);
$touristCookies = $resLogin['cookies'];
echo "   Status: " . $resLogin['code'] . " - " . ($resLogin['body']['message'] ?? 'No message') . "\n";

// 3. Test Profile Fetch (Requires Login)
echo "\n3. Testing Tourist Profile Fetch...\n";
$resProfile = makeRequest("$baseUrl/tourist/profile.php", 'GET', null, $touristCookies);
echo "   Status: " . $resProfile['code'] . " - " . ($resProfile['body']['message'] ?? 'No message') . "\n";

// 4. Test Search Hotels
echo "\n4. Testing Hotels Search...\n";
$resSearch = makeRequest("$baseUrl/tourist/search.php?type=hotel", 'GET', null, $touristCookies);
echo "   Status: " . $resSearch['code'] . " - Found " . (isset($resSearch['body']['data']) ? count($resSearch['body']['data']) : 0) . " hotels.\n";

// 5. Test Admin Login
echo "\n5. Testing Admin Login...\n";
$adminLoginData = ['email' => 'admin@questlog.com', 'password' => 'password'];
$resAdmin = makeRequest("$baseUrl/auth/login.php", 'POST', $adminLoginData);
$adminCookies = $resAdmin['cookies'];
echo "   Status: " . $resAdmin['code'] . " - " . ($resAdmin['body']['message'] ?? 'No message') . "\n";

if ($resAdmin['code'] === 200) {
    // 6. Test Admin Add Hotel
    echo "\n6. Testing Admin Add Hotel...\n";
    $hotelData = [
        'name' => 'Grand Test Hotel',
        'location' => 'Bali',
        'description' => 'A beautiful testing resort.',
        'price_per_night' => 150.00,
        'rooms_available' => 5,
        'amenities' => ['Pool', 'WiFi']
    ];
    $resAddHotel = makeRequest("$baseUrl/admin/manage_hotels.php", 'POST', $hotelData, $adminCookies);
    echo "   Status: " . $resAddHotel['code'] . " - " . ($resAddHotel['body']['message'] ?? 'No message') . "\n";
    
    // 7. Testing Admin Add Transport
    echo "\n7. Testing Admin Add Transport...\n";
    $transportData = [
        'type' => 'Flight',
        'source' => 'NYC',
        'destination' => 'LAX',
        'departure_date' => '2026-10-10',
        'departure_time' => '10:00:00',
        'price' => 299.99,
        'total_seats' => 150
    ];
    $resAddTrans = makeRequest("$baseUrl/admin/manage_transports.php", 'POST', $transportData, $adminCookies);
    echo "   Status: " . $resAddTrans['code'] . " - " . ($resAddTrans['body']['message'] ?? 'No message') . "\n";

    // 8. Test Tourist Booking (Now that there is a hotel)
    echo "\n8. Testing Tourist Hotel Booking...\n";
    if (isset($resAddHotel['body']['data']['id'])) {
        $hotelId = $resAddHotel['body']['data']['id'];
        $bookingData = [
            'type' => 'Hotel',
            'entity_id' => $hotelId,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'guests_count' => 2
        ];
        $resBooking = makeRequest("$baseUrl/tourist/booking.php", 'POST', $bookingData, $touristCookies);
        echo "   Status: " . $resBooking['code'] . " - " . ($resBooking['body']['message'] ?? 'No message') . "\n";
    } else {
        echo "   Skipped: Hotel creation failed.\n";
    }
} else {
    echo "   Skipped Admin actions due to failed Admin login.\n";
}

echo "\nTests Completed.\n";
?>
