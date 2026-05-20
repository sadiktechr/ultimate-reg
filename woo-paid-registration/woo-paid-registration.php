<?php
/**
 * Plugin Name: WooCommerce Paid Registration
 * Plugin URI: https://example.com/woo-paid-registration
 * Description: Force users to pay for membership during registration. Select any existing WooCommerce product as the membership fee. No registration completes without payment.
 * Version: 3.0.0
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
    exit;
}

// Define plugin constants
define('WPR_VERSION', '3.0.0');
define('WPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class WPR_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 20);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('woo-paid-registration', false, dirname(WPR_PLUGIN_BASENAME) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        WPR_Product_Manager::get_instance();
        WPR_Registration_Handler::get_instance();
        WPR_Payment_Handler::get_instance();
        WPR_User_Meta::get_instance();
        
        if (is_admin()) {
            WPR_Admin_Settings::get_instance();
            WPR_Admin_Notices::get_instance();
        }
    }
    
    private function includes() {
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-product-manager.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-registration-handler.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-payment-handler.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-user-meta.php';
        require_once WPR_PLUGIN_DIR . 'admin/class-wpr-admin-settings.php';
        require_once WPR_PLUGIN_DIR . 'admin/class-wpr-admin-notices.php';
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('WooCommerce Paid Registration requires WooCommerce to be installed and active.', 'woo-paid-registration'); ?></p>
        </div>
        <?php
    }
    
    public function activate() {
        // Check WooCommerce on activation
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(WPR_PLUGIN_BASENAME);
            wp_die(__('WooCommerce Paid Registration requires WooCommerce to be installed and active.', 'woo-paid-registration'));
        }
        
        // Set default options
        add_option('wpr_cleanup_hours', '24');
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('wpr_cleanup_pending_registrations')) {
            wp_schedule_event(time(), 'hourly', 'wpr_cleanup_pending_registrations');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('wpr_cleanup_pending_registrations');
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function wpr_init() {
    return WPR_Plugin::get_instance();
}
wpr_init();
