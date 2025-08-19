=== VesessAuth ===
Contributors: vesseslabs
Tags: authentication, passwordless, login
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable secure passwordless login for WordPress through magic email links. Enhance security by eliminating password vulnerabilities.

== Description ==
VesessAuth plugin provides a modern, secure authentication method for WordPress websites without requiring passwords. Instead of remembering complex passwords, users receive a secure, time-limited login link via email.

**Key Features:**

* One-click email authentication
* No passwords to remember or store
* Time-limited secure tokens
* Brute-force attack protection
* Compatible with existing WordPress user accounts
* Easy setup and configuration

Passwordless authentication improves security by eliminating common password-related vulnerabilities such as weak passwords, password reuse, and phishing attacks.

== Privacy Policy ==

This plugin collects and processes the following user data:

* **Email Addresses**: Used solely for authentication purposes to send login links.
* **IP Addresses**: Temporarily stored for security purposes to prevent abuse.
* **Login Timestamps**: Recorded to maintain security logs.

All collected data is stored exclusively in your WordPress database and is not shared with third parties. Authentication tokens are temporarily stored and automatically expire after login completion or a predefined timeout period.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Create pages for login and sign up and add the corresponding shortcodes:

    - Create a login page with the slug "login"
       * `[vesesslabs_vesessauth_login]` - Displays the login form

    - Create a sign up page with the slug "sign-up"
       * `[vesesslabs_vesessauth_registration]` - Displays the registration form

4. IMPORTANT: This plugin sends secure login links via email. To ensure emails are delivered reliably (and don’t end up in spam), you must configure a mailer service such as:
   - WP Mail SMTP
   - An external SMTP service (e.g., Gmail, Outlook, SendGrid, Amazon SES, Mailgun, Zoho, etc.)
5. To enable the Sign Up form, go to  **Settings -> General -> Membership** and check the "Anyone can register" checkbox.
6. Once email delivery is working, go to **Settings -> VesessAuth** to configure the plugin.

7. The passwordless login option will now appear on your login page.


== Frequently Asked Questions ==

= How does passwordless authentication work? =

When a user attempts to log in, they enter their email address. The plugin sends a secure, time-limited login link to that email. When clicked, the link automatically authenticates the user if the token is valid and hasn't expired.

= Is passwordless authentication secure? =

Yes, it's often more secure than traditional passwords. It eliminates password-related vulnerabilities like weak or reused passwords. The magic links expire quickly and can only be used once.

= What happens if someone doesn't receive the login email? =

Users can request another login link if needed. Make sure to check spam folders. If problems persist, verify the email settings on your WordPress installation.

= Can I use this alongside traditional passwords? =

Yes, the plugin can be configured to offer passwordless login as an option while keeping traditional password login available.

== Screenshots ==

1. Passwordless login screen
2. Admin configuration page
3. Login email example
4. Registration example

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
First public release of VesessAuth.

== Technical Details ==

The plugin creates secure, time-limited tokens stored in the WordPress database. When a token is used, it's immediately invalidated to prevent replay attacks. All communication containing authentication tokens uses encryption when possible.
