<?php
/**
 * Plugin Name: WooCommerce Paid Registration
 * Plugin URI: https://example.com/woo-paid-registration
 * Description: Make WooCommerce registration paid by requiring payment for a hidden membership product before completing registration.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-paid-registration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active before loading
if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('WooCommerce Paid Registration requires WooCommerce', 'woo-paid-registration'); ?></strong>
                <?php _e('Please install and activate WooCommerce to use this plugin.', 'woo-paid-registration'); ?>
            </p>
        </div>
        <?php
    });
    return;
}

// Define plugin constants
define('WPR_VERSION', '1.0.0');
define('WPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class Woo_Paid_Registration {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'), 20); // Priority 20 to ensure WooCommerce is loaded first
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes - WooCommerce already checked at top of file
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-product-manager.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-registration-handler.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-payment-handler.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-user-meta.php';
        
        // Admin classes
        if (is_admin()) {
            require_once WPR_PLUGIN_DIR . 'admin/class-wpr-admin-settings.php';
            require_once WPR_PLUGIN_DIR . 'admin/class-wpr-admin-notices.php';
        }
        
        return true;
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('woo-paid-registration', false, dirname(WPR_PLUGIN_BASENAME) . '/languages');
        
        // Check if WooCommerce is properly loaded
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Initialize components
        WPR_Product_Manager::get_instance();
        WPR_Registration_Handler::get_instance();
        WPR_Payment_Handler::get_instance();
        WPR_User_Meta::get_instance();
        
        if (is_admin()) {
            WPR_Admin_Settings::get_instance();
            WPR_Admin_Notices::get_instance();
        }
        
        do_action('wpr_initialized');
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('This plugin requires WooCommerce to be installed and active.', 'woo-paid-registration'),
                __('Plugin Activation Error', 'woo-paid-registration'),
                array('back_link' => true)
            );
        }
        
        // Create default settings
        $default_settings = array(
            'wpr_enabled' => 'yes',
            'wpr_product_id' => '',
            'wpr_require_payment' => 'yes',
            'wpr_redirect_after_payment' => '',
            'wpr_enable_free_registration' => 'no',
            'wpr_free_roles' => array(),
            'wpr_cleanup_hours' => 24,
        );
        
        if (!get_option('wpr_settings')) {
            add_option('wpr_settings', $default_settings);
        }
        
        // Create the hidden membership product if it doesn't exist
        WPR_Product_Manager::get_instance()->create_membership_product();
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('wpr_cleanup_pending_registrations')) {
            wp_schedule_event(time(), 'hourly', 'wpr_cleanup_pending_registrations');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wpr_cleanup_pending_registrations');
        
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function wpr_init() {
    return Woo_Paid_Registration::get_instance();
}

// Start the plugin
wpr_init();
