<?php
/**
 * Product Manager Class
 * 
 * Handles the creation and management of the hidden membership product
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
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Remove incompatible filters that cause WC compatibility warnings
        // Only use safe, non-intrusive hooks
        add_action('wp', array($this, 'exclude_membership_product_from_catalog'));
        add_filter('woocommerce_account_menu_items', array($this, 'remove_membership_from_orders'), 999);
    }
    
    /**
     * Get the membership product ID from settings
     */
    public function get_membership_product_id() {
        $settings = get_option('wpr_settings', array());
        return isset($settings['wpr_product_id']) && !empty($settings['wpr_product_id']) 
            ? absint($settings['wpr_product_id']) 
            : false;
    }
    
    /**
     * Create the hidden membership product
     */
    public function create_membership_product() {
        $existing_product_id = $this->get_membership_product_id();
        
        if ($existing_product_id && get_post($existing_product_id)) {
            return $existing_product_id;
        }
        
        // Create the product
        $product = new WC_Product_Simple();
        $product->set_name(__('Membership Registration Fee', 'woo-paid-registration'));
        $product->set_description(__('This is a required membership fee for completing your registration. This product is not visible in the store.', 'woo-paid-registration'));
        $product->set_regular_price(apply_filters('wpr_membership_price', '10.00'));
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_catalog_visibility('hidden');
        $product->set_purchase_note(__('This is a mandatory membership fee for registration.', 'woo-paid-registration'));
        $product->set_status('publish');
        
        // Add meta to mark as membership product
        $product->update_meta_data('_wpr_membership_product', 'yes');
        $product->update_meta_data('_visibility', 'hidden');
        
        $product_id = $product->save();
        
        // Update settings with the product ID
        $settings = get_option('wpr_settings', array());
        $settings['wpr_product_id'] = $product_id;
        update_option('wpr_settings', $settings);
        
        do_action('wpr_membership_product_created', $product_id);
        
        return $product_id;
    }
    
    /**
     * Hide membership product from shop and catalog
     */
    public function exclude_membership_product_from_catalog() {
        if (!is_admin() && (is_shop() || is_product_category() || is_search())) {
            $membership_id = $this->get_membership_product_id();
            if ($membership_id) {
                // Remove from global query
                global $wp_query;
                if (isset($wp_query->posts)) {
                    $wp_query->posts = array_filter($wp_query->posts, function($post) use ($membership_id) {
                        return $post->ID != $membership_id;
                    });
                    if (isset($wp_query->post_count)) {
                        $wp_query->post_count = count($wp_query->posts);
                    }
                }
            }
        }
    }
    
    /**
     * Remove membership product from customer orders list
     */
    public function remove_membership_from_orders($items) {
        // Keep the orders menu item but we'll filter the actual orders elsewhere
        return $items;
    }
    
    /**
     * Check if a product is the membership product
     */
    public function is_membership_product($product_id) {
        $membership_id = $this->get_membership_product_id();
        return $product_id == $membership_id;
    }
    
    /**
     * Get membership product price
     */
    public function get_membership_price() {
        $membership_id = $this->get_membership_product_id();
        if (!$membership_id) {
            return 0;
        }
        
        $product = wc_get_product($membership_id);
        if (!$product) {
            return 0;
        }
        
        return $product->get_price();
    }
    
    /**
     * Update membership product price
     */
    public function update_membership_price($price) {
        $membership_id = $this->get_membership_product_id();
        if (!$membership_id) {
            return false;
        }
        
        $product = wc_get_product($membership_id);
        if (!$product) {
            return false;
        }
        
        $product->set_regular_price($price);
        $product->save();
        
        do_action('wpr_membership_price_updated', $price, $membership_id);
        
        return true;
    }
    
    /**
     * Ensure membership product exists (admin action)
     */
    public function ensure_product_exists() {
        $product_id = $this->create_membership_product();
        return $product_id ? true : false;
    }
}
