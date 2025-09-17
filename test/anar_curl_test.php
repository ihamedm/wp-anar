<?php
/**
 * Anar API Connection Test - Command Line Version
 * 
 * This is a simple command-line version for testing Anar API connection.
 * It uses pure cURL without any WordPress dependencies.
 * 
 * Usage:
 * php anar_curl_test.php YOUR_TOKEN_HERE
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if cURL is available
if (!function_exists('curl_init')) {
    die("ERROR: cURL extension is not available.\n");
}

// Get token from command line argument
if ($argc < 2) {
    echo "Usage: php anar_curl_test.php YOUR_TOKEN_HERE\n";
    echo "Example: php anar_curl_test.php Bearer your_token_here\n";
    exit(1);
}

$token = $argv[1];

// Test URLs
$test_url = 'https://api.anar360.com/wp/auth/validate';
$categories_url = 'https://api.anar360.com/wp/categories';

echo "=== Anar API Connection Test ===\n";
echo "Token: " . substr($token, 0, 20) . "...\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Function to make API call
function makeApiCall($url, $token) {
    echo "Testing: $url\n";
    
    // Add check parameter
    $url .= (strpos($url, '?') !== false ? '&' : '?') . 'check=true';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Anar-API-Test/1.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Accept: application/json',
            'wp-header: localhost'
        ],
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    if ($response === false || !empty($error)) {
        echo "❌ cURL Error: $error\n";
        return false;
    }
    
    echo "✅ HTTP Code: $http_code\n";
    echo "✅ Response Time: " . round($info['total_time'], 2) . "s\n";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ Valid JSON Response\n";
            return $data;
        } else {
            echo "❌ Invalid JSON Response\n";
            echo "Raw Response: " . substr($response, 0, 200) . "...\n";
            return false;
        }
    } else {
        echo "❌ HTTP Error: $http_code\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
        return false;
    }
}

// Test 1: Token Validation
echo "--- Test 1: Token Validation ---\n";
$token_result = makeApiCall($test_url, $token);

if ($token_result && isset($token_result['success']) && $token_result['success'] === true) {
    echo "✅ Token is valid!\n";
    if (isset($token_result['shopUrl'])) {
        echo "   Shop URL: " . $token_result['shopUrl'] . "\n";
    }
    if (isset($token_result['subscriptionPlan'])) {
        echo "   Plan: " . $token_result['subscriptionPlan'] . "\n";
    }
    if (isset($token_result['subscriptionRemaining'])) {
        echo "   Remaining: " . $token_result['subscriptionRemaining'] . "\n";
    }
} else {
    echo "❌ Token validation failed!\n";
    if ($token_result && isset($token_result['error'])) {
        echo "   Error: " . $token_result['error'] . "\n";
    }
}

echo "\n";

// Test 2: Categories API
echo "--- Test 2: Categories API ---\n";
$categories_result = makeApiCall($categories_url, $token);

if ($categories_result && isset($categories_result['data'])) {
    $count = count($categories_result['data']);
    echo "✅ Categories fetched successfully!\n";
    echo "   Count: $count categories\n";
    
    if (isset($categories_result['total'])) {
        echo "   Total: " . $categories_result['total'] . " categories\n";
    }
    
    // Show first few categories
    if ($count > 0) {
        echo "   Sample categories:\n";
        $sample = array_slice($categories_result['data'], 0, 3);
        foreach ($sample as $category) {
            if (isset($category['name'])) {
                echo "     - " . $category['name'] . "\n";
            }
        }
    }
} else {
    echo "❌ Categories fetch failed!\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
$token_valid = $token_result && isset($token_result['success']) && $token_result['success'] === true;
$categories_valid = $categories_result && isset($categories_result['data']);

if ($token_valid && $categories_valid) {
    echo "✅ All tests passed! API connection is working.\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Check the errors above.\n";
    exit(1);
}
