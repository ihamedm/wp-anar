/**
 * Tooltip functionality module
 * Handles tooltip display for elements with .awca-tooltip-on class
 */
jQuery(document).ready(function($) {
    var tooltip = jQuery('<div class="awca-tooltip" style="position: absolute; background-color: black; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: none; z-index: 1000;"></div>');
    jQuery('body').append(tooltip);

    jQuery(document).on('mouseenter', '.awca-tooltip-on', function() {
        // Get the title attribute
        var title = jQuery(this).attr('title');
        // Set the tooltip text
        tooltip.text(title);

        // Calculate the right offset for the tooltip
        var elementRightOffset = jQuery(this).offset().left + jQuery(this).outerWidth();
        var tooltipWidth = tooltip.outerWidth();

        tooltip.css({
            display: 'block',
            left: elementRightOffset - tooltipWidth + 'px',
            top: jQuery(this).offset().top - tooltip.outerHeight() + 'px'
        });
    });

    jQuery(document).on('mouseleave', '.awca-tooltip-on', function() {
        // Hide the tooltip
        tooltip.hide();
    });
});
