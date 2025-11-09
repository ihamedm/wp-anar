<?php

use Anar\ProductData;

/**
 * Gets the Anar SKU for a WooCommerce product
 *
 * Retrieves the Anar SKU associated with a WooCommerce product by its product ID.
 * The SKU is stored in product metadata.
 *
 * @param int $wc_product_id WooCommerce product ID
 * @return string|WP_Error The Anar SKU if found, WP_Error otherwise
 */
function anar_get_product_anar_sku($wc_product_id){
    return \Anar\ProductData::get_anar_sku($wc_product_id);
}

/**
 * Gets the WooCommerce product ID by Anar SKU
 *
 * Finds the WooCommerce product ID that corresponds to a given Anar SKU.
 * Searches both the primary '_anar_sku' and backup '_anar_sku_backup' meta fields.
 *
 * @param string $anar_sku The Anar SKU to search for
 * @return int|WP_Error The WooCommerce product ID if found, WP_Error if not found
 */
function anar_get_product_sku($anar_sku){
    $wc_product_id = ProductData::get_simple_product_by_anar_sku($anar_sku);

    if (is_wp_error($wc_product_id)) {
        return $wc_product_id;
    }

    return $wc_product_id;
}

/**
 * Fetches Anar product data by WooCommerce product ID or Anar SKU
 *
 * Retrieves product data from the Anar API. Can search by either:
 * - WooCommerce product ID: First retrieves the Anar SKU from the product, then fetches data
 * - Anar SKU: Directly uses the provided SKU to fetch data
 *
 * @param int|string $value The WooCommerce product ID (if $by is 'ID') or Anar SKU (if $by is 'sku')
 * @param string $by Search method: 'ID' to search by WooCommerce product ID, 'sku' to search by Anar SKU. Default 'sku'
 * @return object|WP_Error The Anar product data object if successful, WP_Error otherwise
 */
function anar_fetch_product_data_by($value, $by = 'sku'){
    $sku = '';

    if($by == 'ID'){
        $sku = anar_get_product_anar_sku($value);
        if(is_wp_error($sku)){
            return $sku;
        }
    }
    elseif($by == 'sku'){
        $sku = $value;
    }

    return \Anar\ProductData::fetch_anar_product($sku);
}