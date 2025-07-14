import {awca_toast, paginateLinks} from "./functions";

function fetchPendingPayments(page, limit){
    var anarPayments =  jQuery('#awca_payments')
    if(anarPayments.length !== 0){

        var loadingIcon = anarPayments.find('.spinner-loading')
        var payableEl = anarPayments.find('#awca_payable')
        var listEl = anarPayments.find('#awca_payment_list')
        var loadingFrame = anarPayments.find('#awca-loading-frame')
        var msgType = 'error'

        jQuery.ajax({
            url: awca_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                action: 'awca_fetch_payments_ajax',
                page: page,
                limit: limit
            },
            beforeSend: function () {
                loadingFrame.show();
            },
            success: function (response) {
                if (response.success) {
                    listEl.html(response.data.output)
                    payableEl.html(response.data.payable)
                    paginatePendingPayments(response.data.total, page, limit)
                    msgType = 'success'
                }
                awca_toast(response.data.message, msgType);
            },
            error: function (xhr, status, err) {
                awca_toast(xhr.responseText)
                loadingFrame.hide();

            },
            complete: function () {
                loadingFrame.hide();
            },
        });

    }

    function paginatePendingPayments(total, page, limit) {
        var pagination = jQuery('#awca_pagination');
        var totalPages = Math.ceil(total / limit);

        // Generate pagination links using WordPress-style pagination
        var paginationHtml = paginateLinks({
            current: page,
            total: totalPages,
            base: '#page-%#%', // Placeholder for pagination links
            format: '?page=%#%', // URL structure for pages
            prev_text: '&laquo;', // Previous page link text
            next_text: '&raquo;', // Next page link text
        });

        pagination.html(paginationHtml);

        // Handle pagination clicks
        pagination.find('a').on('click', function (e) {
            e.preventDefault();
            var newPage = jQuery(this).data('page');
            AnarHandler.fetchPendingPayments(newPage, limit);
        });
    }
}

fetchPendingPayments(1,10)