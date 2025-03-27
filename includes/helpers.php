<?php

/**
 * Helper functions for the passwordless authentication plugin.
 */

/**
 * Get the plugin option with a fallback default value.
 *
 * @param string $key The option key.
 * @param mixed $default The default value.
 * @return mixed The option value.
 */
function my_passwordless_auth_get_option($key, $default = '')
{
    $options = get_option('my_passwordless_auth_options');
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Check if a user's email is verified.
 *
 * @param int $user_id The user ID.
 * @return bool Whether the email is verified.
 */
function my_passwordless_auth_is_email_verified($user_id)
{
    return (bool) get_user_meta($user_id, 'email_verified', true);
}

/**
 * Generate a unique token for various auth operations.
 *
 * @return string A unique token.
 */
function my_passwordless_auth_generate_token()
{
    return bin2hex(random_bytes(16));
}

/**
 * Get the URL for a template file.
 *
 * @param string $template The template name.
 * @param array $args Optional. Additional query arguments.
 * @return string The URL.
 */
function my_passwordless_auth_get_template_url($template, $args = [])
{
    $query_args = array_merge(['my_passwordless_auth_template' => $template], $args);
    return add_query_arg($query_args, home_url());
}

/**
 * Generate a verification URL for email confirmation.
 *
 * @param int $user_id The user ID to verify.
 * @param string $code The verification code.
 * @return string The verification URL.
 */
function my_passwordless_auth_get_verification_url($user_id, $code)
{
    return add_query_arg(
        [
            'action' => 'verify_email',
            'user_id' => $user_id,
            'code' => $code
        ],
        home_url()
    );
}

/**
 * Process email verification when a user clicks the verification link.
 * 
 * @return bool True if verification was successful, false otherwise.
 */
function my_passwordless_auth_process_email_verification()
{
    // Check if this is a verification request
    if (
        isset($_GET['action']) && $_GET['action'] === 'verify_email' &&
        isset($_GET['user_id']) && isset($_GET['code'])
    ) {

        $user_id = intval($_GET['user_id']);
        $code = sanitize_text_field($_GET['code']);

        // Get stored verification code for this user
        $stored_code = get_user_meta($user_id, 'email_verification_code', true);

        // Verify the code matches
        if ($stored_code && $stored_code === $code) {
            // Update user meta to mark email as verified
            update_user_meta($user_id, 'email_verified', true);
            // Delete the verification code as it's no longer needed
            delete_user_meta($user_id, 'email_verification_code');

            my_passwordless_auth_log("Email verified successfully for user ID: $user_id");
            return true;
        } else {
            my_passwordless_auth_log("Email verification failed for user ID: $user_id - invalid code", 'error');
            return false;
        }
    }

    return false;
}

/**
 * Log authentication events for debugging.
 *
 * @param string $message The log message.
 * @param string $level The log level (info, warning, error).
 * @param bool $display Whether to display the message to the user.
 */
function my_passwordless_auth_log($message, $level = 'info', $display = false)
{
    // Log to PHP error log
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("My Passwordless Auth [{$level}]: {$message}");
    }

    // Store log in transient for admin dashboard viewing
    $logs = get_transient('my_passwordless_auth_logs') ?: [];
    $logs[] = [
        'time' => current_time('mysql'),
        'message' => $message,
        'level' => $level
    ];

    // Keep only the last 100 logs
    if (count($logs) > 100) {
        array_shift($logs);
    }

    set_transient('my_passwordless_auth_logs', $logs, DAY_IN_SECONDS);

    // Log to browser console (only works when rendering a page)
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        $console_action = function () use ($message, $level) {
            echo '<script>
                console.' . esc_js($level === 'error' ? 'error' : ($level === 'warning' ? 'warn' : 'log')) . '("My Passwordless Auth: ' . esc_js($message) . '");
            </script>';
        };

        add_action('wp_footer', $console_action);
        add_action('admin_footer', $console_action);
    }

    // Optionally display message to user
    if ($display) {
        $notice_type = $level === 'error' ? 'error' : ($level === 'warning' ? 'warning' : 'success');
        add_action('admin_notices', function () use ($message, $notice_type) {
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });

        if (!is_admin()) {
            // For frontend
            add_action('wp_footer', function () use ($message, $notice_type) {
                echo '<div class="my-passwordless-auth-notice my-passwordless-auth-notice-' . esc_attr($notice_type) . '">' .
                    esc_html($message) .
                    '</div>';
                echo '<style>
                    .my-passwordless-auth-notice {
                        padding: 10px 15px;
                        margin: 15px 0;
                        border-radius: 3px;
                    }
                    .my-passwordless-auth-notice-success {
                        background-color: #dff0d8;
                        color: #3c763d;
                        border: 1px solid #d6e9c6;
                    }
                    .my-passwordless-auth-notice-warning {
                        background-color: #fcf8e3;
                        color: #8a6d3b;
                        border: 1px solid #faebcc;
                    }
                    .my-passwordless-auth-notice-error {
                        background-color: #f2dede;
                        color: #a94442;
                        border: 1px solid #ebccd1;
                    }
                </style>';
            });
        }
    }
}

/**
 * Create a magic login link for a user
 * 
 * @param string $user_email The user's email address
 * @return string|false Login link or false if user not found
 */
if (!function_exists('my_passwordless_auth_create_login_link')) {
    function my_passwordless_auth_create_login_link($user_email)
    {
        $user = get_user_by('email', $user_email);

        if (!$user) {
            my_passwordless_auth_log("Failed to create login link: User with email $user_email not found", 'error');
            return false;
        }

        // Generate a secure token
        $token = my_passwordless_auth_generate_login_token($user->ID);

        // Create a login URL with the token
        $login_link = add_query_arg([
            'action' => 'magic_login',
            'uid' => my_passwordless_auth_encrypt_user_id($user->ID),
            'token' => $token,
        ], home_url());

        my_passwordless_auth_log("Magic login link created for user ID: {$user->ID}");
        return $login_link;
    }
}

/**
 * Generate a login token for a user
 * 
 * @param int $user_id The user ID
 * @return string The generated token
 */
if (!function_exists('my_passwordless_auth_generate_login_token')) {
    function my_passwordless_auth_generate_login_token($user_id)
    {
        // Generate a random token
        $token = bin2hex(random_bytes(32));

        // Store the token with an expiration time (15 minutes)
        $expiration = time() + (15 * MINUTE_IN_SECONDS);
        $token_data = [
            'token' => $token,
            'expiration' => $expiration
        ];

        update_user_meta($user_id, 'passwordless_auth_login_token', $token_data);

        my_passwordless_auth_log("Generated login token for user ID: $user_id, token: $token, expires: " . date('Y-m-d H:i:s', $expiration));

        return $token;
    }
}

/**
 * Send magic login link to a user
 * 
 * @param string $user_email The user's email address
 * @param string|null $subject Optional custom email subject
 * @param string|null $message Optional custom email message
 * @return bool Whether the email was sent successfully
 */
if (!function_exists('my_passwordless_auth_send_magic_link')) {
    function my_passwordless_auth_send_magic_link($user_email, $subject = null, $message = null)
    {
        $user = get_user_by('email', $user_email);

        if (!$user) {
            my_passwordless_auth_log("Failed to send magic link: User with email $user_email not found", 'error');
            return false;
        }

        $login_link = my_passwordless_auth_create_login_link($user_email);

        if (!$login_link) {
            return false;
        }

        // Default email subject
        if (!$subject) {
            $options = get_option('my_passwordless_auth_options', []);
            $subject = isset($options['email_subject']) ? $options['email_subject'] : '';

            if (empty($subject)) {
                $subject = sprintf(__('Login link for %s', 'my-passwordless-auth'), get_bloginfo('name'));
            }
        }

        // Default email message
        if (!$message) {
            $options = get_option('my_passwordless_auth_options', []);
            $template = isset($options['email_template']) ? $options['email_template'] : '';

            if (empty($template)) {
                $message = sprintf(
                    __('Hello %s,

Click the link below to log in:

%s

This link will expire in 15 minutes.

If you did not request this login link, please ignore this email.

Regards,
%s', 'my-passwordless-auth'),
                    $user->display_name,
                    $login_link,
                    get_bloginfo('name')
                );
            } else {
                // Replace placeholders in the template
                $message = str_replace(
                    ['{display_name}', '{login_link}', '{site_name}'],
                    [$user->display_name, $login_link, get_bloginfo('name')],
                    $template
                );
            }
        }

        // Convert line breaks to <br> for HTML emails
        $message = nl2br($message);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Allow filtering of email content
        $subject = apply_filters('my_passwordless_auth_email_subject', $subject, $user);
        $message = apply_filters('my_passwordless_auth_email_message', $message, $user, $login_link);
        $headers = apply_filters('my_passwordless_auth_email_headers', $headers, $user);

        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if ($sent) {
            my_passwordless_auth_log("Magic login link sent to user ID: {$user->ID}");
        } else {
            my_passwordless_auth_log("Failed to send magic login link to user ID: {$user->ID}", 'error');
        }

        return $sent;
    }
}

/**
 * Process magic login when a user clicks the login link
 * 
 * @return bool|WP_Error True if login was successful, WP_Error on failure
 */
if (!function_exists('my_passwordless_auth_process_magic_login')) {
    function my_passwordless_auth_process_magic_login()
    {
        // Check if this is a magic login request
        if (
            !isset($_GET['action']) || $_GET['action'] !== 'magic_login' ||
            !isset($_GET['uid']) || !isset($_GET['token'])
        ) {
            return false;
        }

        my_passwordless_auth_log("Processing magic login request with uid: " . sanitize_text_field($_GET['uid']));

        $uid = sanitize_text_field($_GET['uid']);
        $token = sanitize_text_field($_GET['token']);

        $user_id = my_passwordless_auth_decrypt_user_id($uid);

        if ($user_id === false) {
            my_passwordless_auth_log("Magic login failed - could not decrypt user ID from: $uid", 'error');
            return new WP_Error('invalid_user', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        my_passwordless_auth_log("Successfully decrypted user ID: $user_id");

        // Get stored token data for this user
        $stored_data = get_user_meta($user_id, 'passwordless_auth_login_token', true);

        if (empty($stored_data)) {
            my_passwordless_auth_log("Magic login failed - no token stored for user ID: $user_id", 'error');
            return new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        my_passwordless_auth_log("Stored token data: " . json_encode($stored_data));

        // Check if stored data has correct format
        if (!is_array($stored_data) || !isset($stored_data['token']) || !isset($stored_data['expiration'])) {
            my_passwordless_auth_log("Magic login failed - token data format invalid for user ID: $user_id", 'error');
            return new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        // Check if token matches
        if ($stored_data['token'] !== $token) {
            my_passwordless_auth_log("Magic login failed - token mismatch for user ID: $user_id", 'error');
            my_passwordless_auth_log("Expected: {$stored_data['token']}, Got: {$token}", 'error');
            return new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        // Check if token has expired
        if (time() > $stored_data['expiration']) {
            my_passwordless_auth_log("Magic login failed - token expired for user ID: $user_id", 'error');
            delete_user_meta($user_id, 'passwordless_auth_login_token');
            return new WP_Error('expired_token', __('This login link has expired. Please request a new one.', 'my-passwordless-auth'));
        }

        // Get the user
        $user = get_user_by('id', $user_id);
        if (!$user) {
            my_passwordless_auth_log("Magic login failed - user ID $user_id not found", 'error');
            return new WP_Error('invalid_user', __('User not found. Please try again.', 'my-passwordless-auth'));
        }

        // Delete the token as it's no longer needed
        delete_user_meta($user_id, 'passwordless_auth_login_token');

        // Log the user in
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        my_passwordless_auth_log("User ID: $user_id successfully logged in via magic link");

        // Fire action for other plugins
        do_action('my_passwordless_auth_after_magic_login', $user);

        return true;
    }
}

/**
 * Validate email address format
 * 
 * @param string $email The email address to validate
 * @return bool Whether the email is valid
 */
function my_passwordless_auth_is_valid_email($email)
{
    return (bool) is_email($email);
}



/**
 * Encrypt user ID for use in magic links
 * 
 * @param int $user_id The user ID to encrypt
 * @return string Encrypted user ID
 */
function my_passwordless_auth_encrypt_user_id($user_id)
{
    // Define a fixed encryption key and IV
    $encryption_key = 'PwLessWpAuthPluginSecretKey123!';
    $iv = 'PwLessWpAuthIv16';

    // Salt the ID with just the user ID - keep it simple
    $data_to_encrypt = (string)$user_id;

    // Log for debugging
    my_passwordless_auth_log("Encrypting user ID: " . $data_to_encrypt);

    // Use a simpler encryption method
    $encrypted = openssl_encrypt(
        $data_to_encrypt,
        'AES-128-CBC',
        $encryption_key,
        0,
        $iv
    );

    if ($encrypted === false) {
        my_passwordless_auth_log("Encryption failed: " . openssl_error_string(), 'error');
        return false;
    }

    // URL-safe base64 encoding
    $result = strtr(base64_encode($encrypted), '+/=', '-_,');

    // Log for debugging
    my_passwordless_auth_log("Encryption result: " . $result);

    return $result;
}

/**
 * Decrypt user ID from magic links
 * 
 * @param string $encrypted_id The encrypted user ID
 * @return int|false Decrypted user ID or false on failure
 */
function my_passwordless_auth_decrypt_user_id($encrypted_id)
{
    // Define the same fixed encryption key and IV used in encryption
    $encryption_key = 'PwLessWpAuthPluginSecretKey123!';
    $iv = 'PwLessWpAuthIv16';

    try {
        // Log the input for debugging
        my_passwordless_auth_log("Attempting to decrypt: " . $encrypted_id);

        // URL-safe base64 decoding
        $encrypted_data = base64_decode(strtr($encrypted_id, '-_,', '+/='));
        if ($encrypted_data === false) {
            my_passwordless_auth_log("Decryption failed: Invalid base64 encoding", 'error');
            return false;
        }

        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted_data,
            'AES-128-CBC',
            $encryption_key,
            0,
            $iv
        );

        if ($decrypted === false) {
            my_passwordless_auth_log("Decryption failed: " . openssl_error_string(), 'error');
            return false;
        }

        // Log decryption result for debugging
        my_passwordless_auth_log("Successfully decrypted to: " . $decrypted);

        // Validate that we got a numeric result
        if (!is_numeric($decrypted)) {
            my_passwordless_auth_log("Decryption result is not numeric: " . $decrypted, 'error');
            return false;
        }

        return (int)$decrypted;
    } catch (Exception $e) {
        my_passwordless_auth_log("Exception during decryption: " . $e->getMessage(), 'error');
        return false;
    }
}
