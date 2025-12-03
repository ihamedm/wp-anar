/**
 * Shared utilities for widget HTML generation
 */

/**
 * Escape HTML characters
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get label translation
 */
export function getLabel(key) {
    const labels = {
        'version': 'نسخه',
        'multisite': 'چندسایتی',
        'language': 'زبان',
        'timezone': 'منطقه زمانی',
        'memory_limit': 'حد حافظه',
        'max_execution_time': 'حد زمان اجرا',
        'currency': 'واحد پول',
        'products_count': 'تعداد محصولات',
        'orders_count': 'تعداد سفارشات',
        'php_version': 'نسخه PHP',
        'mysql_version': 'نسخه MySQL',
        'server_software': 'نرم‌افزار سرور',
        'upload_max_filesize': 'حد آپلود فایل',
        'post_max_size': 'حد اندازه پست',
        'plugin_version': 'نسخه افزونه',
        'anar_products': 'محصولات انار',
        'last_sync': 'آخرین همگام‌سازی',
        'total': 'کل',
        'with_prices': 'با قیمت',
        'zero_profit': 'سود صفر',
        'deprecated': 'منسوخ شده',
        'recently_synced': 'همگام‌سازی شده',
        'not_synced_recently': 'همگام‌سازی نشده',
        'never_synced': 'هرگز همگام‌سازی نشده',
        'need_fix': 'نیازمند رفع مشکل',
        'sync_error': 'خطای همگام‌سازی',
        'created_today': 'ایجاد شده امروز',
        'updated_today': 'به‌روزرسانی شده امروز'
    };
    
    return labels[key] || key;
}

/**
 * Get status label translation
 */
export function getStatusLabel(status) {
    const labels = {
        'published': 'منتشر شده',
        'draft': 'پیش‌نویس',
        'pending': 'در انتظار بررسی',
        'private': 'خصوصی',
        'trash': 'سطل زباله'
    };
    
    return labels[status] || status;
}

/**
 * Get level label translation
 */
export function getLevelLabel(level) {
    const labels = {
        'critical': 'بحرانی',
        'error': 'خطا',
        'warning': 'هشدار',
        'info': 'اطلاعات'
    };
    
    return labels[level] || level;
}

/**
 * Get benchmark score CSS class
 */
export function getBenchmarkScoreClass(score) {
    const value = parseFloat(score);
    if (isNaN(value)) {
        return '';
    }

    if (value >= 8) {
        return 'anar-benchmark-item-score--excellent';
    }
    if (value >= 6) {
        return 'anar-benchmark-item-score--good';
    }
    if (value >= 4) {
        return 'anar-benchmark-item-score--average';
    }
    if (value >= 2) {
        return 'anar-benchmark-item-score--fair';
    }
    return 'anar-benchmark-item-score--poor';
}

