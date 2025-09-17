/**
 * Product update functionality module
 * Handles asynchronous product updates on product pages
 */
jQuery(document).ready(function($) {
    var metaTag = $('meta[name="anar-product-id"]');
    var productId = null;

    if (metaTag.length > 0) {
        productId = metaTag.attr('content');
    }

    if (productId && !isNaN(productId)) {
        productId = parseInt(productId, 10);
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'anar_update_product_async',
                product_id: productId,
                nonce: awca_ajax_object.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                } else {
                }
                console.log(response);
            },
            error: function(xhr, status, error) {
            },
            complete: function() {
            }
        });
    } else {
    }
});
