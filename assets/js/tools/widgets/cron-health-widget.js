import { escapeHtml } from './utils';

/**
 * Cron Health Widget HTML Generator
 */
export function generateCronHealthHTML(data) {
    let html = '<div class="anar-widget-section">';
    
    // Overall Status (Yes/No)
    const isWorking = data.is_working !== false;
    const statusClass = isWorking ? 'anar-cron-status-success' : 'anar-cron-status-error';
    const statusText = isWorking ? '✓ بله - Cron در حال کار است' : '✗ خیر - Cron مشکل دارد';
    html += '<div class="anar-cron-overall-status ' + statusClass + '">';
    html += '<h4>وضعیت Cron:</h4>';
    html += '<div class="anar-cron-overall-status-badge ' + statusClass + '">' + statusText + '</div>';
    html += '</div>';
    
    // WP Cron Status
    if (data.wp_cron_disabled) {
        // Use the status from PHP (warning for disabled, success for enabled)
        const statusClass = 'anar-cron-status-' + (data.wp_cron_disabled.status || (data.wp_cron_disabled.disabled ? 'warning' : 'success'));
        const statusIcon = data.wp_cron_disabled.disabled ? '⚠' : '✓';
        const statusText = data.wp_cron_disabled.disabled ? 'غیرفعال' : 'فعال';
        html += '<div class="anar-cron-status-item ' + statusClass + '">';
        html += '<div class="anar-cron-status-header">';
        html += '<span class="anar-cron-status-title">وضعیت WP Cron</span>';
        html += '<span class="anar-cron-status-badge ' + statusClass + '">' + statusIcon + ' ' + statusText + '</span>';
        html += '</div>';
        html += '<div class="anar-cron-status-message">' + escapeHtml(data.wp_cron_disabled.message) + '</div>';
        html += '</div>';
    }
    
    // Last Cron Run
    if (data.wp_cron_status) {
        const statusClass = 'anar-cron-status-' + data.wp_cron_status.status;
        html += '<div class="anar-cron-status-item ' + statusClass + '">';
        html += '<div class="anar-cron-status-header">';
        html += '<span class="anar-cron-status-title">آخرین اجرای Cron</span>';
        html += '<span class="anar-cron-status-badge ' + statusClass + '">' + 
            (data.wp_cron_status.status === 'success' ? '✓' : data.wp_cron_status.status === 'warning' ? '⚠' : '✗') + 
            '</span>';
        html += '</div>';
        if (data.wp_cron_status.last_run_formatted) {
            html += '<div class="anar-cron-status-details">';
            html += '<span><strong>زمان:</strong> ' + escapeHtml(data.wp_cron_status.last_run_formatted) + '</span>';
            if (data.wp_cron_status.time_since_last_run_formatted) {
                html += '<span><strong>مدت زمان:</strong> ' + escapeHtml(data.wp_cron_status.time_since_last_run_formatted) + '</span>';
            }
            html += '</div>';
        }
        html += '<div class="anar-cron-status-message">' + escapeHtml(data.wp_cron_status.message) + '</div>';
        html += '</div>';
    }
    
    // Cron Spawn Test
    if (data.cron_spawn_test) {
        const spawnTest = data.cron_spawn_test;
        const statusClass = 'anar-cron-status-' + spawnTest.status;
        html += '<div class="anar-cron-status-item ' + statusClass + '">';
        html += '<div class="anar-cron-status-header">';
        html += '<span class="anar-cron-status-title">تست HTTP برای wp-cron.php</span>';
        html += '<span class="anar-cron-status-badge ' + statusClass + '">' + 
            (spawnTest.status === 'success' ? '✓' : spawnTest.status === 'warning' ? '⚠' : '✗') + 
            '</span>';
        html += '</div>';
        html += '<div class="anar-cron-status-details">';
        if (spawnTest.url) {
            html += '<span><strong>URL:</strong> <code>' + escapeHtml(spawnTest.url) + '</code></span>';
        }
        if (spawnTest.status_code !== null) {
            html += '<span><strong>کد پاسخ:</strong> ' + escapeHtml(spawnTest.status_code) + '</span>';
        }
        if (spawnTest.response_time !== null) {
            html += '<span><strong>زمان پاسخ:</strong> ' + escapeHtml(spawnTest.response_time) + ' ms</span>';
        }
        if (spawnTest.error) {
            html += '<span><strong>خطا:</strong> ' + escapeHtml(spawnTest.error) + '</span>';
        }
        html += '</div>';
        html += '<div class="anar-cron-status-message">' + escapeHtml(spawnTest.message) + '</div>';
        html += '</div>';
    }
    
    // Important Jobs
    if (data.important_jobs && Object.keys(data.important_jobs).length > 0) {
        html += '<div class="anar-cron-jobs">';
        html += '<h4>وظایف مهم:</h4>';
        html += '<div class="anar-cron-jobs-list">';
        
        Object.entries(data.important_jobs).forEach(([jobKey, job]) => {
            const statusClass = 'anar-cron-job-' + job.status;
            html += '<div class="anar-cron-job-item ' + statusClass + '">';
            html += '<div class="anar-cron-job-header">';
            html += '<div class="anar-cron-job-title-wrapper">';
            html += '<span class="anar-cron-job-name">' + escapeHtml(job.display_name) + '</span>';
            if (job.hook_name) {
                html += '<span class="anar-cron-job-hook">' + escapeHtml(job.hook_name) + '</span>';
            }
            html += '</div>';
            html += '<span class="anar-cron-job-badge ' + statusClass + '">' + 
                (job.status === 'success' ? '✓' : job.status === 'warning' ? '⚠' : '✗') + 
                '</span>';
            html += '</div>';
            
            html += '<div class="anar-cron-job-details">';
            
            if (job.scheduled) {
                html += '<span class="anar-cron-job-meta">';
                html += '<strong>زمان‌بندی:</strong> ' + escapeHtml(job.interval);
                html += '</span>';
                if (job.next_run_formatted) {
                    html += '<span class="anar-cron-job-meta">';
                    html += '<strong>اجرای بعدی:</strong> ' + escapeHtml(job.next_run_formatted);
                    html += '</span>';
                }
            } else {
                html += '<span class="anar-cron-job-meta anar-cron-job-error">';
                html += '<strong>زمان‌بندی:</strong> زمان‌بندی نشده';
                html += '</span>';
            }
            
            if (job.last_run_formatted) {
                html += '<span class="anar-cron-job-meta">';
                html += '<strong>آخرین اجرا:</strong> ' + escapeHtml(job.last_run_formatted);
                html += '</span>';
                if (job.time_since_last_run_formatted) {
                    html += '<span class="anar-cron-job-meta">';
                    html += '<strong>مدت زمان:</strong> ' + escapeHtml(job.time_since_last_run_formatted);
                    html += '</span>';
                }
            } else {
                html += '<span class="anar-cron-job-meta anar-cron-job-error">';
                html += '<strong>آخرین اجرا:</strong> هرگز اجرا نشده';
                html += '</span>';
            }
            
            html += '</div>';
            
            if (job.message) {
                html += '<div class="anar-cron-job-message">' + escapeHtml(job.message) + '</div>';
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        html += '</div>';
    }
    
    html += '</div>';
    
    if (data.timestamp) {
        html += '<div class="anar-benchmark-timestamp">';
        html += '<small>زمان تست: ' + escapeHtml(data.timestamp) + '</small>';
        html += '</div>';
    }
    
    return html;
}

