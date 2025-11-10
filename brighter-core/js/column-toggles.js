/**
 * Column Toggle Buttons JavaScript
 *
 * Handles show/hide functionality for admin column groups
 */
(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        // Get column groups from PHP
        const columnGroups = bwColumnToggles.columnGroups || {};
        let hiddenGroups = bwColumnToggles.hiddenGroups || [];

        // Initialize: Add group classes to columns
        function initializeColumnClasses() {
            $.each(columnGroups, function(group, columns) {
                $.each(columns, function(i, col) {
                    // Add classes to TH (headers)
                    $('.wp-list-table thead th#' + col).addClass('bw-group-' + group);
                    $('.wp-list-table thead th.column-' + col).addClass('bw-group-' + group);

                    // Add classes to TD (cells)
                    $('.wp-list-table tbody td.column-' + col).addClass('bw-group-' + group);
                });
            });

            // Also detect SEOPress columns dynamically
            $('.wp-list-table thead th').each(function() {
                const columnId = $(this).attr('id');
                if (columnId && columnId.startsWith('seopress')) {
                    $(this).addClass('bw-group-seopress');
                    $('.wp-list-table tbody td.column-' + columnId).addClass('bw-group-seopress');
                }
            });
        }

        // Apply hidden groups on load
        function applyHiddenGroups() {
            hiddenGroups.forEach(function(group) {
                $('.bw-group-' + group).hide();
            });
        }

        // Toggle button click
        $('.bw-toggle-group').on('click', function(e) {
            e.preventDefault();
            const group = $(this).data('group');
            const columns = $('.bw-group-' + group);

            if (columns.is(':visible')) {
                // Hide
                columns.hide();
                $(this).removeClass('button-primary');
                if (hiddenGroups.indexOf(group) === -1) {
                    hiddenGroups.push(group);
                }
            } else {
                // Show
                columns.show();
                $(this).addClass('button-primary');
                const index = hiddenGroups.indexOf(group);
                if (index > -1) {
                    hiddenGroups.splice(index, 1);
                }
            }

            // Save preference
            saveHiddenGroups(hiddenGroups);
        });

        // Show all button
        $('.bw-toggle-all').on('click', function(e) {
            e.preventDefault();

            // Show all columns
            $('.wp-list-table thead th').show();
            $('.wp-list-table tbody td').show();

            // Update button states
            $('.bw-toggle-group').addClass('button-primary');

            // Clear hidden groups
            hiddenGroups = [];
            saveHiddenGroups([]);
        });

        // Save hidden groups via AJAX
        function saveHiddenGroups(groups) {
            $.post(ajaxurl, {
                action: 'bw_save_hidden_column_groups',
                groups: groups,
                nonce: bwColumnToggles.nonce
            }, function(response) {
                if (response.success) {
                    console.log('Column preferences saved:', response.data.saved);
                }
            });
        }

        // Initialize on page load
        initializeColumnClasses();
        applyHiddenGroups();

        // Also handle when screen options are changed
        $('#adv-settings').on('click', 'input[type="checkbox"]', function() {
            // Re-apply group classes after screen options change
            setTimeout(function() {
                initializeColumnClasses();
                applyHiddenGroups();
            }, 100);
        });
    });

})(jQuery);
