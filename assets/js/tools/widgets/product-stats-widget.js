import { getLabel, getStatusLabel } from './utils';

/**
 * Product Stats Widget HTML Generator
 */
export function generateProductStatsHTML(data) {
    let html = '<div class="anar-widget-section">';
    html += '<h4>آمار کلی محصولات</h4>';
    html += '<div class="anar-stats-grid">';
    
    Object.entries(data.total_products).forEach(([status, count]) => {
        html += `<div class="anar-stat-item">
            <span class="anar-stat-label">${getStatusLabel(status)}:</span>
            <span class="anar-stat-value">${count}</span>
        </div>`;
    });
    
    html += '</div></div>';

    if (data.anar_products) {
        html += '<div class="anar-widget-section">';
        html += '<h4>محصولات انار</h4>';
        html += '<div class="anar-stats-grid">';
        
        Object.entries(data.anar_products).forEach(([key, count]) => {
            html += `<div class="anar-stat-item">
                <span class="anar-stat-label">${getLabel(key)}:</span>
                <span class="anar-stat-value">${count}</span>
            </div>`;
        });
        
        html += '</div></div>';
    }

    if (data.sync_status) {
        html += '<div class="anar-widget-section">';
        html += '<h4>وضعیت همگام‌سازی</h4>';
        html += '<div class="anar-stats-grid">';
        
        Object.entries(data.sync_status).forEach(([key, count]) => {
            html += `<div class="anar-stat-item">
                <span class="anar-stat-label">${getLabel(key)}:</span>
                <span class="anar-stat-value">${count}</span>
            </div>`;
        });
        
        html += '</div></div>';
    }

    return html;
}

