<?php
/**
 * Anar API Connection Test - Browser cURL Version
 * 
 * This file runs the cURL test in the browser with a simple form.
 * It uses pure cURL without any WordPress dependencies.
 * 
 * Usage:
 * 1. Copy this file to your public_html directory
 * 2. Access it via browser: https://yourdomain.com/anar_browser_curl_test.php
 * 3. Enter your Anar token and click "Test"
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Check if cURL is available
if (!function_exists('curl_init')) {
    die('cURL extension is not available. Please install cURL extension for PHP.');
}

// Set content type for proper display
header('Content-Type: text/html; charset=UTF-8');

// Simple HTML form for token input
if (!isset($_POST['token']) || empty($_POST['token'])) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تست cURL API انار</title>
        <style>
            body {
                font-family: 'Tahoma', 'Arial', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            h1 {
                color: #333;
                margin-bottom: 30px;
                font-size: 24px;
            }
            .form-group {
                margin-bottom: 20px;
                text-align: right;
            }
            label {
                display: block;
                margin-bottom: 8px;
                color: #555;
                font-weight: bold;
            }
            input[type="text"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 8px;
                font-size: 16px;
                box-sizing: border-box;
                transition: border-color 0.3s;
            }
            input[type="text"]:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            button:hover {
                transform: translateY(-2px);
            }
            .info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                color: #666;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>تست cURL API انار</h1>
            <div class="info">
                <strong>راهنمای استفاده:</strong><br>
                1. توکن انار خود را در فیلد زیر وارد کنید<br>
                2. دکمه "تست cURL" را کلیک کنید<br>
                3. نتیجه تست در قالب متنی نمایش داده خواهد شد
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="token">توکن انار:</label>
                    <input type="text" id="token" name="token" placeholder="Bearer your_token_here" required>
                </div>
                <button type="submit">تست cURL</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get token from form
$token = trim($_POST['token']);

// Test URLs
$test_url = 'https://api.anar360.com/wp/auth/validate';
$categories_url = 'https://api.anar360.com/wp/categories';

// Function to make API call
function makeApiCall($url, $token) {
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
            'wp-header: ' . $_SERVER['HTTP_HOST']
        ],
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    if ($response === false || !empty($error)) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error,
            'http_code' => 0,
            'response_time' => 0,
            'response' => ''
        ];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => $http_code == 200 && $data !== null,
        'error' => $http_code != 200 ? 'HTTP Error: ' . $http_code : ($data === null ? 'Invalid JSON' : ''),
        'http_code' => $http_code,
        'response_time' => round($info['total_time'], 2),
        'response' => $response,
        'data' => $data
    ];
}

// Start testing
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتیجه تست cURL API انار</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #ffffff;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4CAF50;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .success {
            background: #1b5e20;
            border-color: #4CAF50;
        }
        .error {
            background: #b71c1c;
            border-color: #f44336;
        }
        .info {
            background: #1565c0;
            border-color: #2196F3;
        }
        .test-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
            color: #FFD700;
        }
        .result-line {
            margin: 8px 0;
            padding: 5px 0;
        }
        .success-text {
            color: #4CAF50;
        }
        .error-text {
            color: #f44336;
        }
        .info-text {
            color: #2196F3;
        }
        .json-output {
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            color: #e0e0e0;
        }
        .back-button {
            text-align: center;
            margin-top: 30px;
        }
        .back-button a {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: background 0.3s;
        }
        .back-button a:hover {
            background: #45a049;
        }
        .summary {
            background: #333;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid;
        }
        .summary.success {
            border-color: #4CAF50;
            color: #4CAF50;
        }
        .summary.error {
            border-color: #f44336;
            color: #f44336;
        }
        .system-info {
            background: #424242;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>=== Anar API Connection Test ===</h1>
        
        <div class="system-info">
            <strong>System Information:</strong><br>
            Token: <?php echo htmlspecialchars(substr($token, 0, 20)) . '...'; ?><br>
            Date: <?php echo date('Y-m-d H:i:s'); ?><br>
            PHP Version: <?php echo PHP_VERSION; ?><br>
            cURL Version: <?php echo curl_version()['version']; ?><br>
            SSL Support: <?php echo (curl_version()['features'] & CURL_VERSION_SSL ? 'Yes' : 'No'); ?><br>
        </div>

        <?php
        // Test 1: Token Validation
        echo '<div class="test-section info">';
        echo '<div class="test-title">--- Test 1: Token Validation ---</div>';
        echo '<div class="result-line">Testing: ' . $test_url . '</div>';
        
        $token_result = makeApiCall($test_url, $token);
        
        if ($token_result['success']) {
            echo '<div class="result-line success-text">✅ HTTP Code: ' . $token_result['http_code'] . '</div>';
            echo '<div class="result-line success-text">✅ Response Time: ' . $token_result['response_time'] . 's</div>';
            echo '<div class="result-line success-text">✅ Valid JSON Response</div>';
            
            if (isset($token_result['data']['success']) && $token_result['data']['success'] === true) {
                echo '<div class="result-line success-text">✅ Token is valid!</div>';
                if (isset($token_result['data']['shopUrl'])) {
                    echo '<div class="result-line info-text">   Shop URL: ' . htmlspecialchars($token_result['data']['shopUrl']) . '</div>';
                }
                if (isset($token_result['data']['subscriptionPlan'])) {
                    echo '<div class="result-line info-text">   Plan: ' . htmlspecialchars($token_result['data']['subscriptionPlan']) . '</div>';
                }
                if (isset($token_result['data']['subscriptionRemaining'])) {
                    echo '<div class="result-line info-text">   Remaining: ' . htmlspecialchars($token_result['data']['subscriptionRemaining']) . '</div>';
                }
            } else {
                echo '<div class="result-line error-text">❌ Token validation failed!</div>';
                if (isset($token_result['data']['error'])) {
                    echo '<div class="result-line error-text">   Error: ' . htmlspecialchars($token_result['data']['error']) . '</div>';
                }
            }
        } else {
            echo '<div class="result-line error-text">❌ ' . $token_result['error'] . '</div>';
            if ($token_result['http_code'] > 0) {
                echo '<div class="result-line error-text">HTTP Code: ' . $token_result['http_code'] . '</div>';
            }
        }
        echo '</div>';

        // Test 2: Categories API
        echo '<div class="test-section info">';
        echo '<div class="test-title">--- Test 2: Categories API ---</div>';
        echo '<div class="result-line">Testing: ' . $categories_url . '</div>';
        
        $categories_result = makeApiCall($categories_url, $token);
        
        if ($categories_result['success']) {
            echo '<div class="result-line success-text">✅ HTTP Code: ' . $categories_result['http_code'] . '</div>';
            echo '<div class="result-line success-text">✅ Response Time: ' . $categories_result['response_time'] . 's</div>';
            echo '<div class="result-line success-text">✅ Valid JSON Response</div>';
            
            if (isset($categories_result['data']['data'])) {
                $count = count($categories_result['data']['data']);
                echo '<div class="result-line success-text">✅ Categories fetched successfully!</div>';
                echo '<div class="result-line info-text">   Count: ' . $count . ' categories</div>';
                
                if (isset($categories_result['data']['total'])) {
                    echo '<div class="result-line info-text">   Total: ' . $categories_result['data']['total'] . ' categories</div>';
                }
                
                // Show first few categories
                if ($count > 0) {
                    echo '<div class="result-line info-text">   Sample categories:</div>';
                    $sample = array_slice($categories_result['data']['data'], 0, 3);
                    foreach ($sample as $category) {
                        if (isset($category['name'])) {
                            echo '<div class="result-line info-text">     - ' . htmlspecialchars($category['name']) . '</div>';
                        }
                    }
                }
            } else {
                echo '<div class="result-line error-text">❌ No categories data found in response</div>';
            }
        } else {
            echo '<div class="result-line error-text">❌ ' . $categories_result['error'] . '</div>';
            if ($categories_result['http_code'] > 0) {
                echo '<div class="result-line error-text">HTTP Code: ' . $categories_result['http_code'] . '</div>';
            }
        }
        echo '</div>';

        // Summary
        $token_valid = $token_result['success'] && isset($token_result['data']['success']) && $token_result['data']['success'] === true;
        $categories_valid = $categories_result['success'] && isset($categories_result['data']['data']);
        
        echo '<div class="summary ' . ($token_valid && $categories_valid ? 'success' : 'error') . '">';
        echo '<div class="test-title">=== Test Summary ===</div>';
        
        if ($token_valid && $categories_valid) {
            echo '<div class="result-line">✅ All tests passed! API connection is working.</div>';
        } else {
            echo '<div class="result-line">❌ Some tests failed. Check the errors above.</div>';
        }
        echo '</div>';

        // Show raw responses for debugging
        echo '<div class="test-section info">';
        echo '<div class="test-title">Raw Responses (for debugging)</div>';
        
        echo '<div class="result-line info-text">Token Validation Response:</div>';
        echo '<div class="json-output">' . htmlspecialchars($token_result['response']) . '</div>';
        
        echo '<div class="result-line info-text">Categories Response:</div>';
        echo '<div class="json-output">' . htmlspecialchars($categories_result['response']) . '</div>';
        echo '</div>';
        ?>
        
        <div class="back-button">
            <a href="javascript:history.back()">بازگشت</a>
        </div>
    </div>
</body>
</html>
