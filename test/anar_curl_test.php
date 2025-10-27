<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('curl_init')) {
    die('افزونه cURL در دسترس نیست.');
}

header('Content-Type: text/html; charset=UTF-8');

$endpoints = [
    'auth/validate' => 'https://api.anar360.com/wp/auth/validate?check=true',
    'products' => 'https://api.anar360.com/wp/products?check=true',
    'categories' => 'https://api.anar360.com/wp/categories?check=true',
    'attributes' => 'https://api.anar360.com/wp/attributes?check=true'
];

$token = 'Bearer invalid_token_12345';

function makeCurlRequest($url, $token) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60, // Increased from 30 to 60 seconds
        CURLOPT_CONNECTTIMEOUT => 15, // Increased from 10 to 15 seconds
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Simple-cURL-Test/1.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Accept: application/json',
            'wp-header: ' . $_SERVER['HTTP_HOST']
        ],
        CURLOPT_VERBOSE => false, // Set to true for debugging
        CURLOPT_FRESH_CONNECT => true, // Force fresh connection
        CURLOPT_FORBID_REUSE => true, // Don't reuse connections
    ]);

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    // Enhanced error detection
    $is_success = $response !== false && empty($error) && $http_code > 0 && $curl_errno === 0;
    
    // Additional timeout detection
    $is_timeout = false;
    // Use numeric values for cURL error codes (more compatible)
    if ($curl_errno === 28 || $curl_errno === 7) { // CURLE_OPERATION_TIMEOUTED = 28, CURLE_COULDNT_CONNECT = 7
        $is_timeout = true;
    }

    return [
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error,
        'curl_errno' => $curl_errno,
        'response_time' => round($info['total_time'], 2),
        'connect_time' => round($info['connect_time'], 2),
        'dns_time' => round($info['namelookup_time'], 2),
        'success' => $is_success,
        'is_timeout' => $is_timeout,
        'total_time' => round(($end_time - $start_time) * 1000, 2) // in milliseconds
    ];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست اتصال هاست به سرویس های انار</title>
    <style>
        body { direction: rtl;font-family:tahoma; }
        h2 { text-align: center; }
        table { width: 95%; margin: 20px auto; }
        th, td { border: 1px solid #ddd; padding: 2px 4px; text-align: right; }
        th { background: #eee; }
        .success { color: green;  }
        .error { color: red; }
        .status-icon { font-size: 18px; margin-left: 5px; }
        pre { white-space: pre-wrap; word-break: break-word; direction: ltr; background: #f1f1f1; padding: 5px; }
    </style>
</head>
<body>

<h2>نتایج تست cURL</h2>
<p style="text-align:center;">میزبان: <?= $_SERVER['HTTP_HOST'] ?></p>


<table>
    <tr>
        <th>نام تست</th>
        <th>آدرس</th>
        <th>کد HTTP</th>
        <th>زمان پاسخ</th>
        <th>DNS زمان</th>
        <th>اتصال زمان</th>
        <th>وضعیت اتصال</th>
        <th>پاسخ / خطا</th>
    </tr>
    <?php foreach ($endpoints as $name => $url):
        $result = makeCurlRequest($url, $token); ?>
        <tr>
            <td><?= htmlspecialchars($name) ?></td>
            <td style="direction:ltr;"><?= htmlspecialchars($url) ?></td>
            <td><?= $result['http_code'] ?: '-' ?></td>
            <td><?= $result['response_time'] ?> ثانیه</td>
            <td><?= $result['dns_time'] ?> ثانیه</td>
            <td><?= $result['connect_time'] ?> ثانیه</td>
            <td class="<?= $result['success'] ? 'success' : 'error' ?>">
                <?php if ($result['is_timeout']): ?>
                    تایم اوت ⏰
                <?php elseif ($result['success']): ?>
                    موفق ✅
                <?php else: ?>
                    ناموفق ❌
                <?php endif; ?>
                <?php if ($result['curl_errno'] > 0): ?>
                    <br><small>کد خطا: <?= $result['curl_errno'] ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($result['success']): ?>
                    <pre><?= htmlspecialchars($result['response']) ?></pre>
                <?php else: ?>
                    <strong>خطا:</strong> <?= $result['error'] ?: 'بدون پاسخ' ?><br>
                    <strong>زمان کل:</strong> <?= $result['total_time'] ?> میلی‌ثانیه
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
