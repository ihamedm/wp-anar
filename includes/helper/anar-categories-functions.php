<?php
function awca_combine_cats_arrays($arrayOne, $arrayTwo)
{
    $combinedArray = [];

    foreach ($arrayOne as $key => $value) {
        $combinedArray[$key] = [
            'anarCat' => $value,
            'wooCat' => $arrayTwo[$key] ?? null
        ];
    }

    return $combinedArray;
}

function awca_create_woocommerce_category($category_name)
{
    try {
        $existing_category = get_term_by('name', $category_name, 'product_cat');

        if (!$existing_category) {
            $term = wp_insert_term(
                $category_name,
                'product_cat'
            );

            if (!is_wp_error($term)) {
                return $term['term_id'];
            } else {
                throw new Exception('Category creation failed: ' . $term->get_error_message());
            }
        } else {
            return $existing_category->term_id;
        }
    } catch (Exception $e) {
        awca_log('Error: ' . $e->getMessage());

        // Sentry\captureException($e);

        return 0;
    }
}

function awca_map_product_categories($product_categories, $combinedCategories, $categoryMap)
{
    $mapped_categories = [];
    $IDs = [];
    foreach ($product_categories as $categoryName) {
        $newCategoryName = $categoryMap[$categoryName];
        if ($newCategoryName == 'select') {
            $category_id = awca_create_woocommerce_category($categoryName);
            $IDs[] = $category_id;
        } else {
            $product_cat = get_term_by( 'name', $newCategoryName, 'product_cat' );
            $category_id = $product_cat->term_id;
            $IDs[] = $category_id;
        }
    }

    return $IDs;
}
