<?php
// Get recent sync_force jobs
$job_manager = \Anar\JobManager::get_instance();
$recent_jobs = $job_manager->get_jobs([
    'source' => ['sync_force'],
    'limit' => 10,
    'orderby' => 'start_time',
    'order' => 'DESC'
]);

// Get active job
$active_job = null;
foreach ($recent_jobs as $job) {
    if (in_array($job['status'], ['in_progress', 'paused'])) {
        $active_job = $job;
        break;
    }
}

// Remove active job from history
$history_jobs = array_filter($recent_jobs, function($job) use ($active_job) {
    return $job['job_id'] !== ($active_job ? $active_job['job_id'] : null);
});

// Helper function to get status text in Persian
function get_job_status_text($status) {
    $status_map = [
        'pending' => 'در انتظار',
        'in_progress' => 'در حال اجرا',
        'completed' => 'تکمیل شده',
        'failed' => 'ناموفق',
        'cancelled' => 'لغو شده',
        'paused' => 'متوقف شده'
    ];
    return $status_map[$status] ?? $status;
}

// Helper function to format date
function format_job_date($date_string) {
    if (empty($date_string)) return '-';
    return date_i18n('Y/m/d - H:i:s', strtotime($date_string));
}
?>

<div class="mini-tool">

    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>"  id="anar_force_sync_products"
          style="display: flex; flex-direction: column; align-items: center;justify-between: center ; gap: 16px;">

        <h2 class="awca_plugin_titles">بروزرسانی اجباری کل محصولات</h2>
        <input type="hidden" name="action" value="anar_force_sync_products" />
        <?php wp_nonce_field('anar_force_sync_products_nonce', 'security_nonce'); ?>

        <div style="display: flex; gap: 16px;">
            <button type="submit" class="awca-btn awca-success-btn" id="submit-form-btn"
                    <?php echo $active_job ? 'disabled' : ''; ?>
                    data-has-active-job="<?php echo $active_job ? 'true' : 'false'; ?>">
                <?php echo $active_job ? 'در حال بروزرسانی...' : 'شروع بروزرسانی'; ?>
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>


        </div>
    </form>

    <?php if ($active_job): ?>
    <div class="active-job-container" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;" data-job-id="<?php echo esc_attr($active_job['job_id']); ?>">
        <h3>وظیفه فعال</h3>
        <div class="active-job-details" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="job-info" style="display: flex; gap: 24px;">
                <div>
                    <strong>شناسه:</strong> <?php echo esc_html($active_job['job_id']); ?>
                </div>
                <div>
                    <strong>وضعیت:</strong>
                    <span class="job-status <?php echo esc_attr($active_job['status']); ?>">
                        <?php echo esc_html(get_job_status_text($active_job['status'])); ?>
                    </span>
                </div>
                <div>
                    <strong>زمان شروع:</strong> <?php echo esc_html(format_job_date($active_job['start_time'])); ?>
                </div>
            </div>
            
            <div class="job-progress-container">
                <div class="job-progress-info" style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>پیشرفت بروزرسانی</span>
                    <span><?php echo esc_html($active_job['processed_products']); ?>/<?php echo esc_html($active_job['total_products']); ?></span>
                </div>
                <div class="job-progress">
                    <div class="job-progress-bar" style="width: <?php echo esc_attr(round(($active_job['processed_products'] / $active_job['total_products']) * 100)); ?>%"></div>
                </div>
            </div>

            <div class="job-controls">
                <?php if ($active_job['status'] === 'in_progress'): ?>
                    <button class="job-control-btn pause" data-action="pause" data-job-id="<?php echo esc_attr($active_job['job_id']); ?>">توقف</button>
                    <button class="job-control-btn cancel" data-action="cancel" data-job-id="<?php echo esc_attr($active_job['job_id']); ?>">لغو</button>
                <?php elseif ($active_job['status'] === 'paused'): ?>
                    <button class="job-control-btn resume" data-action="resume" data-job-id="<?php echo esc_attr($active_job['job_id']); ?>">ادامه</button>
                    <button class="job-control-btn cancel" data-action="cancel" data-job-id="<?php echo esc_attr($active_job['job_id']); ?>">لغو</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="jobs-table-container">
        <h3>بروزرسانی‌های اخیر</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>شناسه</th>
                    <th>وضعیت</th>
                    <th>تعداد کل</th>
                    <th>بروزرسانی شده</th>
                    <th>ناموفق</th>
                    <th>زمان شروع</th>
                    <th>زمان پایان</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent_jobs = $job_manager->get_jobs([
                    'source' => ['sync_force'],
                    'limit' => 10,
                    'orderby' => 'start_time',
                    'order' => 'DESC'
                ]);

                if (!empty($recent_jobs)) :
                    foreach ($recent_jobs as $job) :
                        $job_type = $job['source'] === 'sync_force' ? 'بروزرسانی اجباری' : 'بروزرسانی خودکار';
                        $status_text = '';
                        switch ($job['status']) {
                            case 'pending':
                                $status_text = 'در انتظار';
                                break;
                            case 'in_progress':
                                $status_text = 'در حال اجرا';
                                break;
                            case 'completed':
                                $status_text = 'تکمیل شده';
                                break;
                            case 'failed':
                                $status_text = 'ناموفق';
                                break;
                            case 'cancelled':
                                $status_text = 'لغو شده';
                                break;
                            case 'paused':
                                $status_text = 'متوقف شده';
                                break;
                            default:
                                $status_text = $job['status'];
                        }
                ?>
                    <tr>
                        <td><?php echo esc_html($job['job_id']); ?></td>
                        <td>
                            <span class="job-status <?php echo esc_attr($job['status']); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($job['total_products']); ?></td>
                        <td><?php echo esc_html($job['processed_products']); ?></td>
                        <td><?php echo esc_html($job['failed_products']); ?></td>
                        <td><?php echo esc_html($job['start_time']); ?></td>
                        <td><?php echo esc_html($job['end_time'] ?? '-'); ?></td>
                    </tr>
                <?php
                    endforeach;
                else :
                ?>
                    <tr>
                        <td colspan="8">هیچ بروزرسانی انجام نشده است.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<style>
.jobs-table-container {
    margin-top: 20px;
}

.job-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.job-status.pending { background-color: #f0f0f0; }
.job-status.in_progress { background-color: #e3f2fd; }
.job-status.completed { background-color: #e8f5e9; }
.job-status.failed { background-color: #ffebee; }
.job-status.cancelled { background-color: #ffe1e1; }

.job-progress {
    width: 100%;
    background-color: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.job-progress-bar {
    height: 8px;
    background-color: #2196f3;
    transition: width 0.3s ease;
}

.job-controls {
    display: flex;
    gap: 8px;
}

.job-control-btn,
.job-control-btn:hover
{
    padding: 4px 8px !important;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px !important;
}

.job-control-btn.cancel { background-color: #ffebee; color: #d32f2f; }
.job-control-btn.pause { background-color: #fff3e0; color: #f57c00; display: none; }
.job-control-btn.resume { background-color: #e8f5e9; color: #2e7d32; display: none; }

.spinner-loading {
    display: none;
    animation: rotate 2s linear infinite;
}

@keyframes rotate {
    100% {
        transform: rotate(360deg);
    }
}

/* Disabled button styles */
.awca-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
