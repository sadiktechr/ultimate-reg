/**
 * WooCommerce Paid Registration - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Handle manual cleanup button
        $('#wprManualCleanup').on('click', function() {
            var $button = $(this);
            var $result = $('#wprCleanupResult');
            
            if (!confirm('Are you sure you want to run cleanup now? This will delete all pending registrations older than the specified hours.')) {
                return;
            }
            
            $button.addClass('wpr-loading').prop('disabled', true);
            $result.html('');
            
            $.ajax({
                url: wprAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpr_manual_cleanup',
                    nonce: wprAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="wpr-message wpr-message-success">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span class="wpr-message wpr-message-error">' + (response.data.message || 'Error running cleanup') + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="wpr-message wpr-message-error">An error occurred. Please try again.</span>');
                },
                complete: function() {
                    $button.removeClass('wpr-loading').prop('disabled', false);
                    
                    // Clear message after 5 seconds
                    setTimeout(function() {
                        $result.html('');
                    }, 5000);
                }
            });
        });
        
        // Handle product selection change
        $('#wpr_membership_product_id').on('change', function() {
            var productId = $(this).val();
            var $productInfo = $('#wprProductInfo');
            
            if (productId === '0' || productId === '') {
                // No product selected
                $productInfo.removeClass('wpr-product-selected').addClass('wpr-product-warning');
                $productInfo.html(`
                    <p><strong>⚠️ ${wprAdmin.strings.noProductSelected}</strong></p>
                    <p>${wprAdmin.strings.selectProduct}</p>
                    <a href="${wpApiSettings ? wpApiSettings.root.replace('/wp-json/', '/post-new.php?post_type=product') : '#'}" class="wpr-button wpr-button-primary" target="_blank">
                        Create New Product →
                    </a>
                `);
                return;
            }
            
            // Fetch product info via AJAX
            $.ajax({
                url: wprAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpr_get_product_info',
                    nonce: wprAdmin.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        var product = response.data;
                        $productInfo.removeClass('wpr-product-warning').addClass('wpr-product-selected');
                        $productInfo.html(`
                            <div class="wpr-product-details">
                                <strong>${wprAdmin.strings.productSelected}:</strong>
                                <span>${product.name}</span>
                                <span class="wpr-product-price">${product.price}</span>
                            </div>
                            <a href="${product.editUrl}" class="wpr-button wpr-button-secondary" target="_blank">
                                ${wprAdmin.strings.editProduct} ↗
                            </a>
                        `);
                    }
                }
            });
        });
        
        // Add confirmation to settings form submit
        $('form[action="options.php"]').on('submit', function(e) {
            var productId = $('#wpr_membership_product_id').val();
            
            if (productId === '0' || productId === '') {
                if (!confirm('You have not selected a membership product. Are you sure you want to save these settings? The plugin will not work without a valid product.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Highlight current section on scroll
        $(window).on('scroll', function() {
            var scrollPosition = $(window).scrollTop() + 100;
            
            $('.wpr-card').each(function() {
                var $section = $(this);
                var sectionTop = $section.offset().top;
                var sectionHeight = $section.outerHeight();
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    $section.css('border-color', '#667eea');
                } else {
                    $section.css('border-color', '#e2e8f0');
                }
            });
        });
        
        // Add smooth scroll to anchor links
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.hash);
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 20
                }, 500);
            }
        });
        
        // Show/hide help text on focus
        $('.wpr-select, .wpr-input').on('focus', function() {
            $(this).next('.wpr-help-text').fadeIn(200);
        }).on('blur', function() {
            $(this).next('.wpr-help-text').fadeOut(200);
        });
        
        // Auto-dismiss success messages
        $('.notice.is-dismissible').each(function() {
            var $notice = $(this);
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 10000);
        });
        
        // Add keyboard navigation for cards
        $('.wpr-card').attr('tabindex', '0').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                $(this).find('button, a').first().focus();
            }
        });
        
        // Initialize tooltips (if any)
        $('[data-tooltip]').each(function() {
            var $element = $(this);
            var tooltipText = $element.attr('data-tooltip');
            
            $element.on('mouseenter', function() {
                var $tooltip = $('<div class="wpr-tooltip">' + tooltipText + '</div>');
                $tooltip.css({
                    position: 'absolute',
                    background: '#1e293b',
                    color: '#fff',
                    padding: '8px 12px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    zIndex: 1000
                });
                
                $('body').append($tooltip);
                
                var offset = $element.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 5,
                    left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                });
            }).on('mouseleave', function() {
                $('.wpr-tooltip').remove();
            });
        });
        
    });

})(jQuery);
