/**
 * Database Health Widget HTML Generator
 */
export function generateDatabaseHealthHTML(data) {
    let html = '';

    // Database Info Section
    if (data.database_info) {
        html += '<div class="anar-widget-section">';
        html += '<h4>اطلاعات پایگاه داده</h4>';
        html += '<div class="anar-database-info">';
        html += `<div class="anar-info-item"><span class="anar-info-label">نسخه:</span> <span class="anar-info-value">${data.database_info.version || 'نامشخص'}</span></div>`;
        html += `<div class="anar-info-item"><span class="anar-info-label">نام پایگاه داده:</span> <span class="anar-info-value">${data.database_info.database_name || 'نامشخص'}</span></div>`;
        html += `<div class="anar-info-item"><span class="anar-info-label">اندازه کل:</span> <span class="anar-info-value">${data.database_info.database_size || '0 B'}</span></div>`;
        html += `<div class="anar-info-item"><span class="anar-info-label">Charset:</span> <span class="anar-info-value">${data.database_info.charset || 'utf8mb4'}</span></div>`;
        html += '</div></div>';
    }

    // InnoDB Status Section
    if (data.innodb_status) {
        html += '<div class="anar-widget-section">';
        html += '<h4>وضعیت InnoDB</h4>';
        const innodbStatusClass = data.innodb_status.can_create_indexes ? 'status-good' : 'status-error';
        html += `<div class="anar-innodb-overall ${innodbStatusClass}">`;
        html += `<span class="anar-status-badge">${data.innodb_status.can_create_indexes ? '✓ می‌توان ایندکس ایجاد کرد' : '✗ نمی‌توان ایندکس ایجاد کرد'}</span>`;
        html += '</div>';
        
        html += '<div class="anar-innodb-tables">';
        Object.entries(data.innodb_status.tables).forEach(([key, table]) => {
            const statusClass = table.status === 'good' ? 'status-good' : 'status-error';
            html += `<div class="anar-innodb-table-item">
                <div class="anar-table-name">${table.name}</div>
                <div class="anar-table-details">
                    <span>engine: ${table.engine}</span>
                    <span class="${statusClass}">${table.is_innodb ? '✓ InnoDB' : '✗ ' + table.engine}</span>
                </div>
            </div>`;
        });
        html += '</div></div>';
    }

    // Tables Health Section
    if (data.tables) {
        html += '<div class="anar-widget-section">';
        html += '<h4>وضعیت جداول</h4>';
        html += '<div class="anar-table-health">';
        
        Object.entries(data.tables).forEach(([key, table]) => {
            const statusClass = table.status === 'good' ? 'status-good' : 'status-warning';
            const innodbClass = table.is_innodb ? 'status-good' : 'status-error';
            html += `<div class="anar-table-item">
                <div class="anar-table-header">
                    <span class="anar-table-name">${table.name}</span>
                    <span class="${statusClass}">${table.status === 'good' ? '✓' : '⚠'}</span>
                </div>
                <div class="anar-table-details">
                    <div class="anar-table-detail-row">
                        <span>ردیف‌ها: <strong>${table.rows.toLocaleString()}</strong></span>
                        <span>اندازه کل: <strong>${table.total_size}</strong></span>
                    </div>
                    <div class="anar-table-detail-row">
                        <span>داده: ${table.data_length}</span>
                        <span>ایندکس: ${table.index_length}</span>
                    </div>
                    <div class="anar-table-detail-row">
                        <span>engine: <strong>${table.engine}</strong></span>
                        <span class="${innodbClass}">${table.is_innodb ? '✓ InnoDB' : '✗ ' + table.engine}</span>
                    </div>
                </div>
            </div>`;
        });
        
        html += '</div></div>';
    }

    // Indexes Health Section
    if (data.indexes && !data.indexes.error) {
        html += '<div class="anar-widget-section">';
        html += '<h4>وضعیت ایندکس‌های همگام‌سازی</h4>';
        
        const indexStatusClass = data.indexes.all_exist ? 'status-good' : 'status-warning';
        html += `<div class="anar-indexes-summary ${indexStatusClass}">`;
        html += `<span class="anar-status-badge">${data.indexes.existing_count}/${data.indexes.total_required} ایندکس موجود</span>`;
        if (!data.indexes.all_exist) {
            html += `<span class="anar-missing-count">${data.indexes.missing_count} ایندکس مفقود</span>`;
        }
        html += '</div>';
        
        html += '<div class="anar-indexes-health">';
        
        if (data.indexes.indexes) {
            Object.entries(data.indexes.indexes).forEach(([key, index]) => {
                const statusClass = index.status === 'good' ? 'status-good' : 'status-error';
                html += `<div class="anar-index-item">
                    <div class="anar-index-info">
                        <span class="anar-index-name">${index.display_name || index.name}</span>
                        <span class="anar-index-key">${index.name}</span>
                    </div>
                    <span class="${statusClass}">${index.exists ? '✓ موجود' : '✗ موجود نیست'}</span>
                </div>`;
            });
        }
        
        if (data.indexes.missing_indexes && data.indexes.missing_indexes.length > 0) {
            html += '<div class="anar-missing-indexes">';
            html += '<strong>ایندکس‌های مفقود:</strong> ';
            html += data.indexes.missing_indexes.join(', ');
            html += '</div>';
        }
        
        html += '</div></div>';
    } else if (data.indexes && data.indexes.error) {
        html += '<div class="anar-widget-section">';
        html += '<div class="anar-error-message">';
        html += `<p>خطا در بررسی ایندکس‌ها: ${data.indexes.message || 'خطای نامشخص'}</p>`;
        html += '</div></div>';
    }

    // Performance Metrics Section
    if (data.performance) {
        html += '<div class="anar-widget-section">';
        html += '<h4>معیارهای عملکرد</h4>';
        html += '<div class="anar-performance-metrics">';
        
        Object.entries(data.performance).forEach(([key, metric]) => {
            let metricHtml = '';
            const statusClass = metric.status === 'good' ? 'status-good' : 
                               metric.status === 'error' ? 'status-error' : 
                               metric.status === 'warning' ? 'status-warning' : 'status-info';
            
            if (key === 'mysql_version') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">نسخه MySQL:</span>
                    <span class="anar-metric-value">${metric.value}</span>
                </div>`;
            } else if (key === 'slow_query_log') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">Slow Query Log:</span>
                    <span class="${statusClass}">${metric.enabled ? '✓ فعال' : '✗ غیرفعال'}</span>
                </div>`;
            } else if (key === 'query_cache') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">Query Cache:</span>
                    <span class="anar-metric-value">${metric.size}</span>
                    <span class="${statusClass}">${metric.enabled ? '✓' : '✗'}</span>
                </div>`;
            } else if (key === 'innodb_buffer_pool') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">InnoDB Buffer Pool:</span>
                    <span class="anar-metric-value">${metric.size}</span>
                    <span class="${statusClass}">${metric.size_bytes > 134217728 ? '✓' : '⚠'}</span>
                </div>`;
            } else if (key === 'max_connections') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">حداکثر اتصالات:</span>
                    <span class="anar-metric-value">${metric.value}</span>
                    <span class="${statusClass}">${metric.value >= 100 ? '✓' : '⚠'}</span>
                </div>`;
            } else if (key === 'current_connections') {
                metricHtml = `<div class="anar-metric-item">
                    <span class="anar-metric-label">اتصالات فعلی:</span>
                    <span class="anar-metric-value">${metric.value}</span>
                </div>`;
            }
            
            if (metricHtml) {
                html += metricHtml;
            }
        });
        
        html += '</div></div>';
    }

    // Recommendations Section
    if (data.recommendations && data.recommendations.length > 0) {
        html += '<div class="anar-widget-section">';
        html += '<h4>توصیه‌ها</h4>';
        html += '<div class="anar-recommendations">';
        
        data.recommendations.forEach((rec) => {
            const recClass = rec.type === 'error' ? 'anar-rec-error' : 
                           rec.type === 'warning' ? 'anar-rec-warning' : 
                           'anar-rec-info';
            html += `<div class="anar-recommendation-item ${recClass}">`;
            html += `<span class="anar-rec-icon">${rec.type === 'error' ? '✗' : rec.type === 'warning' ? '⚠' : 'ℹ'}</span>`;
            html += `<span class="anar-rec-message">${rec.message}</span>`;
            html += '</div>';
        });
        
        html += '</div></div>';
    }

    // Timestamp
    if (data.timestamp) {
        html += '<div class="anar-widget-footer">';
        html += `<span class="anar-timestamp">آخرین بررسی: ${data.timestamp}</span>`;
        html += '</div>';
    }

    return html || '<p>هیچ داده‌ای یافت نشد.</p>';
}
