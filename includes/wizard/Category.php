<?php

namespace Anar\Wizard;

use Anar\ApiDataHandler;
use Exception;

class Category{

    public function __construct(){
        add_action('wp_ajax_awca_handle_pair_categories_ajax', [$this, 'pair_and_save_mapped_categories_ajax']);
        add_action('wp_ajax_nopriv_awca_handle_pair_categories_ajax', [$this, 'pair_and_save_mapped_categories_ajax']);

        add_action( 'wp_ajax_awca_get_categories_save_on_db_ajax', [$this, 'fetch_and_save_categories_from_api_to_db_ajax'] );
        add_action( 'wp_ajax_nopriv_awca_get_categories_save_on_db_ajax', [$this, 'fetch_and_save_categories_from_api_to_db_ajax'] );
    }



    public function pair_and_save_mapped_categories_ajax()
    {
        $anarCats = $_POST['anar-cats'];
        $wooCats = $_POST['product_categories'];

        set_transient('_anar_api_categories_transient', $anarCats, WEEK_IN_SECONDS);
        set_transient('_anar_woocomerce_categories_transient', $wooCats, WEEK_IN_SECONDS);

        $categoryMap = [];
        foreach($anarCats as $key => $anarCat) {
            $categoryMap[$anarCat] = $wooCats[$key];
        }

        update_option('categoryMap', $categoryMap);

        if ($anarCats !== false && $wooCats !== false) {
            $combinedCategories = $this->combine_anar_and_wc_cat_arrays($anarCats, $wooCats);
            update_option('combinedCategories', $combinedCategories);
        } else {
            $response = array(
                'success' => false,
                'message' => 'اطلاعات به درستی ارسال نشده است',
            );
            wp_send_json_error($response);
            return;
        }

        foreach($categoryMap as $key => $val) {
            if ($val == 'select') {
                $cat_id = self::create_woocommerce_category($key);
            }
        }

        $response = array(
            'success' => true,
            'message' => 'معادل سازی دسته بندی ها با موفقیت ذخیره شد'
        );
        awca_log('categories saved successfully');
        wp_send_json($response);
    }


    public function fetch_and_save_categories_from_api_to_db_ajax() {
        $api_data_handler = new ApiDataHandler('categories', 'https://api.anar360.com/wp/categories');
        $result = $api_data_handler->fetchAndStoreApiResponse();
        if ($result) {
            wp_send_json_success('Categories fetched and stored successfully.');
        } else {
            wp_send_json_error('Failed to fetch and store categories.');
        }
    }


    private function combine_anar_and_wc_cat_arrays($arrayOne, $arrayTwo): array
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


    public static function create_woocommerce_category($category_name)
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
            return 0;
        }
    }


    public static function map_anar_product_cats_with_saved_cats($product_categories, $categoryMap)
    {
        $IDs = [];
        foreach ($product_categories as $categoryName) {
            $newCategoryName = $categoryMap[$categoryName];
            if ($newCategoryName == 'select') {
                $category_id = self::create_woocommerce_category($categoryName);
                $IDs[] = $category_id;
            } else {
                $product_cat = get_term_by( 'name', $newCategoryName, 'product_cat' );
                $category_id = $product_cat->term_id;
                $IDs[] = $category_id;
            }
        }

        return $IDs;
    }


    public static function find_best_match_category_from_categories($categories)
    {
        $longestRouteLen = 0;
        $matchCategoryName = "Not Found";
        foreach($categories as $category) {
            $route = $category->route;
            $routeLen = count($route);
            if ($routeLen > $longestRouteLen) {
                $longestRouteLen = $routeLen;
                $matchCategoryName = end($route);
            }
        }

        return $matchCategoryName;
    }

}