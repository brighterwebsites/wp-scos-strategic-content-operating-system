/**
 * Field Tooltips JavaScript
 *
 * Displays inline help tooltips for dropdown fields
 */
(function($) {
    'use strict';

    let $tooltipPopup = null;

    // Create tooltip popup element
    function createTooltipPopup() {
        if ($tooltipPopup) return $tooltipPopup;

        $tooltipPopup = $('<div class="bw-tooltip-popup"></div>').appendTo('body');
        return $tooltipPopup;
    }

    // Get tooltip text for a field value
    function getTooltipText(type, value) {
        if (!bwTooltips || !bwTooltips[type]) return null;
        return bwTooltips[type][value] || null;
    }

    // Position tooltip near icon
    function positionTooltip($icon) {
        const iconOffset = $icon.offset();
        const iconHeight = $icon.outerHeight();

        $tooltipPopup.css({
            top: iconOffset.top + iconHeight + 8,
            left: iconOffset.left - 10
        });

        // Adjust if tooltip goes off-screen
        const tooltipWidth = $tooltipPopup.outerWidth();
        const windowWidth = $(window).width();

        if (iconOffset.left + tooltipWidth > windowWidth) {
            $tooltipPopup.css('left', windowWidth - tooltipWidth - 20);
        }
    }

    // Show tooltip
    function showTooltip($element, type, value) {
        const tooltipText = getTooltipText(type, value);
        if (!tooltipText) return;

        const popup = createTooltipPopup();

        // Get the option label for title
        const $select = $element.closest('.bw-cs-field').find('select');
        const optionLabel = $select.find('option[value="' + value + '"]').text();

        popup.html('<strong>' + optionLabel + '</strong>' + tooltipText).show();
        positionTooltip($element);
    }

    // Hide tooltip
    function hideTooltip() {
        if ($tooltipPopup) {
            $tooltipPopup.hide();
        }
    }

    // Initialize
    $(document).ready(function() {
        // Show tooltip on icon hover
        $(document).on('mouseenter', '.bw-tooltip-icon', function() {
            const $icon = $(this);
            const type = $icon.data('tooltip-type');
            const $select = $icon.closest('.bw-cs-field').find('select');
            const value = $select.val();

            showTooltip($icon, type, value);
        });

        // Hide tooltip on icon leave
        $(document).on('mouseleave', '.bw-tooltip-icon', function() {
            hideTooltip();
        });

        // Update tooltip when dropdown changes
        $(document).on('change', '.bw-field-with-tooltip', function() {
            const $select = $(this);
            const type = $select.data('tooltip-type');
            const value = $select.val();
            const $icon = $select.closest('.bw-cs-field').find('.bw-tooltip-icon');

            // Update icon's current value
            $icon.data('current-value', value);

            // If tooltip is visible, update it
            if ($tooltipPopup && $tooltipPopup.is(':visible')) {
                showTooltip($icon, type, value);
            }
        });

        // Hide tooltip when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.bw-tooltip-icon, .bw-tooltip-popup').length) {
                hideTooltip();
            }
        });

        // Hide tooltip on scroll
        $(window).on('scroll', function() {
            hideTooltip();
        });
    });

})(jQuery);
