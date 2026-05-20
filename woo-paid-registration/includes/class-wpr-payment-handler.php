<?php
/**
 * Payment Handler Class
 * 
 * Handles payment completion and order status changes
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Payment_Handler {
    
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
        // Listen for order status changes
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'handle_order_completed'), 10, 1);
        
        // Handle failed/cancelled orders
        add_action('woocommerce_order_status_failed', array($this, 'handle_order_failed'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancelled'), 10, 1);
        
        // Modify checkout for membership registration
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'), 999);
        add_action('woocommerce_before_checkout_form', array($this, 'display_membership_message'));
        
        // Prevent removal of membership product from cart during registration
        add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'prevent_membership_removal'), 10, 1);
        add_filter('woocommerce_remove_cart_item', array($this, 'prevent_membership_product_removal'), 10, 2);
        
        // Redirect after successful payment
        add_filter('woocommerce_get_return_url', array($this, 'custom_return_url'), 999, 2);
    }
    
    /**
     * Handle completed order (payment successful)
     */
    public function handle_order_completed($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if this is a membership order
        $is_membership_order = $order->get_meta('_wpr_membership_order');
        
        if (!$is_membership_order || $is_membership_order !== 'yes') {
            return;
        }
        
        // Get the user ID
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            $pending_user_id = $order->get_meta('_wpr_pending_user_id');
            if ($pending_user_id) {
                $user_id = absint($pending_user_id);
            }
        }
        
        if (!$user_id) {
            return;
        }
        
        // Complete the registration
        $registration_handler = WPR_Registration_Handler::get_instance();
        $registration_handler->complete_registration($user_id, $order_id);
        
        do_action('wpr_membership_payment_completed', $user_id, $order_id, $order);
    }
    
    /**
     * Handle failed order
     */
    public function handle_order_failed($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if this is a membership order
        $is_membership_order = $order->get_meta('_wpr_membership_order');
        
        if (!$is_membership_order || $is_membership_order !== 'yes') {
            return;
        }
        
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            $pending_user_id = $order->get_meta('_wpr_pending_user_id');
            if ($pending_user_id) {
                $user_id = absint($pending_user_id);
            }
        }
        
        if (!$user_id) {
            return;
        }
        
        // Mark user registration as failed
        $user_meta = WPR_User_Meta::get_instance();
        $user_meta->set_payment_failed($user_id);
        
        // Store failure reason
        update_user_meta($user_id, '_wpr_payment_failure_reason', __('Payment failed or was declined', 'woo-paid-registration'));
        
        do_action('wpr_membership_payment_failed', $user_id, $order_id, $order);
    }
    
    /**
     * Handle cancelled order
     */
    public function handle_order_cancelled($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if this is a membership order
        $is_membership_order = $order->get_meta('_wpr_membership_order');
        
        if (!$is_membership_order || $is_membership_order !== 'yes') {
            return;
        }
        
        $user_id = $order->get_customer_id();
        
        if (!$user_id) {
            $pending_user_id = $order->get_meta('_wpr_pending_user_id');
            if ($pending_user_id) {
                $user_id = absint($pending_user_id);
            }
        }
        
        if (!$user_id) {
            return;
        }
        
        // Mark user registration as cancelled
        $user_meta = WPR_User_Meta::get_instance();
        $user_meta->set_cancelled($user_id);
        
        do_action('wpr_membership_payment_cancelled', $user_id, $order_id, $order);
    }
    
    /**
     * Modify checkout fields for membership registration
     */
    public function modify_checkout_fields($fields) {
        $is_membership_checkout = isset($_GET['wpr_membership_checkout']) && $_GET['wpr_membership_checkout'] == '1';
        
        if (!$is_membership_checkout) {
            return $fields;
        }
        
        // Add a notice at the top of checkout
        add_filter('woocommerce_checkout_before_customer_details', array($this, 'add_membership_checkout_notice'));
        
        // Optionally simplify checkout fields for membership-only purchase
        $cart = WC()->cart;
        $has_only_membership = true;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (!WPR_Product_Manager::get_instance()->is_membership_product($product_id)) {
                $has_only_membership = false;
                break;
            }
        }
        
        if ($has_only_membership) {
            // Simplify checkout - only keep essential fields
            // This can be customized based on requirements
        }
        
        return $fields;
    }
    
    /**
     * Add notice at membership checkout
     */
    public function add_membership_checkout_notice() {
        echo '<div class="woocommerce-info wpr-membership-notice">';
        echo __('You are completing your registration by paying the membership fee.', 'woo-paid-registration');
        echo '</div>';
    }
    
    /**
     * Display membership message before checkout form
     */
    public function display_membership_message() {
        $is_membership_checkout = isset($_GET['wpr_membership_checkout']) && $_GET['wpr_membership_checkout'] == '1';
        
        if (!$is_membership_checkout) {
            return;
        }
        
        echo '<div class="wpr-checkout-message" style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #2271b1;">';
        echo '<strong>' . __('Membership Registration', 'woo-paid-registration') . '</strong><br>';
        echo __('Complete your payment to activate your account. Once payment is confirmed, you will have full access to the site.', 'woo-paid-registration');
        echo '</div>';
    }
    
    /**
     * Prevent removal of membership product from cart during registration
     */
    public function prevent_membership_product_removal($remove, $cart_item_key) {
        $is_membership_checkout = isset($_GET['wpr_membership_checkout']) && $_GET['wpr_membership_checkout'] == '1';
        
        if (!$is_membership_checkout) {
            return $remove;
        }
        
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_item_key);
        
        if (!$cart_item) {
            return $remove;
        }
        
        $product_id = $cart_item['product_id'];
        $product_manager = WPR_Product_Manager::get_instance();
        
        if ($product_manager->is_membership_product($product_id)) {
            // Prevent removal during membership registration checkout
            wc_add_notice(__('The membership fee cannot be removed during registration.', 'woo-paid-registration'), 'error');
            return false;
        }
        
        return $remove;
    }
    
    /**
     * Handle cart updated action
     */
    public function prevent_membership_removal($cart_updated) {
        $is_membership_checkout = isset($_GET['wpr_membership_checkout']) && $_GET['wpr_membership_checkout'] == '1';
        
        if (!$is_membership_checkout) {
            return $cart_updated;
        }
        
        // Check if someone tried to remove the membership product
        if (isset($_POST['remove_item'])) {
            $cart_item_key = sanitize_text_field($_POST['remove_item']);
            $cart = WC()->cart;
            $cart_item = $cart->get_cart_item($cart_item_key);
            
            if ($cart_item) {
                $product_id = $cart_item['product_id'];
                $product_manager = WPR_Product_Manager::get_instance();
                
                if ($product_manager->is_membership_product($product_id)) {
                    wc_add_notice(__('The membership fee is required for registration.', 'woo-paid-registration'), 'error');
                    return false;
                }
            }
        }
        
        return $cart_updated;
    }
    
    /**
     * Custom return URL after membership payment
     */
    public function custom_return_url($return_url, $order) {
        $is_membership_order = $order->get_meta('_wpr_membership_order');
        
        if (!$is_membership_order || $is_membership_order !== 'yes') {
            return $return_url;
        }
        
        // Check settings for custom redirect
        $settings = get_option('wpr_settings', array());
        $custom_redirect = isset($settings['wpr_redirect_after_payment']) ? $settings['wpr_redirect_after_payment'] : '';
        
        if (!empty($custom_redirect)) {
            $return_url = $custom_redirect;
        } else {
            // Default to my account page with welcome message
            $return_url = wc_get_account_endpoint_url('dashboard');
        }
        
        return $return_url;
    }
    
    /**
     * Check if current checkout is for membership registration
     */
    public function is_membership_checkout() {
        if (!WC()->cart) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (WPR_Product_Manager::get_instance()->is_membership_product($product_id)) {
                return true;
            }
        }
        
        return false;
    }
}
