<?php
function awca_combine_attributes_arrays($arrayOne, $arrayTwo)
{
    $combinedArray = [];

    foreach ($arrayOne as $key => $value) {
        $combinedArray[$key] = [
            'anarAttr' => $value,
            'wooAttr' => $arrayTwo[$key] ?? null
        ];
    }

    return $combinedArray;
}

function awca_map_product_variations($product_attributes, $combinedattributes)
{
    $mapped_attributes = [];
    foreach ($product_attributes as $anarAttr) {
        foreach ($combinedattributes as $atts) {
            if ($atts['anarAttr'] == $anarAttr['name']) {
                if ($atts['wooAttr'] == 'select' || $atts['wooAttr'] == null) {
                    $attribute_id = awca_create_woocommerce_attributes([$anarAttr]);
                    if ($attribute_id !== 0) {
                        $mapped_attributes[] = [
                            'name' => $anarAttr['name'],
                            'values' => $anarAttr['values']
                        ];
                    }
                } else {
                    $mapped_attributes[] = [
                        'name' => $anarAttr['name'],
                        'values' => $anarAttr['values']
                    ];
                }
                break;
            }
        }
    }
    return $mapped_attributes;
}


function awca_create_attribute_taxonomy($attribute)
{
    $attribute_taxonomy = wc_sanitize_taxonomy_name($attribute['name']);
    $existing_attributes = wc_get_attribute_taxonomies();

    $exists = false;
    foreach ($existing_attributes as $existing_attribute) {
        if ($existing_attribute->attribute_name === $attribute_taxonomy) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $attribute_id = wc_create_attribute(array(
            'name' => $attribute['name'],
            'slug' => $attribute_taxonomy,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false
        ));

        if (is_wp_error($attribute_id)) {
            throw new Exception('Error creating attribute: ' . $attribute_id->get_error_message());
        }
    }

    return $attribute_taxonomy;
}

function awca_create_attribute_terms($attribute_taxonomy, $values)
{
    foreach ($values as $option) {
        $term = term_exists($option, 'pa_' . $attribute_taxonomy);
        if (!$term) {
            $term = wp_insert_term($option, 'pa_' . $attribute_taxonomy);

            if (is_wp_error($term)) {
                throw new Exception('Error inserting term: ' . $term->get_error_message());
            }
        }
    }
}

function awca_create_woocommerce_attributes($product_attributes)
{
    try {
        foreach ($product_attributes as $attribute) {
            // Ensure the attribute exists and get its taxonomy name
            $attribute_taxonomy = awca_create_attribute_taxonomy($attribute);
            // Add terms to the attribute
            awca_create_attribute_terms($attribute_taxonomy, $attribute['values']);
        }
    } catch (Exception $e) {
        awca_log('Error in awca_create_woocommerce_attributes: ' . $e->getMessage());
        // Sentry\captureException($e);
        return 0;
    }
}


function awca_assign_attributes_to_product($product_id, $product_attributes)
{
    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    $attributes_data = [];
    foreach ($product_attributes as $attribute) {
        $taxonomy = wc_attribute_taxonomy_name($attribute['name']);
        $terms = [];
        foreach ($attribute['values'] as $option) {
            $term = get_term_by('name', $option, $taxonomy);
            if ($term) {
                $terms[] = $term->slug;
            }
        }

        $attributes_data[$taxonomy] = [
            'name' => $taxonomy,
            'value' => implode(' | ', $terms),
            'position' => 0,
            'is_visible' => 1,
            'is_variation' => 1,
            'is_taxonomy' => 1,
        ];
    }

    $product->set_attributes($attributes_data);
    $product->save();
}
