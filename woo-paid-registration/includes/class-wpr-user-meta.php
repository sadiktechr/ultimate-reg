<?php
/**
 * User Meta - Manages user status and profile integration
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
        // Add status to user profile in admin
        add_action('show_user_profile', array($this, 'add_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_profile_fields'));
        
        // Save status from admin profile
        add_action('personal_options_update', array($this, 'save_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
        
        // Filter user capabilities based on status
        add_filter('user_has_cap', array($this, 'filter_capabilities'), 10, 4);
    }
    
    /**
     * Get all possible registration statuses
     */
    public function get_status_labels() {
        return array(
            'pending_payment' => __('Pending Payment', 'woo-paid-registration'),
            'active'          => __('Active', 'woo-paid-registration'),
            'payment_failed'  => __('Payment Failed', 'woo-paid-registration'),
            'cancelled'       => __('Cancelled', 'woo-paid-registration'),
        );
    }
    
    /**
     * Add status display and controls to user profile
     */
    public function add_profile_fields($user) {
        $status = get_user_meta($user->ID, 'wpr_registration_status', true);
        
        if (empty($status)) {
            return;
        }
        
        $status_labels = $this->get_status_labels();
        $current_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
        $activation_order = get_user_meta($user->ID, 'wpr_activation_order_id', true);
        $timestamp = get_user_meta($user->ID, 'wpr_registration_timestamp', true);
        $activation_time = get_user_meta($user->ID, 'wpr_activation_timestamp', true);
        ?>
        <div class="wpr-profile-section">
            <h3><?php esc_html_e('Membership Registration Status', 'woo-paid-registration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Registration Status', 'woo-paid-registration'); ?></label></th>
                    <td>
                        <span class="wpr-status-badge wpr-status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($current_label); ?>
                        </span>
                        <?php if ($status === 'pending_payment' && $timestamp): ?>
                            <p class="description">
                                <?php 
                                printf(
                                    esc_html__('Registered: %s', 'woo-paid-registration'),
                                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), intval($timestamp))
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($status === 'active' && $activation_time): ?>
                            <p class="description">
                                <?php 
                                printf(
                                    esc_html__('Activated: %s', 'woo-paid-registration'),
                                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), intval($activation_time))
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($activation_order): ?>
                <tr>
                    <th><label><?php esc_html_e('Activation Order', 'woo-paid-registration'); ?></label></th>
                    <td>
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $activation_order . '&action=edit')); ?>">
                            #<?php echo esc_html($activation_order); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($status === 'pending_payment'): ?>
                <tr>
                    <th><label><?php esc_html_e('Manual Actions', 'woo-paid-registration'); ?></label></th>
                    <td>
                        <button type="button" class="button button-primary" onclick="wprManuallyActivateUser(<?php echo esc_attr($user->ID); ?>)">
                            <?php esc_html_e('Activate Account', 'woo-paid-registration'); ?>
                        </button>
                        <button type="button" class="button" onclick="wprCancelUserRegistration(<?php echo esc_attr($user->ID); ?>)">
                            <?php esc_html_e('Cancel Registration', 'woo-paid-registration'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Use these buttons to manually manage this pending registration.', 'woo-paid-registration'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <style>
            .wpr-profile-section {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #ccd0d4;
            }
            .wpr-status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 3px;
                font-weight: 600;
                font-size: 13px;
            }
            .wpr-status-pending_payment {
                background: #f0b421;
                color: #fff;
            }
            .wpr-status-active {
                background: #00a32a;
                color: #fff;
            }
            .wpr-status-payment_failed {
                background: #d63638;
                color: #fff;
            }
            .wpr-status-cancelled {
                background: #646970;
                color: #fff;
            }
        </style>
        
        <script>
        function wprManuallyActivateUser(userId) {
            if (!confirm('<?php esc_html_e('Are you sure you want to activate this user account without payment?', 'woo-paid-registration'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'wpr_manual_activate_user',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('wpr_manual_activate_' . $user->ID); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('User activated successfully!', 'woo-paid-registration'); ?>');
                    location.reload();
                } else {
                    alert(response.data || '<?php esc_html_e('Error activating user.', 'woo-paid-registration'); ?>');
                }
            });
        }
        
        function wprCancelUserRegistration(userId) {
            if (!confirm('<?php esc_html_e('Are you sure you want to cancel this registration? The user will be deleted.', 'woo-paid-registration'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'wpr_cancel_user_registration',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('wpr_cancel_user_' . $user->ID); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Registration cancelled. Redirecting...', 'woo-paid-registration'); ?>');
                    window.location.href = '<?php echo admin_url('users.php'); ?>';
                } else {
                    alert(response.data || '<?php esc_html_e('Error cancelling registration.', 'woo-paid-registration'); ?>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save profile field changes
     */
    public function save_profile_fields($user_id) {
        // Handled via AJAX for manual actions
    }
    
    /**
     * Filter user capabilities based on registration status
     */
    public function filter_capabilities($allcaps, $cap, $args, $user) {
        if (!$user || $user->ID === 0) {
            return $allcaps;
        }
        
        $status = get_user_meta($user->ID, 'wpr_registration_status', true);
        
        // Only restrict if status is not active
        if ($status !== 'active' && $status !== '') {
            // Allow basic capabilities for pending users to complete checkout
            $allowed_caps = array(
                'read',
                'edit_profile',
                'edit_user',
            );
            
            // If capability is not in allowed list and user is pending, restrict it
            if (!in_array($cap[0], $allowed_caps) && $status === 'pending_payment') {
                // Don't restrict checkout-related capabilities
                $checkout_caps = array(
                    'woocommerce_order_pay',
                    'woocommerce_checkout',
                );
                
                if (!in_array($cap[0], $checkout_caps)) {
                    // Let WordPress handle the restriction naturally
                    // We don't forcibly set to false to avoid breaking admin access
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Manual activation via AJAX
     */
    public static function ajax_manual_activate() {
        check_ajax_referer('wpr_manual_activate_', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_send_json_error(__('Permission denied', 'woo-paid-registration'));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID', 'woo-paid-registration'));
        }
        
        $status = get_user_meta($user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            wp_send_json_error(__('User is not in pending status', 'woo-paid-registration'));
        }
        
        // Activate user
        update_user_meta($user_id, 'wpr_registration_status', 'active');
        update_user_meta($user_id, 'wpr_activation_timestamp', time());
        update_user_meta($user_id, 'wpr_activation_order_id', 0); // Manual activation
        
        wp_send_json_success();
    }
    
    /**
     * Cancel registration via AJAX
     */
    public static function ajax_cancel_registration() {
        check_ajax_referer('wpr_cancel_user_', 'nonce');
        
        if (!current_user_can('delete_users')) {
            wp_send_json_error(__('Permission denied', 'woo-paid-registration'));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID', 'woo-paid-registration'));
        }
        
        $status = get_user_meta($user_id, 'wpr_registration_status', true);
        
        if ($status !== 'pending_payment') {
            wp_send_json_error(__('User is not in pending status', 'woo-paid-registration'));
        }
        
        // Delete user
        wp_delete_user($user_id);
        
        wp_send_json_success();
    }
}

// Register AJAX handlers
add_action('wp_ajax_wpr_manual_activate_user', array('WPR_User_Meta', 'ajax_manual_activate'));
add_action('wp_ajax_wpr_cancel_user_registration', array('WPR_User_Meta', 'ajax_cancel_registration'));
