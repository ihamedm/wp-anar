import {awca_toast} from "./functions";

jQuery(document).ready(function() {
    let currentApplication = 'wordpress'; // Default active tab
    let currentPage = { wordpress: 1, all: 1 }; // Track page for each tab
    const limit = 100;

    const markAsReadBtn = jQuery('#mark-page-as-read-btn');
    const markAsReadBtnSpinner = markAsReadBtn.find('.spinner-loading');

    function mark_page_as_read(page, limit, application) {
        var msgType = 'error'
        jQuery.ajax({
            url: anar_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                action: 'anar_mark_as_read_notifications_ajax',
                page: page,
                limit: limit,
                application: application || 'wordpress'
            },
            beforeSend: function () {
                markAsReadBtnSpinner.show()
                markAsReadBtn.prop('disabled', true);
            },
            success: function (response) {
                if(response.success) {
                    msgType = 'success'
                }
                awca_toast(response.data.message, msgType);
            },
            error: function (xhr, status, err) {
                awca_toast(xhr.responseText)
            },
            complete: function () {
                markAsReadBtnSpinner.hide()
                markAsReadBtn.prop('disabled', false);
            }
        });
    }

    // Set up mark as read button handler once
    markAsReadBtn.on('click', function(e){
        const page = jQuery(this).data('page') || 1;
        const limit = jQuery(this).data('limit') || 100;
        const application = jQuery(this).data('application') || currentApplication;
        mark_page_as_read(page, limit, application)
    });

    function fetchNotifications (page, limit, application){
        var anarNotifications =  jQuery('#anar_notification_list')

        if(anarNotifications.length !== 0){

            var loadingIcon = anarNotifications.find('.spinner-loading')
            var msgType = 'error'

            jQuery.ajax({
                url: anar_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: {
                    action: 'anar_fetch_notifications_ajax',
                    page: page,
                    limit: limit,
                    application: application || 'wordpress'
                },
                beforeSend: function () {
                    loadingIcon.show();
                    markAsReadBtn.prop('disabled', true);
                },
                success: function (response) {
                    if (response.success) {
                        anarNotifications.html(response.data.output)
                        msgType = 'success'
                        paginateNotifications(response.data.total, response.data.page, response.data.limit, application)
                        updateMarkAsReadBtn(response.data.page, response.data.limit, application);
                        // Update current page for this application
                        currentPage[application] = response.data.page;
                        // Set up mark as read button handlers for individual notifications
                        setupMarkAsReadButtons();
                        // Set up notification click handlers
                        setupNotificationClick();
                    }
                    awca_toast(response.data.message, msgType);
                },
                error: function (xhr, status, err) {
                    awca_toast(xhr.responseText)
                    loadingIcon.hide();
                    console.log(err)
                },
                complete: function () {
                    loadingIcon.hide();
                    markAsReadBtn.prop('disabled', false);
                },
            });

            function paginateNotifications(total, page, limit, application) {
                var pagination = jQuery('#awca_pagination');
                var totalPages = Math.ceil(total / limit);
                var paginationHtml = '';

                for (var i = 1; i <= totalPages; i++) {
                    var classNames = (i === page) ? 'current' : '';
                    paginationHtml += '<button class="pagination-btn '+classNames+'" data-page="' + i + '">' + i + '</button>';
                }

                pagination.html(paginationHtml);

                pagination.find('.pagination-btn').off('click').on('click', function () {
                    fetchNotifications(jQuery(this).data('page'), limit, application);
                });
            }

            function updateMarkAsReadBtn(page, limit, application) {
                markAsReadBtn.attr('data-page', page);
                markAsReadBtn.attr('data-limit', limit);
                markAsReadBtn.attr('data-application', application);
            }

        }

    }

    // Function to mark a single notification as read
    function mark_single_notification_read(notificationId, element, notificationItem) {
        // If notificationItem is not provided, get it from element
        if (!notificationItem && element) {
            notificationItem = element.closest('.item');
        }
        
        let originalContent = null;
        // If we have an element, show loading state
        if (element && element.length) {
            originalContent = element.html();
            element.css('pointer-events', 'none').css('opacity', '0.6');
            element.html('<svg class="spinner-loading" width="20px" height="20px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg"><circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle></svg>');
        }
        
        jQuery.ajax({
            url: anar_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                action: 'anar_mark_single_notification_read_ajax',
                notification_id: notificationId
            },
            success: function (response) {
                if (response.success) {
                    // Remove unread class and add read class
                    if (notificationItem && notificationItem.length) {
                        notificationItem.removeClass('unread').addClass('read');
                        notificationItem.data('is-unread', '0');
                        // Remove the mark as read span if it exists
                        notificationItem.find('.anar-mark-read-btn').remove();
                    }
                    // Update unread count badge if needed
                    awca_toast(response.data.message, 'success');
                } else {
                    awca_toast(response.data.message || 'خطا در علامت گذاری', 'error');
                    if (element && element.length && originalContent) {
                        element.html(originalContent);
                        element.css('pointer-events', 'auto').css('opacity', '1');
                    }
                }
            },
            error: function (xhr, status, err) {
                awca_toast('مشکلی پیش آمد', 'error');
                if (element && element.length && originalContent) {
                    element.html(originalContent);
                    element.css('pointer-events', 'auto').css('opacity', '1');
                }
            }
        });
    }

    // Set up mark as read button handlers using event delegation
    function setupMarkAsReadButtons() {
        jQuery(document).off('click', '.anar-mark-read-btn').on('click', '.anar-mark-read-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const span = jQuery(this);
            const notificationId = span.data('notification-id');
            if (notificationId) {
                mark_single_notification_read(notificationId, span);
            }
        });
    }

    // Handle notification item click to expand and mark as read
    function setupNotificationClick() {
        jQuery(document).off('click', '#anar_notification_list .item').on('click', '#anar_notification_list .item', function(e) {
            // Don't trigger if clicking on the mark as read button
            if (jQuery(e.target).closest('.anar-mark-read-btn').length) {
                return;
            }

            const item = jQuery(this);
            const isUnread = item.data('is-unread') === 1 || item.hasClass('unread');
            const notificationId = item.data('notification-id');
            const fullContent = item.find('.notification-full-content');
            const isExpanded = fullContent.is(':visible');

            // Toggle expand/collapse
            if (isExpanded) {
                fullContent.slideUp(200);
                item.removeClass('expanded');
            } else {
                fullContent.slideDown(200);
                item.addClass('expanded');
                
                // If unread, mark as read when expanded
                if (isUnread && notificationId) {
                    mark_single_notification_read(notificationId, null, item);
                }
            }
        });
    }

    // Tab switching functionality
    jQuery('.anar-tab-btn').on('click', function() {
        const application = jQuery(this).data('application');
        
        // Update active tab
        jQuery('.anar-tab-btn').removeClass('active');
        jQuery(this).addClass('active');
        
        // Update current application
        currentApplication = application;
        
        // Fetch notifications for the selected tab
        fetchNotifications(currentPage[application] || 1, limit, application);
    });

    // Initial load - fetch wordpress notifications
    fetchNotifications(currentPage.wordpress, limit, currentApplication)
    
    // Set up mark as read buttons on initial load
    setupMarkAsReadButtons();
    // Set up notification click handlers on initial load
    setupNotificationClick();

})