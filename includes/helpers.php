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

        // Create a login URL directly to avoid encoding issues
        $base_url = home_url();
        $login_link = esc_url_raw(
            $base_url . '?action=magic_login' .
            '&uid=' . my_passwordless_auth_encrypt_user_id($user->ID) .
            '&token=' . $token
        );

        my_passwordless_auth_log("Magic login link created for user ID: {$user->ID}");
        return $login_link;
    }
}

/**
 * Generate a login token for a user
 * 
 * @param int $user_id The user ID
 * @return string The encrypted token to be used in magic links
 */
if (!function_exists('my_passwordless_auth_generate_login_token')) {
    function my_passwordless_auth_generate_login_token($user_id)
    {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Encrypt the token for storage in database
        $encrypted_token_for_storage = my_passwordless_auth_encrypt_token($token);
        
        // Encrypt the token differently for use in URLs
        $encrypted_token_for_url = my_passwordless_auth_encrypt_token_for_url($token);

        // Get the configured expiration time from settings
        $expiration_minutes = (int) my_passwordless_auth_get_option('code_expiration', 15);
        $expiration = time() + ($expiration_minutes * MINUTE_IN_SECONDS);
        $token_data = [
            'token' => $encrypted_token_for_storage, // Store encrypted token
            'expiration' => $expiration
        ];

        update_user_meta($user_id, 'passwordless_auth_login_token', $token_data);

        my_passwordless_auth_log("Generated login token for user ID: $user_id, expires: " . date('Y-m-d H:i:s', $expiration));

        return $encrypted_token_for_url; // Return token encrypted for URL use
    }
}

/**
 * Encrypt a token for storage in the database
 *
 * @param string $token The plain text token
 * @return string The encrypted token
 */
function my_passwordless_auth_encrypt_token($token) {
    // Define encryption key - in production, use wp_salt() or similar
    $encryption_key = 'PwLessWpAuthPluginSecretKey123!';
    $iv = substr('PwLessWpAuthIv16----', 0, 16); // Ensure exactly 16 bytes
    
    // Encrypt the token
    $encrypted = openssl_encrypt(
        $token,
        'AES-256-CBC',
        $encryption_key,
        0,
        $iv
    );
    
    return $encrypted;
}

/**
 * Encrypt a token for use in URLs
 *
 * @param string $token The plain text token
 * @return string URL-safe encrypted token
 */
function my_passwordless_auth_encrypt_token_for_url($token) {
    // Use a different key for URL tokens to prevent token reuse
    $encryption_key = 'UrlTokenEncryptionKey456!';
    $iv = substr('UrlTokenIv16Val--', 0, 16); // Ensure exactly 16 bytes
    
    // Encrypt the token
    $encrypted = openssl_encrypt(
        $token,
        'AES-256-CBC',
        $encryption_key,
        0,
        $iv
    );
    
    // Make URL safe
    return strtr(base64_encode($encrypted), '+/=', '-_,');
}

/**
 * Decrypt a token from URL format
 *
 * @param string $encrypted_token The encrypted token from URL
 * @return string The original plain text token
 */
function my_passwordless_auth_decrypt_token_from_url($encrypted_token) {
    // Use the same key as in encryption
    $encryption_key = 'UrlTokenEncryptionKey456!';
    $iv = substr('UrlTokenIv16Val--', 0, 16); // Ensure exactly 16 bytes - MUST match encryption
    
    try {
        // Convert from URL-safe format
        $encrypted_data = base64_decode(strtr($encrypted_token, '-_,', '+/='));
        
        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted_data,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );
        
        return $decrypted;
    } catch (Exception $e) {
        my_passwordless_auth_log("Exception during token decryption: " . $e->getMessage(), 'error');
        return false;
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
        $encrypted_token = sanitize_text_field($_GET['token']);

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

        // Decrypt the token from the URL
        $token = my_passwordless_auth_decrypt_token_from_url($encrypted_token);
        if ($token === false) {
            my_passwordless_auth_log("Magic login failed - could not decrypt token from URL", 'error');
            return new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        // Check if stored data has correct format
        if (!is_array($stored_data) || !isset($stored_data['token']) || !isset($stored_data['expiration'])) {
            my_passwordless_auth_log("Magic login failed - token data format invalid for user ID: $user_id", 'error');
            return new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
        }

        // Encrypt the decrypted URL token using the database encryption method for comparison
        $encrypted_token_for_comparison = my_passwordless_auth_encrypt_token($token);

        // Check if token matches the stored encrypted token
        if ($stored_data['token'] !== $encrypted_token_for_comparison) {
            my_passwordless_auth_log("Magic login failed - token mismatch for user ID: $user_id", 'error');
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
 * Encrypt user ID for use in magic links
 * 
 * @param int $user_id The user ID to encrypt
 * @return string Encrypted user ID
 */
function my_passwordless_auth_encrypt_user_id($user_id)
{
    // Define a fixed encryption key and IV
    $encryption_key = 'PwLessWpAuthPluginSecretKey123!';
    $iv = substr('PwLessWpAuthIv16----', 0, 16); // Ensure exactly 16 bytes

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
    $iv = substr('PwLessWpAuthIv16----', 0, 16); // Ensure exactly 16 bytes

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
