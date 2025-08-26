# VesessAuth

Tested up to: 6.8
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A WordPress plugin that provides passwordless authentication via email verification codes.

## Description

VesessAuth offers a modern, secure way to log in to WordPress sites without using passwords. Instead, users receive one-time verification codes via email.

### Key Features

* **Passwordless Login**: Users log in using their email address and a one-time verification code
* **Custom Registration**: Includes custom registration form with email verification
* **Shortcodes**: Easy implementation with ready-to-use shortcodes
* **Developer Friendly**: Hooks and filters for extending functionality
* **Enhanced Security**: Environment variable support for encryption keys
* **CSRF Protection**: Complete nonce verification throughout all forms and processes
* **Secure Redirects**: Protection against open redirect vulnerabilities
* **Input Validation**: Thorough sanitization and validation of all user inputs

## Installation

1. Upload the plugin folder to the `/wp-content/plugins` directory, or install through the WordPress plugins screen
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

* `[vesesslabs_vesessauth_login]` - Displays the login form
* `[vesesslabs_vesessauth_registration]` - Displays the registration form

## Security Benefits

Passwordless authentication eliminates many security issues related to password-based systems:

* No passwords to be stolen or brute-forced
* No risk of password reuse across multiple sites
* Protection against phishing attacks targeting credentials
* Complete CSRF protection with WordPress nonces
* Secure handling of redirects to prevent open redirect vulnerabilities
* Strict input validation and sanitization throughout the plugin
* Rate limiting to prevent brute force attacks


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

## For Developers

This section helps contributors and maintainers get the plugin running locally and understand where to make changes.

- Quick setup
   - Use a local WordPress environment (Local by Flywheel, MAMP, XAMPP, Docker, etc.).
   - Install the plugin into `/wp-content/plugins/pw-less-wp-plugin` and activate it.
   - Enable debugging: set `WP_DEBUG` and `WP_DEBUG_LOG` to `true` in `wp-config.php` to capture errors.

- Recommended tooling
   - PHP 7.4+ for development parity (plugin supports PHP 7.2+ in production).
   - A code linter / formatter that follows WordPress PHP Coding Standards (phpcs with WordPress rules recommended).
   - Editor with PHP and JS support.

- Project layout pointers
   - PHP classes: `includes/` contains the core plugin classes.
   - Public assets and templates: `public/` contains CSS, JS and partials used by shortcodes and forms.
   - Entry file: `vesessauth.php` bootstraps the plugin.

- Hooks, filters and shortcodes
   - The plugin exposes actions and filters for extension — search the `includes/` files to find available hooks.
   - Shortcodes: `[vesesslabs_vesessauth_login]` and `[vesesslabs_vesessauth_registration]` render the public forms.

- Testing & debugging
   - There is no test harness in this repo by default. Recommended approach:
      1. Install and configure PHPUnit and the WP PHPUnit scaffold if you want automated tests.
      2. Use a disposable local WP site for manual testing of flows (login, registration, email code delivery).
   - Use `error_log()` or the debug log for quick traces; watch emails via a mail-capture tool (MailHog, Mailtrap) in local setups.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Vesess](https://www.vesess.com/)

## Privacy Policy

### Data Collection & Usage

VesessAuth plugin collects and processes the following user data:

1. **Email Addresses**: Used solely for authentication purposes to send login links to users.
2. **IP Addresses**: Temporarily stored for security purposes to prevent abuse of the authentication system.
3. **Login Timestamps**: Recorded to maintain security logs and monitor for suspicious login activity.

All collected data is stored exclusively in your WordPress database and is not shared with third parties or external services. Authentication tokens are temporarily stored and automatically expire after login completion or a predefined timeout period.

### Data Retention

- Email addresses are retained as long as the user account exists in your WordPress installation.
- IP addresses and authentication tokens are stored temporarily and purged after successful authentication or expiration (typically 24 hours).
- Login timestamps are retained in logs for monitoring purposes.

### Third-Party Services

This plugin does not send any user data to external services. All authentication processes occur within your WordPress installation.

### User Rights

Site administrators can view and manage all stored authentication data through the WordPress admin interface. Individual users can request data export or deletion through your site's standard WordPress privacy tools.
