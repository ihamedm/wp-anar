import { escapeHtml, getBenchmarkScoreClass } from './utils';

/**
 * API Health Widget HTML Generator
 */
export function generateApiHealthHTML(data) {
    let html = '<div class="anar-widget-section">';
    
    // Overall Health Score
    const healthScore = data.health_score || 0;
    html += '<h4>امتیاز کلی: <strong class="anar-benchmark-score-label ' + getBenchmarkScoreClass(healthScore) + '">' + healthScore.toFixed(2) + ' / 10</strong></h4>';
    
    // Summary Stats
    html += '<div class="anar-api-health-summary">';
    html += '<div class="anar-api-health-stat">';
    html += '<span class="anar-api-health-stat-label">کل تست‌ها:</span>';
    html += '<span class="anar-api-health-stat-value">' + (data.total_tested || 0) + '</span>';
    html += '</div>';
    html += '<div class="anar-api-health-stat anar-api-health-stat-success">';
    html += '<span class="anar-api-health-stat-label">موفق:</span>';
    html += '<span class="anar-api-health-stat-value">' + (data.total_success || 0) + '</span>';
    html += '</div>';
    html += '<div class="anar-api-health-stat anar-api-health-stat-error">';
    html += '<span class="anar-api-health-stat-label">ناموفق:</span>';
    html += '<span class="anar-api-health-stat-value">' + (data.total_failed || 0) + '</span>';
    html += '</div>';
    html += '</div>';

    // API Domain
    if (data.api_domain) {
        html += '<div class="anar-api-domain">';
        html += '<strong>دامنه API:</strong> <code>' + escapeHtml(data.api_domain) + '</code>';
        html += '</div>';
    }

    // Endpoint Results
    if (data.endpoints && Object.keys(data.endpoints).length > 0) {
        html += '<div class="anar-api-endpoints">';
        html += '<h4>نتایج هر Endpoint:</h4>';
        html += '<div class="anar-api-endpoints-list">';
        
        Object.entries(data.endpoints).forEach(([endpointKey, endpointResult]) => {
            const statusClass = endpointResult.success ? 'anar-api-endpoint-success' : 'anar-api-endpoint-error';
            const statusIcon = endpointResult.success ? '✓' : '✗';
            const statusText = endpointResult.success ? 'موفق' : 'ناموفق';
            
            html += '<div class="anar-api-endpoint-item ' + statusClass + '">';
            html += '<div class="anar-api-endpoint-header">';
            html += '<span class="anar-api-endpoint-name">' + escapeHtml(endpointKey) + '</span>';
            html += '<span class="anar-api-endpoint-status">' + statusIcon + ' ' + statusText + '</span>';
            html += '</div>';
            
            html += '<div class="anar-api-endpoint-details">';
            
            if (endpointResult.url) {
                html += '<div class="anar-api-endpoint-url">';
                html += '<strong>URL:</strong> <code>' + escapeHtml(endpointResult.url) + '</code>';
                html += '</div>';
            }
            
            if (endpointResult.status_code) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>Status Code:</strong> ' + endpointResult.status_code;
                html += '</span>';
            }
            
            if (endpointResult.duration_ms !== undefined) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>زمان پاسخ:</strong> ' + endpointResult.duration_ms.toFixed(2) + ' ms';
                html += '</span>';
            }
            
            if (endpointResult.body_size_formatted) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>اندازه پاسخ:</strong> ' + endpointResult.body_size_formatted;
                html += '</span>';
            }
            
            if (endpointResult.status_message) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>پیام:</strong> ' + escapeHtml(endpointResult.status_message);
                html += '</span>';
            }
            
            if (endpointResult.error) {
                html += '<div class="anar-api-endpoint-error-message">';
                html += '<strong>خطا:</strong> ' + escapeHtml(endpointResult.error);
                if (endpointResult.error_code) {
                    html += ' <span class="anar-api-error-code">(' + escapeHtml(endpointResult.error_code) + ')</span>';
                }
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        html += '</div>';
    }

    // Custom URL Connectivity Tests
    if (data.custom_urls && Object.keys(data.custom_urls).length > 0) {
        html += '<div class="anar-api-custom-urls">';
        html += '<h4>تست اتصال به URL های خارجی:</h4>';
        html += '<div class="anar-api-endpoints-list">';
        
        Object.entries(data.custom_urls).forEach(([urlName, urlResult]) => {
            const statusClass = urlResult.success ? 'anar-api-endpoint-success' : 'anar-api-endpoint-error';
            const statusIcon = urlResult.success ? '✓' : '✗';
            const statusText = urlResult.success ? 'موفق' : 'ناموفق';
            
            html += '<div class="anar-api-endpoint-item ' + statusClass + '">';
            html += '<div class="anar-api-endpoint-header">';
            html += '<span class="anar-api-endpoint-name">' + escapeHtml(urlResult.name || urlName) + '</span>';
            html += '<span class="anar-api-endpoint-status">' + statusIcon + ' ' + statusText + '</span>';
            html += '</div>';
            
            html += '<div class="anar-api-endpoint-details">';
            
            if (urlResult.url) {
                html += '<div class="anar-api-endpoint-url">';
                html += '<strong>URL:</strong> <code>' + escapeHtml(urlResult.url) + '</code>';
                html += '</div>';
            }
            
            if (urlResult.status_code) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>Status Code:</strong> ' + urlResult.status_code;
                html += '</span>';
            }
            
            if (urlResult.duration_ms !== undefined) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>زمان پاسخ:</strong> ' + urlResult.duration_ms.toFixed(2) + ' ms';
                html += '</span>';
            }
            
            if (urlResult.body_size_formatted) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>اندازه پاسخ:</strong> ' + urlResult.body_size_formatted;
                html += '</span>';
            }
            
            if (urlResult.status_message) {
                html += '<span class="anar-api-endpoint-meta">';
                html += '<strong>پیام:</strong> ' + escapeHtml(urlResult.status_message);
                html += '</span>';
            }
            
            if (urlResult.error) {
                html += '<div class="anar-api-endpoint-error-message">';
                html += '<strong>خطا:</strong> ' + escapeHtml(urlResult.error);
                if (urlResult.error_code) {
                    html += ' <span class="anar-api-error-code">(' + escapeHtml(urlResult.error_code) + ')</span>';
                }
                html += '</div>';
            }
            
            html += '</div>';
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

