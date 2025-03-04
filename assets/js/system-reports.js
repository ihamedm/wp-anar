import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    const reportEl = $('#anar-system-reports');
    const downloadBtn = $('#anar-download-system-report');
    const showBtn = $('#anar-show-system-report');
    let reportData = ''; // Variable to store the report data
    let isReportVisible = false;

    // Initially disable both buttons
    downloadBtn.prop('disabled', true);
    showBtn.prop('disabled', true);

    function getSystemReports() {
        const action = reportEl.data('action');

        if(reportEl.length === 0) return;

        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                action: action,
            },
            beforeSend: function () {
                awca_toast('در حال دریافت اطلاعات', 'success');
                reportEl.val('در حال دریافت اطلاعات...');
                downloadBtn.prop('disabled', true);
                showBtn.prop('disabled', true);
            },
            success: function (response) {
                var msgType = response.success ? 'success' : 'error';

                if (response.data && response.data.reports) {
                    // Show toast message if exists
                    if (response.data.toast) {
                        awca_toast(response.data.toast, msgType);
                    }

                    // Store the report data
                    reportData = response.data.reports;

                    // Update textarea
                    reportEl.val(reportData);

                    // Enable both buttons
                    downloadBtn.prop('disabled', false);
                    showBtn.prop('disabled', false);
                }
            },
            error: function (xhr, status, err) {
                awca_toast(xhr.responseText || 'خطا در دریافت اطلاعات', 'error');
                reportEl.val('خطا در دریافت اطلاعات. لطفا مجددا تلاش کنید.');
                downloadBtn.prop('disabled', true);
                showBtn.prop('disabled', true);
                hideReport(); // Hide report on error
            }
        });
    }

    // Function to download report as text file
    function downloadReport() {
        if (!reportData) {
            awca_toast('اطلاعاتی برای دانلود وجود ندارد', 'error');
            return;
        }

        // Create file name with current date and time
        const now = new Date();
        const fileName = `system-report-${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}.txt`;

        // Create blob and download
        const blob = new Blob([reportData], { type: 'text/plain;charset=utf-8' });

        if (window.navigator.msSaveOrOpenBlob) {
            // IE11 support
            window.navigator.msSaveOrOpenBlob(blob, fileName);
        } else {
            const element = document.createElement('a');
            element.href = URL.createObjectURL(blob);
            element.download = fileName;
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
            URL.revokeObjectURL(element.href);
        }

        awca_toast('فایل گزارش با موفقیت دانلود شد', 'success');
    }

    // Function to toggle report visibility
    function toggleReport() {
        isReportVisible = !isReportVisible;

        if (isReportVisible) {
            reportEl.slideDown(300);
            showBtn.text('پنهان کردن گزارش');
        } else {
            reportEl.slideUp(300);
            showBtn.text('نمایش گزارش');
        }
    }

    // Function to hide report
    function hideReport() {
        isReportVisible = false;
        reportEl.hide();
        showBtn.text('نمایش گزارش');
    }

    // Attach click handlers
    downloadBtn.on('click', downloadReport);
    showBtn.on('click', toggleReport);

    // Initial load of system reports
    getSystemReports();
});