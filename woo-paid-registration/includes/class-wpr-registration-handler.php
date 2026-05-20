<?php
/**
 * Registration Handler - Intercepts registration and forces payment
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
        // Intercept registration
        add_action('user_register', array($this, 'handle_new_registration'), 1, 1);
        
        // Block login for pending users
        add_filter('wp_authenticate_user', array($this, 'block_pending_login'), 10, 2);
        
        // Redirect after registration to checkout
        add_filter('woocommerce_registration_redirect', array($this, 'redirect_to_checkout'), 999);
        
        // Add membership product to cart automatically after registration
        add_action('template_redirect', array($this, 'add_membership_to_cart'), 99);
        
        // Prevent checkout without membership product for new pending users
        add_action('woocommerce_before_checkout_form', array($this, 'validate_checkout_for_pending_users'));
    }
    
    /**
     * Handle new user registration
     */
    public function handle_new_registration($user_id) {
        // Only process if membership product is configured
        $product_manager = WPR_Product_Manager::get_instance();
        if (!$product_manager->is_configured()) {
            return;
        }
        
        // Set user as pending payment
        update_user_meta($user_id, 'wpr_registration_status', 'pending_payment');
        update_user_meta($user_id, 'wpr_registration_timestamp', time());
        
        // Store user ID in session for cart redirect
        if (WC()->session) {
            WC()->session->set('wpr_pending_user_id', $user_id);
        }
    }
    
    /**
     * Block login for users with pending payment status
     */
    public function block_pending_login($user, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        $status = get_user_meta($user->ID, 'wpr_registration_status', true);
        
        if ($status === 'pending_payment') {
            $timestamp = get_user_meta($user->ID, 'wpr_registration_timestamp', true);
            $hours = absint(get_option('wpr_cleanup_hours', 24));
            $cutoff = strtotime("-{$hours} hours");
            
            // If expired, delete user
            if ($timestamp && intval($timestamp) < $cutoff) {
                wp_delete_user($user->ID);
                return new WP_Error(
                    'registration_expired',
                    __('Your registration has expired. Please register again.', 'woo-paid-registration')
                );
            }
            
            // Block login with message
            return new WP_Error(
                'payment_pending',
                __('Please complete your payment to activate your account. Check your email for checkout instructions.', 'woo-paid-registration')
            );
        }
        
        if ($status === 'payment_failed') {
            return new WP_Error(
                'payment_failed',
                __('Your payment failed. Please contact support or try registering again.', 'woo-paid-registration')
            );
        }
        
        if ($status === 'cancelled') {
            return new WP_Error(
                'registration_cancelled',
                __('This registration was cancelled. Please register again.', 'woo-paid-registration')
            );
        }
        
        return $user;
    }
    
    /**
     * Redirect newly registered users to checkout
     */
    public function redirect_to_checkout($redirect) {
        if (WC()->session && WC()->session->get('wpr_pending_user_id')) {
            return wc_get_checkout_url();
        }
        return $redirect;
    }
    
    /**
     * Automatically add membership product to cart for pending users
     */
    public function add_membership_to_cart() {
        if (!is_checkout() && !is_cart()) {
            return;
        }
        
        $session = WC()->session;
        if (!$session) {
            return;
        }
        
        $pending_user_id = $session->get('wpr_pending_user_id');
        
        if (!$pending_user_id || !is_user_logged_in()) {
            return;
        }
        
        // Verify this user is actually pending
        $status = get_user_meta($pending_user_id, 'wpr_registration_status', true);
        if ($status !== 'pending_payment') {
            $session->__unset('wpr_pending_user_id');
            return;
        }
        
        // Get membership product
        $product_manager = WPR_Product_Manager::get_instance();
        $product = $product_manager->get_membership_product();
        
        if (!$product) {
            return;
        }
        
        // Check if product already in cart
        $found = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $product->get_id()) {
                $found = true;
                break;
            }
        }
        
        // Add if not found
        if (!$found) {
            WC()->cart->add_to_cart($product->get_id(), 1);
        }
    }
    
    /**
     * Validate that pending users are purchasing the membership product
     */
    public function validate_checkout_for_pending_users() {
        $session = WC()->session;
        if (!$session) {
            return;
        }
        
        $pending_user_id = $session->get('wpr_pending_user_id');
        
        if (!$pending_user_id) {
            return;
        }
        
        $status = get_user_meta($pending_user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            return;
        }
        
        // Check cart contains membership product
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_product_id = $product_manager->get_membership_product_id();
        
        $has_membership = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $membership_product_id) {
                $has_membership = true;
                break;
            }
        }
        
        if (!$has_membership) {
            wc_add_notice(
                __('You must purchase the membership product to complete your registration.', 'woo-paid-registration'),
                'error'
            );
        }
    }
    
    /**
     * Clear pending user session after successful registration flow
     */
    public function clear_pending_session($order_id) {
        if (WC()->session) {
            WC()->session->__unset('wpr_pending_user_id');
        }
    }
}
