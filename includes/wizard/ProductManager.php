<?php
namespace Anar\Wizard;

use Anar\CronJob_Process_Products;
use Anar\ProductData;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductManager{

    protected static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        add_action( 'wp_ajax_awca_get_products_save_on_db_ajax', [$this, 'fetch_and_save_products_from_api_to_db_ajax'] );
        add_action( 'wp_ajax_nopriv_awca_get_products_save_on_db_ajax', [$this, 'fetch_and_save_products_from_api_to_db_ajax'] );

    }

    public function fetch_and_save_products_from_api_to_db_ajax() {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $products_api = "https://api.anar360.com/wp/products";
        $total_items_per_page = 0 ;
        $api_data_handler = new \Anar\ApiDataHandler('products', 'https://api.anar360.com/wp/products');
        $result = $api_data_handler->fetchAndStoreApiResponseByPage($page);
//    $result = awca_fetch_and_store_anar_api_response_by_page('products', $products_api, $page);
        if ($result !== false) {
            if($page == 1){
                // lock to prevent run again until all product created , after that we unset this lock
                update_option('awca_product_save_lock', true);

                // reset counter
                update_option('awca_proceed_products', 0);
            }
            $total_items = $result['total_items'];
            $total_products = $result['total_products']; // Assuming the API response includes total products count

            // Check if there are more pages to fetch
            $has_more = ($total_items > 0 && $page * 30 < $total_products);

            $response = array(
                'success' => true,
                'has_more' => $has_more,
                'total_added' => $total_items,
                'total_products' => $total_products
            );

            // allow to start create product cronjob
            if(!$has_more){
                CronJob_Process_Products::unlock_create_products_cron();
            }

            wp_send_json($response);
        } else {
            $response = array(
                'success' => false,
                'message' => 'مشکلی در ارتباط با سرور انار پیش آمده است.  '
            );
            wp_send_json($response);
        }
    }


    public static function create_wc_product($product_data, $attributeMap, $categoryMap) {
        set_time_limit(300);

        try {
            if (!function_exists('wc_get_product')) {
                return false;
            }

            $existing_product_id = ProductData::get_product_variation_by_anar_sku($product_data['sku']);
            $product_id = 0;
            $product_created = true;

            if ($existing_product_id) {
                $product = wc_get_product($existing_product_id);
                self::update_existing_product($product, $product_data, $attributeMap);
                $product_created = false;
            } else {
                $product = self::initialize_new_product($product_data);
                self::setup_new_product($product, $product_data, $attributeMap, $categoryMap);
            }

            $product_id = $product->get_id();
            self::update_common_meta_data($product_id, $product_data);

            return ['product_id' => $product_id, 'created' => $product_created];

        } catch (\Throwable $th) {
            awca_log('Error in awca_create_woocommerce_product: ' . $th->getMessage());
            return false;
        }
    }

    private static function update_existing_product($product, $product_data, $attributeMap) {
        $product_type = $product->get_type();

        if ($product_type == 'simple') {
            self::update_simple_product($product, $product_data);
        } elseif ($product_type == 'variable') {
            self::update_variable_product($product, $product_data, $attributeMap);
        }

        if (!empty($product_data['image'])) {
            update_post_meta($product->get_id(), '_product_image_url', $product_data['image']);
        }

        $product->save();
        awca_log('Product Exist: Type[' . $product->get_type() . '], Name: ' . $product->get_name() .
            ' ID: #' . $product->get_id() . ' SKU: ' . $product_data['sku']);
    }

    private static function update_simple_product($product, $product_data) {
        $product->set_price(awca_convert_price_to_woocommerce_currency($product_data['price']));
        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
        $product->set_stock_quantity($product_data['stock_quantity']);
        $product->set_manage_stock(true);
    }

    private static function update_variable_product($product, $product_data, $attributeMap) {
        // Delete variations
        $variations = $product->get_children();
        foreach ($variations as $variation_id) {
            wp_delete_post($variation_id, true);
        }

        // Reset and recreate attributes and variations
        $product->set_attributes(array());
        $product->save();

        self::setup_attributes_and_variations($product, $product_data, $attributeMap);
    }

    private static function initialize_new_product($product_data) {
        return !empty($product_data['attributes'])
            ? new WC_Product_Variable()
            : new WC_Product_Simple();
    }

    private static function setup_new_product($product, $product_data, $attributeMap, $categoryMap) {
        $product->set_name($product_data['name']);
        $product->set_status('draft');
        $product->set_description($product_data['description']);
        $product->set_category_ids(
            \Anar\wizard\Category::map_anar_product_cats_with_saved_cats($product_data['categories'], $categoryMap)
        );

        $product_id = $product->save();

        update_post_meta($product_id, '_anar_sku', $product_data['sku']);
        update_post_meta($product_id, '_anar_variant_id', $product_data['variants'][0]->_id);

        if (isset($product_data['attributes']) && !empty($product_data['attributes'])) {
            self::setup_attributes_and_variations($product, $product_data, $attributeMap);
        } else {
            self::setup_simple_product_data($product, $product_data);
        }
    }

    private static function setup_attributes_and_variations($product, $product_data, $attributeMap) {
        $attrsObject = Attributes::create_attributes($product_data['attributes']);
        $product->set_props(['attributes' => $attrsObject]);
        $product->save();
        self::create_product_variations($product, $product_data['variants'], $product_data['attributes'], $attributeMap);
    }

    private static function setup_simple_product_data($product, $product_data) {
        $product->set_price(awca_convert_price_to_woocommerce_currency($product_data['price']));
        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
        $product->set_stock_quantity($product_data['stock_quantity']);
        $product->set_manage_stock(true);
        $product->save();
    }

    private static function update_common_meta_data($product_id, $product_data) {
        // Shipments data
        update_post_meta($product_id, '_anar_shipments', $product_data['shipments']);
        update_post_meta($product_id, '_anar_shipments_ref', $product_data['shipments_ref']);
        update_post_meta($product_id, '_anar_products', 'true');

        // Image and gallery
        if (!empty($product_data['image'])) {
            update_post_meta($product_id, '_product_image_url', $product_data['image']);
        }

        if (isset($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
            update_post_meta($product_id, '_anar_gallery_images', $product_data['gallery_images']);
        }
    }


    public static function product_serializer($product)
    {
        if (!is_object($product)) {
            return null;
        }

        $prepared_product = array();


        // change array keys
        $prepared_product['image'] = isset($product->mainImage) ? $product->mainImage : '';
        $prepared_product['name'] = isset($product->title) ? $product->title : '';
        $prepared_product['sku'] = isset($product->id) ? $product->id : '';
        $prepared_product['description'] = isset($product->description) ? preg_replace('/<a.*>(.*)<\/a>/isU','$1',$product->description) : '';
        $prepared_product['regular_price'] = isset($product->variants[0]->price) ? $product->variants[0]->price : 0;
        $prepared_product['stock_quantity'] = isset($product->variants[0]->stock) ? $product->variants[0]->stock : 0;
        $prepared_product['categories'] = isset($product->categories) ? $product->categories : '';
        $prepared_product['category'] = isset($product->categories) ? Category::find_best_match_category_from_categories($product->categories) : '';
        $prepared_product['formatted_price'] = number_format($product->variants[0]->priceForResell);
        $prepared_product['shipments'] = isset($product->shipments) ? json_encode($product->shipments, JSON_UNESCAPED_UNICODE) : '';

        if(isset($product->shipmentsReferenceId) && isset($product->shipmentsReferenceState) && isset($product->shipmentsReferenceCity)){

            $prepared_product['shipments_ref'] = array(
                'shipmentsReferenceId' => $product->shipmentsReferenceId,
                'shipmentsReferenceState' => $product->shipmentsReferenceState,
                'shipmentsReferenceCity' => $product->shipmentsReferenceCity,
            );

        }


        $categories = isset($product->categories) ? $product->categories : array();
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = isset($category->name) ? $category->name : 'این دسته بندی است';
        }
        $prepared_product['categories'] = $category_names;


        $attributes = isset($product->attributes) ? $product->attributes : array();
        $attribute_data = array();

        foreach ($attributes as $attribute) {
            $attribute_key = isset($attribute->key) ? $attribute->key : '';
            $attribute_name = isset($attribute->name) ? $attribute->name : '';
            $attribute_values = isset($attribute->values) ? $attribute->values : array();

            $attribute_data[$attribute_key] = array(
                'name' => $attribute_name,
                'values' => $attribute_values,
                'key' => $attribute_key
            );
        }

        $prepared_product['attributes'] = $attribute_data;


        $variants = isset($product->variants) ? $product->variants : array();
        $variant_data = array();

        foreach ($variants as $variant) {
            $variant_data[] = $variant;
        }

        $prepared_product['variants'] = $variant_data;

        $gallery_images = array();
        if (isset($product->images) && is_array($product->images)) {
            foreach ($product->images as $image) {
                if (isset($image->_type) && $image->_type === 'image' && isset($image->src)) {
                    $gallery_images[] = $image->src;
                }
            }
        }
        $prepared_product['gallery_images'] = count($gallery_images) > 0 ? $gallery_images : false;

        return $prepared_product;
    }


    public static function create_product_variations($product, $variations, $attributes, $attributeMap) {
        foreach ($variations as $variation_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_regular_price(awca_convert_price_to_woocommerce_currency($variation_data->price));
            $variation->set_stock_quantity($variation_data->stock);
            $variation->set_manage_stock(true);

            $variation_attributes = array();
            if (!empty($attributes)) {
                $theseAttributesCalculated = [];
//            awca_log('---------------- start check --------------------');
//            awca_log('#0 $variation_data->attributes: ' . print_r($variation_data->attributes, true));
                foreach ($variation_data->attributes as $attr_key => $attr_value) {
                    $attr_name = $attributes[$attr_key]['name']; // Use the name directly from $attributes dictionary

//                awca_log('#1 : $attr_key  : ' . print_r($attr_key, true) .' - $attr_name:' . print_r($attr_name, true) .' - $attr_value:' . print_r($attr_value, true));

                    // if need to map
                    if ($attributeMap != null && $attributeMap != '' ) {

                        if(isset($attributeMap[$attr_name])){
                            $mapped_attribute = $attributeMap[$attr_name];
                        }elseif(isset($attributeMap[$attr_key])){
                            $mapped_attribute = $attributeMap[$attr_key];
                        }else{
                            continue; // Skip if mapping is incomplete
                        }

//                    awca_log('#2 : $mapped_attribute find : ' . print_r($mapped_attribute, true));

                        // Double check for find attribute
                        if (isset($mapped_attribute['name'])) {
                            $attr_name = $mapped_attribute['name'];
                        } else {
                            continue; // Skip if mapping is incomplete
                        }

                        if (isset($mapped_attribute['map'])) {
                            $attr_slug = ($mapped_attribute['map'] == "select") ? $mapped_attribute['key'] : $mapped_attribute['map'];
                        } else {
                            $attr_slug = $attr_name; // Fall back to attribute name
                        }

                        // Ensure consistent slug creation
                        $attr_slug = sanitize_title($attr_slug);
//                    awca_log('#3 : $attr_slug : ' . print_r($attr_slug, true));
                    } else {
                        // Use the attribute name directly for slug creation, if not in attributeMap
                        $attr_slug = sanitize_title($attr_name);
                    }

                    $theseAttributesCalculated['pa_' . $attr_slug] = sanitize_title($attr_value);
                }

//            awca_log("Attributes calculated for variation: " . print_r($theseAttributesCalculated, true));
            }

            if(isset($theseAttributesCalculated)) {
                $variation->set_attributes($theseAttributesCalculated);
            }

            $variation->save();

            $variation_id = $variation->get_id(); // Get the ID of the saved variation

            if ($variation_id) {
                update_post_meta($variation_id, '_anar_sku', $variation_data->_id);
                update_post_meta($variation_id, '_anar_variant_id', $variation_data->_id);
            } else {
                awca_log("Failed to save variation for product ID: " . $product->get_id());
            }
        }
    }



}