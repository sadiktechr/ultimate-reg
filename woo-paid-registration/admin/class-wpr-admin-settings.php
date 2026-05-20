<?php
/**
 * Admin Settings Page - Clean, focused settings for paid registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wpr_get_product_info', array($this, 'ajax_get_product_info'));
    }
    
    /**
     * Add admin menu under WooCommerce
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Paid Registration', 'woo-paid-registration'),
            __('Paid Registration', 'woo-paid-registration'),
            'manage_woocommerce',
            'wpr-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wpr_settings_group', 'wpr_membership_product_id');
        register_setting('wpr_settings_group', 'wpr_cleanup_hours');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_wpr-settings') {
            return;
        }
        
        wp_enqueue_style(
            'wpr-admin-css',
            WPR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPR_VERSION
        );
        
        wp_enqueue_script(
            'wpr-admin-js',
            WPR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WPR_VERSION,
            true
        );
        
        wp_localize_script('wpr-admin-js', 'wprAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpr_admin_nonce'),
            'strings' => array(
                'productSelected' => __('Product Selected', 'woo-paid-registration'),
                'noProductSelected' => __('No Product Selected', 'woo-paid-registration'),
                'selectProduct' => __('Please select a product from the dropdown above.', 'woo-paid-registration'),
                'editProduct' => __('Edit Product', 'woo-paid-registration'),
            )
        ));
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        $membership_product_id = absint(get_option('wpr_membership_product_id', 0));
        $cleanup_hours = absint(get_option('wpr_cleanup_hours', 24));
        $product_manager = WPR_Product_Manager::get_instance();
        $products = $product_manager->get_available_products();
        $selected_product = $membership_product_id ? wc_get_product($membership_product_id) : null;
        ?>
        <div class="wrap wpr-settings-page">
            <div class="wpr-header">
                <h1><?php esc_html_e('WooCommerce Paid Registration', 'woo-paid-registration'); ?></h1>
                <p class="wpr-subtitle"><?php esc_html_e('Force users to pay for membership during registration. No registration completes without payment.', 'woo-paid-registration'); ?></p>
            </div>
            
            <div class="wpr-content-grid">
                <!-- Configuration Card -->
                <div class="wpr-card wpr-card-primary">
                    <div class="wpr-card-header">
                        <h2><?php esc_html_e('Membership Product', 'woo-paid-registration'); ?></h2>
                        <span class="wpr-icon">🛒</span>
                    </div>
                    <div class="wpr-card-body">
                        <form method="post" action="options.php">
                            <?php settings_fields('wpr_settings_group'); ?>
                            
                            <div class="wpr-form-group">
                                <label for="wpr_membership_product_id" class="wpr-label">
                                    <?php esc_html_e('Select Membership Product', 'woo-paid-registration'); ?>
                                </label>
                                <select name="wpr_membership_product_id" id="wpr_membership_product_id" class="wpr-select">
                                    <option value="0"><?php esc_html_e('— Select a Product —', 'woo-paid-registration'); ?></option>
                                    <?php foreach ($products as $id => $label): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($membership_product_id, $id); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="wpr-help-text">
                                    <?php esc_html_e('Choose an existing WooCommerce product that will be used as the membership fee. Users must purchase this product to complete registration.', 'woo-paid-registration'); ?>
                                </p>
                            </div>
                            
                            <?php if ($selected_product): ?>
                            <div class="wpr-product-info wpr-product-selected" id="wprProductInfo">
                                <div class="wpr-product-details">
                                    <strong><?php esc_html_e('Selected Product:', 'woo-paid-registration'); ?></strong>
                                    <span><?php echo esc_html($selected_product->get_name()); ?></span>
                                    <span class="wpr-product-price"><?php echo $selected_product->get_price_html(); ?></span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $selected_product->get_id() . '&action=edit')); ?>" 
                                   class="wpr-button wpr-button-secondary" target="_blank">
                                    <?php esc_html_e('Edit Product', 'woo-paid-registration'); ?> ↗
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="wpr-product-info wpr-product-warning" id="wprProductInfo">
                                <p><strong>⚠️ <?php esc_html_e('No product selected!', 'woo-paid-registration'); ?></strong></p>
                                <p><?php esc_html_e('Please create a product in WooCommerce first, then select it above.', 'woo-paid-registration'); ?></p>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" 
                                   class="wpr-button wpr-button-primary" target="_blank">
                                    <?php esc_html_e('Create New Product', 'woo-paid-registration'); ?> →
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="wpr-form-actions">
                                <button type="submit" class="wpr-button wpr-button-primary wpr-button-large">
                                    <?php esc_html_e('Save Settings', 'woo-paid-registration'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cleanup Settings Card -->
                <div class="wpr-card">
                    <div class="wpr-card-header">
                        <h2><?php esc_html_e('Cleanup Settings', 'woo-paid-registration'); ?></h2>
                        <span class="wpr-icon">🧹</span>
                    </div>
                    <div class="wpr-card-body">
                        <form method="post" action="options.php">
                            <?php settings_fields('wpr_settings_group'); ?>
                            
                            <div class="wpr-form-group">
                                <label for="wpr_cleanup_hours" class="wpr-label">
                                    <?php esc_html_e('Auto-Cleanup Hours', 'woo-paid-registration'); ?>
                                </label>
                                <input type="number" 
                                       name="wpr_cleanup_hours" 
                                       id="wpr_cleanup_hours" 
                                       class="wpr-input" 
                                       value="<?php echo esc_attr($cleanup_hours); ?>" 
                                       min="1" 
                                       max="168" />
                                <p class="wpr-help-text">
                                    <?php esc_html_e('Pending registrations older than this many hours will be automatically deleted. Range: 1-168 hours (1 week).', 'woo-paid-registration'); ?>
                                </p>
                            </div>
                            
                            <div class="wpr-form-actions">
                                <button type="submit" class="wpr-button wpr-button-primary">
                                    <?php esc_html_e('Save Cleanup Settings', 'woo-paid-registration'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <hr class="wpr-divider" />
                        
                        <div class="wpr-manual-cleanup">
                            <h3><?php esc_html_e('Manual Cleanup', 'woo-paid-registration'); ?></h3>
                            <p class="wpr-help-text">
                                <?php esc_html_e('Immediately remove all pending registrations that are older than the specified hours above.', 'woo-paid-registration'); ?>
                            </p>
                            <button type="button" class="wpr-button wpr-button-danger" id="wprManualCleanup">
                                <?php esc_html_e('Run Cleanup Now', 'woo-paid-registration'); ?>
                            </button>
                            <span id="wprCleanupResult"></span>
                        </div>
                    </div>
                </div>
                
                <!-- How It Works Card -->
                <div class="wpr-card wpr-card-info">
                    <div class="wpr-card-header">
                        <h2><?php esc_html_e('How It Works', 'woo-paid-registration'); ?></h2>
                        <span class="wpr-icon">ℹ️</span>
                    </div>
                    <div class="wpr-card-body">
                        <ol class="wpr-steps">
                            <li>
                                <strong><?php esc_html_e('User Registers', 'woo-paid-registration'); ?></strong>
                                <p><?php esc_html_e('A new user fills out the registration form and submits it.', 'woo-paid-registration'); ?></p>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Account Created (Pending)', 'woo-paid-registration'); ?></strong>
                                <p><?php esc_html_e('User account is created with "Pending Payment" status. They cannot log in yet.', 'woo-paid-registration'); ?></p>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Redirected to Checkout', 'woo-paid-registration'); ?></strong>
                                <p><?php esc_html_e('User is automatically redirected to WooCommerce checkout with the membership product in cart.', 'woo-paid-registration'); ?></p>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Payment Required', 'woo-paid-registration'); ?></strong>
                                <p><?php esc_html_e('User must complete payment. The membership product cannot be removed from cart.', 'woo-paid-registration'); ?></p>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Account Activated', 'woo-paid-registration'); ?></strong>
                                <p><?php esc_html_e('Once payment is successful, the account is activated and welcome email is sent.', 'woo-paid-registration'); ?></p>
                            </li>
                        </ol>
                    </div>
                </div>
                
                <!-- Status Reference Card -->
                <div class="wpr-card">
                    <div class="wpr-card-header">
                        <h2><?php esc_html_e('Registration Statuses', 'woo-paid-registration'); ?></h2>
                        <span class="wpr-icon">📊</span>
                    </div>
                    <div class="wpr-card-body">
                        <table class="wpr-status-table">
                            <tr>
                                <td><span class="wpr-status-badge wpr-status-pending_payment"><?php esc_html_e('Pending Payment', 'woo-paid-registration'); ?></span></td>
                                <td><?php esc_html_e('User registered but has not completed payment. Cannot log in.', 'woo-paid-registration'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="wpr-status-badge wpr-status-active"><?php esc_html_e('Active', 'woo-paid-registration'); ?></span></td>
                                <td><?php esc_html_e('Payment completed successfully. Full account access granted.', 'woo-paid-registration'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="wpr-status-badge wpr-status-payment_failed"><?php esc_html_e('Payment Failed', 'woo-paid-registration'); ?></span></td>
                                <td><?php esc_html_e('Payment attempt failed. User needs to re-register or contact support.', 'woo-paid-registration'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="wpr-status-badge wpr-status-cancelled"><?php esc_html_e('Cancelled', 'woo-paid-registration'); ?></span></td>
                                <td><?php esc_html_e('Registration was cancelled by user or admin.', 'woo-paid-registration'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to get product info
     */
    public function ajax_get_product_info() {
        check_ajax_referer('wpr_admin_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID', 'woo-paid-registration')));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found', 'woo-paid-registration')));
        }
        
        wp_send_json_success(array(
            'name' => $product->get_name(),
            'price' => $product->get_price_html(),
            'editUrl' => admin_url('post.php?post=' . $product_id . '&action=edit'),
        ));
    }
    
    /**
     * Manual cleanup via AJAX
     */
    public static function ajax_manual_cleanup() {
        check_ajax_referer('wpr_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-paid-registration')));
        }
        
        $product_manager = WPR_Product_Manager::get_instance();
        $product_manager->cleanup_pending_registrations();
        
        wp_send_json_success(array('message' => __('Cleanup completed successfully', 'woo-paid-registration')));
    }
}

// Register manual cleanup AJAX handler
add_action('wp_ajax_wpr_manual_cleanup', array('WPR_Admin_Settings', 'ajax_manual_cleanup'));
