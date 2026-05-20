<?php
/**
 * Admin Notices Class
 * 
 * Handles admin notifications and messages
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Admin_Notices {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('network_admin_notices', array($this, 'display_admin_notices'));
        
        // Dismiss notice handler
        add_action('wp_ajax_wpr_dismiss_notice', array($this, 'ajax_dismiss_notice'));
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $screen = get_current_screen();
        
        // Only show on relevant screens
        $relevant_screens = array(
            'woocommerce_page_wpr-settings',
            'woocommerce_page_wc-settings',
            'plugins',
            'dashboard',
        );
        
        if (!in_array($screen->id, $relevant_screens)) {
            return;
        }
        
        // Check if WooCommerce is active
        $this->check_woocommerce_active();
        
        // Check if membership product exists
        $this->check_membership_product();
        
        // Check for pending registrations
        $this->check_pending_registrations();
        
        // Show setup notice if needed
        $this->show_setup_notice();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function check_woocommerce_active() {
        if (!class_exists('WooCommerce')) {
            $dismissed = get_user_meta(get_current_user_id(), '_wpr_dismissed_woo_notice', true);
            
            if (!$dismissed) {
                ?>
                <div class="notice notice-error wpr-notice is-dismissible" data-notice="woo_required">
                    <p>
                        <strong><?php _e('WooCommerce Paid Registration requires WooCommerce', 'woo-paid-registration'); ?></strong>
                        <?php _e('Please install and activate WooCommerce to use this plugin.', 'woo-paid-registration'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Check if membership product exists
     */
    private function check_membership_product() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $settings = get_option('wpr_settings', array());
        $product_id = isset($settings['wpr_product_id']) ? absint($settings['wpr_product_id']) : false;
        $product = $product_id ? wc_get_product($product_id) : false;
        
        if (!$product && isset($settings['wpr_enabled']) && $settings['wpr_enabled'] === 'yes') {
            $dismissed = get_user_meta(get_current_user_id(), '_wpr_dismissed_product_notice', true);
            
            if (!$dismissed) {
                ?>
                <div class="notice notice-warning wpr-notice is-dismissible" data-notice="product_missing">
                    <p>
                        <strong><?php _e('Membership Product Not Found', 'woo-paid-registration'); ?></strong>
                        <?php _e('The membership product is missing. Please create it in the plugin settings.', 'woo-paid-registration'); ?>
                        <a href="<?php echo esc_url(admin_url('woocommerce_page_wpr-settings')); ?>" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Go to Settings', 'woo-paid-registration'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Check for pending registrations
     */
    private function check_pending_registrations() {
        global $wpdb;
        
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_wpr_registration_status' 
             AND meta_value = 'pending_payment'"
        );
        
        if ($pending_count > 0) {
            $dismissed = get_user_meta(get_current_user_id(), '_wpr_dismissed_pending_notice', true);
            
            if (!$dismissed) {
                ?>
                <div class="notice notice-info wpr-notice is-dismissible" data-notice="pending_registrations">
                    <p>
                        <strong><?php _e('Pending Registrations', 'woo-paid-registration'); ?></strong>
                        <?php printf(
                            _n(
                                'There is %d user with pending payment.',
                                'There are %d users with pending payments.',
                                $pending_count,
                                'woo-paid-registration'
                            ),
                            $pending_count
                        ); ?>
                        <a href="<?php echo esc_url(admin_url('users.php')); ?>" style="margin-left: 10px;">
                            <?php _e('View Users', 'woo-paid-registration'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Show setup notice for new installations
     */
    private function show_setup_notice() {
        $settings = get_option('wpr_settings', array());
        
        // Only show if plugin is newly activated and not configured
        if (empty($settings) || !isset($settings['wpr_product_id'])) {
            $dismissed = get_user_meta(get_current_user_id(), '_wpr_dismissed_setup_notice', true);
            
            if (!$dismissed && current_user_can('manage_options')) {
                ?>
                <div class="notice notice-info wpr-notice is-dismissible" data-notice="setup_required">
                    <p>
                        <strong><?php _e('Welcome to WooCommerce Paid Registration!', 'woo-paid-registration'); ?></strong>
                        <?php _e('Get started by configuring your paid registration settings.', 'woo-paid-registration'); ?>
                        <a href="<?php echo esc_url(admin_url('woocommerce_page_wpr-settings')); ?>" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Configure Now', 'woo-paid-registration'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * AJAX handler to dismiss notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('wpr_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        $notice = isset($_POST['notice']) ? sanitize_text_field($_POST['notice']) : '';
        $user_id = get_current_user_id();
        
        switch ($notice) {
            case 'woo_required':
                update_user_meta($user_id, '_wpr_dismissed_woo_notice', true);
                break;
            case 'product_missing':
                update_user_meta($user_id, '_wpr_dismissed_product_notice', true);
                break;
            case 'pending_registrations':
                update_user_meta($user_id, '_wpr_dismissed_pending_notice', true);
                break;
            case 'setup_required':
                update_user_meta($user_id, '_wpr_dismissed_setup_notice', true);
                break;
            default:
                wp_send_json_error();
        }
        
        wp_send_json_success();
    }
}
