# My Passwordless Authentication

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

## Installation

1. Upload the plugin files to the `/wp-content/plugins/my-passwordless-auth` directory, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Passwordless Auth to configure the plugin:
   * Set up the "User Home URL" to match your site's base URL structure
   * Configure the "Redirect After Login" URL to specify where users should be directed after logging in
   * Customize other settings like email templates and login code expiration
4. Create two pages in WordPress:
   * Create a page named exactly "login" (e.g., `/login/`) and add the login form shortcode
   * Create a page named exactly "registration" (e.g., `/registration/`) and add the registration form shortcode
   * These specific page names are required for proper form redirections to work correctly
5. Go to Settings → Permalinks to configure the URLs:
   * Set "Permalink structure" structure to "Post name".
6. Add the shortcodes to your pages
7. (Optional but recommended) Set up secure encryption keys using the .env file system (see Security section below)

### Available Shortcodes

* `[passwordless_login]` - Displays the login form
* `[passwordless_registration]` - Displays the registration form
* `[passwordless_profile]` - Displays the user profile management form

## Security Benefits

Passwordless authentication eliminates many security issues related to password-based systems:

* No passwords to be stolen or brute-forced
* No risk of password reuse across multiple sites
* Verification codes expire quickly after generation
* Protection against credential stuffing attacks

### Enhanced Security with Environment Variables

For production use, this plugin supports storing encryption keys securely in an environment file:

1. Copy the `.env.example` file to `.env` in one of these locations:
   * WordPress root directory
   * One level above WordPress root directory
   * Plugin directory
   * One level above plugin directory

2. Replace the placeholder values with strong random strings:
   * For each `KEY` entry, use a 32-character random string
   * For each `IV` entry, use a 16-character random string
   * You can use online secure random generators or command line tools:
   ```bash
   # On Linux/Mac:
   openssl rand -base64 24 | cut -c1-32  # For 32-char keys
   openssl rand -base64 12 | cut -c1-16  # For 16-char IVs
   
   # On Windows (PowerShell):
   -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object {[char]$_})  # For 32-char keys
   -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 16 | ForEach-Object {[char]$_})  # For 16-char IVs
   ```

3. Each set of keys should be unique - don't reuse the same values.

This approach provides several security benefits:
* Encryption keys are kept outside your code repository
* Keys are never visible in database or PHP files 
* Keys can be different across development, staging, and production environments
* If compromised, keys can be easily rotated without code changes

## How It Works

1. A user enters their email address on the login form
2. A unique, time-limited verification code is generated and sent to their email
3. The user enters the code to authenticate
4. Upon successful verification, the user is logged in
5. The code becomes invalid immediately after use or expiration

## Customization

### Email Settings

You can customize the following email settings in the admin panel:

* From name
* From email address
* Code expiration time

### Template Customization

For advanced customization, you can override the template files by copying them to your theme:

```
your-theme/my-passwordless-auth/login-form.php
your-theme/my-passwordless-auth/registration-form.php
your-theme/my-passwordless-auth/profile-page.php
```

### Developer Hooks

The plugin provides various action and filter hooks for developers:

```php
// Customize login redirect URL
add_filter('my_passwordless_auth_login_redirect', function($redirect_url, $user_id) {
    return home_url('/dashboard/');
}, 10, 2);

// Do something after successful login
add_action('my_passwordless_auth_after_login', function($user_id, $ip_address) {
    // Custom code here
}, 10, 2);

// Modify verification code length (default: 6)
add_filter('my_passwordless_auth_code_length', function($length) {
    return 8; // Use 8-character codes instead of 6
});
```

## Requirements

* WordPress 5.0 or higher
* PHP 7.2 or higher

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Vesess](https://www.vesess.com/)
