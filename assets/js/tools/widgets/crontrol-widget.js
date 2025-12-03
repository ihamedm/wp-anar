import { escapeHtml } from './utils';

/**
 * Crontrol Widget HTML Generator
 */
export function generateCrontrolHTML(data) {
    let html = '<div class="anar-widget-section">';
    
    // Cron Jobs List
    if (data.cron_jobs && Array.isArray(data.cron_jobs) && data.cron_jobs.length > 0) {
        html += '<div class="anar-crontrol-jobs">';
        html += '<h4>Anar Cron Jobs:</h4>';
        html += '<div class="anar-crontrol-jobs-list">';
        
        data.cron_jobs.forEach((job) => {
            const statusClass = 'anar-crontrol-cron-' + job.status;
            html += '<div class="anar-crontrol-job-item ' + statusClass + '">';
            html += '<div class="anar-crontrol-job-header">';
            html += '<span class="anar-crontrol-job-name">' + escapeHtml(job.name) + '</span>';
            html += '<span class="anar-crontrol-job-badge ' + statusClass + '">' + 
                (job.status === 'success' ? '✓' : job.status === 'warning' ? '⚠' : '✗') + 
                '</span>';
            html += '</div>';
            html += '<div class="anar-crontrol-job-details">';
            html += '<div class="anar-crontrol-job-info">';
            html += '<span><strong>Hook:</strong> <code>' + escapeHtml(job.hook) + '</code></span>';
            html += '<span><strong>Schedule:</strong> ' + escapeHtml(job.interval) + '</span>';
            if (job.is_scheduled) {
                if (job.next_run_formatted) {
                    html += '<span><strong>Next Run:</strong> ' + escapeHtml(job.next_run_formatted) + '</span>';
                }
            } else {
                html += '<span class="anar-crontrol-not-scheduled">✗ Not scheduled</span>';
            }
            html += '</div>';
            // Add "Run Now" link
            if (job.run_now_link) {
                html += '<div class="anar-crontrol-job-actions">';
                html += '<a href="' + escapeHtml(job.run_now_link) + '" class="button button-small button-primary" title="Run this cron job immediately">Run Now</a>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        html += '</div>';
    } else {
        html += '<p style="text-align: center; color: #666; padding: 20px;">No cron jobs found.</p>';
    }
    
    html += '</div>';
    
    if (data.timestamp) {
        html += '<div class="anar-benchmark-timestamp">';
        html += '<small>Report Time: ' + escapeHtml(data.timestamp) + '</small>';
        html += '</div>';
    }
    
    return html;
}

