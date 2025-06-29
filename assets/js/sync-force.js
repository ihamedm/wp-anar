import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    const form = $('#anar_force_sync_products');
    const submitBtn = $('#submit-form-btn');
    const clearBtn = $('#clear-sync-times-btn');
    const spinner = submitBtn.find('.spinner-loading');
    let jobId = null;
    let statusCheckInterval = null;
    let isReloading = false;

    // Initialize the form
    function initForm() {
        console.log('Form initialized');
        spinner.hide();
        const hasActiveJob = submitBtn.data('has-active-job') === true;
        submitBtn.prop('disabled', hasActiveJob);

        // If there's an active job, start checking its status
        if (hasActiveJob) {
            const activeJobId = $('.active-job-container').data('job-id');
            if (activeJobId) {
                startStatusCheck(activeJobId);
            }
        }

        // Initialize clear sync times button
        initClearSyncTimesButton();
    }

    // Initialize clear sync times button
    function initClearSyncTimesButton() {
        if (clearBtn.length) {
            clearBtn.on('click', function(e) {
                e.preventDefault();
                if (confirm('آیا از پاک کردن زمان‌های بروزرسانی اطمینان دارید؟ این کار باعث بروزرسانی مجدد تمام محصولات خواهد شد.')) {
                    clearSyncTimes();
                }
            });
        }
    }

    // Clear sync times
    function clearSyncTimes() {
        clearBtn.prop('disabled', true);
        clearBtn.text('در حال پاک کردن...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anar_clear_sync_times',
                security_nonce: $('#security_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message);
                    // Reload the page after a short delay
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showNotification(response.data.message, 'error');
                    clearBtn.prop('disabled', false);
                    clearBtn.text('پاک کردن زمان‌های بروزرسانی');
                }
            },
            error: function() {
                showNotification('خطا در ارسال درخواست', 'error');
                clearBtn.prop('disabled', false);
                clearBtn.text('پاک کردن زمان‌های بروزرسانی');
            }
        });
    }

    // Show loading state
    function showLoading() {
        spinner.show();
        submitBtn.prop('disabled', true);
    }

    // Hide loading state
    function hideLoading() {
        spinner.hide();
        submitBtn.prop('disabled', false);
    }

    // Show notification
    function showNotification(message, type = 'success') {
        awca_toast(message, type);
    }

    // Format date
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('fa-IR');
    }

    // Get status text in Persian
    function getStatusText(status) {
        const statusMap = {
            'pending': 'در انتظار',
            'in_progress': 'در حال اجرا',
            'completed': 'تکمیل شده',
            'failed': 'ناموفق',
            'cancelled': 'لغو شده',
            'paused': 'متوقف شده'
        };
        return statusMap[status] || status;
    }

    // Handle job control actions
    function handleJobControl(action, jobId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anar_control_sync_job',
                job_id: jobId,
                action_type: action,
                security_nonce: $('#security_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message);
                    // Reload the page to show updated status
                    window.location.reload();
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('خطا در اجرای عملیات', 'error');
            }
        });
    }

    // Update job status in UI
    function updateJobStatus(jobData) {
        const status = jobData.status;
        const progress = jobData.progress;

        // Update progress bar
        const progressBar = $('.job-progress-bar');
        const progressText = $('.job-progress-info span:last-child');
        const progressPercentage = Math.round((progress.processed / progress.total) * 100);
        
        progressBar.css('width', `${progressPercentage}%`);
        progressText.text(`${progress.processed}/${progress.total}`);

        // Update status text
        $('.job-status').text(getStatusText(status));

        // Update button text
        if (status === 'in_progress') {
            submitBtn.text(`در حال بروزرسانی (${progress.processed}/${progress.total})`);
            submitBtn.prop('disabled', true);
        } else if (status === 'completed' && !isReloading) {
            submitBtn.text('بروزرسانی اجباری کل محصولات');
            showNotification('بروزرسانی با موفقیت انجام شد');
            stopStatusCheck();
            hideLoading();
            isReloading = true;
            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 2000);
        } else if (status === 'failed' && !isReloading) {
            submitBtn.text('بروزرسانی اجباری کل محصولات');
            showNotification('خطا در بروزرسانی: ' + (jobData.error_log || 'خطای ناشناخته'), 'error');
            stopStatusCheck();
            hideLoading();
            isReloading = true;
            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 2000);
        } else if (status === 'cancelled' && !isReloading) {
            submitBtn.text('بروزرسانی اجباری کل محصولات');
            showNotification('بروزرسانی لغو شد');
            stopStatusCheck();
            hideLoading();
            isReloading = true;
            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 2000);
        }
    }

    // Check job status
    function checkJobStatus(jobId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anar_get_job_status',
                job_id: jobId,
                security_nonce: $('#security_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    updateJobStatus(response.data);
                    
                    // If job is completed or failed, stop checking
                    if (response.data.completed) {
                        stopStatusCheck();
                    }
                } else {
                    showNotification(response.data.message, 'error');
                    stopStatusCheck();
                }
            },
            error: function() {
                showNotification('Error checking job status', 'error');
                stopStatusCheck();
            }
        });
    }

    // Start status check interval
    function startStatusCheck(jobId) {
        stopStatusCheck(); // Clear any existing interval
        statusCheckInterval = setInterval(() => checkJobStatus(jobId), 10000);
    }

    // Stop status check interval
    function stopStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
    }

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        // Prevent submission if there's an active job
        if (submitBtn.prop('disabled')) {
            showNotification('یک بروزرسانی در حال اجراست. لطفا صبر کنید.', 'error');
            return;
        }
        
        showLoading();
        submitBtn.text('شروع بروزرسانی...');

        const formData = {
            action: 'anar_force_sync_products',
            security_nonce: $('#security_nonce').val()
        };

        console.log('Sending AJAX request with data:', formData);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                console.log('AJAX request starting...');
            },
            success: function(response) {
                console.log('AJAX response received:', response);
                
                if (response.success) {
                    console.log('Success response received');
                    jobId = response.data.job_id;
                    submitBtn.text('در حال بروزرسانی...');
                    submitBtn.prop('disabled', true);
                    console.log('Button disabled');
                    
                    // Show success message
                    showNotification('بروزرسانی با موفقیت شروع شد');
                    console.log('Toast shown');
                    
                    // Reload the page to show the active job container
                    isReloading = true;
                    console.log('Setting isReloading to true');
                    
                    console.log('Scheduling page reload...');
                    setTimeout(function() {
                        console.log('Executing page reload...');
                        window.location.reload();
                    }, 1000);
                } else {
                    console.log('Error response received:', response);
                    hideLoading();
                    showNotification(response.data.message, 'error');
                    submitBtn.text('بروزرسانی اجباری کل محصولات');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', {xhr: xhr, status: status, error: error});
                hideLoading();
                showNotification('خطا در ارسال درخواست', 'error');
                submitBtn.text('بروزرسانی اجباری کل محصولات');
            }
        });
    });

    // Handle job control button clicks
    $(document).on('click', '.job-control-btn', function() {
        const action = $(this).data('action');
        const jobId = $(this).data('job-id');
        handleJobControl(action, jobId);
    });

    // Initialize the form
    initForm();
});
