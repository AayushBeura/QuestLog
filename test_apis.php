<?php
// test_apis.php
// Run this script via command line: php test_apis.php

$baseUrl = "http://localhost/QuestLog/api"; // Adjust depending on XAMPP folder setup

echo "==========================================================\n";
echo "  QuestLog API Test Suite\n";
echo "==========================================================\n";
echo "Note: These tests assume XAMPP Apache + MySQL are running\n";
echo "and serving this folder at /QuestLog.\n\n";

$passed = 0;
$failed = 0;
$total  = 0;

// ---- Helper Functions ----

function makeRequest($url, $method = 'GET', $data = null, $cookies = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
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

function assertTest($testName, $condition, $message, &$passed, &$failed, &$total) {
    $total++;
    if ($condition) {
        $passed++;
        echo "   ✅ PASS: $testName\n";
    } else {
        $failed++;
        echo "   ❌ FAIL: $testName — $message\n";
    }
}

// ============================================================
// SECTION 1: Core API Tests (Auth, Profile, Search, Admin CRUD)
// ============================================================
echo "--- Section 1: Core API Tests ---\n\n";

// 1. Signup
echo "1. Testing Tourist Signup...\n";
$signupData = [
    'name' => 'Test Tourist',
    'email' => 'tourist@test.com',
    'password' => 'password123',
    'country' => 'US',
    'countryName' => 'United States',
    'mobile' => '1234567890'
];
$res = makeRequest("$baseUrl/auth/signup.php", 'POST', $signupData);
assertTest("Signup returns 201 or 409 (already exists)",
    in_array($res['code'], [201, 409]),
    "Got HTTP {$res['code']}", $passed, $failed, $total);

// 2. Tourist Login
echo "\n2. Testing Tourist Login...\n";
$loginData = ['email' => 'tourist@test.com', 'password' => 'password123'];
$resLogin = makeRequest("$baseUrl/auth/login.php", 'POST', $loginData);
$touristCookies = $resLogin['cookies'];
assertTest("Login returns 200",
    $resLogin['code'] === 200 && ($resLogin['body']['success'] ?? false),
    "Got HTTP {$resLogin['code']}", $passed, $failed, $total);

// 3. Profile Fetch
echo "\n3. Testing Tourist Profile Fetch...\n";
$resProfile = makeRequest("$baseUrl/tourist/profile.php", 'GET', null, $touristCookies);
assertTest("Profile returns 200 with data",
    $resProfile['code'] === 200 && ($resProfile['body']['success'] ?? false),
    "Got HTTP {$resProfile['code']}", $passed, $failed, $total);

// 4. Search Hotels (no auth needed)
echo "\n4. Testing Hotels Search...\n";
$resSearch = makeRequest("$baseUrl/tourist/search.php?type=hotel", 'GET', null, $touristCookies);
assertTest("Hotel search returns 200",
    $resSearch['code'] === 200,
    "Got HTTP {$resSearch['code']}", $passed, $failed, $total);

// 5. Admin Login
echo "\n5. Testing Admin Login...\n";
$adminLoginData = ['email' => 'admin@questlog.com', 'password' => 'password'];
$resAdmin = makeRequest("$baseUrl/auth/login.php", 'POST', $adminLoginData);
$adminCookies = $resAdmin['cookies'];
assertTest("Admin login returns 200",
    $resAdmin['code'] === 200 && ($resAdmin['body']['success'] ?? false),
    "Got HTTP {$resAdmin['code']}", $passed, $failed, $total);

$hotelId = null;
$transportId = null;

if ($resAdmin['code'] === 200) {
    // 6. Admin Add Hotel
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
    $hotelId = $resAddHotel['body']['data']['id'] ?? null;
    assertTest("Hotel add returns 201",
        $resAddHotel['code'] === 201 && $hotelId !== null,
        "Got HTTP {$resAddHotel['code']}", $passed, $failed, $total);

    // 7. Admin Add Transport
    echo "\n7. Testing Admin Add Transport...\n";
    $transportData = [
        'type' => 'Flight',
        'source' => 'NYC',
        'destination' => 'LAX',
        'departure_date' => '2027-10-10',
        'departure_time' => '10:00:00',
        'price' => 299.99,
        'total_seats' => 150
    ];
    $resAddTrans = makeRequest("$baseUrl/admin/manage_transports.php", 'POST', $transportData, $adminCookies);
    $transportId = $resAddTrans['body']['data']['id'] ?? null;
    assertTest("Transport add returns 201",
        $resAddTrans['code'] === 201 && $transportId !== null,
        "Got HTTP {$resAddTrans['code']}", $passed, $failed, $total);

    // 8. Valid Tourist Hotel Booking
    echo "\n8. Testing Valid Tourist Hotel Booking...\n";
    if ($hotelId) {
        $bookingData = [
            'type' => 'Hotel',
            'entity_id' => $hotelId,
            'start_date' => '2027-12-01',
            'end_date' => '2027-12-05',
            'guests_count' => 2
        ];
        $resBooking = makeRequest("$baseUrl/tourist/booking.php", 'POST', $bookingData, $touristCookies);
        assertTest("Valid hotel booking returns 201",
            $resBooking['code'] === 201 && ($resBooking['body']['success'] ?? false),
            "Got HTTP {$resBooking['code']}: " . ($resBooking['body']['message'] ?? ''), $passed, $failed, $total);
    } else {
        echo "   ⏩ Skipped: Hotel creation failed.\n";
    }
} else {
    echo "   ⏩ Skipped Admin actions due to failed Admin login.\n";
}

// ============================================================
// SECTION 2: Bug Fix Validation Tests
// ============================================================
echo "\n\n--- Section 2: Bug Fix Validation Tests ---\n\n";

// Need to re-login as tourist since session may be overwritten by admin login
$resLogin2 = makeRequest("$baseUrl/auth/login.php", 'POST', $loginData);
$touristCookies = $resLogin2['cookies'];

// BUG FIX TEST 1: Booking with zero guests should fail
echo "9. BUG FIX: Booking with guests_count=0 should be rejected...\n";
if ($hotelId) {
    $badGuestData = [
        'type' => 'Hotel',
        'entity_id' => $hotelId,
        'start_date' => '2027-11-01',
        'end_date' => '2027-11-03',
        'guests_count' => 0
    ];
    $res = makeRequest("$baseUrl/tourist/booking.php", 'POST', $badGuestData, $touristCookies);
    assertTest("Zero guests booking returns 400",
        $res['code'] === 400 && !($res['body']['success'] ?? true),
        "Got HTTP {$res['code']}: " . ($res['body']['message'] ?? ''), $passed, $failed, $total);
} else {
    echo "   ⏩ Skipped: No hotel available.\n";
}

// BUG FIX TEST 2: Booking with negative guests should fail
echo "\n10. BUG FIX: Booking with guests_count=-1 should be rejected...\n";
if ($hotelId) {
    $negGuestData = [
        'type' => 'Hotel',
        'entity_id' => $hotelId,
        'start_date' => '2027-11-01',
        'end_date' => '2027-11-03',
        'guests_count' => -1
    ];
    $res = makeRequest("$baseUrl/tourist/booking.php", 'POST', $negGuestData, $touristCookies);
    assertTest("Negative guests booking returns 400",
        $res['code'] === 400 && !($res['body']['success'] ?? true),
        "Got HTTP {$res['code']}: " . ($res['body']['message'] ?? ''), $passed, $failed, $total);
} else {
    echo "   ⏩ Skipped: No hotel available.\n";
}

// BUG FIX TEST 3: Hotel booking with inverted dates (end < start) should fail
echo "\n11. BUG FIX: Hotel booking with inverted dates should be rejected...\n";
if ($hotelId) {
    $invertedDateData = [
        'type' => 'Hotel',
        'entity_id' => $hotelId,
        'start_date' => '2027-12-10',
        'end_date' => '2027-12-05',
        'guests_count' => 1
    ];
    $res = makeRequest("$baseUrl/tourist/booking.php", 'POST', $invertedDateData, $touristCookies);
    assertTest("Inverted dates booking returns 400",
        $res['code'] === 400 && !($res['body']['success'] ?? true),
        "Got HTTP {$res['code']}: " . ($res['body']['message'] ?? ''), $passed, $failed, $total);
} else {
    echo "   ⏩ Skipped: No hotel available.\n";
}

// BUG FIX TEST 4: Hotel booking with same start and end date should fail
echo "\n12. BUG FIX: Hotel booking with same start and end date should be rejected...\n";
if ($hotelId) {
    $sameDateData = [
        'type' => 'Hotel',
        'entity_id' => $hotelId,
        'start_date' => '2027-12-10',
        'end_date' => '2027-12-10',
        'guests_count' => 1
    ];
    $res = makeRequest("$baseUrl/tourist/booking.php", 'POST', $sameDateData, $touristCookies);
    assertTest("Same start/end date booking returns 400",
        $res['code'] === 400 && !($res['body']['success'] ?? true),
        "Got HTTP {$res['code']}: " . ($res['body']['message'] ?? ''), $passed, $failed, $total);
} else {
    echo "   ⏩ Skipped: No hotel available.\n";
}

// BUG FIX TEST 5: Hotel booking with past check-in date should fail
echo "\n13. BUG FIX: Hotel booking with past check-in date should be rejected...\n";
if ($hotelId) {
    $pastDateData = [
        'type' => 'Hotel',
        'entity_id' => $hotelId,
        'start_date' => '2020-01-01',
        'end_date' => '2020-01-05',
        'guests_count' => 1
    ];
    $res = makeRequest("$baseUrl/tourist/booking.php", 'POST', $pastDateData, $touristCookies);
    assertTest("Past date booking returns 400",
        $res['code'] === 400 && !($res['body']['success'] ?? true),
        "Got HTTP {$res['code']}: " . ($res['body']['message'] ?? ''), $passed, $failed, $total);
} else {
    echo "   ⏩ Skipped: No hotel available.\n";
}

// BUG FIX TEST 6: Create a past-date transport (via admin) and try to cancel
echo "\n14. BUG FIX: Cancelling a past transport booking should be rejected...\n";
// We need admin to add a past-departure-date transport for this test.
// Re-login as admin
$resAdmin2 = makeRequest("$baseUrl/auth/login.php", 'POST', $adminLoginData);
$adminCookies2 = $resAdmin2['cookies'];
if ($resAdmin2['code'] === 200) {
    // Add a transport with a past departure date
    $pastTransportData = [
        'type' => 'Train',
        'source' => 'TestPastSrc',
        'destination' => 'TestPastDst',
        'departure_date' => '2024-01-01',
        'departure_time' => '08:00:00',
        'price' => 50.00,
        'total_seats' => 10
    ];
    $resAddPast = makeRequest("$baseUrl/admin/manage_transports.php", 'POST', $pastTransportData, $adminCookies2);
    $pastTransportId = $resAddPast['body']['data']['id'] ?? null;

    if ($pastTransportId) {
        // Login as tourist and book it (booking itself doesn't validate transport dates currently)
        $resLogin3 = makeRequest("$baseUrl/auth/login.php", 'POST', $loginData);
        $touristCookies3 = $resLogin3['cookies'];

        $pastBookData = [
            'type' => 'Transport',
            'entity_id' => $pastTransportId,
            'guests_count' => 1
        ];
        $resBook = makeRequest("$baseUrl/tourist/booking.php", 'POST', $pastBookData, $touristCookies3);
        $pastBookingId = $resBook['body']['data']['booking_id'] ?? null;

        if ($pastBookingId) {
            $cancelRes = makeRequest("$baseUrl/tourist/cancel_booking.php", 'POST',
                ['booking_id' => $pastBookingId], $touristCookies3);
            assertTest("Past transport cancel returns 400",
                $cancelRes['code'] === 400 && !($cancelRes['body']['success'] ?? true),
                "Got HTTP {$cancelRes['code']}: " . ($cancelRes['body']['message'] ?? ''), $passed, $failed, $total);
        } else {
            echo "   ⏩ Skipped: Could not create past transport booking.\n";
        }
    } else {
        echo "   ⏩ Skipped: Past transport creation failed (expected if date validation blocks it).\n";
    }
} else {
    echo "   ⏩ Skipped: Admin re-login failed.\n";
}

// ============================================================
// SUMMARY
// ============================================================
echo "\n==========================================================\n";
echo "  TEST RESULTS: $passed PASSED / $failed FAILED / $total TOTAL\n";
echo "==========================================================\n";

if ($failed === 0) {
    echo "  🎉 All tests passed!\n";
} else {
    echo "  ⚠️  Some tests failed. Review output above.\n";
}
echo "\n";
?>

