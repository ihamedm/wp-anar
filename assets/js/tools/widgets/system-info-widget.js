import { getLabel } from './utils';

/**
 * System Info Widget HTML Generator
 */
export function generateSystemInfoHTML(data) {
    let html = '<div class="anar-widget-section">';
    html += '<h4>اطلاعات وردپرس</h4>';
    html += '<div class="anar-info-grid">';
    
    Object.entries(data.wp_info).forEach(([key, value]) => {
        html += `<div class="anar-info-item">
            <span class="anar-info-label">${getLabel(key)}:</span>
            <span class="anar-info-value">${value}</span>
        </div>`;
    });
    
    html += '</div></div>';

    if (data.wc_info && Object.keys(data.wc_info).length > 0) {
        html += '<div class="anar-widget-section">';
        html += '<h4>اطلاعات ووکامرس</h4>';
        html += '<div class="anar-info-grid">';
        
        Object.entries(data.wc_info).forEach(([key, value]) => {
            html += `<div class="anar-info-item">
                <span class="anar-info-label">${getLabel(key)}:</span>
                <span class="anar-info-value">${value}</span>
            </div>`;
        });
        
        html += '</div></div>';
    }

    if (data.anar_info) {
        html += '<div class="anar-widget-section">';
        html += '<h4>اطلاعات انار</h4>';
        html += '<div class="anar-info-grid">';
        
        Object.entries(data.anar_info).forEach(([key, value]) => {
            html += `<div class="anar-info-item">
                <span class="anar-info-label">${getLabel(key)}:</span>
                <span class="anar-info-value">${value}</span>
            </div>`;
        });
        
        html += '</div></div>';
    }

    return html;
}

