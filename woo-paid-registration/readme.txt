# WooCommerce Paid Registration

**Version:** 1.0.0  
**Requires at least:** WordPress 5.8, WooCommerce 5.0  
**Tested up to:** WordPress 6.4, WooCommerce 8.0  
**PHP Version:** 7.4 or higher  
**License:** GPL v2 or later

## Description

WooCommerce Paid Registration is a powerful plugin that transforms your WordPress registration system into a paid membership gateway. It requires users to complete payment for a hidden membership product before their registration is fully activated.

## Features

- **Paid Registration Flow**: Automatically redirects new registrations to checkout
- **Hidden Membership Product**: Creates and manages a hidden product for membership fees
- **Flexible Pricing**: Set custom membership prices through the admin panel
- **Free Registration Options**: Allow specific user roles to register without payment
- **Payment Protection**: Prevents removal of membership product during registration checkout
- **User Status Management**: Track pending, active, failed, and cancelled registrations
- **Auto Cleanup**: Automatically remove pending registrations after configurable time period
- **Admin Dashboard**: Beautiful, user-friendly settings page with real-time actions
- **Manual Activation**: Admin can manually activate/deactivate memberships
- **Access Restriction**: Optionally restrict site access to paid members only
- **Custom Redirects**: Set custom redirect URLs after successful payment
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce checkout and orders

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"
5. Navigate to WooCommerce → Paid Registration to configure settings

## Configuration

### General Settings

- **Enable Paid Registration**: Turn the paid registration system on/off
- **Require Payment**: Toggle whether payment is required for registration

### Membership Product

- **Product Status**: View and edit the automatically created membership product
- **Default Price**: Set the default membership fee (editable in product)

### Free Registration Options

- **Allow Free Registration**: Enable free registration for specific roles
- **Free Roles**: Select which user roles can register without payment

### Redirect Settings

- **Redirect After Payment**: Custom URL to redirect users after successful payment

### Access Restriction

- **Restrict Site Access**: Limit site access to active members only

### Cleanup Settings

- **Pending Registration Expiry**: Hours after which unpaid registrations are deleted
- **Manual Cleanup**: Button to immediately clean old pending registrations

## How It Works

1. User fills out the registration form
2. Upon submission, a user account is created with "pending_payment" status
3. A pending order is automatically created for the membership product
4. User is redirected to WooCommerce checkout
5. User completes payment
6. Upon successful payment, user status changes to "active"
7. Welcome email is sent and user gains full access

## Hooks and Filters

### Actions

```php
// Fired when membership product is created
do_action('wpr_membership_product_created', $product_id);

// Fired when membership price is updated
do_action('wpr_membership_price_updated', $price, $product_id);

// Fired when membership order is created
do_action('wpr_membership_order_created', $order_id, $customer_id);

// Fired when registration is completed
do_action('wpr_registration_completed', $user_id, $order_id);

// Fired when payment is completed
do_action('wpr_membership_payment_completed', $user_id, $order_id, $order);

// Fired when payment fails
do_action('wpr_membership_payment_failed', $user_id, $order_id, $order);

// Fired when pending registration is cleaned up
do_action('wpr_pending_registration_cleaned', $user_id);
```

### Filters

```php
// Modify membership product price
add_filter('wpr_membership_price', function($price) {
    return '25.00';
});

// Modify pending registration expiry hours
add_filter('wpr_pending_registration_expiry', function($hours) {
    return 48; // 48 hours instead of default 24
});

// Modify restricted capabilities for non-members
add_filter('wpr_restricted_capabilities', function($caps) {
    return array_merge($caps, array('edit_posts'));
});
```

## Scheduled Tasks

The plugin schedules cleanup of pending registrations via WordPress cron:

```php
// Schedule cleanup (runs hourly)
wp_schedule_event(time(), 'hourly', 'wpr_cleanup_pending_registrations');
```

## Troubleshooting

### Membership product not created
- Go to WooCommerce → Paid Registration
- Click "Create Membership Product" button

### Users stuck in pending status
- Check if payment gateway is working correctly
- Manually activate from Users → Edit User profile
- Use the "Clean Pending Registrations" button

### Checkout issues
- Ensure WooCommerce is properly configured
- Check that payment gateways are enabled
- Verify the membership product is published

## Support

For support and feature requests, please contact us through our website.

## Changelog

### Version 1.0.0
- Initial release
- Complete paid registration system
- Admin settings panel
- User status management
- Automatic cleanup system

## Credits

Developed by Your Name  
Licensed under GPL v2 or later
