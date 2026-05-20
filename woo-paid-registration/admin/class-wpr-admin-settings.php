<?php
/**
 * Admin Settings Class
 * 
 * Creates the plugin settings page in WordPress admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Admin_Settings {
    
    private static $instance = null;
    private $settings_tab_id = 'wpr-settings';
    
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
        // Add settings menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Handle AJAX actions
        add_action('wp_ajax_wpr_create_membership_product', array($this, 'ajax_create_membership_product'));
        add_action('wp_ajax_wpr_cleanup_pending_users', array($this, 'ajax_cleanup_pending_users'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Paid Registration', 'woo-paid-registration'),
            __('Paid Registration', 'woo-paid-registration'),
            'manage_options',
            $this->settings_tab_id,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wpr_settings_group', 'wpr_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // General settings
        $sanitized['wpr_enabled'] = isset($input['wpr_enabled']) && $input['wpr_enabled'] ? 'yes' : 'no';
        $sanitized['wpr_require_payment'] = isset($input['wpr_require_payment']) && $input['wpr_require_payment'] ? 'yes' : 'no';
        
        // Product settings
        $sanitized['wpr_product_id'] = isset($input['wpr_product_id']) ? absint($input['wpr_product_id']) : '';
        $sanitized['wpr_membership_price'] = isset($input['wpr_membership_price']) ? floatval($input['wpr_membership_price']) : 10.00;
        
        // Free registration settings
        $sanitized['wpr_enable_free_registration'] = isset($input['wpr_enable_free_registration']) && $input['wpr_enable_free_registration'] ? 'yes' : 'no';
        $sanitized['wpr_free_roles'] = isset($input['wpr_free_roles']) && is_array($input['wpr_free_roles']) 
            ? array_map('sanitize_text_field', $input['wpr_free_roles']) 
            : array();
        
        // Redirect settings
        $sanitized['wpr_redirect_after_payment'] = isset($input['wpr_redirect_after_payment']) 
            ? esc_url_raw($input['wpr_redirect_after_payment']) 
            : '';
        
        // Access restriction settings
        $sanitized['wpr_restrict_site_access'] = isset($input['wpr_restrict_site_access']) && $input['wpr_restrict_site_access'] ? 'yes' : 'no';
        
        // Cleanup settings
        $sanitized['wpr_cleanup_hours'] = isset($input['wpr_cleanup_hours']) ? absint($input['wpr_cleanup_hours']) : 24;
        
        return $sanitized;
    }
    
    /**
     * Add WooCommerce settings tab
     * Note: Removed as we're using a dedicated submenu page under WooCommerce
     */
    public function add_woocommerce_settings_tab($settings) {
        // Keeping this method empty to avoid fatal errors
        // The plugin uses a dedicated submenu page instead
        return $settings;
    }
    
    /**
     * Render the main settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = get_option('wpr_settings', array());
        ?>
        <div class="wrap wpr-admin-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpr-settings-container">
                <!-- Header Card -->
                <div class="wpr-card wpr-card-header">
                    <h2><?php _e('WooCommerce Paid Registration', 'woo-paid-registration'); ?></h2>
                    <p class="description"><?php _e('Configure your paid membership registration system', 'woo-paid-registration'); ?></p>
                </div>
                
                <form method="post" action="options.php" class="wpr-settings-form">
                    <?php settings_fields('wpr_settings_group'); ?>
                    
                    <div class="wpr-settings-grid">
                        <!-- General Settings Card -->
                        <div class="wpr-card">
                            <h3><?php _e('General Settings', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_enabled"><?php _e('Enable Paid Registration', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <label class="wpr-toggle-label">
                                            <input type="checkbox" id="wpr_enabled" name="wpr_settings[wpr_enabled]" value="yes" <?php checked(isset($settings['wpr_enabled']) && $settings['wpr_enabled'] === 'yes'); ?> />
                                            <span class="wpr-toggle-slider"></span>
                                        </label>
                                        <p class="description"><?php _e('Enable or disable the paid registration system', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_require_payment"><?php _e('Require Payment', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <label class="wpr-toggle-label">
                                            <input type="checkbox" id="wpr_require_payment" name="wpr_settings[wpr_require_payment]" value="yes" <?php checked(!isset($settings['wpr_require_payment']) || $settings['wpr_require_payment'] === 'yes'); ?> />
                                            <span class="wpr-toggle-slider"></span>
                                        </label>
                                        <p class="description"><?php _e('Require payment to complete registration', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Membership Product Card -->
                        <div class="wpr-card">
                            <h3><?php _e('Membership Product', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Product Status', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        $product_id = isset($settings['wpr_product_id']) ? absint($settings['wpr_product_id']) : false;
                                        $product = $product_id ? wc_get_product($product_id) : false;
                                        
                                        if ($product) {
                                            echo '<p><strong>' . __('Product exists:', 'woo-paid-registration') . '</strong> #' . $product_id . ' - ' . $product->get_name() . '</p>';
                                            echo '<p><strong>' . __('Price:', 'woo-paid-registration') . '</strong> ' . wc_price($product->get_price()) . '</p>';
                                            echo '<a href="' . esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')) . '" class="button">' . __('Edit Product', 'woo-paid-registration') . '</a>';
                                        } else {
                                            echo '<p class="description">' . __('No membership product found.', 'woo-paid-registration') . '</p>';
                                            echo '<button type="button" class="button button-primary" id="wpr-create-product">' . __('Create Membership Product', 'woo-paid-registration') . '</button>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_membership_price"><?php _e('Default Price', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="wpr_membership_price" name="wpr_settings[wpr_membership_price]" 
                                               value="<?php echo esc_attr(isset($settings['wpr_membership_price']) ? $settings['wpr_membership_price'] : '10.00'); ?>" 
                                               step="0.01" min="0" class="small-text" />
                                        <p class="description"><?php _e('Default price for the membership product (can be edited in product)', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Free Registration Card -->
                        <div class="wpr-card">
                            <h3><?php _e('Free Registration Options', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_enable_free_registration"><?php _e('Allow Free Registration', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <label class="wpr-toggle-label">
                                            <input type="checkbox" id="wpr_enable_free_registration" name="wpr_settings[wpr_enable_free_registration]" value="yes" <?php checked(isset($settings['wpr_enable_free_registration']) && $settings['wpr_enable_free_registration'] === 'yes'); ?> />
                                            <span class="wpr-toggle-slider"></span>
                                        </label>
                                        <p class="description"><?php _e('Allow free registration for specific user roles', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Free Roles', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        $editable_roles = get_editable_roles();
                                        $free_roles = isset($settings['wpr_free_roles']) ? $settings['wpr_free_roles'] : array();
                                        ?>
                                        <div class="wpr-checkbox-group">
                                            <?php foreach ($editable_roles as $role_key => $role_data): ?>
                                                <label class="wpr-checkbox-label">
                                                    <input type="checkbox" name="wpr_settings[wpr_free_roles][]" value="<?php echo esc_attr($role_key); ?>" <?php echo in_array($role_key, $free_roles) ? 'checked' : ''; ?> />
                                                    <?php echo esc_html($role_data['name']); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="description"><?php _e('Select roles that can register without payment', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Redirect Settings Card -->
                        <div class="wpr-card">
                            <h3><?php _e('Redirect Settings', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_redirect_after_payment"><?php _e('Redirect After Payment', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" id="wpr_redirect_after_payment" name="wpr_settings[wpr_redirect_after_payment]" 
                                               value="<?php echo esc_attr(isset($settings['wpr_redirect_after_payment']) ? $settings['wpr_redirect_after_payment'] : ''); ?>" 
                                               class="regular-text" placeholder="<?php echo esc_attr(site_url('/my-account/')); ?>" />
                                        <p class="description"><?php _e('URL to redirect users after successful payment (leave empty for default My Account page)', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Access Restriction Card -->
                        <div class="wpr-card">
                            <h3><?php _e('Access Restriction', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_restrict_site_access"><?php _e('Restrict Site Access', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <label class="wpr-toggle-label">
                                            <input type="checkbox" id="wpr_restrict_site_access" name="wpr_settings[wpr_restrict_site_access]" value="yes" <?php checked(isset($settings['wpr_restrict_site_access']) && $settings['wpr_restrict_site_access'] === 'yes'); ?> />
                                            <span class="wpr-toggle-slider"></span>
                                        </label>
                                        <p class="description"><?php _e('Restrict site access for users without active membership', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Cleanup Settings Card -->
                        <div class="wpr-card">
                            <h3><?php _e('Cleanup Settings', 'woo-paid-registration'); ?></h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wpr_cleanup_hours"><?php _e('Pending Registration Expiry', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="wpr_cleanup_hours" name="wpr_settings[wpr_cleanup_hours]" 
                                               value="<?php echo esc_attr(isset($settings['wpr_cleanup_hours']) ? $settings['wpr_cleanup_hours'] : '24'); ?>" 
                                               min="1" max="168" class="small-text" />
                                        <p class="description"><?php _e('Hours after which pending registrations are deleted (1-168)', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Manual Cleanup', 'woo-paid-registration'); ?></label>
                                    </th>
                                    <td>
                                        <button type="button" class="button" id="wpr-cleanup-pending"><?php _e('Clean Pending Registrations Now', 'woo-paid-registration'); ?></button>
                                        <p class="description"><?php _e('Remove all pending registrations older than the specified hours', 'woo-paid-registration'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Save Settings', 'woo-paid-registration'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
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
                'creating' => __('Creating...', 'woo-paid-registration'),
                'created' => __('Product created successfully!', 'woo-paid-registration'),
                'error' => __('Error:', 'woo-paid-registration'),
                'cleaning' => __('Cleaning...', 'woo-paid-registration'),
                'cleaned' => __('Cleanup completed!', 'woo-paid-registration'),
            ),
        ));
    }
    
    /**
     * AJAX handler to create membership product
     */
    public function ajax_create_membership_product() {
        check_ajax_referer('wpr_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-paid-registration')));
        }
        
        $product_manager = WPR_Product_Manager::get_instance();
        $product_id = $product_manager->create_membership_product();
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            wp_send_json_success(array(
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_price' => wc_price($product->get_price()),
                'edit_url' => admin_url('post.php?post=' . $product_id . '&action=edit'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create product', 'woo-paid-registration')));
        }
    }
    
    /**
     * AJAX handler to cleanup pending users
     */
    public function ajax_cleanup_pending_users() {
        check_ajax_referer('wpr_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-paid-registration')));
        }
        
        $registration_handler = WPR_Registration_Handler::get_instance();
        $registration_handler->cleanup_pending_registrations();
        
        wp_send_json_success(array('message' => __('Cleanup completed successfully', 'woo-paid-registration')));
    }
}
