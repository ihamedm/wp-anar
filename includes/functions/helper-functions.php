<?php

use Anar\ApiDataHandler;

/**
 * Gets the saved Anar activation token
 *
 * Retrieves the activation key/token that was previously saved during plugin activation.
 *
 * @return false|mixed|null The activation token if available, false or null otherwise
 */
function anar_get_saved_token(){
    return Anar\Core\Activation::get_saved_activation_key();
}

/**
 * Gets an Anar icon by name and size
 *
 * Retrieves an icon SVG string from the Icons class based on the provided name and size.
 *
 * @param string $name The icon name/identifier
 * @param int|string $size The size of the icon (width/height)
 * @return string The icon SVG markup
 */
function get_anar_icon($name, $size){
    return Anar\Core\Icons::get_sized_icon($name, $size);
}

/**
 * Limits a string to a maximum length
 *
 * Truncates a string to the specified maximum length, ensuring UTF-8 encoding
 * and appending '...' if the string is longer than the limit.
 *
 * @param string $string The string to truncate
 * @param int $maxLength Maximum number of characters
 * @return string The truncated string with '...' appended if needed
 */
function awca_limit_chars($string, $maxLength): string
{
    // Ensure the string is in UTF-8 encoding
    $string = mb_convert_encoding($string, 'UTF-8', 'auto');

    // Truncate the string to the desired length
    return mb_strimwidth($string, 0, $maxLength, '...', 'UTF-8');
}


/**
 * Converts a database timestamp to a human-readable "time ago" string in Persian
 *
 * Calculates the time difference between the provided database timestamp and the current time,
 * then returns a localized Persian string (e.g., "5 دقیقه قبل", "2 ساعت قبل").
 *
 * @param string $db_time Database timestamp in MySQL datetime format (Y-m-d H:i:s)
 * @return string Human-readable time difference in Persian (e.g., "5 دقیقه قبل")
 */
function awca_time_ago($db_time): string
{
    $time_ago = strtotime($db_time);
    $current_time = current_time('timestamp');
    $time_difference = $current_time - $time_ago;

    // Define some time units in seconds
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);

    // Determine the time difference and return the appropriate string
    if ($seconds <= 60) {
        return "لحظاتی پیش";
    } else if ($minutes <= 60) {
        return "$minutes دقیقه قبل";
    } else if ($hours <= 24) {
        return "$hours ساعت قبل";
    } else if ($days <= 7) {
        return "$days روز قبل";
    } else if ($weeks <= 4.3) { // 4.3 == 30/7
        return "$weeks هفته قبل";
    } else if ($months <= 12) {
        return "$months ماه قبل";
    } else {
        return "$years سال قبل";
    }
}

/**
 * Checks if a database timestamp has expired based on a threshold
 *
 * Compares the provided database timestamp with the current time and determines
 * if the time difference exceeds 60000 seconds (approximately 16.67 hours).
 * Used to check if cached data or temporary values have expired.
 *
 * @param string|false|null $db_time Database timestamp in MySQL datetime format (Y-m-d H:i:s), or false/null
 * @return bool True if expired (time difference > 60000 seconds) or if $db_time is falsy, false otherwise
 */
function awca_check_expiration_by_db_time($db_time): bool
{

    anar_log(print_r($db_time, true), 'debug');

    if(!$db_time)
        return true;

    $db_time_unix = strtotime($db_time);
    $current_time = current_time('timestamp');
    $time_difference = $current_time - $db_time_unix;

    anar_log(print_r($time_difference, true), 'debug');

    return ($time_difference > 60000) ?? false;
}



/**
 * Transforms an image URL using the ImageDownloader service
 *
 * Processes an image URL through the ImageDownloader to potentially download,
 * cache, or transform it to a local WordPress media library URL.
 *
 * @param string $url The original image URL to transform
 * @return string The transformed image URL (may be local or original)
 */
function awca_transform_image_url($url): string
{
    $image_downloader = Anar\Core\ImageDownloader::get_instance();
    return $image_downloader->transform_image_url($url);
}


/**
 * Convert a price from IRT (Iranian Toman) to the WooCommerce configured currency.
 *
 * This function checks the current WooCommerce currency setting and converts the price
 * from IRT (Iranian Toman) to IRR (Iranian Rial) if necessary. The conversion rate is
 * assumed to be 1 IRT = 10 IRR.
 *
 * @param float $price_in_irt The price in IRT (Iranian Toman) to be converted.
 * @return float The converted price in the WooCommerce configured currency.
 */
function awca_convert_price_to_woocommerce_currency($price_in_irt)
{
    // Define the conversion rate from IRT to IRR
    $conversion_rate = 10; // 1 IRT = 10 IRR

    // Get the current WooCommerce currency
    $woocommerce_currency = get_woocommerce_currency();

    // Convert the price based on the WooCommerce currency
    if ($woocommerce_currency === 'IRR') {
        // Convert IRT to IRR
        return $price_in_irt * $conversion_rate;
    } else {
        // Return the price in IRT (no conversion needed)
        return $price_in_irt;
    }
}

/**
 * Formats an Anar price for display with WooCommerce currency formatting
 *
 * Converts an Anar price (in IRT) to WooCommerce currency, formats it with
 * thousand separators and decimal places, and appends the currency symbol.
 *
 * @param float|string $anar_price The price from Anar API (in IRT)
 * @return string Formatted price string with currency symbol (e.g., "1,000,000 ریال")
 */
function anar_get_formatted_price($anar_price){
    $converted_price = awca_convert_price_to_woocommerce_currency($anar_price);
    $currency_symbol = get_woocommerce_currency_symbol();
    $thousand_separator = get_option( 'woocommerce_price_thousand_sep', ',' );
    $decimal_separator = get_option( 'woocommerce_price_num_decimals', 0 );

    // Format the price
    $formatted_price = number_format(floatval(preg_replace('/[^\d.]/', '', $converted_price)), 0, $decimal_separator, $thousand_separator);

    return $formatted_price . ' ' . $currency_symbol;
}


/**
 * Checks if WooCommerce High-Performance Order Storage (HPOS) is enabled
 *
 * Verifies if the site is using WooCommerce's custom orders table (HPOS) instead of
 * the traditional WordPress posts table for order storage.
 *
 * @return bool True if HPOS is enabled, false otherwise or if WooCommerce is not active
 */
function awca_is_hpos_enable(): bool
{
    // Check if WooCommerce is active
    if (!function_exists('WC') || !class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        return false;
    }

    // Now safely check if HPOS is enabled
    try {
        return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    } catch (Exception $e) {
        // Handle any exceptions that might occur
        return false;
    }
}


/**
 * Translates Anar order and shipping status strings to Persian
 *
 * Converts English order statuses (e.g., 'unpaid', 'paid', 'delivered') and shipping
 * methods (e.g., 'bike', 'post', 'express') to their Persian equivalents.
 * Returns the original string if no translation is found.
 *
 * @param string $string The English status or shipping method string to translate
 * @return string The Persian translation if available, otherwise the original string
 */
function anar_translator($string) {
    $orders = [
        'unpaid' => 'پرداخت نشده',
        'paid' => 'پرداخت شده',
        'expired' => 'منقضی شده',
        'approvalPending' => 'در انتظار تایید (دستی یا سیستمی)',
        'rejected' => 'رد شده (لغو کامل) ',
        'preparing' => 'در حال پردازش اولیه / تامین سفارش',
        'readyToPost' => 'بسته‌بندی یا آماده‌سازی برای ارسال',
        'posted' => 'ارسال شده به پست / تیپاکس و ...',
        'delivered' => 'تحویل داده شده به مشتری نهایی',
        'returned' => 'بازگشت‌خورده از سمت مشتری یا پست',
        'returnRequested' => 'درخواست مرجوعی ثبت شده',
        'resendRequested' => 'درخواست ارسال مجدد ثبت شده',
        'accepted' => 'قبول شده',
    ];

    $shipping = [
        //--- Courier ---
        'bike' => 'پیک',
        'bikePostPaid' => 'پیک (کرایه در مقصد)',
        'bikeFree' => 'پیک رایگان',
        'bikeFast' => 'ارسال سریع با پیک',
        'bikeFastPostPaid' => 'پیک سریع (کرایه در مقصد)',
        'bikeVipPostPaid' => 'پیک ویژه (کرایه در مقصد)',


        //--- Post ---
        'post' => 'پست',
        'postFree' => 'پست رایگان',
        'postPostPaid' => 'پست (کرایه در مقصد)',
        'express' => 'پیشتاز',
        'expressPost' => 'پست اکسپرس',
        'expressPostPaid' => 'پیشتاز (کرایه در مقصد)',
        'chapar' => 'چاپار',
        'chaparPostPaid' => 'چاپار (کرایه در مقصد)',
        'tipax' => 'تیپاکس',
        'tipaxPostPaid' => 'تیپاکس (کرایه در مقصد)',


        // --- Custom ---
        'tehranBasic' => 'ارسال پایه در تهران',
        'specialPost' => 'پست سفارشی',


        // --- Deprecated ---
        'afterFare' => 'afterFare',
        'tipaxCOD' => 'تیپاکس (کرایه در مقصد)',
        'bikeCOD' => 'پیک (کرایه در مقصد)',
    ];

    $map = $orders + $shipping;

    return $map[$string] ?? $string;
}




/**
 * Logs a message using the Anar Logger with a specific prefix
 *
 * Wrapper function for the Logger class that logs messages with a specified prefix
 * (e.g., 'sync', 'import', 'general') for better log organization.
 *
 * @param string $message The log message to record
 * @param string $prefix The log prefix/context (e.g., 'sync', 'import', 'general')
 * @param string|null $level Log level ('info', 'error', 'warning', 'debug'). Defaults to 'info' if null
 * @return void
 */
function awca_log($message, $prefix = 'general', $level = null) {
    // Create an instance of the logger class
    $logger = Anar\Core\Logger::get_instance();
    $level = $level ?? 'info';
    // Log the message
    $logger->log($message, $prefix, $level);
}

/**
 * Logs a message using the Anar Logger with 'general' prefix
 *
 * Convenience function for general-purpose logging. Uses 'general' prefix and 'debug' level by default.
 *
 * @param string $message The log message to record
 * @param string|null $level Log level ('info', 'error', 'warning', 'debug'). Defaults to 'debug' if null
 * @return void
 */
function anar_log($message, $level = null) {
    $logger = Anar\Core\Logger::get_instance();
    $level = $level ?? 'debug';
    $logger->log($message, 'general', $level);
}


/**
 * Checks if the product import process is currently in progress
 *
 * Determines if the Anar product import cron job is currently running by checking
 * if the import lock is active.
 *
 * @return bool True if import is in progress (not locked), false if import is locked/not running
 */
function anar_is_import_in_progress(){
    return !Anar\Import::is_create_products_cron_locked();
}

/**
 * Gets the user ID of the first administrator user
 *
 * Retrieves the ID of the first administrator user ordered by ID (lowest ID first).
 * Useful for assigning default ownership or notifications.
 *
 * @return int The administrator user ID, or 0 if no administrator is found
 */
function awca_get_first_admin_user_id() {
    $admins = get_users(array(
        'role'    => 'administrator',
        'orderby' => 'ID',
        'order'   => 'ASC',
        'number'  => 1
    ));

    if (!empty($admins)) {
        return $admins[0]->ID;
    }

    return 0; // Return 0 if no admin found
}

/**
 * Checks if Anar shipping feature is enabled
 *
 * Verifies if the Anar shipping integration is enabled via the plugin settings.
 *
 * @return bool True if Anar shipping is enabled ('yes'), false otherwise
 */
function anar_shipping_enabled() {
    $ship_to_stock = get_option('anar_conf_feat__anar_shipping', 'yes');

    if($ship_to_stock == 'yes') {
        return true;
    }
    return false;
}

/**
 * Checks if "Ship to Stock" feature is enabled
 *
 * Verifies if the Ship to Stock feature (allowing orders to be shipped to reseller stock)
 * is enabled via the plugin settings.
 *
 * @return bool True if Ship to Stock is enabled ('yes'), false otherwise
 */
function anar_is_ship_to_stock_enabled() {
    $ship_to_stock = get_option('anar_conf_feat__ship_to_stock', 'no');

    if($ship_to_stock == 'yes') {
        return true;
    }
    return false;
}

/**
 * Checks if an order can be shipped to reseller stock
 *
 * Determines if a specific order is eligible to be shipped to reseller stock.
 * This requires the Ship to Stock feature to be enabled and the order to meet
 * certain criteria defined by the Order class.
 *
 * @param int $order_id WooCommerce order ID
 * @return bool True if the order can be shipped to reseller stock, false otherwise
 */
function anar_order_can_ship_to_stock($order_id) {

    if(!anar_is_ship_to_stock_enabled())
        return false;

    $anar_order = \Anar\Order::get_instance();
    return $anar_order->canShipToResellerStock($order_id);
}

/**
 * Checks if an order can be shipped directly to the customer
 *
 * Determines if a specific order is eligible to be shipped directly to the end customer.
 * Uses the Order class to validate order-specific shipping rules.
 *
 * @param int $order_id WooCommerce order ID
 * @return bool True if the order can be shipped to customer, false otherwise
 */
function anar_order_can_ship_to_customer($order_id) {
    $anar_order = \Anar\Order::get_instance();
    return $anar_order->canShipToCustomer($order_id);
}


