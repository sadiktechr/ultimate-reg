# WooCommerce Paid Registration

**Version:** 2.0.0  
**Requires at least:** WordPress 5.8, WooCommerce 5.0  
**Tested up to:** WordPress 6.4, WooCommerce 8.0  
**PHP Version:** 7.4 or higher  
**License:** GPL v2 or later

## Description

WooCommerce Paid Registration is a powerful plugin that transforms your WordPress registration system into a paid membership gateway. It requires users to complete payment for a selected product before their registration is fully activated. Simply select any existing WooCommerce product as your membership fee!

## Features

- **Paid Registration Flow**: Automatically redirects new registrations to checkout
- **Select Any Product**: Choose any existing WooCommerce product as the membership fee - no automatic product creation needed!
- **Flexible Pricing**: Use any product with any price you set in WooCommerce
- **Free Registration Options**: Allow specific user roles to register without payment
- **Payment Protection**: Prevents removal of membership product during registration checkout
- **User Status Management**: Track pending, active, failed, and cancelled registrations
- **Auto Cleanup**: Automatically remove pending registrations after configurable time period
- **Admin Dashboard**: Beautiful, user-friendly settings page with product dropdown selector
- **Manual Activation**: Admin can manually activate/deactivate memberships from user profile
- **Access Restriction**: Optionally restrict site access to paid members only
- **Custom Redirects**: Set custom redirect URLs after successful payment
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce checkout and orders
- **No Compatibility Issues**: Uses safe, non-intrusive hooks to avoid WooCommerce conflicts

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

- **Select Product**: Choose any existing WooCommerce product from the dropdown to use as the membership fee
- **Product Info**: View selected product details including name, price, and status
- **Edit Product**: Quick link to edit the product in WooCommerce
- **Suggested Price**: Reference field (actual price is set in the product itself)

### How to Set Up Membership Product

1. Go to **WooCommerce → Products → Add New**
2. Create your membership product (e.g., "Membership Fee", "Registration Charge")
3. Set the price, description, and any other product details
4. Publish the product
5. Go to **WooCommerce → Paid Registration**
6. Select your newly created product from the "Select Product" dropdown
7. Save settings

That's it! The plugin will now require this product to be purchased during registration.

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
4. User is redirected to WooCommerce checkout to complete payment
5. Once payment is completed, the user account is activated
6. Welcome email is sent and user can access their account

If payment fails or is cancelled, the user remains in "pending_payment" status until they complete payment or the registration expires.

## Frequently Asked Questions

### Can I use any product as the membership fee?

Yes! You can select any existing WooCommerce product from the dropdown in the settings. This gives you complete flexibility to use simple products, variable products, or subscription products.

### What happens if I change the product price?

The price is managed entirely in WooCommerce. When you update the product price in WooCommerce, that new price will be used for all new registrations.

### Can I offer free registration to some users?

Yes! You can enable free registration for specific user roles. For example, you might want administrators or editors to register without payment.

### How do I manually activate a user?

Go to Users → All Users, click on the user to edit their profile, and scroll down to the "Membership Status" section. You can manually activate or deactivate their membership.

### What happens to pending registrations?

Pending registrations are automatically cleaned up after the specified number of hours (default: 24 hours). You can also manually trigger cleanup from the settings page.

### Is the membership product hidden from the shop?

The product you select will remain visible in your shop unless you manually hide it. We recommend creating a dedicated "Membership Fee" product and setting it to hidden visibility if you don't want it appearing in your regular shop.

## Changelog

### Version 2.0.0
- **NEW**: Select any existing WooCommerce product instead of auto-creating one
- **IMPROVED**: Removed incompatible WooCommerce hooks to prevent compatibility warnings
- **IMPROVED**: Better product selection UI with dropdown and product info display
- **FIXED**: WooCommerce compatibility issues with product query filters
- **REMOVED**: Automatic membership product creation (use manual product selection instead)

### Version 1.0.0
- Initial release

## Support

For support, feature requests, or bug reports, please contact us at support@example.com or visit our website at https://example.com

## Credits

Developed by Your Name  
Licensed under GPL v2 or later
