<?php

namespace Anar\Import;

use Anar\JobManager;

class BackgroundImporter
{
    private const CRON_HOOK = 'anar_import_v2_process_batch';
    private const RECOVERY_HOOK = 'anar_import_v2_recovery_check';
    private const OPTION_ACTIVE_JOB = 'anar_import_v2_active_job';
    private const OPTION_LAST_BATCH_TIME = 'anar_import_v2_last_batch_time';
    private const JOB_SOURCE = 'import_v2';
    private const DEFAULT_BATCH_SIZE = 30;
    private const OPTION_UI_LOG = 'anar_import_v2_ui_log';
    private const STALE_JOB_TIMEOUT = 300; // 5 minutes

    private static ?self $instance = null;

    private ProductsRepository $products_repository;
    private JobManager $job_manager;
    private int $batch_size = self::DEFAULT_BATCH_SIZE;

    private function __construct()
    {
        $this->products_repository = new ProductsRepository();
        $this->job_manager = JobManager::get_instance();

        // Register WP Cron hooks
        add_action(self::CRON_HOOK, [$this, 'process_batch_cron'], 10, 1);
        add_action(self::RECOVERY_HOOK, [$this, 'recovery_check']);
        
        // Schedule recovery check cron (runs every 2 minutes)
        if (!wp_next_scheduled(self::RECOVERY_HOOK)) {
            wp_schedule_event(time(), 'every_two_min', self::RECOVERY_HOOK);
        }
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function start_job(int $batch_size = self::DEFAULT_BATCH_SIZE): array
    {
        if ($this->has_active_job()) {
            throw new \RuntimeException(__('فرآیند ساخت محصولات در حال اجراست.', 'wp-anar'));
        }

        $pending = $this->products_repository->count_pending();
        if ($pending === 0) {
            throw new \RuntimeException(__('محصولی برای ساخت وجود ندارد.', 'wp-anar'));
        }

        $this->reset_ui_log();

        $job = $this->job_manager->create_job(self::JOB_SOURCE, $pending);
        if (!$job || empty($job['job_id'])) {
            throw new \RuntimeException(__('ایجاد فرآیند جدید با خطا مواجه شد.', 'wp-anar'));
        }

        $job_id = $job['job_id'];
        $this->job_manager->update_job($job_id, [
            'status' => JobManager::STATUS_IN_PROGRESS,
            'processed_products' => 0,
            'created_products' => 0,
            'existing_products' => 0,
            'failed_products' => 0,
        ]);

        update_option(self::OPTION_ACTIVE_JOB, $job_id, false);
        update_option(self::OPTION_LAST_BATCH_TIME, time(), false);
        
        $this->append_ui_log(
            sprintf(__('فرآیند ساخت %d محصول در پس‌زمینه آغاز شد.', 'wp-anar'), $pending),
            'info'
        );

        $this->batch_size = $batch_size;
        
        // Schedule first batch immediately (or very soon)
        $this->schedule_next_batch($job_id, 5); // 5 seconds delay for first batch

        return $this->job_manager->get_job_by_id($job_id);
    }
    
    /**
     * Schedule the next batch using WP Cron
     */
    private function schedule_next_batch(string $job_id, int $delay_seconds = 30): void
    {
        // Clear any existing scheduled batch for this job
        $this->clear_scheduled_batch($job_id);
        
        // Schedule next batch
        $timestamp = time() + $delay_seconds;
        wp_schedule_single_event($timestamp, self::CRON_HOOK, [$job_id]);
        
        anar_log("BackgroundImporter: Scheduled next batch for job {$job_id} in {$delay_seconds} seconds", 'debug');
    }
    
    /**
     * Clear scheduled batch for a job
     */
    private function clear_scheduled_batch(string $job_id): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK, [$job_id]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK, [$job_id]);
        }
    }
    
    /**
     * WP Cron callback for processing batches
     */
    public function process_batch_cron($job_id): void
    {
        $this->process_batch($job_id);
    }
    
    /**
     * Manual trigger endpoint - can be called via AJAX or directly
     */
    public function trigger_batch_processing(): bool
    {
        $job_id = get_option(self::OPTION_ACTIVE_JOB);
        if (!$job_id) {
            return false;
        }
        
        $this->process_batch($job_id);
        return true;
    }
    
    /**
     * Recovery check - runs periodically to detect and recover stuck jobs
     */
    public function recovery_check(): void
    {
        $job_id = get_option(self::OPTION_ACTIVE_JOB);
        if (!$job_id) {
            return;
        }
        
        $job = $this->job_manager->get_job_by_id($job_id);
        if (!$job || $job['status'] !== JobManager::STATUS_IN_PROGRESS) {
            return;
        }
        
        $last_batch_time = get_option(self::OPTION_LAST_BATCH_TIME, 0);
        $time_since_last_batch = time() - $last_batch_time;
        
        // Check if job is stale (no batch processed in last 5 minutes)
        if ($time_since_last_batch > self::STALE_JOB_TIMEOUT) {
            $pending = $this->products_repository->count_pending();
            
            if ($pending > 0) {
                // Job appears stuck - reschedule next batch
                anar_log(
                    "BackgroundImporter: Detected stale job {$job_id}. Last batch was {$time_since_last_batch} seconds ago. Rescheduling...",
                    'warning'
                );
                
                $this->append_ui_log(
                    sprintf(
                        __('تشخیص فرآیند متوقف شده. آخرین بسته %d ثانیه پیش پردازش شد. در حال از سرگیری...', 'wp-anar'),
                        $time_since_last_batch
                    ),
                    'warning'
                );
                
                // Update heartbeat to show we're still alive
                $this->job_manager->update_job_heartbeat($job_id);
                update_option(self::OPTION_LAST_BATCH_TIME, time(), false);
                
                // Reschedule next batch immediately
                $this->schedule_next_batch($job_id, 10);
            } else {
                // No pending products but job still marked as in_progress - complete it
                anar_log("BackgroundImporter: Job {$job_id} has no pending products but still marked in_progress. Completing...", 'info');
                $this->complete_job($job_id);
            }
        } else {
            // Job is healthy - update heartbeat
            $this->job_manager->update_job_heartbeat($job_id);
        }
    }

    public function process_batch($job_id): void
    {
        // Validate job_id
        if (!$job_id || $job_id !== get_option(self::OPTION_ACTIVE_JOB)) {
            anar_log("BackgroundImporter: Invalid or mismatched job_id: {$job_id}", 'error');
            return;
        }

        // Update last batch time immediately
        update_option(self::OPTION_LAST_BATCH_TIME, time(), false);
        
        // Update heartbeat
        $this->job_manager->update_job_heartbeat($job_id);

        // Set timeout to prevent stuck processes
        set_time_limit(300);

        try {
            $batch = $this->products_repository->get_pending_batch($this->batch_size);

            if (empty($batch)) {
                $this->complete_job($job_id);
                return;
            }

            anar_log("BackgroundImporter: Processing batch of " . count($batch) . " products for job {$job_id}", 'debug');

            // Load v2 mappings from repository
            $category_manager = new CategoryManager();
            $attribute_manager = new AttributeManager();
            
            // Clear attribute cache at start of batch for fresh lookups
            $attribute_manager->clear_cache();
            
            // Ensure mappings exist in database (initialize empty arrays if needed)
            $this->ensure_mappings_initialized($category_manager, $attribute_manager);
            
            $category_map = $this->build_category_map($category_manager);
            $attribute_map = $this->build_attribute_map($attribute_manager);
            
            if (empty($category_map) && empty($attribute_map)) {
                anar_log('BackgroundImporter: No category/attribute mappings found; proceeding with default creation.', 'debug');
                $this->append_ui_log(
                    __('هیچ نقشه‌ای برای ویژگی یا دسته‌بندی ثبت نشده است؛ محصولات با تنظیمات پیش‌فرض ساخته می‌شوند.', 'wp-anar'),
                    'warning'
                );
            }
            
            $creator = new ProductCreatorV2($attribute_map, $category_map);
            $processed = 0;
            $created = 0;
            $existing = 0;
            $failed = 0;

            $success_skus = [];

            $this->append_ui_log(
                sprintf(__('پردازش %d محصول آغاز شد.', 'wp-anar'), count($batch)),
                'info'
            );

            foreach ($batch as $record) {
                $processed++;
                $sku = $record['anar_sku'] ?? 'unknown';

                try {
                    $result = $creator->create_from_staged_product($record);
                    if (!empty($result['created'])) {
                        $created++;
                    } else {
                        $existing++;
                    }
                    $success_skus[] = $sku;
                } catch (\Throwable $throwable) {
                    $failed++;
                    $this->products_repository->mark_failed($sku);
                    
                    $error_message = sprintf(
                        "Import V2: Failed to create SKU %s. Error: %s\nStack trace: %s",
                        $sku,
                        $throwable->getMessage(),
                        $throwable->getTraceAsString()
                    );
                    anar_log($error_message, 'error');
                    $this->append_ui_log(
                        sprintf(
                            __('خطا در ساخت محصول با SKU %1$s: %2$s', 'wp-anar'),
                            $sku,
                            $throwable->getMessage()
                        ),
                        'error'
                    );
                    
                    // Continue processing other products even if one fails
                }
            }

            // Delete successfully processed products from staging table
            if (!empty($success_skus)) {
                $this->products_repository->delete_products($success_skus);
            }

            // Update job progress
            $job = $this->job_manager->get_job_by_id($job_id);
            if ($job) {
                $this->job_manager->update_job_progress(
                    $job_id,
                    ((int) $job['processed_products']) + $processed,
                    ((int) $job['created_products']) + $created,
                    ((int) $job['existing_products']) + $existing,
                    ((int) $job['failed_products']) + $failed
                );

                $batch_message = sprintf(
                    "BackgroundImporter: Batch complete - Processed: %d, Created: %d, Updated: %d, Failed: %d",
                    $processed,
                    $created,
                    $existing,
                    $failed
                );
                anar_log($batch_message, 'info');
                $this->append_ui_log(
                    sprintf(
                        __("بسته پردازش شد - پردازش‌شده: %1\$d، ایجاد: %2\$d، به‌روزرسانی: %3\$d، خطا: %4\$d", 'wp-anar'),
                        $processed,
                        $created,
                        $existing,
                        $failed
                    ),
                    $failed > 0 ? 'warning' : 'info'
                );
            }

            // Schedule next batch or complete job
            $pending_count = $this->products_repository->count_pending();
            if ($pending_count > 0) {
                // Schedule next batch in 30 seconds (or adjust based on batch size)
                $delay = max(10, min(60, 30)); // Between 10-60 seconds
                $this->schedule_next_batch($job_id, $delay);
                anar_log("BackgroundImporter: Scheduled next batch. Remaining: {$pending_count}", 'debug');
            } else {
                $this->complete_job($job_id);
                anar_log("BackgroundImporter: Job {$job_id} completed", 'info');
            }
        } catch (\Throwable $e) {
            // Critical error - log and mark job as failed
            $error_message = sprintf(
                "BackgroundImporter: Critical error processing batch for job %s. Error: %s\nStack trace: %s",
                $job_id,
                $e->getMessage(),
                $e->getTraceAsString()
            );
            anar_log($error_message, 'error');
            
            // Mark job as failed
            try {
                $this->complete_job($job_id, JobManager::STATUS_FAILED, $e->getMessage());
            } catch (\Throwable $inner_e) {
                anar_log("BackgroundImporter: Failed to mark job as failed: " . $inner_e->getMessage(), 'error');
            }
        }
    }

    public function get_active_job_status(): ?array
    {
        $job_id = get_option(self::OPTION_ACTIVE_JOB);
        if (!$job_id) {
            return null;
        }

        $job = $this->job_manager->get_job_by_id($job_id);
        if (!$job) {
            delete_option(self::OPTION_ACTIVE_JOB);
        }

        return $job ?: null;
    }

    public function has_pending_products(): bool
    {
        return $this->products_repository->count_pending() > 0;
    }

    private function has_active_job(): bool
    {
        $job = $this->get_active_job_status();

        if (!$job) {
            return false;
        }

        if (in_array($job['status'], [
            JobManager::STATUS_COMPLETED,
            JobManager::STATUS_FAILED,
            JobManager::STATUS_CANCELLED,
        ], true)) {
            delete_option(self::OPTION_ACTIVE_JOB);
            return false;
        }

        return true;
    }

    public function cancel_active_job(?string $message = null): void
    {
        $job = $this->get_active_job_status();

        if (!$job) {
            return;
        }

        $job_id = $job['job_id'];
        
        // Clear any scheduled batches
        $this->clear_scheduled_batch($job_id);
        
        $this->complete_job($job_id, JobManager::STATUS_CANCELLED, $message);
    }

    private function complete_job(string $job_id, string $status = JobManager::STATUS_COMPLETED, ?string $message = null): void
    {
        $pending = $this->products_repository->count_pending();

        if ($status === JobManager::STATUS_CANCELLED) {
            $final_status = JobManager::STATUS_CANCELLED;
        } else {
            $final_status = $pending === 0 ? $status : JobManager::STATUS_FAILED;
        }

        $this->job_manager->complete_job($job_id, $final_status, $message);

        $final_message = $message;

        if (!$final_message) {
            if ($final_status === JobManager::STATUS_COMPLETED) {
                $final_message = __('فرآیند ساخت محصولات با موفقیت به پایان رسید.', 'wp-anar');
            } elseif ($final_status === JobManager::STATUS_CANCELLED) {
                $final_message = __('فرآیند ساخت محصولات متوقف شد.', 'wp-anar');
            } else {
                $final_message = __('فرآیند ساخت محصولات با خطا متوقف شد.', 'wp-anar');
            }
        }

        $log_level = 'info';
        if ($final_status === JobManager::STATUS_FAILED) {
            $log_level = 'error';
        } elseif ($final_status === JobManager::STATUS_CANCELLED) {
            $log_level = 'warning';
        }

        $this->append_ui_log($final_message, $log_level);
        delete_option(self::OPTION_ACTIVE_JOB);
    }

    public function get_ui_logs(): array
    {
        $log = get_option(self::OPTION_UI_LOG, []);
        return is_array($log) ? $log : [];
    }

    private function reset_ui_log(): void
    {
        update_option(self::OPTION_UI_LOG, [], false);
    }

    private function append_ui_log(string $message, string $type = 'info'): void
    {
        $entry = [
            'id' => uniqid('import_log_', true),
            'time' => current_time('mysql'),
            'message' => $message,
            'type' => $type,
        ];

        $log = $this->get_ui_logs();
        $log[] = $entry;

        if (count($log) > 200) {
            $log = array_slice($log, -200);
        }

        update_option(self::OPTION_UI_LOG, $log, false);
    }

    /**
     * Ensure mappings are initialized in database (save empty arrays if they don't exist).
     * This prevents errors when no mappings have been created yet.
     */
    private function ensure_mappings_initialized(CategoryManager $category_manager, AttributeManager $attribute_manager): void
    {
        $repository = \Anar\AnarDataRepository::get_instance();
        
        // Check if category map exists, if not initialize with empty array
        if (!$repository->exists('categoryMap')) {
            $repository->save('categoryMap', []);
            anar_log('Initialized empty categoryMap in database', 'debug');
        }
        
        // Check if attribute map exists, if not initialize with empty array
        if (!$repository->exists('attributeMap')) {
            $repository->save('attributeMap', []);
            anar_log('Initialized empty attributeMap in database', 'debug');
        }
    }

    /**
     * Build category map in format expected by ProductCreatorV2.
     * Expected format: ['anar_category_name' => 'woo_category_name']
     */
    private function build_category_map(CategoryManager $manager): array
    {
        $map = [];
        $mappings = $manager->get_mappings();
        
        // Mappings are stored as: ['anar_id' => ['anar_name' => ..., 'wc_term_name' => ...]]
        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }
            
            $anar_name = $mapping['anar_name'] ?? '';
            $woo_name = $mapping['wc_term_name'] ?? '';
            
            if ($anar_name && $woo_name) {
                $map[$anar_name] = $woo_name;
            }
        }
        
        return $map;
    }

    /**
     * Build attribute map in format expected by ProductCreatorV2.
     * Expected format: ['attr_key' => ['name' => ..., 'map' => ...]]
     * The 'map' field should be the WooCommerce attribute slug (wc_attribute_name) used when creating attributes.
     * 
     * Note: Mappings are now key-based (primary identifier), but we also include name for backward compatibility.
     */
    private function build_attribute_map(AttributeManager $manager): array
    {
        $map = [];
        $mappings = $manager->get_mappings();
        
        // Mappings are now stored as: ['anar_key' => ['wc_attribute_name' => ..., 'wc_attribute_label' => ..., 'anar_name' => ...]]
        foreach ($mappings as $key => $mapping) {
            if (!is_array($mapping)) {
                continue;
            }
            
            // Use wc_attribute_name as the slug (this is what AttributeManager uses when creating attributes)
            $wc_slug = $mapping['wc_attribute_name'] ?? sanitize_title($mapping['anar_name'] ?? $key);
            $wc_label = $mapping['wc_attribute_label'] ?? $mapping['wc_attribute_name'] ?? ($mapping['anar_name'] ?? $key);
            
            // Add mapping by key (primary)
            $map[$key] = [
                'name' => $wc_label,
                'map' => $wc_slug, // This is the WooCommerce attribute slug used in taxonomy creation
            ];
            
            // Also add mapping by name for backward compatibility
//            $anar_name = $mapping['anar_name'] ?? $key;
//            if ($anar_name !== $key) {
//                $map[$anar_name] = [
//                    'name' => $wc_label,
//                    'map' => $wc_slug,
//                ];
//            }
        }
        
        return $map;
    }
}

