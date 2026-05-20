<?php
/**
 * Payment Handler - Processes payment completion and activates accounts
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
        // Listen for successful payments
        add_action('woocommerce_payment_complete', array($this, 'handle_successful_payment'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'handle_order_completed'), 10, 1);
        
        // Handle failed/cancelled payments
        add_action('woocommerce_order_status_failed', array($this, 'handle_failed_payment'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_cancelled_payment'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this, 'handle_on_hold_payment'), 10, 1);
        
        // Prevent removal of membership product from cart for pending users
        add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'prevent_membership_removal'), 10, 1);
        
        // Add notice on checkout for pending users
        add_action('woocommerce_before_checkout_form', array($this, 'add_pending_user_notice'));
    }
    
    /**
     * Handle successful payment
     */
    public function handle_successful_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $status = get_user_meta($user_id, 'wpr_registration_status', true);
        
        // Only process if user is pending
        if ($status !== 'pending_payment') {
            return;
        }
        
        // Check if order contains membership product
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_product_id = $product_manager->get_membership_product_id();
        
        $has_membership = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $membership_product_id) {
                $has_membership = true;
                break;
            }
        }
        
        if (!$has_membership) {
            return;
        }
        
        // Activate the user account
        $this->activate_user_account($user_id, $order_id);
    }
    
    /**
     * Handle order completed/processing status
     */
    public function handle_order_completed($order_id) {
        $this->handle_successful_payment($order_id);
    }
    
    /**
     * Activate user account after successful payment
     */
    private function activate_user_account($user_id, $order_id) {
        // Update user status
        update_user_meta($user_id, 'wpr_registration_status', 'active');
        update_user_meta($user_id, 'wpr_activation_timestamp', time());
        update_user_meta($user_id, 'wpr_activation_order_id', $order_id);
        
        // Clear pending session
        if (WC()->session) {
            WC()->session->__unset('wpr_pending_user_id');
        }
        
        // Send welcome email
        $this->send_welcome_email($user_id, $order_id);
        
        // Trigger action for third-party integrations
        do_action('wpr_registration_completed', $user_id, $order_id);
        
        // Log activation
        error_log(sprintf(
            '[WPR] User account activated: User ID %d (%s), Order ID %d',
            $user_id,
            wp_get_current_user()->user_email,
            $order_id
        ));
    }
    
    /**
     * Send welcome email to newly activated user
     */
    private function send_welcome_email($user_id, $order_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('Welcome! Your account has been activated - %s', 'woo-paid-registration'), get_bloginfo('name'));
        
        $message = sprintf(
            __("Hi %s,\n\nGreat news! Your payment has been received and your account is now active.\n\nYou can now log in and start using all the features available to members.\n\nLogin URL: %s\n\nThank you for joining us!", 'woo-paid-registration'),
            $user->display_name,
            wp_login_url()
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Handle failed payment
     */
    public function handle_failed_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $status = get_user_meta($user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            return;
        }
        
        // Check if order contains membership product
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_product_id = $product_manager->get_membership_product_id();
        
        $has_membership = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $membership_product_id) {
                $has_membership = true;
                break;
            }
        }
        
        if (!$has_membership) {
            return;
        }
        
        // Update user status
        update_user_meta($user_id, 'wpr_registration_status', 'payment_failed');
        update_user_meta($user_id, 'wpr_failed_order_id', $order_id);
        
        // Notify user
        $this->send_payment_failed_email($user_id, $order_id);
        
        do_action('wpr_payment_failed', $user_id, $order_id);
    }
    
    /**
     * Handle cancelled payment
     */
    public function handle_cancelled_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $status = get_user_meta($user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            return;
        }
        
        // Update user status
        update_user_meta($user_id, 'wpr_registration_status', 'cancelled');
        
        do_action('wpr_registration_cancelled', $user_id, $order_id);
    }
    
    /**
     * Handle on-hold payment
     */
    public function handle_on_hold_payment($order_id) {
        // Keep user as pending until payment clears
        // No action needed, user remains in pending_payment status
    }
    
    /**
     * Send payment failed notification
     */
    private function send_payment_failed_email($user_id, $order_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('Payment Failed - Action Required - %s', 'woo-paid-registration'), get_bloginfo('name'));
        
        $message = sprintf(
            __("Hi %s,\n\nYour payment for membership has failed.\n\nTo complete your registration, please try again or contact support if you need assistance.\n\nYour account is currently inactive. You can try registering again or contact us to resolve this issue.\n\nThank you!", 'woo-paid-registration'),
            $user->display_name
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Prevent removal of membership product from cart for pending users
     */
    public function prevent_membership_removal($updated) {
        if (!WC()->session) {
            return $updated;
        }
        
        $pending_user_id = WC()->session->get('wpr_pending_user_id');
        
        if (!$pending_user_id) {
            return $updated;
        }
        
        $status = get_user_meta($pending_user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            return $updated;
        }
        
        $product_manager = WPR_Product_Manager::get_instance();
        $membership_product_id = $product_manager->get_membership_product_id();
        
        // Check if membership product was removed
        $has_membership = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $membership_product_id) {
                $has_membership = true;
                break;
            }
        }
        
        if (!$has_membership && !empty($_POST['cart']) && is_array($_POST['cart'])) {
            // Re-add membership product
            WC()->cart->add_to_cart($membership_product_id, 1);
            
            wc_add_notice(
                __('The membership product is required to complete your registration and cannot be removed.', 'woo-paid-registration'),
                'notice'
            );
        }
        
        return true;
    }
    
    /**
     * Add notice for pending users on checkout
     */
    public function add_pending_user_notice() {
        if (!WC()->session) {
            return;
        }
        
        $pending_user_id = WC()->session->get('wpr_pending_user_id');
        
        if (!$pending_user_id) {
            return;
        }
        
        $status = get_user_meta($pending_user_id, 'wpr_registration_status', true);
        
        if ($status === 'pending_payment') {
            wc_print_notice(
                __('Please complete your payment to activate your account. Your registration is pending until payment is received.', 'woo-paid-registration'),
                'notice'
            );
        }
    }
}
