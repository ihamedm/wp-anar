<?php

/**
 * @return false|mixed|null
 */
function awca_get_activation_key(){
   return Anar\Core\Activation::get_saved_activation_key();
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
    $image_downloader = new \Anar\Core\Image_Downloader();
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

function awca_get_formatted_price($anar_price){
    $converted_price = awca_convert_price_to_woocommerce_currency($anar_price);
    $currency_symbol = get_woocommerce_currency_symbol();
    $thousand_separator = ',';
    $decimal_separator = '.';

    // Format the price
    $formatted_price = number_format(floatval(preg_replace('/[^\d.]/', '', $converted_price)), 0, $decimal_separator, $thousand_separator);

    return $formatted_price . ' ' . $currency_symbol;
}


/**
 * @return bool
 */
function awca_is_hpos_enable(): bool
{
    return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}


/**
 * @param $string
 * @return mixed|string
 */
function awca_translator($string){

    if ($string === 'bike') {
        return 'پیک موتوری';
    } elseif ($string === 'post') {
        return 'پست';
    } elseif ($string === 'express') {
        return 'اکسپرس';
    } elseif ($string === 'unpaid') {
        return 'پرداخت نشده';
    } elseif ($string === 'paid') {
        return 'پرداخت شده';
    } else {
        return $string;
    }
}


/**
 * @param $message
 * @return void
 */
function awca_log($message) {
    // Create an instance of the logger class
    $logger = Anar\Core\Logger::get_instance();

    // Log the message
    $logger->log($message);
}


/**
 * @param $api_url
 * @return false|mixed
 * @deprecated use \ApiDataHandler instead
 */
function awca_call_anar_api($api_url)
{
    $retries = 5;
    $retry_delay = 10; // seconds
    $token = awca_get_activation_key();

    while ($retries > 0) {
        try {
            $response = \Anar\ApiDataHandler::callAnarApi($api_url);

            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                $data = json_decode($response['body']);
                return $data;
            } else {
                $error_message = '';
                if (is_array($response)) {
                    $error_message = $response['response']['message'];
                } elseif (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                } else {
                    $error_message = 'Unknown error';
                }
                awca_log('Failed to fetch data from API: ' . $api_url . '  Error: ' . $error_message . '. Retries left: ' . ($retries - 1));
                sleep($retry_delay); // wait before retrying
            }
        } catch (Exception $e) {
            awca_log('Exception caught while fetching data from API: ' . $e->getMessage() . '. Retries left: ' . ($retries - 1));
            sleep($retry_delay); // wait before retrying
        }

        $retries--;
    }

    awca_log('Failed to fetch data from API after multiple retries: ' . $api_url);
    return false;
}



function awca_is_import_products_running(){
    return !Anar\CronJob_Process_Products::is_create_products_cron_locked();
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