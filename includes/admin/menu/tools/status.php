<br class="clear">

<h2>وضعیت سیستم</h2>

<p>لطفا درصورت درخواست پشتیبانی برای ارسال گزارش سیستم، روی دکمه <strong>دانلود فایل گزارش</strong> کلیک کنید و فایل دریافتی را ارسال کنید.</p>

<div class="system-report-buttons">
    <button class="button button-primary" id="anar-download-system-report" disabled>
        <span class="dashicons dashicons-download"></span>
        دانلود فایل گزارش
    </button>

    <button class="button button-secondary" id="anar-show-system-report" disabled>
        <span class="dashicons dashicons-visibility"></span>
        نمایش گزارش
    </button>
</div>

<br>

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
</style>