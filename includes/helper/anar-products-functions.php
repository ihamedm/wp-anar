<?php
function awca_product_list_serializer($product)
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
    $prepared_product['category'] = isset($product->categories) ? awca_find_best_match_category_from_categories($product->categories) : '';
    $prepared_product['formatted_price'] = awca_product_price_digits_seprator($product->variants[0]->priceForResell);
    $prepared_product['shipments'] = isset($product->shipments) ? json_encode($product->shipments) : '';

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

function awca_create_woocommerce_product($product_data, $combinedCategories, $attributeMap, $categoryMap)
{

    set_time_limit(300);

    try {
        if (!function_exists('wc_get_product')) {
            return false;
        }
        
//        $existing_product_id = wc_get_product_id_by_sku($product_data['sku']);
        $existing_product_id = awca_get_product_variation_by_anar_sku($product_data['sku']);
        $product_id = 0;
        $product_created = true;
        if ($existing_product_id) {
            $product = wc_get_product($existing_product_id);
            $product_id = $product->save();
            $product_created = false;
        } else {
            if (isset($product_data['attributes']) && !empty($product_data['attributes'])) {
                $product = new WC_Product_Variable();
            } else {
                $product = new WC_Product_Simple();
            }
            $product->set_name($product_data['name']);
            $product->set_status('draft'); // Set the product status to draft
            $product->set_description($product_data['description']);
//            $product->set_sku($product_data['sku']);
            $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
            $product->set_category_ids(awca_map_product_categories($product_data['categories'], $combinedCategories, $categoryMap));
            
            $product_id = $product->save();

            update_post_meta($product_id, '_anar_sku', $product_data['sku']);

            if (isset($product_data['attributes']) && !empty($product_data['attributes'])) {
                $attrsObject = awca_set_product_attributes($product, $product_data['attributes'], $product_data['category'], $attributeMap);
                $product->set_props(array(
                    'attributes'        => $attrsObject,
                ));
                
                $product_id = $product->save();
                awca_create_product_variations($product, $product_data['variants'], $product_data['attributes'], $product_data['category'], $attributeMap);
            } else {
                $product->set_price(awca_convert_price_to_woocommerce_currency($product_data['price']));
                $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
                $product->set_stock_quantity($product_data['stock_quantity']);
                $product->set_manage_stock(true);
                $product->save();
            }

            if (!empty($product_data['image'])) {
                $image_url = $product_data['image'];
                update_post_meta($product_id, '_product_image_url', $image_url);
            }

            if (isset($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
                update_post_meta($product_id, '_anar_gallery_images', $product_data['gallery_images']);
            }
            update_post_meta($product_id, '_anar_products', 'true');

            // shipments data
            update_post_meta($product_id, '_anar_shipments', $product_data['shipments']);
            update_post_meta($product_id, '_anar_shipments_ref', $product_data['shipments_ref']);

            awca_log('------- Product Created ----------------' . print_r($product_id, true) . '-----');
        }
        return ['product_id' => $product_id , 'created' => $product_created];
    } catch (\Throwable $th) {
        awca_log('Error in awca_create_woocommerce_product: ' . $th->getMessage());
        return false;
    }
}

function awca_set_product_attributes($product, $attributes, $category, $attributeMap) {
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



function awca_create_product_variations($product, $variations, $attributes, $category, $attributeMap) {
    $parent_id = $product->get_id();
    foreach ($variations as $variation_data) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->set_regular_price(awca_convert_price_to_woocommerce_currency($variation_data->price));
        $variation->set_stock_quantity($variation_data->stock);
//        $variation->set_sku($variation_data->_id);
        $variation->set_manage_stock(true);

        $variation_attributes = array();
        if (!empty($attributes)) {
            $theseAttributesCalculated = [];
            foreach ($variation_data->attributes as $attr_key => $attr_value) {
                $attr_label = awca_get_final_attribute_name_from_attribute_and_category($attributes[$attr_key]['name'], $category);
                $attr_slug = awca_slugify($attr_label);
                $attr_name = awca_hashIT($attr_slug);

                // if need to map
                if ($attributeMap != null && $attributeMap != '' && isset($attributeMap[$attr_slug]) && $attributeMap[$attr_slug] != "select" && $attributeMap[$attr_slug] != null) {
                    $attr_slug = $attributeMap[$attr_slug];
                    $attr_name = ($attr_slug);
                }

                $theseAttributesCalculated['pa_'.sanitize_title($attr_name)] = sanitize_title($attr_value);
            }
        }

        $variation->set_props( array(
            'attributes'        => $theseAttributesCalculated,
        ));

        $variation->save();

        $variation_id = $variation->get_id(); // Get the ID of the saved variation
        if ($variation_id) {
            update_post_meta($variation_id, '_anar_sku', $variation_data->_id);
        } else {
            awca_log("Failed to save variation for product ID: $parent_id");
        }
    }
}





function awca_get_anar_sku($product_id){
    return get_post_meta($product_id, '_anar_sku', true);
}