<?php
/**
 * Product Manager - Handles membership product selection
 * No WooCommerce query filters to avoid compatibility warnings
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_Product_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // No filters that could cause WC compatibility issues
        add_action('wpr_cleanup_pending_registrations', array($this, 'cleanup_pending_registrations'));
    }
    
    /**
     * Get selected membership product ID from settings
     */
    public function get_membership_product_id() {
        return absint(get_option('wpr_membership_product_id', 0));
    }
    
    /**
     * Get membership product object
     */
    public function get_membership_product() {
        $product_id = $this->get_membership_product_id();
        
        if (!$product_id) {
            return null;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
            return null;
        }
        
        return $product;
    }
    
    /**
     * Check if a product is the membership product
     */
    public function is_membership_product($product_id) {
        return $product_id == $this->get_membership_product_id();
    }
    
    /**
     * Get all purchasable products for dropdown
     */
    public function get_available_products() {
        $products = array();
        
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $product->is_purchasable()) {
                    $products[get_the_ID()] = sprintf(
                        '#%d - %s - %s',
                        get_the_ID(),
                        get_the_title(),
                        $product->get_price_html()
                    );
                }
            }
            wp_reset_postdata();
        }
        
        return $products;
    }
    
    /**
     * Cleanup pending registrations older than specified hours
     */
    public function cleanup_pending_registrations() {
        $hours = absint(get_option('wpr_cleanup_hours', 24));
        $cutoff_time = strtotime("-{$hours} hours");
        
        $users = get_users(array(
            'meta_key'   => 'wpr_registration_status',
            'meta_value' => 'pending_payment',
        ));
        
        foreach ($users as $user) {
            $registered_time = get_user_meta($user->ID, 'wpr_registration_timestamp', true);
            
            if ($registered_time && intval($registered_time) < $cutoff_time) {
                // Delete pending user
                wp_delete_user($user->ID);
                
                // Log cleanup
                error_log(sprintf(
                    '[WPR] Cleaned up pending registration: User ID %d (%s)',
                    $user->ID,
                    $user->user_email
                ));
            }
        }
    }
    
    /**
     * Check if membership product is configured
     */
    public function is_configured() {
        $product = $this->get_membership_product();
        return $product !== null;
    }
}
