<?php
/**
 * Registration Handler Class
 * 
 * Intercepts the registration process and redirects to checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Registration_Handler {
    
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
        // Intercept registration
        add_action('woocommerce_register_post', array($this, 'validate_registration'), 10, 3);
        add_action('woocommerce_created_customer', array($this, 'handle_customer_creation'), 10, 2);
        
        // Modify registration form behavior
        add_filter('woocommerce_registration_redirect', array($this, 'redirect_to_checkout'), 10, 1);
        
        // Add hidden field to track pending registration
        add_action('woocommerce_register_form_end', array($this, 'add_hidden_fields'));
        
        // Handle pending registration cleanup
        add_action('wpr_cleanup_pending_registrations', array($this, 'cleanup_pending_registrations'));
    }
    
    /**
     * Validate registration before processing
     */
    public function validate_registration($username, $email, $validation_errors) {
        if (!$this->is_paid_registration_enabled()) {
            return $validation_errors;
        }
        
        // Check if membership product exists
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_id = $product_manager->get_membership_product_id();
        
        if (!$membership_id) {
            $validation_errors->add('wpr_error', __('Membership system is not properly configured. Please contact the site administrator.', 'woo-paid-registration'));
        }
        
        return $validation_errors;
    }
    
    /**
     * Handle customer creation - create user with pending status
     */
    public function handle_customer_creation($customer_id, $new_customer_data) {
        if (!$this->is_paid_registration_enabled()) {
            return;
        }
        
        // Mark user as pending payment
        $user_meta = WPR_User_Meta::get_instance();
        $user_meta->set_pending_payment($customer_id);
        
        // Store registration data in session for checkout
        WC()->session->set('wpr_pending_user_id', $customer_id);
        WC()->session->set('wpr_registration_complete', false);
        
        // Create pending order for membership
        $this->create_membership_order($customer_id);
    }
    
    /**
     * Create a pending order for the membership product
     */
    private function create_membership_order($customer_id) {
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_id = $product_manager->get_membership_product_id();
        
        if (!$membership_id) {
            return false;
        }
        
        // Create order
        $order = wc_create_order();
        $order->add_product(wc_get_product($membership_id), 1);
        $order->set_customer_id($customer_id);
        
        // Set billing details from customer
        $customer = new WC_Customer($customer_id);
        $order->set_billing_email($customer->get_email());
        $order->set_billing_first_name($customer->get_first_name());
        $order->set_billing_last_name($customer->get_last_name());
        
        // Add meta to mark as membership registration order
        $order->update_meta_data('_wpr_membership_order', 'yes');
        $order->update_meta_data('_wpr_pending_user_id', $customer_id);
        
        // Calculate totals
        $order->calculate_totals();
        
        // Set status to pending
        $order->set_status('pending', __('Order created for membership registration', 'woo-paid-registration'));
        
        $order_id = $order->save();
        
        // Store order ID in user meta
        $user_meta = WPR_User_Meta::get_instance();
        $user_meta->set_membership_order_id($customer_id, $order_id);
        
        // Store order ID in session
        WC()->session->set('wpr_membership_order_id', $order_id);
        
        do_action('wpr_membership_order_created', $order_id, $customer_id);
        
        return $order_id;
    }
    
    /**
     * Redirect to checkout after registration
     */
    public function redirect_to_checkout($redirect) {
        if (!$this->is_paid_registration_enabled()) {
            return $redirect;
        }
        
        $pending_user_id = WC()->session->get('wpr_pending_user_id');
        
        if ($pending_user_id) {
            // Redirect to checkout page
            $checkout_url = wc_get_checkout_url();
            
            // Add query arg to indicate this is a membership registration checkout
            $checkout_url = add_query_arg('wpr_membership_checkout', '1', $checkout_url);
            
            return $checkout_url;
        }
        
        return $redirect;
    }
    
    /**
     * Add hidden fields to registration form
     */
    public function add_hidden_fields() {
        if (!$this->is_paid_registration_enabled()) {
            return;
        }
        
        echo '<input type="hidden" name="wpr_membership_registration" value="1" />';
    }
    
    /**
     * Check if paid registration is enabled
     */
    private function is_paid_registration_enabled() {
        $settings = get_option('wpr_settings', array());
        
        // Check if plugin is enabled
        if (isset($settings['wpr_enabled']) && $settings['wpr_enabled'] !== 'yes') {
            return false;
        }
        
        // Check if payment is required
        if (isset($settings['wpr_require_payment']) && $settings['wpr_require_payment'] !== 'yes') {
            return false;
        }
        
        // Check if free registration is allowed for this user role
        if ($this->is_free_registration_allowed()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if free registration is allowed
     */
    private function is_free_registration_allowed() {
        $settings = get_option('wpr_settings', array());
        
        if (isset($settings['wpr_enable_free_registration']) && $settings['wpr_enable_free_registration'] === 'yes') {
            // Check if current registration is for a free role
            if (isset($_POST['role'])) {
                $role = sanitize_text_field($_POST['role']);
                $free_roles = isset($settings['wpr_free_roles']) ? $settings['wpr_free_roles'] : array();
                
                if (in_array($role, $free_roles)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Cleanup pending registrations older than 24 hours
     */
    public function cleanup_pending_registrations() {
        global $wpdb;
        
        $expiry_hours = apply_filters('wpr_pending_registration_expiry', 24);
        $expiry_time = strtotime("-{$expiry_hours} hours");
        
        // Get users with pending payment status older than expiry time
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = '_wpr_registration_status' 
             AND meta_value = 'pending_payment'
             AND user_id IN (
                 SELECT user_id FROM {$wpdb->usermeta} 
                 WHERE meta_key = '_wpr_registration_timestamp' 
                 AND meta_value < %d
             )",
            $expiry_time
        ));
        
        foreach ($user_ids as $user_id) {
            // Delete the user
            wp_delete_user($user_id);
            
            do_action('wpr_pending_registration_cleaned', $user_id);
        }
    }
    
    /**
     * Complete registration after successful payment
     */
    public function complete_registration($user_id, $order_id) {
        $user_meta = WPR_User_Meta::get_instance();
        
        // Update user status to active
        $user_meta->set_active($user_id);
        
        // Store membership activation date
        $user_meta->set_membership_start_date($user_id, current_time('timestamp'));
        
        // Store associated order
        $user_meta->set_membership_order_id($user_id, $order_id);
        
        // Clear session data
        WC()->session->__unset('wpr_pending_user_id');
        WC()->session->__unset('wpr_membership_order_id');
        WC()->session->set('wpr_registration_complete', true);
        
        // Send welcome email
        $this->send_welcome_email($user_id, $order_id);
        
        do_action('wpr_registration_completed', $user_id, $order_id);
    }
    
    /**
     * Send welcome email to new member
     */
    private function send_welcome_email($user_id, $order_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        // Use WooCommerce's built-in customer new account email
        $mailer = WC()->mailer();
        $email = $mailer->emails['WC_Email_Customer_New_Account'];
        
        if ($email) {
            $email->trigger($user_id, '', true);
        }
        
        // Optionally send a custom membership welcome email
        do_action('wpr_membership_welcome_email', $user_id, $order_id);
    }
}
