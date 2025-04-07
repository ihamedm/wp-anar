<br class="clear">

<h2>وضعیت سیستم</h2>

<p>لطفا درصورت درخواست پشتیبانی برای ارسال گزارش سیستم، روی دکمه <strong>دانلود فایل گزارش</strong> کلیک کنید و فایل دریافتی را ارسال کنید.</p>

<div class="system-report-buttons">
    <button class="button button-primary" id="anar-download-system-report" disabled>
        <span class="dashicons dashicons-download"></span>
        دانلود فایل گزارش
    </button>
</div>

<div id="anar-system-reports-table" class="anar-system-reports-table"></div>

<textarea
        id="anar-system-reports"
        rows="20"
        data-action="anar_get_system_reports"
        readonly
        style="width: 100%; direction: ltr; text-align: left; font-family: monospace; resize: vertical; display: none; margin-top: 10px;"
>در حال دریافت اطلاعات...</textarea>

<style>
    .system-report-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .system-report-buttons .button {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .system-report-buttons .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
        margin-top: 0;
    }

    #anar-system-reports {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 15px;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.05);
    }

    .anar-system-reports-table {
        margin-top: 20px;
    }
    
    .anar-report-group {
        margin-bottom: 30px;
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .anar-report-group h3 {
        margin: 0;
        padding: 12px 15px;
        border-bottom: 1px solid #ccd0d4;
        background: #f8f9fa;
    }
    
    .anar-report-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .anar-report-table tr {
        border-bottom: 1px solid #f0f0f1;
    }
    
    .anar-report-table tr:last-child {
        border-bottom: none;
    }
    
    .anar-report-table td {
        padding: 12px 15px;
    }
    
    .anar-report-table td:first-child {
        width: 25%;
        font-weight: 600;
    }
    
    .status-icon {
        margin-right: 5px;
    }
    
    .status-good {
        color: #46b450;
    }
    
    .status-warning {
        color: #ffb900;
    }
    
    .status-critical {
        color: #dc3232;
    }
    
    .report-link {
        color: #0073aa;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    
    .report-link .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        margin-left: 4px;
    }
</style>
