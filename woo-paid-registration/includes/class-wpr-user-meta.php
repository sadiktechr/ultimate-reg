<?php
/**
 * User Meta Class
 * 
 * Handles user metadata for membership status
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPR_User_Meta {
    
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
        // Add custom user meta fields to admin profile
        add_action('show_user_profile', array($this, 'add_membership_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_membership_profile_fields'));
        
        // Save custom user meta fields
        add_action('personal_options_update', array($this, 'save_membership_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_membership_profile_fields'));
        
        // Filter user capabilities based on membership status
        add_filter('user_has_cap', array($this, 'filter_capabilities'), 10, 4);
    }
    
    /**
     * Set user status to pending payment
     */
    public function set_pending_payment($user_id) {
        update_user_meta($user_id, '_wpr_registration_status', 'pending_payment');
        update_user_meta($user_id, '_wpr_registration_timestamp', current_time('timestamp'));
        update_user_meta($user_id, '_wpr_membership_active', 'no');
    }
    
    /**
     * Set user status to active
     */
    public function set_active($user_id) {
        update_user_meta($user_id, '_wpr_registration_status', 'active');
        update_user_meta($user_id, '_wpr_membership_active', 'yes');
        update_user_meta($user_id, '_wpr_activated_timestamp', current_time('timestamp'));
    }
    
    /**
     * Set user status to payment failed
     */
    public function set_payment_failed($user_id) {
        update_user_meta($user_id, '_wpr_registration_status', 'payment_failed');
    }
    
    /**
     * Set user status to cancelled
     */
    public function set_cancelled($user_id) {
        update_user_meta($user_id, '_wpr_registration_status', 'cancelled');
    }
    
    /**
     * Set membership start date
     */
    public function set_membership_start_date($user_id, $timestamp) {
        update_user_meta($user_id, '_wpr_membership_start_date', $timestamp);
    }
    
    /**
     * Set membership order ID
     */
    public function set_membership_order_id($user_id, $order_id) {
        update_user_meta($user_id, '_wpr_membership_order_id', $order_id);
    }
    
    /**
     * Get user membership status
     */
    public function get_status($user_id) {
        return get_user_meta($user_id, '_wpr_registration_status', true);
    }
    
    /**
     * Check if user has active membership
     */
    public function is_active_member($user_id) {
        $status = $this->get_status($user_id);
        $is_active = get_user_meta($user_id, '_wpr_membership_active', true);
        
        return $status === 'active' && $is_active === 'yes';
    }
    
    /**
     * Get membership start date
     */
    public function get_membership_start_date($user_id) {
        return get_user_meta($user_id, '_wpr_membership_start_date', true);
    }
    
    /**
     * Get membership order ID
     */
    public function get_membership_order_id($user_id) {
        return get_user_meta($user_id, '_wpr_membership_order_id', true);
    }
    
    /**
     * Get registration timestamp
     */
    public function get_registration_timestamp($user_id) {
        return get_user_meta($user_id, '_wpr_registration_timestamp', true);
    }
    
    /**
     * Add membership info to user profile (admin)
     */
    public function add_membership_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status = $this->get_status($user->ID);
        $is_active = $this->is_active_member($user->ID);
        $start_date = $this->get_membership_start_date($user->ID);
        $order_id = $this->get_membership_order_id($user->ID);
        ?>
        <h3><?php _e('Membership Information', 'woo-paid-registration'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Membership Status', 'woo-paid-registration'); ?></label></th>
                <td>
                    <?php
                    $status_labels = array(
                        'pending_payment' => __('Pending Payment', 'woo-paid-registration'),
                        'active' => __('Active', 'woo-paid-registration'),
                        'payment_failed' => __('Payment Failed', 'woo-paid-registration'),
                        'cancelled' => __('Cancelled', 'woo-paid-registration'),
                    );
                    
                    $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
                    $status_color = $is_active ? '#46b450' : '#dc3232';
                    
                    echo '<span style="display:inline-block;padding:4px 8px;border-radius:3px;background:' . $status_color . ';color:#fff;font-weight:bold;">' . esc_html($status_label) . '</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Member Since', 'woo-paid-registration'); ?></label></th>
                <td>
                    <?php
                    if ($start_date) {
                        echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $start_date);
                    } else {
                        _e('Not activated', 'woo-paid-registration');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Membership Order', 'woo-paid-registration'); ?></label></th>
                <td>
                    <?php
                    if ($order_id) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            printf(
                                '<a href="%s">#%d</a> - %s',
                                esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')),
                                absint($order_id),
                                esc_html($order->get_status())
                            );
                        } else {
                            echo '#' . absint($order_id);
                        }
                    } else {
                        _e('No order found', 'woo-paid-registration');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="wpr_manual_activate"><?php _e('Manual Activation', 'woo-paid-registration'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpr_manual_activate" name="wpr_manual_activate" value="1" <?php checked($is_active, true); ?> />
                        <?php _e('Activate membership manually', 'woo-paid-registration'); ?>
                    </label>
                    <p class="description"><?php _e('Check this to manually activate/deactivate membership.', 'woo-paid-registration'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save membership profile fields
     */
    public function save_membership_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['wpr_manual_activate'])) {
            $activate = $_POST['wpr_manual_activate'] == '1';
            
            if ($activate) {
                $this->set_active($user_id);
                update_user_meta($user_id, '_wpr_manually_activated', 'yes');
            } else {
                $this->set_cancelled($user_id);
                update_user_meta($user_id, '_wpr_membership_active', 'no');
            }
        }
    }
    
    /**
     * Filter user capabilities based on membership status
     */
    public function filter_capabilities($allcaps, $cap, $args, $user) {
        // Don't restrict administrators
        if (in_array('administrator', $user->roles)) {
            return $allcaps;
        }
        
        // Check if we need to restrict access
        $settings = get_option('wpr_settings', array());
        
        if (isset($settings['wpr_restrict_site_access']) && $settings['wpr_restrict_site_access'] === 'yes') {
            if (!$this->is_active_member($user->ID)) {
                // Allow basic capabilities but restrict content access
                $restricted_caps = apply_filters('wpr_restricted_capabilities', array(
                    'read',
                    'level_0',
                ));
                
                foreach ($allcaps as $cap_key => $cap_value) {
                    if (!in_array($cap_key, $restricted_caps) && $cap_value) {
                        $allcaps[$cap_key] = false;
                    }
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Get all pending users
     */
    public function get_pending_users($limit = 100) {
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = '_wpr_registration_status' 
             AND meta_value = 'pending_payment'
             LIMIT %d",
            $limit
        ));
        
        $users = array();
        foreach ($user_ids as $user_id) {
            $users[] = get_userdata($user_id);
        }
        
        return $users;
    }
    
    /**
     * Get all active members
     */
    public function get_active_members($limit = 100) {
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = '_wpr_membership_active' 
             AND meta_value = 'yes'
             LIMIT %d",
            $limit
        ));
        
        $users = array();
        foreach ($user_ids as $user_id) {
            $users[] = get_userdata($user_id);
        }
        
        return $users;
    }
}
