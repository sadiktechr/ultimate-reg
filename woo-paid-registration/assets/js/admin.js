/**
 * Admin JavaScript for WooCommerce Paid Registration
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Product selection change handler - show/hide product info box
        $('.wpr-product-select').on('change', function() {
            var selectedProductId = $(this).val();
            
            if (selectedProductId) {
                // User selected a product, form will save on submit
                console.log('Product selected: ' + selectedProductId);
            } else {
                // No product selected
                console.log('No product selected');
            }
        });
        
        // Cleanup Pending Registrations
        $('#wpr-cleanup-pending').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete all pending registrations older than the specified hours? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.addClass('wpr-loading').text(wprAdmin.strings.cleaning);
            
            $.ajax({
                url: wprAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpr_cleanup_pending_users',
                    nonce: wprAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', wprAdmin.strings.cleaned);
                        
                        // Remove pending registration notice if visible
                        $('.wpr-notice[data-notice="pending_registrations"]').fadeOut();
                    } else {
                        $button.removeClass('wpr-loading').text(originalText);
                        showNotice('error', wprAdmin.strings.error + ' ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $button.removeClass('wpr-loading').text(originalText);
                    showNotice('error', wprAdmin.strings.error + ' Failed to cleanup');
                }
            });
        });
        
        // Dismiss Notices
        $('.wpr-notice.is-dismissible').on('click', '.notice-dismiss', function() {
            var $notice = $(this).closest('.wpr-notice');
            var noticeType = $notice.data('notice');
            
            if (noticeType) {
                $.ajax({
                    url: wprAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpr_dismiss_notice',
                        nonce: wprAdmin.nonce,
                        notice: noticeType
                    }
                });
            }
        });
        
        // Price field validation
        $('#wpr_membership_price').on('change', function() {
            var price = parseFloat($(this).val());
            
            if (isNaN(price) || price < 0) {
                $(this).val('10.00');
                showNotice('error', 'Please enter a valid price greater than or equal to 0');
            }
        });
        
        // Cleanup hours validation
        $('#wpr_cleanup_hours').on('change', function() {
            var hours = parseInt($(this).val());
            
            if (isNaN(hours) || hours < 1) {
                $(this).val('24');
                showNotice('error', 'Please enter a valid number of hours (minimum 1)');
            } else if (hours > 168) {
                $(this).val('168');
                showNotice('error', 'Maximum cleanup hours is 168 (7 days)');
            }
        });
        
        // Toggle visibility based on settings
        toggleFieldVisibility();
        
        $('#wpr_enable_free_registration').on('change', function() {
            toggleFieldVisibility();
        });
        
        function toggleFieldVisibility() {
            var enableFree = $('#wpr_enable_free_registration').is(':checked');
            var freeRolesRow = $('input[name="wpr_settings[wpr_free_roles][]"]').closest('tr');
            
            if (enableFree) {
                freeRolesRow.show();
            } else {
                freeRolesRow.hide();
            }
        }
        
        // Helper function to show notices
        function showNotice(type, message) {
            var noticeClass = type === 'success' ? 'wpr-message success' : 'wpr-message error';
            var $notice = $('<div class="' + noticeClass + '">' + message + '</div>');
            
            // Remove existing notices
            $('.wpr-message').remove();
            
            // Insert at top of form
            $('.wpr-settings-form').prepend($notice);
            
            // Fade out after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Auto-hide success messages
        $('.updated, .notice-success').not('.is-dismissible').delay(5000).fadeOut();
        
        // Confirm before leaving with unsaved changes
        var formChanged = false;
        $('.wpr-settings-form input, .wpr-settings-form select, .wpr-settings-form textarea').on('change input', function() {
            formChanged = true;
        });
        
        $('.wpr-settings-form').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    });

})(jQuery);
