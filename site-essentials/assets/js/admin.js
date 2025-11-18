/**
 * Site Essentials Admin JavaScript
 *
 * @package    SiteEssentials
 * @subpackage Assets
 * @version    1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initModuleToggles();
        initImportExport();
        initCacheClear();
        initModuleSettingsAccordion();
    });

    /**
     * Initialize module toggles
     */
    function initModuleToggles() {
        $('.se-module-toggle').on('change', function() {
            const $toggle = $(this);
            const $card = $toggle.closest('.se-module-card');
            const moduleId = $toggle.data('module-id');
            const enabled = $toggle.is(':checked');

            // Disable toggle during request
            $toggle.prop('disabled', true);

            // Send AJAX request
            $.ajax({
                url: siteEssentials.ajaxurl,
                type: 'POST',
                data: {
                    action: 'site_essentials_toggle_module',
                    nonce: siteEssentials.nonce,
                    module_id: moduleId,
                    enabled: enabled
                },
                success: function(response) {
                    // Debug logging
                    console.log('Toggle response:', response);

                    if (response.success) {
                        // Verify the toggle worked
                        if (response.data.verified !== true) {
                            console.error('Toggle verification failed!', response.data);
                            $toggle.prop('checked', !enabled);
                            showNotice('error', 'Module toggle failed verification. Please refresh and try again.');
                            $toggle.prop('disabled', false);
                            return;
                        }

                        // Update card state
                        if (enabled) {
                            $card.removeClass('disabled').addClass('enabled');
                            // Show settings card
                            $('.se-module-settings-card[data-module-id="' + moduleId + '"]').slideDown();
                            showNotice('success', 'Module enabled successfully.');
                        } else {
                            $card.removeClass('enabled').addClass('disabled');
                            // Hide settings card
                            $('.se-module-settings-card[data-module-id="' + moduleId + '"]').slideUp();
                            showNotice('success', 'Module disabled. Settings saved and hidden.');
                        }
                    } else {
                        // Revert toggle on error
                        $toggle.prop('checked', !enabled);
                        showNotice('error', response.data.message || 'Failed to toggle module');
                    }
                },
                error: function() {
                    // Revert toggle on error
                    $toggle.prop('checked', !enabled);
                    showNotice('error', 'Failed to communicate with server');
                },
                complete: function() {
                    // Re-enable toggle
                    $toggle.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize import/export functionality
     */
    function initImportExport() {
        // Export settings
        $('#se-export-settings').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: siteEssentials.ajaxurl,
                type: 'POST',
                data: {
                    action: 'site_essentials_export_settings',
                    nonce: siteEssentials.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Download JSON file
                        downloadJSON(response.data.json, response.data.filename);
                        showNotice('success', 'Settings exported successfully');
                    } else {
                        showNotice('error', response.data.message || 'Export failed');
                    }
                },
                error: function() {
                    showNotice('error', 'Failed to communicate with server');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Export Settings');
                }
            });
        });

        // Import settings
        $('#se-import-settings').on('click', function() {
            const $button = $(this);
            const json = $('#se-import-json').val();
            const merge = $('#se-import-merge').is(':checked');

            if (!json) {
                showNotice('error', 'Please paste JSON data to import');
                return;
            }

            // Validate JSON
            try {
                JSON.parse(json);
            } catch (e) {
                showNotice('error', 'Invalid JSON format');
                return;
            }

            if (!confirm('Are you sure you want to import these settings? This will modify your current configuration.')) {
                return;
            }

            $button.prop('disabled', true).text('Importing...');

            $.ajax({
                url: siteEssentials.ajaxurl,
                type: 'POST',
                data: {
                    action: 'site_essentials_import_settings',
                    nonce: siteEssentials.nonce,
                    json: json,
                    merge: merge
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', 'Settings imported successfully. Reloading page...');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice('error', response.data.message || 'Import failed');
                        $button.prop('disabled', false).text('Import Settings');
                    }
                },
                error: function() {
                    showNotice('error', 'Failed to communicate with server');
                    $button.prop('disabled', false).text('Import Settings');
                }
            });
        });
    }

    /**
     * Initialize module settings accordion
     *
     * Show/hide settings cards based on module enabled state
     */
    function initModuleSettingsAccordion() {
        // Hide settings for disabled modules on page load
        $('.se-module-card.disabled').each(function() {
            const moduleId = $(this).data('module-id');
            $('.se-module-settings-card[data-module-id="' + moduleId + '"]').hide();
        });

        // Add click handler to toggle cards for instant show/hide (before AJAX)
        $('.se-module-toggle').on('click', function() {
            const moduleId = $(this).data('module-id');
            const $settingsCard = $('.se-module-settings-card[data-module-id="' + moduleId + '"]');

            if ($(this).is(':checked')) {
                $settingsCard.slideDown(300);
            } else {
                $settingsCard.slideUp(300);
            }
        });
    }

    /**
     * Initialize cache clear functionality
     */
    function initCacheClear() {
        $('#se-clear-cache').on('click', function() {
            const $button = $(this);

            if (!confirm('Are you sure you want to clear all Site Essentials cache?')) {
                return;
            }

            $button.prop('disabled', true).text('Clearing...');

            // Note: This will be implemented when we add the AJAX handler
            // For now, just show a message
            setTimeout(function() {
                showNotice('info', 'Cache clear functionality will be implemented in next update');
                $button.prop('disabled', false).text('Clear All Cache');
            }, 500);
        });
    }

    /**
     * Download JSON data as file
     *
     * @param {string} content  JSON content
     * @param {string} filename Filename
     */
    function downloadJSON(content, filename) {
        const blob = new Blob([content], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /**
     * Show admin notice
     *
     * @param {string} type    Notice type (success, error, warning, info)
     * @param {string} message Notice message
     */
    function showNotice(type, message) {
        const $notice = $('<div>')
            .addClass('notice notice-' + type + ' is-dismissible')
            .html('<p>' + message + '</p>')
            .hide();

        $('.site-essentials-wrap h1').after($notice);
        $notice.slideDown();

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.slideUp(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $notice.slideUp(function() {
                $(this).remove();
            });
        });
    }

})(jQuery);
