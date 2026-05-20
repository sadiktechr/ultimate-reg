<?php
/**
 * Admin Notices - Display important notifications to admins
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
        add_action('admin_notices', array($this, 'display_notices'));
        add_action('network_admin_notices', array($this, 'display_notices'));
    }
    
    /**
     * Display admin notices
     */
    public function display_notices() {
        $screen = get_current_screen();
        
        // Only show on relevant screens
        $relevant_screens = array(
            'woocommerce_page_wpr-settings',
            'plugins',
            'product',
            'edit-product',
        );
        
        if ($screen && !in_array($screen->id, $relevant_screens) && $screen->id !== 'dashboard') {
            return;
        }
        
        $this->check_product_configured();
        $this->check_pending_registrations();
    }
    
    /**
     * Check if membership product is configured
     */
    private function check_product_configured() {
        $product_id = absint(get_option('wpr_membership_product_id', 0));
        
        if (!$product_id) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooCommerce Paid Registration:', 'woo-paid-registration'); ?></strong>
                    <?php esc_html_e('No membership product selected. Please configure the plugin settings.', 'woo-paid-registration'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpr-settings')); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php esc_html_e('Configure Now', 'woo-paid-registration'); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }
        
        // Check if product still exists and is valid
        $product = wc_get_product($product_id);
        
        if (!$product) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooCommerce Paid Registration:', 'woo-paid-registration'); ?></strong>
                    <?php esc_html_e('The selected membership product no longer exists. Please select a different product.', 'woo-paid-registration'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpr-settings')); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php esc_html_e('Fix Settings', 'woo-paid-registration'); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }
        
        if (!$product->is_purchasable()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooCommerce Paid Registration:', 'woo-paid-registration'); ?></strong>
                    <?php esc_html_e('The selected membership product is not purchasable. Please check the product settings.', 'woo-paid-registration'); ?>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')); ?>" class="button button-secondary" style="margin-left: 10px;" target="_blank">
                        <?php esc_html_e('Edit Product', 'woo-paid-registration'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        if (!$product->is_in_stock()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooCommerce Paid Registration:', 'woo-paid-registration'); ?></strong>
                    <?php esc_html_e('The selected membership product is out of stock. Users will not be able to complete registration.', 'woo-paid-registration'); ?>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')); ?>" class="button button-secondary" style="margin-left: 10px;" target="_blank">
                        <?php esc_html_e('Edit Product', 'woo-paid-registration'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Check for old pending registrations
     */
    private function check_pending_registrations() {
        $hours = absint(get_option('wpr_cleanup_hours', 24));
        $cutoff_time = strtotime("-{$hours} hours");
        
        $pending_users = get_users(array(
            'meta_key'   => 'wpr_registration_status',
            'meta_value' => 'pending_payment',
            'fields'     => 'ID',
        ));
        
        $old_pending = 0;
        foreach ($pending_users as $user_id) {
            $timestamp = get_user_meta($user_id, 'wpr_registration_timestamp', true);
            if ($timestamp && intval($timestamp) < $cutoff_time) {
                $old_pending++;
            }
        }
        
        if ($old_pending > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooCommerce Paid Registration:', 'woo-paid-registration'); ?></strong>
                    <?php 
                    printf(
                        esc_html(_n(
                            'There is %d pending registration older than %d hours.',
                            'There are %d pending registrations older than %d hours.',
                            $old_pending,
                            'woo-paid-registration'
                        )),
                        $old_pending,
                        $hours
                    );
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpr-settings')); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Run Cleanup', 'woo-paid-registration'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Show success notice after settings save
     */
    public static function show_success_notice($message) {
        set_transient('wpr_admin_notice', $message, 5);
    }
    
    /**
     * Display transient notices
     */
    public static function display_transient_notices() {
        $message = get_transient('wpr_admin_notice');
        
        if ($message) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
            delete_transient('wpr_admin_notice');
        }
    }
}

// Add transient notice display
add_action('admin_notices', array('WPR_Admin_Notices', 'display_transient_notices'));
