/**
 * System Health JavaScript
 * PAX Support Pro
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initializeHealthCheck();
    });

    /**
     * Initialize health check functionality
     */
    function initializeHealthCheck() {
        $('#pax-recheck-health').on('click', function(e) {
            e.preventDefault();
            recheckHealth();
        });
    }

    /**
     * Recheck system health
     */
    function recheckHealth() {
        const $button = $('#pax-recheck-health');
        const originalText = $button.html();
        
        // Show loading state
        $button.prop('disabled', true);
        $button.html('<span class="dashicons dashicons-update"></span> Checking...');
        $button.find('.dashicons').css('animation', 'pax-spin 1s linear infinite');

        // Reload page after a short delay to simulate check
        setTimeout(function() {
            location.reload();
        }, 1500);
    }

})(jQuery);
