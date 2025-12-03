import { escapeHtml, getBenchmarkScoreClass } from './utils';

/**
 * Benchmark Widget HTML Generator
 */
export function generateBenchmarkHTML(data) {
    let html = '<div class="anar-widget-section">';
    
    // Overall Score with Gradient Line
    const score = Math.min(10, Math.max(0, data.overall_score)); // Clamp between 0-10
    const markerPosition = (score / 10) * 100; // Convert to percentage
    html += '<h4>امتیاز کلی: <strong class="anar-benchmark-score-label ' + getBenchmarkScoreClass(score) + '">' + score.toFixed(2) + ' / 10</strong></h4>';
    html += '<div class="anar-benchmark-gradient-line">';
    html += '<div class="anar-benchmark-gradient-bar">';
    html += '<div class="anar-benchmark-score-marker" style="left: ' + markerPosition.toFixed(2) + '%;"></div>';
    html += '</div>';
    html += '<div class="anar-benchmark-scale">';
    html += '<span>10</span>';
    html += '<span>5</span>';
    html += '<span>0</span>';
    html += '</div>';
    html += '</div>';

    // Individual Test Results
    html += '<div class="anar-benchmark-details">';
    
    // Disk Read
    if (data.disk_read && data.disk_read.success) {
        html += '<div class="anar-benchmark-item">';
        html += '<div class="anar-benchmark-item-header">';
        html += '<span class="anar-benchmark-item-title">سرعت خواندن دیسک</span>';
        html += '<span class="anar-benchmark-item-score ' + getBenchmarkScoreClass(data.disk_read.score) + '">امتیاز: ' + data.disk_read.score.toFixed(2) + '</span>';
        html += '</div>';
        html += '<div class="anar-benchmark-item-details">';
        html += '<span>میانگین سرعت: <strong>' + data.disk_read.speed_mbps + ' MB/s</strong></span>';
        if (data.disk_read.peak_speed_mbps !== undefined) {
            html += '<span>بیشترین سرعت: ' + data.disk_read.peak_speed_mbps + ' MB/s</span>';
        }
        html += '<span>زمان میانگین: ' + (data.disk_read.duration * 1000).toFixed(2) + ' ms</span>';
        html += '<span>اندازه فایل: ' + data.disk_read.size_mb + ' MB</span>';
        if (data.disk_read.samples !== undefined) {
            html += '<span>نمونه‌ها: ' + data.disk_read.samples + '</span>';
        }
        html += '</div>';
        html += '</div>';
    } else {
        html += '<div class="anar-benchmark-item anar-benchmark-item-error">';
        html += '<span class="anar-benchmark-item-title">سرعت خواندن دیسک</span>';
        html += '<span class="anar-benchmark-error">خطا: ' + (data.disk_read?.error || 'نامشخص') + '</span>';
        html += '</div>';
    }

    // Disk Write
    if (data.disk_write && data.disk_write.success) {
        html += '<div class="anar-benchmark-item">';
        html += '<div class="anar-benchmark-item-header">';
        html += '<span class="anar-benchmark-item-title">سرعت نوشتن دیسک</span>';
        html += '<span class="anar-benchmark-item-score ' + getBenchmarkScoreClass(data.disk_write.score) + '">امتیاز: ' + data.disk_write.score.toFixed(2) + '</span>';
        html += '</div>';
        html += '<div class="anar-benchmark-item-details">';
        html += '<span>میانگین سرعت: <strong>' + data.disk_write.speed_mbps + ' MB/s</strong></span>';
        if (data.disk_write.peak_speed_mbps !== undefined) {
            html += '<span>بیشترین سرعت: ' + data.disk_write.peak_speed_mbps + ' MB/s</span>';
        }
        html += '<span>زمان میانگین: ' + (data.disk_write.duration * 1000).toFixed(2) + ' ms</span>';
        html += '<span>اندازه فایل: ' + data.disk_write.size_mb + ' MB</span>';
        if (data.disk_write.samples !== undefined) {
            html += '<span>نمونه‌ها: ' + data.disk_write.samples + '</span>';
        }
        html += '</div>';
        html += '</div>';
    } else {
        html += '<div class="anar-benchmark-item anar-benchmark-item-error">';
        html += '<span class="anar-benchmark-item-title">سرعت نوشتن دیسک</span>';
        html += '<span class="anar-benchmark-error">خطا: ' + (data.disk_write?.error || 'نامشخص') + '</span>';
        html += '</div>';
    }

    // Network
    if (data.network && data.network.success) {
        html += '<div class="anar-benchmark-item">';
        html += '<div class="anar-benchmark-item-header">';
        html += '<span class="anar-benchmark-item-title">سرعت شبکه</span>';
        html += '<span class="anar-benchmark-item-score ' + getBenchmarkScoreClass(data.network.score) + '">امتیاز: ' + data.network.score.toFixed(2) + '</span>';
        html += '</div>';
        html += '<div class="anar-benchmark-item-details">';
        html += '<span>میانگین سرعت: <strong>' + data.network.speed_mbps + ' MB/s</strong></span>';
        if (data.network.peak_speed_mbps !== undefined) {
            html += '<span>بیشترین سرعت: ' + data.network.peak_speed_mbps + ' MB/s</span>';
        }
        if (data.network.min_speed_mbps !== undefined) {
            html += '<span>کمترین سرعت: ' + data.network.min_speed_mbps + ' MB/s</span>';
        }
        html += '<span>زمان میانگین: ' + (data.network.duration * 1000).toFixed(2) + ' ms</span>';
        html += '<span>اندازه داده: ' + data.network.size_kb + ' KB</span>';
        if (data.network.urls_tested !== undefined) {
            html += '<span>تعداد آدرس‌ها: ' + data.network.urls_tested + '</span>';
        }
        if (data.network.samples !== undefined) {
            html += '<span>کل نمونه‌ها: ' + data.network.samples + '</span>';
        }
        html += '</div>';
        
        // Show individual URL results if available
        if (data.network.url_results && data.network.url_results.length > 0) {
            html += '<div class="anar-benchmark-url-results">';
            html += '<strong>نتایج هر آدرس:</strong>';
            html += '<ul>';
            data.network.url_results.forEach(urlResult => {
                const domain = urlResult.url.replace(/^https?:\/\//, '').split('/')[0];
                html += '<li>';
                html += '<span class="anar-benchmark-url-name">' + escapeHtml(domain) + ':</span> ';
                html += '<span class="anar-benchmark-url-speed">' + urlResult.avg_speed_mbps.toFixed(2) + ' MB/s</span> ';
                html += '<span class="anar-benchmark-url-samples">(' + urlResult.samples + ' نمونه)</span>';
                html += '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
    } else {
        html += '<div class="anar-benchmark-item anar-benchmark-item-error">';
        html += '<span class="anar-benchmark-item-title">سرعت شبکه</span>';
        html += '<span class="anar-benchmark-error">خطا: ' + (data.network?.error || 'نامشخص') + '</span>';
        html += '</div>';
    }

    // Database
    if (data.database && data.database.success) {
        html += '<div class="anar-benchmark-item">';
        html += '<div class="anar-benchmark-item-header">';
        html += '<span class="anar-benchmark-item-title">عملکرد پایگاه داده</span>';
        html += '<span class="anar-benchmark-item-score ' + getBenchmarkScoreClass(data.database.score) + '">امتیاز: ' + data.database.score.toFixed(2) + '</span>';
        html += '</div>';
        html += '<div class="anar-benchmark-item-details">';
        html += '<span>خواندن ساده: <strong>' + data.database.simple_read_ms + ' ms</strong> (امتیاز: ' + data.database.read_score.toFixed(2) + ')</span>';
        html += '<span>نوشتن: <strong>' + data.database.write_ms + ' ms</strong> (امتیاز: ' + data.database.write_score.toFixed(2) + ')</span>';
        html += '<span>کوئری پیچیده: <strong>' + data.database.complex_query_ms + ' ms</strong> (امتیاز: ' + data.database.complex_score.toFixed(2) + ')</span>';
        if (data.database.samples) {
            html += '<span>تعداد نمونه: ' + (data.database.samples.simple_read + data.database.samples.write + data.database.samples.complex_query) + '</span>';
        }
        html += '</div>';
        html += '</div>';
    } else {
        html += '<div class="anar-benchmark-item anar-benchmark-item-error">';
        html += '<span class="anar-benchmark-item-title">عملکرد پایگاه داده</span>';
        html += '<span class="anar-benchmark-error">خطا: ' + (data.database?.error || 'نامشخص') + '</span>';
        html += '</div>';
    }

    html += '</div>'; // anar-benchmark-details
    
    if (data.timestamp) {
        html += '<div class="anar-benchmark-timestamp">';
        html += '<small>زمان تست: ' + escapeHtml(data.timestamp) + '</small>';
        html += '</div>';
    }

    html += '</div>';
    return html;
}

