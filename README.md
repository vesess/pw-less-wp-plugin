# EasyAuth

Tested up to: 6.8
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A WordPress plugin that provides passwordless authentication via email verification codes.

## Description

My Passwordless Authentication offers a modern, secure way to log in to WordPress sites without using passwords. Instead, users receive one-time verification codes via email.

### Key Features

* **Passwordless Login**: Users log in using their email address and a one-time verification code
* **Custom Registration**: Includes custom registration form with email verification
* **User Profile Management**: Users can update their information and delete their accounts
* **Email Notifications**: Customizable email notifications for various user actions
* **Shortcodes**: Easy implementation with ready-to-use shortcodes
* **Developer Friendly**: Hooks and filters for extending functionality
* **Enhanced Security**: Environment variable support for encryption keys
* **CSRF Protection**: Complete nonce verification throughout all forms and processes
* **Secure Redirects**: Protection against open redirect vulnerabilities
* **Input Validation**: Thorough sanitization and validation of all user inputs

## Installation

1. Upload the plugin files to the `/wp-content/plugins/my-passwordless-auth` directory, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Passwordless Auth to configure the plugin:
   * Set up the "User Home URL" to match your site's base URL structure
   * Configure the "Redirect After Login" URL to specify where users should be directed after logging in
   * Customize other settings like email templates and login code expiration
4. Create two pages in WordPress:
   * Create a page named exactly "login" (e.g., `/login/`) and add the login form shortcode
   * Create a page named exactly "sign-up" (e.g., `/sign-up/`) and add the registration form shortcode
   * These specific page names are required for proper form redirections to work correctly
5. Go to Settings → Permalinks to configure the URLs:
   * Set "Permalink structure" structure to "Post name".
6. Add the shortcodes to your pages

### Available Shortcodes

* `[passwordless_login]` - Displays the login form
* `[passwordless_registration]` - Displays the registration form

## Security Benefits

Passwordless authentication eliminates many security issues related to password-based systems:

* No passwords to be stolen or brute-forced
* No risk of password reuse across multiple sites
* Protection against phishing attacks targeting credentials
* Complete CSRF protection with WordPress nonces
* Secure handling of redirects to prevent open redirect vulnerabilities
* Strict input validation and sanitization throughout the plugin
* Rate limiting to prevent brute force attacks

For more details about the security enhancements, please see [security-enhancements.md](security-enhancements.md).

## How It Works

1. A user enters their email address on the login form
2. A unique, time-limited verification code is generated and sent to their email
3. The user enters the code to authenticate
4. Upon successful verification, the user is logged in
5. The code becomes invalid immediately after use or expiration


### Email Settings

You can customize the following email settings in the admin panel:

* From name
* From email address
* Code expiration time


## Requirements

* WordPress 5.0 or higher
* PHP 7.2 or higher

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Vesess](https://www.vesess.com/)
