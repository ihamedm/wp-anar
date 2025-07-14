import {awca_toast} from "./functions";

jQuery(document).ready(function() {
    function fetchNotifications (page, limit){
        var anarNotifications =  jQuery('#awca_notification_list')
        if(anarNotifications.length !== 0){

            var loadingIcon = anarNotifications.find('.spinner-loading')
            var msgType = 'error'

            jQuery.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: {
                    action: 'awca_fetch_notifications_ajax',
                    page: page,
                    limit: limit
                },
                beforeSend: function () {
                    loadingIcon.show();
                },
                success: function (response) {
                    if (response.success) {
                        anarNotifications.html(response.data.output)
                        msgType = 'success'
                        mark_page_as_read(page, limit)
                        paginateNotifications(response.data.total, response.data.page, response.data.limit)
                    }
                    awca_toast(response.data.message, msgType);
                },
                error: function (xhr, status, err) {
                    awca_toast(xhr.responseText)
                    loadingIcon.hide();

                },
                complete: function () {
                    loadingIcon.hide();
                },
            });

            function paginateNotifications(total, page, limit) {
                var pagination = jQuery('#awca_pagination');
                var totalPages = Math.ceil(total / limit);
                var paginationHtml = '';

                for (var i = 1; i <= totalPages; i++) {
                    var classNames = (i === page) ? 'current' : '';
                    paginationHtml += '<button class="pagination-btn '+classNames+'" data-page="' + i + '">' + i + '</button>';
                }

                pagination.html(paginationHtml);

                pagination.find('.pagination-btn').on('click', function () {
                    fetchNotifications(jQuery(this).data('page'), limit);
                });
            }


            function mark_page_as_read(page, limit) {
                var msgType = 'error'
                jQuery.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'awca_mark_as_read_notifications_ajax',
                        page: page,
                        limit: limit
                    },
                    success: function (response) {
                        if (response.success) {
                            msgType = 'success'
                        }
                        awca_toast(response.data.message, msgType);
                    },
                    error: function (xhr, status, err) {
                        awca_toast(xhr.responseText)
                    }
                });
            }

        }

    }

    fetchNotifications(1,100)
})
