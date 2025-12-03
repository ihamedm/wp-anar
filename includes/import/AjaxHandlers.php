<?php

namespace Anar\Import;

use Anar\AnarDataRepository;
use Anar\AnarDataItemRepository;
use Anar\ApiDataHandler;
use Anar\JobManager;
use WP_Error;

class AjaxHandlers
{
    private string $nonce_action = 'awca_import_v2_nonce';
    private AnarDataRepository $repository;
    private ProductsRepository $products_repository;
    private BackgroundImporter $background_importer;

    public function __construct()
    {
        $this->repository = AnarDataRepository::get_instance();
        $this->products_repository = new ProductsRepository();
        $this->background_importer = BackgroundImporter::get_instance();

        add_action('wp_ajax_awca_import_v2_fetch_categories', [$this, 'fetch_categories']);
        add_action('wp_ajax_awca_import_v2_fetch_attributes', [$this, 'fetch_attributes']);
        add_action('wp_ajax_awca_import_v2_fetch_products', [$this, 'fetch_products']);
        add_action('wp_ajax_awca_import_v2_get_wc_categories', [$this, 'get_wc_categories']);
        add_action('wp_ajax_awca_import_v2_save_category_map', [$this, 'save_category_map']);
        add_action('wp_ajax_awca_import_v2_get_wc_attributes', [$this, 'get_wc_attributes']);
        add_action('wp_ajax_awca_import_v2_save_attribute_map', [$this, 'save_attribute_map']);
        add_action('wp_ajax_awca_import_v2_start_creation', [$this, 'start_product_creation']);
        add_action('wp_ajax_awca_import_v2_create_product', [$this, 'create_product_v2']);
        add_action('wp_ajax_awca_import_v2_get_progress', [$this, 'get_creation_progress']);
        add_action('wp_ajax_awca_import_v2_cancel_creation', [$this, 'cancel_product_creation']);
        add_action('wp_ajax_awca_import_v2_trigger_batch', [$this, 'trigger_batch_processing']);
        add_action('wp_ajax_awca_import_v2_create_single_product', [$this, 'create_single_product']);
    }

    public function get_wc_categories(): void
    {
        $this->verify_request();

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = 20;

        $args = [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => ($page - 1) * $per_page,
        ];

        if ($search) {
            $args['search'] = $search;
        }

        $terms = get_terms($args);

        if ($terms instanceof \WP_Error) {
            wp_send_json_error([
                'message' => $terms->get_error_message(),
            ]);
        }

        $results = array_map(
            static function ($term) {
                return [
                    'id'   => $term->term_id,
                    'text' => $term->name,
                ];
            },
            $terms
        );

        $has_more = count($terms) === $per_page;

        wp_send_json_success([
            'results'    => $results,
            'pagination' => ['more' => $has_more],
        ]);
    }

    public function save_category_map(): void
    {
        $this->verify_request();

        $anar_category_id = isset($_POST['anar_category_id']) ? sanitize_text_field(wp_unslash($_POST['anar_category_id'])) : '';
        $anar_category_name = isset($_POST['anar_category_name']) ? sanitize_text_field(wp_unslash($_POST['anar_category_name'])) : '';
        $wc_category_id = isset($_POST['wc_category_id']) ? (int) $_POST['wc_category_id'] : 0;
        $remove = !empty($_POST['remove']);

        if (empty($anar_category_id)) {
            wp_send_json_error([
                'message' => __('شناسه دسته‌بندی انار نامعتبر است.', 'wp-anar'),
            ]);
        }

        $manager = new CategoryManager();

        try {
            if ($remove || $wc_category_id === 0) {
                $manager->remove_mapping($anar_category_id);

                wp_send_json_success([
                    'removed' => true,
                    'anar_id' => $anar_category_id,
                ]);
            }

            $mapping = $manager->save_mapping($anar_category_id, $wc_category_id, $anar_category_name);

            wp_send_json_success([
                'removed' => false,
                'mapping' => $mapping,
            ]);
        } catch (\Throwable $throwable) {
            wp_send_json_error([
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    public function get_wc_attributes(): void
    {
        $this->verify_request();
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        $manager = new AttributeManager();
        $taxonomies = $manager->get_woocommerce_attributes($search);

        $results = array_map(
            static function ($taxonomy) {
                return [
                    'id'    => $taxonomy->attribute_id,
                    'text'  => $taxonomy->attribute_label,
                    'slug'  => $taxonomy->attribute_name,
                ];
            },
            $taxonomies
        );

        // Ensure results is a proper indexed array (not an associative array)
        $results = array_values($results);

        wp_send_json_success([
            'results'    => $results,
            'pagination' => ['more' => false],
        ]);
    }

    public function save_attribute_map(): void
    {
        $this->verify_request();

        $anar_attribute_key = isset($_POST['anar_attribute_key']) ? sanitize_text_field(wp_unslash($_POST['anar_attribute_key'])) : '';
        $anar_attribute_name = isset($_POST['anar_attribute_name']) ? sanitize_text_field(wp_unslash($_POST['anar_attribute_name'])) : '';
        $wc_attribute_id = isset($_POST['wc_attribute_id']) ? (int) $_POST['wc_attribute_id'] : 0;
        $remove = !empty($_POST['remove']);

        // Use key as primary identifier (stable from API, variants use keys)
        // Fallback to name if key is empty (shouldn't happen, but for safety)
        $anar_key = !empty($anar_attribute_key) ? $anar_attribute_key : $anar_attribute_name;

        if (empty($anar_key)) {
            wp_send_json_error([
                'message' => __('کلید یا نام ویژگی انار نامعتبر است.', 'wp-anar'),
            ]);
        }

        $manager = new AttributeManager();

        try {
            if ($remove || $wc_attribute_id === 0) {
                $manager->remove_mapping($anar_key);
                wp_send_json_success([
                    'removed'  => true,
                    'anar_key' => $anar_key,
                    'anar_name' => $anar_attribute_name,
                ]);
            }

            // Save mapping using key as primary identifier, with name for display
            $mapping = $manager->save_mapping($anar_key, $wc_attribute_id, $anar_attribute_name);
            wp_send_json_success([
                'removed' => false,
                'mapping' => $mapping,
            ]);
        } catch (\Throwable $throwable) {
            wp_send_json_error([
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    public function start_product_creation(): void
    {
        $this->verify_request();

        try {
            $job = $this->background_importer->start_job();
            wp_send_json_success([
                'job' => $job,
                'message' => __('ساخت محصولات در پس‌زمینه آغاز شد.', 'wp-anar'),
                'logs' => $this->background_importer->get_ui_logs(),
                'estimated_remaining_minutes' => $this->calculate_estimated_remaining_minutes($job),
            ]);
        } catch (\Throwable $throwable) {
            wp_send_json_error([
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Calculate estimated remaining time in minutes based on job progress
     * Uses stabilized calculation with minimum sample size and cron delay consideration
     * 
     * @param array|null $job Job data
     * @return int|null Estimated remaining minutes or null
     */
    private function calculate_estimated_remaining_minutes(?array $job): ?int
    {
        if (!$job || empty($job['start_time']) || $job['status'] !== 'in_progress') {
            return null;
        }

        $start_time = strtotime($job['start_time']);
        $current_time = current_time('timestamp');
        $processed = (int) ($job['processed_products'] ?? 0);
        $total = (int) ($job['total_products'] ?? 0);
        
        // Require minimum sample size for stable calculation (at least 60 products = 2 batches)
        $min_sample_size = 60;
        if ($processed < $min_sample_size || $processed === 0 || $total <= $processed || $start_time <= 0) {
            return null;
        }

        $elapsed_seconds = $current_time - $start_time;
        
        // Account for cron delays: each batch has ~30 seconds delay
        // Estimate number of batches processed: processed / batch_size (default 30)
        $batch_size = 30; // DEFAULT_BATCH_SIZE from BackgroundImporter
        $batches_processed = max(1, ceil($processed / $batch_size));
        $cron_delay_per_batch = 30; // seconds between batches
        $total_cron_delay = ($batches_processed - 1) * $cron_delay_per_batch;
        
        // Subtract cron delays from elapsed time to get actual processing time
        $actual_processing_seconds = max(1, $elapsed_seconds - $total_cron_delay);
        
        // Calculate processing rate (products per second)
        $rate_per_second = $processed / $actual_processing_seconds;
        
        if ($rate_per_second > 0) {
            $remaining = $total - $processed;
            
            // Calculate remaining batches needed
            $remaining_batches = ceil($remaining / $batch_size);
            
            // Calculate processing time for remaining products
            $processing_time_seconds = $remaining / $rate_per_second;
            
            // Add cron delays for remaining batches
            $remaining_cron_delay = ($remaining_batches - 1) * $cron_delay_per_batch;
            
            // Total estimated seconds = processing time + cron delays
            $estimated_seconds_remaining = $processing_time_seconds + max(0, $remaining_cron_delay);
            
            // Round up to minutes (always round up for conservative estimate)
            $estimated_minutes_remaining = max(1, (int) ceil($estimated_seconds_remaining / 60));
            
            // Apply exponential smoothing to reduce volatility
            // Store previous estimate in transient for smoothing
            $transient_key = 'anar_estimated_minutes_' . ($job['job_id'] ?? '');
            $previous_estimate = get_transient($transient_key);
            
            if ($previous_estimate !== false && is_numeric($previous_estimate)) {
                // Use exponential smoothing: new_estimate = alpha * current + (1-alpha) * previous
                // Lower alpha (0.3) = more smoothing, less volatility
                $alpha = 0.3;
                $smoothed_estimate = round($alpha * $estimated_minutes_remaining + (1 - $alpha) * $previous_estimate);
                $estimated_minutes_remaining = max(1, $smoothed_estimate);
            }
            
            // Store current estimate for next calculation
            set_transient($transient_key, $estimated_minutes_remaining, 300); // 5 minutes expiry
            
            return $estimated_minutes_remaining;
        }

        return null;
    }

    public function get_creation_progress(): void
    {
        $this->verify_request();
        $job = $this->background_importer->get_active_job_status();

        wp_send_json_success([
            'job' => $job,
            'pending_products' => $this->products_repository->count_pending(),
            'logs' => $this->background_importer->get_ui_logs(),
            'estimated_remaining_minutes' => $this->calculate_estimated_remaining_minutes($job),
        ]);
    }

    public function cancel_product_creation(): void
    {
        $this->verify_request();

        $job = $this->background_importer->get_active_job_status();

        if (!$job) {
            wp_send_json_error([
                'message' => __('فرآیند فعالی برای توقف یافت نشد.', 'wp-anar'),
            ]);
        }

        $job_id = $job['job_id'];

        $this->background_importer->cancel_active_job(__('درخواست توسط کاربر لغو شد.', 'wp-anar'));
        $job_manager = JobManager::get_instance();
        $job = $job_manager->get_job_by_id($job_id);

        wp_send_json_success([
            'job' => $job,
            'pending_products' => $this->products_repository->count_pending(),
            'message' => __('فرآیند ساخت محصولات متوقف شد.', 'wp-anar'),
            'logs' => $this->background_importer->get_ui_logs(),
            'estimated_remaining_minutes' => $this->calculate_estimated_remaining_minutes($job),
        ]);
    }

    /**
     * Manually trigger batch processing (fallback if WP Cron is slow)
     */
    public function trigger_batch_processing(): void
    {
        $this->verify_request();

        $job = $this->background_importer->get_active_job_status();

        if (!$job) {
            wp_send_json_error([
                'message' => __('فرآیند فعالی برای پردازش یافت نشد.', 'wp-anar'),
            ]);
        }

        try {
            $triggered = $this->background_importer->trigger_batch_processing();
            
            if ($triggered) {
                $job = $this->background_importer->get_active_job_status();
                wp_send_json_success([
                    'job' => $job,
                    'pending_products' => $this->products_repository->count_pending(),
                    'message' => __('بسته به صورت دستی پردازش شد.', 'wp-anar'),
                    'logs' => $this->background_importer->get_ui_logs(),
                    'estimated_remaining_minutes' => $this->calculate_estimated_remaining_minutes($job),
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('امکان پردازش بسته وجود ندارد.', 'wp-anar'),
                ]);
            }
        } catch (\Throwable $throwable) {
            wp_send_json_error([
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Create a single WooCommerce product by directly fetching an Anar SKU.
     */
    public function create_single_product(): void
    {
        $this->verify_request();

        $anar_sku = isset($_POST['anar_sku']) ? sanitize_text_field(wp_unslash($_POST['anar_sku'])) : '';

        if (empty($anar_sku)) {
            wp_send_json_error([
                'message' => __('لطفاً شناسه SKU انار را وارد کنید.', 'wp-anar'),
            ]);
        }

        $anar_product = anar_fetch_product_data_by($anar_sku, 'sku');
        if (is_wp_error($anar_product)) {
            wp_send_json_error([
                'message' => $anar_product->get_error_message(),
            ]);
        }

        $result = anar_create_single_product($anar_product);
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => __('محصول با موفقیت ساخته شد.', 'wp-anar'),
            'product_id' => $result['product_id'] ?? 0,
            'created' => $result['created'] ?? false,
            'logs' => $result['logs'] ?? [],
        ]);
    }

    private function verify_request(): void
    {
        check_ajax_referer($this->nonce_action, 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('شما مجوز انجام این عملیات را ندارید.', 'wp-anar'),
            ], 403);
        }
    }

    private function decode_response(array|WP_Error $response)
    {
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_message(),
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            $message = wp_remote_retrieve_response_message($response);
            wp_send_json_error([
                'message' => $message ?: __('پاسخ نامعتبر از سرور انار دریافت شد.', 'wp-anar'),
            ]);
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            wp_send_json_error([
                'message' => __('پاسخ دریافتی از سرور انار خالی است.', 'wp-anar'),
            ]);
        }

        $data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('امکان پردازش پاسخ دریافتی وجود ندارد.', 'wp-anar'),
            ]);
        }

        return $data;
    }

    public function fetch_categories(): void
    {
        $this->verify_request();

        $item_repository = AnarDataItemRepository::get_instance();
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $limit = 30;

        // Clear all categories on first page
        if ($page === 1) {
            $item_repository->delete_all_by_key('category');
        }

        // Fetch with pagination
        $url = add_query_arg(
            [
                'page'  => $page,
                'limit' => $limit,
            ],
            ApiDataHandler::getApiUrl('categories')
        );

        $response = ApiDataHandler::callAnarApi($url);
        $data = $this->decode_response($response);

        $items = $data->items ?? [];
        $total = isset($data->total) ? (int) $data->total : 0;
        $has_more = ($page * $limit) < $total;

        // Save each category as individual record
        $saved_count = 0;
        foreach ($items as $item) {
            $item_array = (array) $item;
            $anar_id = $item_array['_id'] ?? ($item_array['id'] ?? '');
            
            if (empty($anar_id)) {
                continue;
            }

            if ($item_repository->save_item('category', $anar_id, $item_array)) {
                $saved_count++;
            }
        }

        wp_send_json_success([
            'total' => $total ?: $item_repository->count_by_key('category'),
            'saved' => $saved_count,
            'page' => $page,
            'has_more' => $has_more,
            'message' => __('دسته‌بندی‌ها با موفقیت دریافت شدند.', 'wp-anar'),
        ]);
    }

    public function fetch_attributes(): void
    {
        $this->verify_request();

        $item_repository = AnarDataItemRepository::get_instance();
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $limit = 30;

        // Clear all attributes on first page
        if ($page === 1) {
            $item_repository->delete_all_by_key('attribute');
        }

        // Fetch with pagination
        $url = add_query_arg(
            [
                'page'  => $page,
                'limit' => $limit,
            ],
            ApiDataHandler::getApiUrl('attributes')
        );

        $response = ApiDataHandler::callAnarApi($url);
        $data = $this->decode_response($response);

        $items = $data->items ?? [];
        $total = isset($data->total) ? (int) $data->total : 0;
        $has_more = ($page * $limit) < $total;

        // Save each attribute as individual record
        // Attributes use 'key' as their unique identifier (not _id)
        $saved_count = 0;
        foreach ($items as $item) {
            $item_array = (array) $item;
            // Attributes don't have _id, they use 'key' as identifier
            $anar_id = $item_array['key'] ?? ($item_array['_id'] ?? ($item_array['id'] ?? ''));
            
            if (empty($anar_id)) {
                continue;
            }

            if ($item_repository->save_item('attribute', $anar_id, $item_array)) {
                $saved_count++;
            }
        }

        wp_send_json_success([
            'total' => $total ?: $item_repository->count_by_key('attribute'),
            'saved' => $saved_count,
            'page' => $page,
            'has_more' => $has_more,
            'message' => __('ویژگی‌ها با موفقیت دریافت شدند.', 'wp-anar'),
        ]);
    }

    public function fetch_products(): void
    {
        $this->verify_request();

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $limit = 30;
        
        // Initialize item repository for new table operations
        $item_repository = AnarDataItemRepository::get_instance();

        if ($page === 1) {
            $this->background_importer->cancel_active_job('Staging reset by user.');
            $this->repository->delete('products');
            $this->products_repository->reset();
            
            // Also clear products from new anar_data table
            $item_repository->delete_all_by_key('product');
        }

        $url = add_query_arg(
            [
                'page'  => $page,
                'limit' => $limit,
            ],
            ApiDataHandler::getApiUrl('products')
        );

        $response = ApiDataHandler::callAnarApi($url);
        $data = $this->decode_response($response);

        $items = $data->items ?? [];
        $total = isset($data->total) ? (int) $data->total : ($page * $limit);
        $has_more = ($page * $limit) < $total;

        // Also save products to new anar_data table
        // For products: key = 'product', _id = product id (products use id as identifier)
        $saved_to_new_table = 0;
        foreach ($items as $item) {
            $item_array = (array) $item;
            // Try different possible SKU field names
            $anar_sku = $item_array['id'] ?? '';
            
            if (empty($anar_sku)) {
                // Log if SKU is missing for debugging
                anar_log("Product missing SKU field. Available keys: " . implode(', ', array_keys($item_array)), 'warning');
                continue;
            }

            // Products: use anar_sku for both key and _id
            $saved = $item_repository->save_item('product', $anar_sku, $item_array);
            if ($saved) {
                $saved_to_new_table++;
            } else {
                anar_log("Failed to save product with SKU '{$anar_sku}' to new table", 'error');
            }
        }

        // Stage products directly to wp_anar_products table (no legacy page storage needed)
        $stage_stats = $this->products_repository->stage_products($items);

        wp_send_json_success([
            'page'        => $page,
            'limit'       => $limit,
            'count'       => count($items),
            'total'       => $total,
            'has_more'    => $has_more,
            'next_page'   => $has_more ? $page + 1 : null,
            'staged'      => $stage_stats,
            'saved_to_new_table' => $saved_to_new_table,
            'message'     => sprintf(__('صفحه %d محصولات با موفقیت ذخیره شد.', 'wp-anar'), $page),
        ]);
    }
}

