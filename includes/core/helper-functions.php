<?php

use Anar\ApiDataHandler;

/**
 * @return false|mixed|null
 */
function anar_get_saved_token(){
    return Anar\Core\Activation::get_saved_activation_key();
}

function get_anar_icon($name, $size){
    return Anar\Core\Icons::get_sized_icon($name, $size);
}

/**
 * @param $string
 * @param $maxLength
 * @return string
 */
function awca_limit_chars($string, $maxLength): string
{
    // Ensure the string is in UTF-8 encoding
    $string = mb_convert_encoding($string, 'UTF-8', 'auto');

    // Truncate the string to the desired length
    return mb_strimwidth($string, 0, $maxLength, '...', 'UTF-8');
}


/**
 * @param $db_time
 * @return string
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
 * This function only compare input time that passed as argument with current time
 * @param $db_time
 * @return bool
 */
function awca_check_expiration_by_db_time($db_time): bool
{
    if(!$db_time)
        return true;

    $db_time_unix = strtotime($db_time);
    $current_time = current_time('timestamp');
    $time_difference = $current_time - $db_time_unix;

    return ($time_difference > 60) ?? false;
}



/**
 * @param $url
 * @return string
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
 * @return bool
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
 * @param $string
 * @return string
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
 * @param $message
 * @return void
 */
function awca_log($message, $prefix = 'general', $level = null) {
    // Create an instance of the logger class
    $logger = Anar\Core\Logger::get_instance();
    $level = $level ?? 'info';
    // Log the message
    $logger->log($message, $prefix, $level);
}

function anar_log($message, $level = null) {
    $logger = Anar\Core\Logger::get_instance();
    $level = $level ?? 'debug';
    $logger->log($message, 'general', $level);
}

function awca_is_import_products_running(){
    return !Anar\Import::is_create_products_cron_locked();
}


function awca_get_dokan_vendors() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return false;
    }

    $vendors = [];

    $results = dokan()->vendor->all();

    if ( ! empty( $results ) ) {
        foreach ( $results as $vendor ) {
            $vendors[] = [
                'id'     => $vendor->get_id(),
                'text'   => ! empty( $vendor->get_shop_name() ) ? $vendor->get_shop_name() : $vendor->get_name(),
            ];
        }
    }

    return $vendors;
}

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