jQuery(document).ready(function($) {
    // Handle alert mark as read link
    $(document).on('click', '.anar-alert-dismiss-link', function(e) {
        e.preventDefault();
        
        const link = $(this);
        const alert = link.closest('.notice');
        const notificationId = link.data('notification-id');
        
        if (!notificationId) {
            // Fallback: just hide the alert
            alert.fadeOut(300, function() {
                $(this).remove();
            });
            return;
        }
        
        // Disable link during request
        link.css('pointer-events', 'none').css('opacity', '0.6');
        
        $.ajax({
            url: anar_alert_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'anar_mark_alert_notification_read_ajax',
                notification_id: notificationId,
                nonce: anar_alert_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    // On error, still hide the alert
                    alert.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            },
            error: function() {
                // On error, still hide the alert
                alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
    });
});

