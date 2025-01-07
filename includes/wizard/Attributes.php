<?php

namespace Anar\Wizard;

use WC_Product_Attribute;

class Attributes{

    public function __construct(){
        add_action('wp_ajax_awca_handle_pair_attributes_ajax', [$this, 'pair_and_save_mapped_attributes_ajax']);
        add_action('wp_ajax_nopriv_awca_handle_pair_attributes_ajax', [$this, 'pair_and_save_mapped_attributes_ajax']);

        add_action( 'wp_ajax_awca_get_attributes_save_on_db_ajax', [$this, 'fetch_and_save_attributes_from_api_to_db_ajax'] );
        add_action( 'wp_ajax_nopriv_awca_get_attributes_save_on_db_ajax', [$this, 'fetch_and_save_attributes_from_api_to_db_ajax'] );
    }


    public function pair_and_save_mapped_attributes_ajax()
    {
        $attributeMap = $_POST['product_attributes'] ?? '';
        $attributeMapFirstTime = $_POST['anar-atts'] ?? '';

        if ($attributeMap == null || $attributeMap == '') {
            update_option('attributeMap', $attributeMapFirstTime);
        } else {
            update_option('attributeMap', $attributeMap);
        }

        $response = array(
            'success' => true,
            'message' => 'معادل سازی ویژگی ها ها با موفقیت ذخیره شد'
        );
        awca_log('attributes saved successfully');
        wp_send_json($response);
    }


    public function fetch_and_save_attributes_from_api_to_db_ajax()
    {
        $api_data_handler = new \Anar\ApiDataHandler('attributes', 'https://api.anar360.com/wp/attributes');
        $result = $api_data_handler->fetchAndStoreApiResponse();

        if ($result) {
            wp_send_json_success('attributes fetched and stored successfully.');
        } else {
            wp_send_json_error('Failed to fetch and store categories.');
        }

    }


    public static function create_attributes($attributes) {
        $attributeMap = get_option('attributeMap');
        $product_attributes = array();
        $attributeObjects = array();

        foreach ($attributes as $index => $product_attribute) {
            // use product_attribute data[key, name] to create new taxonomy
            $tax_slug = $product_attribute['key']; // ex: رنگ
            $tax_name = $product_attribute['name'];

            // Initialize attribute map item
            $attributeMapItem = null;

            // Check and find the matching attribute in the attribute map
            if ($attributeMap != null && $attributeMap != '') {
                if (isset($attributeMap[$product_attribute['name']])) {
                    $attributeMapItem = $attributeMap[$product_attribute['name']];

                    if (isset($attributeMapItem['name'])) {
                        $tax_name = $attributeMapItem['name'];
                    }


                    if (isset($attributeMapItem['map']) && $attributeMapItem['map'] !== 'select') {
                        $tax_slug = $attributeMapItem['map']; // the key of $attributeMapItem ex: رنگ-ووکامرس
                    } else {
                        $tax_slug = $product_attribute['name']; // the key of $attributeMapItem ex: رنگ انار
                    }

                } elseif (isset($attributeMap[$product_attribute['key']])) {
                    $attributeMapItem = $attributeMap[$product_attribute['key']];

                    if (isset($attributeMapItem['name'])) {
                        $tax_name = $attributeMapItem['name'];
                    }

                    if (isset($attributeMapItem['map']) && $attributeMapItem['map'] !== 'select') {
                        $tax_slug = $attributeMapItem['map']; // the key of $attributeMapItem ex: رنگ-ووکامرس
                    } else {
                        $tax_slug = $product_attribute['key']; // the key of $attributeMapItem ex: 64008cca64332f981a2908a7
                    }

                } else {
                    awca_log('#8 Attribute not found in attributeMap for $product_attribute = ' . print_r($product_attribute, true));
                }
            }

            // Ensure tax_name is not empty
            if (empty($tax_name)) {
                awca_log('#5 tax_name is empty after processing attributeMapItem.');
                continue; // Skip to the next attribute
            }

            $attributeId = wc_attribute_taxonomy_id_by_name($tax_slug);

            if ($attributeId == 0) {
                $args = array(
                    'name' => $tax_name,
                    'slug' => $tax_slug,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                );

                $attribute_id = wc_create_attribute($args);
                register_taxonomy('pa_' . $tax_slug, array('product'), []);
            }

            // The taxonomy created, let's add terms
            foreach ($product_attribute['values'] as $value) {
                if (!term_exists($value, 'pa_' . $tax_slug)) {
                    wp_insert_term($value, 'pa_' . $tax_slug);
                }
            }

            $attributeId = wc_attribute_taxonomy_id_by_name($tax_slug);
            $attribute = new WC_Product_Attribute();
            $attribute->set_id($attributeId);
            $attribute->set_position(0);
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $attribute->set_name('pa_' . $tax_slug);
            $attribute->set_options($product_attribute['values']);
            $attributeObjects[] = $attribute;
        }

        return $attributeObjects;
    }


    public static function get_final_attribute_name_from_attribute_and_category($attributeName, $categoryName)
    {
        return $attributeName . '-(' . $categoryName . ')';
    }
}