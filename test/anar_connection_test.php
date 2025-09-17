<?php
/**
 * Anar API Connection Test
 * 
 * This file tests the connection to Anar API by fetching categories.
 * It can be run directly on public_html by entering the token manually.
 * 
 * Usage:
 * 1. Copy this file to your public_html directory
 * 2. Access it via browser: https://yourdomain.com/anar_connection_test.php
 * 3. Enter your Anar token when prompted
 * 4. The test will attempt to fetch categories from the API
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Check if cURL is available
if (!function_exists('curl_init')) {
    die('cURL extension is not available. Please install cURL extension for PHP.');
}

// Prevent direct access if not in web context
if (php_sapi_name() === 'cli') {
    echo "This script should be run in a web browser.\n";
    exit;
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
        <title>تست اتصال به API انار</title>
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
            <h1>تست اتصال به API انار</h1>
            <div class="info">
                <strong>راهنمای استفاده:</strong><br>
                1. توکن انار خود را در فیلد زیر وارد کنید<br>
                2. دکمه "تست اتصال" را کلیک کنید<br>
                3. نتیجه تست نمایش داده خواهد شد
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="token">توکن انار:</label>
                    <input type="text" id="token" name="token" placeholder="توکن خود را وارد کنید..." required>
                </div>
                <button type="submit">تست اتصال</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get token from form
$token = trim($_POST['token']);

// Test configuration
$api_url = 'https://api.anar360.com/wp/categories';
$test_url = 'https://api.anar360.com/wp/auth/validate';

// Function to add query parameters to URL
function add_query_arg($args, $url) {
    $parsed_url = parse_url($url);
    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
    parse_str($query, $existing_args);
    $new_args = array_merge($existing_args, $args);
    $new_query = http_build_query($new_args);
    
    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    
    return $scheme . $host . $port . $path . ($new_query ? '?' . $new_query : '') . $fragment;
}

// Function to make API call using cURL (no WordPress dependencies)
function callAnarApi($url, $token) {
    // Add check parameter if needed (similar to the original code)
    $check_args = ["check" => "true"];
    $url = add_query_arg($check_args, $url);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
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
            'wp-header: ' . rtrim($_SERVER['HTTP_HOST'], '/')
        ],
        CURLOPT_HEADER => true,
        CURLOPT_VERBOSE => false
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    curl_close($ch);
    
    // Handle cURL errors
    if ($response === false || !empty($error)) {
        return [
            'error' => true,
            'message' => 'cURL Error: ' . $error
        ];
    }
    
    // Split headers and body
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    return [
        'error' => false,
        'response_code' => $http_code,
        'body' => $body,
        'headers' => $headers
    ];
}

// Function to test API connection with retries
function testAnarConnection($url, $token, $max_retries = 3) {
    $retry_delay = 2;
    $attempts = 0;
    
    while ($attempts < $max_retries) {
        $attempts++;
        $result = callAnarApi($url, $token);
        
        if (!$result['error']) {
            return $result;
        }
        
        if ($attempts < $max_retries) {
            sleep($retry_delay);
        }
    }
    
    return $result;
}

// Start testing
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتیجه تست اتصال به API انار</title>
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid;
        }
        .success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .test-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .test-result {
            margin-bottom: 10px;
        }
        .json-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .back-button {
            text-align: center;
            margin-top: 30px;
        }
        .back-button a {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: transform 0.2s;
        }
        .back-button a:hover {
            transform: translateY(-2px);
        }
        .summary {
            background: #e9ecef;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary h2 {
            margin-top: 0;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>نتیجه تست اتصال به API انار</h1>
        
        <?php
        // Test 1: Token Validation
        echo '<div class="test-section info">';
        echo '<div class="test-title">تست 1: اعتبارسنجی توکن</div>';
        echo '<div class="test-result">در حال تست توکن...</div>';
        
        $token_result = testAnarConnection($test_url, $token);
        
        if (!$token_result['error']) {
            if ($token_result['response_code'] == 200) {
                $token_data = json_decode($token_result['body'], true);
                if (isset($token_data['success']) && $token_data['success'] === true) {
                    echo '<div class="test-result success">✅ توکن معتبر است</div>';
                    if (isset($token_data['shopUrl'])) {
                        echo '<div class="test-result">🌐 آدرس فروشگاه: ' . htmlspecialchars($token_data['shopUrl']) . '</div>';
                    }
                    if (isset($token_data['subscriptionPlan'])) {
                        echo '<div class="test-result">📋 پلن اشتراک: ' . htmlspecialchars($token_data['subscriptionPlan']) . '</div>';
                    }
                    if (isset($token_data['subscriptionRemaining'])) {
                        echo '<div class="test-result">⏰ باقی‌مانده اشتراک: ' . htmlspecialchars($token_data['subscriptionRemaining']) . '</div>';
                    }
                } else {
                    echo '<div class="test-result error">❌ توکن نامعتبر است</div>';
                    if (isset($token_data['error'])) {
                        echo '<div class="test-result">خطا: ' . htmlspecialchars($token_data['error']) . '</div>';
                    }
                }
            } else {
                echo '<div class="test-result error">❌ خطا در اعتبارسنجی توکن (کد: ' . $token_result['response_code'] . ')</div>';
            }
        } else {
            echo '<div class="test-result error">❌ خطا در اتصال: ' . htmlspecialchars($token_result['message']) . '</div>';
        }
        echo '</div>';
        
        // Test 2: Categories API
        echo '<div class="test-section info">';
        echo '<div class="test-title">تست 2: دریافت دسته‌بندی‌ها</div>';
        echo '<div class="test-result">در حال دریافت دسته‌بندی‌ها...</div>';
        
        $categories_result = testAnarConnection($api_url, $token);
        
        if (!$categories_result['error']) {
            if ($categories_result['response_code'] == 200) {
                $categories_data = json_decode($categories_result['body'], true);
                if ($categories_data && isset($categories_data['data'])) {
                    $category_count = count($categories_data['data']);
                    echo '<div class="test-result success">✅ دسته‌بندی‌ها با موفقیت دریافت شد</div>';
                    echo '<div class="test-result">📊 تعداد دسته‌بندی‌ها: ' . $category_count . '</div>';
                    
                    if (isset($categories_data['total'])) {
                        echo '<div class="test-result">📈 کل دسته‌بندی‌ها: ' . $categories_data['total'] . '</div>';
                    }
                    
                    // Show first few categories as sample
                    if ($category_count > 0) {
                        echo '<div class="test-result">📋 نمونه دسته‌بندی‌ها:</div>';
                        $sample_categories = array_slice($categories_data['data'], 0, 5);
                        foreach ($sample_categories as $category) {
                            if (isset($category['name'])) {
                                echo '<div class="test-result">• ' . htmlspecialchars($category['name']) . '</div>';
                            }
                        }
                        if ($category_count > 5) {
                            echo '<div class="test-result">... و ' . ($category_count - 5) . ' دسته‌بندی دیگر</div>';
                        }
                    }
                } else {
                    echo '<div class="test-result warning">⚠️ پاسخ API خالی یا نامعتبر است</div>';
                }
            } elseif ($categories_result['response_code'] == 403) {
                echo '<div class="test-result error">❌ دسترسی غیرمجاز (403) - توکن نامعتبر یا منقضی شده</div>';
            } else {
                echo '<div class="test-result error">❌ خطا در دریافت دسته‌بندی‌ها (کد: ' . $categories_result['response_code'] . ')</div>';
            }
        } else {
            echo '<div class="test-result error">❌ خطا در اتصال: ' . htmlspecialchars($categories_result['message']) . '</div>';
        }
        echo '</div>';
        
        // Summary
        $overall_success = !$token_result['error'] && !$categories_result['error'] && 
                          $token_result['response_code'] == 200 && $categories_result['response_code'] == 200;
        
        echo '<div class="summary">';
        if ($overall_success) {
            echo '<h2 style="color: #28a745;">✅ تست موفقیت‌آمیز بود!</h2>';
            echo '<p>اتصال به API انار برقرار است و دسته‌بندی‌ها قابل دریافت هستند.</p>';
        } else {
            echo '<h2 style="color: #dc3545;">❌ تست ناموفق بود</h2>';
            echo '<p>مشکلی در اتصال به API انار وجود دارد. لطفاً توکن خود را بررسی کنید.</p>';
        }
        echo '</div>';
        
        // Show raw responses for debugging
        echo '<div class="test-section info">';
        echo '<div class="test-title">پاسخ‌های خام (برای دیباگ)</div>';
        
        echo '<h4>پاسخ اعتبارسنجی توکن:</h4>';
        echo '<div class="json-output">' . htmlspecialchars($token_result['body']) . '</div>';
        
        echo '<h4>پاسخ دسته‌بندی‌ها:</h4>';
        echo '<div class="json-output">' . htmlspecialchars($categories_result['body']) . '</div>';
        
        // Show system information
        echo '<h4>اطلاعات سیستم:</h4>';
        echo '<div class="json-output">';
        echo 'PHP Version: ' . PHP_VERSION . "\n";
        echo 'cURL Version: ' . curl_version()['version'] . "\n";
        echo 'SSL Support: ' . (curl_version()['features'] & CURL_VERSION_SSL ? 'Yes' : 'No') . "\n";
        echo 'Server: ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
        echo 'User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\n";
        echo '</div>';
        echo '</div>';
        ?>
        
        <div class="back-button">
            <a href="javascript:history.back()">بازگشت</a>
        </div>
    </div>
</body>
</html>
