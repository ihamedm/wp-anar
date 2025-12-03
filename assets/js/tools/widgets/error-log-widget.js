import { escapeHtml } from './utils';

/**
 * Error Log Widget HTML Generator
 */
export function generateErrorLogHTML(data) {
    let html = '<div class="anar-widget-section">';
    
    // Log Files Section
    if (data.log_files && data.log_files.length > 0) {
        html += '<h4>فایل‌های لاگ</h4>';
        html += '<div class="anar-log-files-list">';
        
        data.log_files.forEach(logFile => {
            html += `<div class="anar-log-file-item">
                <div class="anar-log-file-info">
                    <div class="anar-log-file-name">${escapeHtml(logFile.filename)}</div>
                    <div class="anar-log-file-meta">
                        <span class="anar-log-file-size">${logFile.size}</span>
                        <span class="anar-log-file-date">${logFile.modified}</span>
                    </div>
                </div>
                <div class="anar-log-file-actions">
                    <button class="button button-small anar-preview-log" 
                            data-file-id="${escapeHtml(logFile.id)}" 
                            data-filename="${escapeHtml(logFile.filename)}"
                            title="پیش‌نمایش">
                        <span class="dashicons dashicons-visibility"></span>
                        پیش‌نمایش
                    </button>
                    <a href="${escapeHtml(logFile.download_url)}" 
                       class="button button-small" 
                       download
                       title="دانلود">
                        <span class="dashicons dashicons-download"></span>
                        دانلود
                    </a>
                </div>
            </div>`;
        });
        
        html += '</div>';
    } else if (data.log_files && data.log_files.length === 0) {
        html += '<div class="anar-widget-section">';
        html += '<h4>فایل‌های لاگ</h4>';
        html += '<p>هیچ فایل لاگی یافت نشد.</p>';
        html += '</div>';
    }

    html += '</div>';
    return html;
}

