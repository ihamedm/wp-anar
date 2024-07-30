<?php

class AnarWooCommerceProductCreator
{
    private $product_data;

    public function __construct($product_data)
    {
        $this->product_data = $product_data;
    }

    public function createProduct()
    {
        // Check if WooCommerce is active
        if (!function_exists('wc_get_product')) {
            return false;
        }

        // Validate product data
        if (!$this->validateProductData($this->product_data)) {
            return false;
        }

        // Create product object
        $product = new WC_Product_Simple();

        // Set product data
        $product->set_name($this->product_data['name']);
        $product->set_regular_price($this->product_data['regular_price']);
        $product->set_description($this->product_data['description']);
        $product->set_short_description($this->product_data['short_description']);

        #notice product category creation has error
        // Check if categories exist, create them if necessary, and set category IDs
        // $category_ids = $this->getCategoryIds($this->product_data['categories']);
        // if ($category_ids === false) {
        //     return false; // Failed to get or create categories
        // }
        // $product->set_category_ids($category_ids);

        // Set product attributes
        // foreach ($this->product_data['attributes'] as $attribute) {
        //     $attribute_id = $this->getAttributeId($attribute['name']);
        //     if ($attribute_id === false) {
        //         // Attribute doesn't exist, create it
        //         $attribute_id = $this->createAttribute($attribute['name']);
        //         if (!$attribute_id) {
        //             return false; // Failed to create attribute
        //         }
        //     }
        //     // Set attribute value for the product
        //     $product->add_meta_data($attribute['name'], $attribute['value']);
        // }

        // Save product
        $product_id = $product->save();

        return $product_id;
    }

    private function getCategoryIds($categories)
    {
        $category_ids = array();
        foreach ($categories as $category_name) {
            $category = get_term_by('name', $category_name, 'product_cat');
            if (!$category) {
                // Category doesn't exist, create it
                $new_category_id = wp_insert_term(
                    $category_name,
                    'product_cat',
                    array(
                        'description' => $category_name,
                        'slug' => $category_name
                    )
                );
                if (is_wp_error($new_category_id)) {
                    // Failed to create category
                    return false;
                }
                $category_ids[] = $new_category_id['term_id'];
            } else {
                $category_ids[] = $category->term_id;
            }
        }
        return $category_ids;
    }

    private function getAttributeId($attribute_name)
    {
        $attribute = wc_attribute_taxonomy_name($attribute_name);
        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute);
        return $attribute_id ? $attribute_id : false;
    }

    private function createAttribute($attribute_name)
    {
        $attribute_label = ucfirst($attribute_name);
        $attribute_slug = wc_sanitize_taxonomy_name($attribute_name);
        $args = array(
            'name' => $attribute_label,
            'slug' => $attribute_slug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => true
        );
        $result = wc_create_attribute($args);
        return $result ? $result : false;
    }

    private function validateProductData($product_data)
    {
        // Validate required fields
        $required_fields = array('name', 'regular_price', 'description', 'short_description', 'categories', 'attributes');
        foreach ($required_fields as $field) {
            if (!isset($product_data[$field]) || empty($product_data[$field])) {
                return false;
            }
        }

        // Validate categories and attributes
        if (!is_array($product_data['categories']) || !is_array($product_data['attributes'])) {
            return false;
        }

        return true;
    }


    public static function create_attribute($raw_name = 'size', $terms = array('small'))
    {
        global $wpdb, $wc_product_attributes;

        // Make sure caches are clean.
        delete_transient('wc_attribute_taxonomies');
        WC_Cache_Helper::incr_cache_prefix('woocommerce-attributes');

        // These are exported as labels, so convert the label to a name if possible first.
        $attribute_labels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
        $attribute_name   = array_search($raw_name, $attribute_labels, true);

        if (!$attribute_name) {
            $attribute_name = wc_sanitize_taxonomy_name($raw_name);
        }

        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

        if (!$attribute_id) {
            $taxonomy_name = wc_attribute_taxonomy_name($attribute_name);

            // Degister taxonomy which other tests may have created...
            unregister_taxonomy($taxonomy_name);

            $attribute_id = wc_create_attribute(
                array(
                    'name'         => $raw_name,
                    'slug'         => $attribute_name,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => 0,
                )
            );

            // Register as taxonomy.
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy_name,
                    array(
                        'labels'       => array(
                            'name' => $raw_name,
                        ),
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    )
                )
            );

            // Set product attributes global.
            $wc_product_attributes = array();

            foreach (wc_get_attribute_taxonomies() as $taxonomy) {
                $wc_product_attributes[wc_attribute_taxonomy_name($taxonomy->attribute_name)] = $taxonomy;
            }
        }

        $attribute = wc_get_attribute($attribute_id);
        $return    = array(
            'attribute_name'     => $attribute->name,
            'attribute_taxonomy' => $attribute->slug,
            'attribute_id'       => $attribute_id,
            'term_ids'           => array(),
        );

        foreach ($terms as $term) {
            $result = term_exists($term, $attribute->slug);

            if (!$result) {
                $result = wp_insert_term($term, $attribute->slug);
                $return['term_ids'][] = $result['term_id'];
            } else {
                $return['term_ids'][] = $result['term_id'];
            }
        }

        return $return;
    }
}

// Define an array of product data
// $products_data = array(
//     array(
//         'name' => 'Awesome T-shirt',
//         'regular_price' => '21.99',
//         'description' => 'This is an awesome t-shirt!',
//         'short_description' => 'Comfortable and stylish',
//         'categories' => array('Clothing', 'T-shirts'), // Category names
//         'attributes' => array(
//             array(
//                 'name' => 'Size',
//                 'value' => 'Large'
//             ),
//             array(
//                 'name' => 'Color',
//                 'value' => 'Red'
//             )
//         )
//     ),
//     array(
//         'name' => 'Awesome pant',
//         'regular_price' => '210.000',
//         'description' => 'This is an awesome t-shirt!',
//         'short_description' => 'Comfortable and stylish',
//         'categories' => array('Clothing', 'T-shirts'), // Category names
//         'attributes' => array(
//             array(
//                 'name' => 'Material',
//                 'value' => 'Large'
//             ),
//             array(
//                 'name' => 'Color',
//                 'value' => 'Red'
//             )
//         )
//     ),

//     // Add more products here...
// );

// // Create products
// foreach ($products_data as $product_data) {
//     $product_creator = new WooCommerceProductCreator($product_data);

//     $product_id = $product_creator->createProduct();

//     if ($product_id) {
//         echo "Product created successfully. Product ID: " . $product_id . "<br>";
//         // $product_creator::create_attribute();
//     } else {
//         echo "Failed to create product.<br>";
//     }
// }
