=== WooCommerce Paid Registration ===
Contributors: yourname
Tags: woocommerce, paid registration, membership, payment, user registration
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 3.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Force users to pay for membership during registration. Select any existing WooCommerce product as the membership fee. No registration completes without payment.

== Description ==

**WooCommerce Paid Registration** transforms your WordPress site into a paid membership platform by requiring users to complete a payment before their registration is finalized.

## Key Features

* **Payment Required for Registration** - Users cannot complete registration without paying for the membership product
* **Select Any Existing Product** - Choose any WooCommerce product as your membership fee (no auto-creation needed)
* **Automatic Cart Addition** - Membership product is automatically added to cart after registration
* **Cannot Remove from Cart** - Pending users cannot remove the membership product from their cart
* **Account Status Management** - Track users as Pending, Active, Failed, or Cancelled
* **Login Blocking** - Users with pending payments cannot log in
* **Auto-Cleanup** - Automatically delete old pending registrations
* **Manual Controls** - Admin can manually activate or cancel pending registrations from user profile
* **Welcome Emails** - Automatic welcome email sent upon successful payment
* **Beautiful Admin Panel** - Modern, user-focused settings page under WooCommerce menu

## How It Works

1. User fills out registration form
2. Account created with "Pending Payment" status
3. User automatically redirected to checkout
4. Membership product added to cart (cannot be removed)
5. User completes payment
6. Account activated and welcome email sent

## User Statuses

* **Pending Payment** - User registered but hasn't paid yet (cannot log in)
* **Active** - Payment completed successfully (full access)
* **Payment Failed** - Payment attempt failed (needs to re-register)
* **Cancelled** - Registration cancelled by user or admin

## Setup Instructions

1. Create a product in WooCommerce (e.g., "Membership Fee")
2. Set your desired price
3. Go to **WooCommerce → Paid Registration**
4. Select your membership product from the dropdown
5. Save settings
6. Test the registration flow!

## For Developers

The plugin includes several action hooks for customization:

* `wpr_registration_completed` - Triggered when registration is completed
* `wpr_payment_failed` - Triggered when payment fails
* `wpr_registration_cancelled` - Triggered when registration is cancelled

== Installation ==

1. Upload the `woo-paid-registration` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and active
4. Go to **WooCommerce → Paid Registration**
5. Select your membership product and save settings

== Frequently Asked Questions ==

= Can I use any WooCommerce product as the membership fee? =

Yes! You can select any existing, purchasable WooCommerce product from the settings dropdown.

= What happens if a user doesn't complete payment? =

The user account remains in "Pending Payment" status and they cannot log in. Old pending registrations are automatically cleaned up based on your settings.

= Can admins manually activate users? =

Yes! Go to Users → Edit User and you'll see manual activation/cancellation buttons for pending users.

= Does this work with all payment gateways? =

Yes, the plugin works with any WooCommerce payment gateway since it uses standard WooCommerce checkout flow.

= Can users register without paying? =

No. The entire purpose of this plugin is to ensure no registration completes without payment.

= What if I want to offer free registration for certain roles? =

This version does not support free registration. All users must pay. You would need custom code modifications for that functionality.

== Changelog ==

= 3.0.0 =
* Complete rewrite for maximum WooCommerce compatibility
* Removed all filters that could cause WC compatibility warnings
* Simplified settings: only product selection and cleanup hours
* Removed free registration options (all users must pay)
* Removed access restriction features
* Cleaner, more focused codebase
* Better error handling and user feedback

= 2.0.0 =
* Added ability to select pre-created products
* Fixed WooCommerce compatibility issues
* Improved admin interface

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.0.0 =
Major update with complete rewrite for better WooCommerce compatibility. Settings have been simplified - please review your configuration after updating.
