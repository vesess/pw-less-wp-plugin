<?php

/**
 * Helper functions for the passwordless authentication plugin.
 */

/**
 * Get the plugin option with a fallback default value.
 *
 * @param string $key The option key.
 * @para        vesesslabs_vesessauth_log("Generated login token for user ID: $user_id, expires: " . gmdate('Y-m-d H:i:s', $expiration)); mixed $default The default value.
 * @return mixed The option value.
 */
function vesesslabs_vesessauth_get_option($key, $default = '')
{
    $options = get_option('vesesslabs_vesessauth_options');
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Check if a user's email is verified.
 *
 * @param int $user_id The user ID.
 * @return bool Whether the email is verified or user is an admin (admins bypass verification).
 */
function vesesslabs_vesessauth_is_email_verified($user_id)
{
    // Check if user is an admin, if so, bypass verification check
    $user = get_user_by('id', $user_id);
    if ($user && user_can($user, 'administrator')) {
        return true;
    }
    
    // Regular verification check for non-admin users
    return (bool) get_user_meta($user_id, 'email_verified', true);
}

/**
 * Generate a unique token for various auth operations.
 *
 * @return string A unique token.
 */
function vesesslabs_vesessauth_generate_token()
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
function vesesslabs_vesessauth_get_template_url($template, $args = [])
{
    $query_args = array_merge(['vesesslabs_vesessauth_template' => $template], $args);
    return add_query_arg($query_args, home_url());
}

/**
 * Log authentication events for debugging.
 *
 * @param string $message The log message.
 * @param string $level The log level (info, warning, error).
 * @param bool $display Whether to display the message to the user.
 */
function vesesslabs_vesessauth_log($message, $level = 'info', $display = false)
{
    // Check if logging is enabled unless we're forcing display to user
    if (!$display) {
        $options = get_option('vesesslabs_vesessauth_options', array());
        $logging_enabled = isset($options['show_auth_logs_menu']) && $options['show_auth_logs_menu'] === 'yes';
        
        // If logging is not enabled, don't log
        if (!$logging_enabled) {
            return;
        }
    }

    // Store log in transient for admin dashboard viewing
    $logs = get_transient('vesesslabs_vesessauth_logs') ?: [];
    $logs[] = [
        'time' => current_time('mysql'),
        'message' => $message,
        'level' => $level
    ];

    // Keep only the last 100 logs
    if (count($logs) > 100) {
        array_shift($logs);
    }

    set_transient('vesesslabs_vesessauth_logs', $logs, DAY_IN_SECONDS);

    // Log to browser console (only works when rendering a page)
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        $console_action = function () use ($message, $level) {
            // Enqueue console debug script
            wp_enqueue_script(
                'vesesslabs_vesessauth-console-debug',
                VESESSLABS_VESESSAUTH_PATH . 'public/js/console-debug.js',
                array(),
                VESESSLABS_VESESSAUTH_VERSION,
                true
            );
            
            // Use inline script to log the specific message
            wp_add_inline_script(
                'vesesslabs_vesessauth-console-debug',
                'window.VesessAuthLog("' . esc_js($message) . '", "' . esc_js($level === 'error' ? 'error' : ($level === 'warning' ? 'warning' : 'log')) . '");'
            );
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
                // Enqueue frontend notice styles
                wp_enqueue_style(
                    'vesesslabs_vesessauth-frontend-notices',
                    VESESSLABS_VESESSAUTH_URL . 'public/css/frontend-notices.css',
                    array(),
                    VESESSLABS_VESESSAUTH_VERSION
                );
                
                echo '<div class="vesesslabs_vesessauth-auth-notice' . esc_attr($notice_type) . '">' .
                    esc_html($message) .
                    '</div>';
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
if (!function_exists('vesesslabs_vesessauth_create_login_link')) {
    function vesesslabs_vesessauth_create_login_link($user_email)
    {
        $user = get_user_by('email', $user_email);

        if (!$user) {
            vesesslabs_vesessauth_log("Failed to create login link: User with email $user_email not found", 'error');
            return false;
        }

        // Generate a secure token
        $token = vesesslabs_vesessauth_generate_login_token($user->ID);        // Create a login URL directly to avoid encoding issues
        $base_url = home_url();
        $login_link = esc_url_raw(
            $base_url . '?action=magic_login' .
            '&uid=' . vesesslabs_vesessauth_encrypt_user_id($user->ID) .
            '&token=' . $token .
            '&_wpnonce=' . wp_create_nonce('magic_login_nonce')
        );

        vesesslabs_vesessauth_log("Magic login link created for user ID: {$user->ID}");
        return $login_link;
    }
}

/**
 * Generate a login token for a user
 * 
 * @param int $user_id The user ID
 * @return string The encrypted token to be used in magic links
 */
if (!function_exists('vesesslabs_vesessauth_generate_login_token')) {
    function vesesslabs_vesessauth_generate_login_token($user_id)
    {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Encrypt the token for storage in database
        $encrypted_token_for_storage = vesesslabs_vesessauth_encrypt_token($token);
        
        // Encrypt the token differently for use in URLs
        $encrypted_token_for_url = vesesslabs_vesessauth_encrypt_token_for_url($token);

        // Get the configured expiration time from settings
        $expiration_minutes = (int) vesesslabs_vesessauth_get_option('code_expiration', 15);
        $expiration = time() + ($expiration_minutes * MINUTE_IN_SECONDS);
        $token_data = [
            'token' => $encrypted_token_for_storage, // Store encrypted token
            'expiration' => $expiration
        ];        update_user_meta($user_id, 'vesesslabs_vesessauth_login_token', $token_data);

        vesesslabs_vesessauth_log("Generated login token for user ID: $user_id, expires: " . gmdate('Y-m-d H:i:s', $expiration));

        return $encrypted_token_for_url; // Return token encrypted for URL use
    }
}

/**
 * Encrypt a token for storage in the database
 *
 * @param string $token The plain text token
 * @return string The encrypted token
 */
function vesesslabs_vesessauth_encrypt_token($token) {
    return Vesesslabs_Vesessauth_Crypto::encrypt_for_storage($token);
}

/**
 * Encrypt a token for use in URLs
 *
 * @param string $token The plain text token
 * @return string URL-safe encrypted token
 */
function vesesslabs_vesessauth_encrypt_token_for_url($token) {
    return Vesesslabs_Vesessauth_Crypto::encrypt_for_url($token);
}

/**
 * Decrypt a token from URL format
 *
 * @param string $encrypted_token The encrypted token from URL
 * @return string The original plain text token
 */
function vesesslabs_vesessauth_decrypt_token_from_url($encrypted_token) {
    return Vesesslabs_Vesessauth_Crypto::decrypt_from_url($encrypted_token);
}

/**
 * Decrypt a token from storage format
 *
 * @param string $encrypted_token The encrypted token from storage
 * @return string|false The original plain text token or false on failure
 */
function vesesslabs_vesessauth_decrypt_token_from_storage($encrypted_token) {
    return Vesesslabs_Vesessauth_Crypto::decrypt_from_storage($encrypted_token);
}

/**
 * Encrypt user ID for use in magic links
 * 
 * @param int $user_id The user ID to encrypt
 * @return string Encrypted user ID
 */
function vesesslabs_vesessauth_encrypt_user_id($user_id) {
    return Vesesslabs_Vesessauth_Crypto::encrypt_user_id($user_id);
}

/**
 * Decrypt user ID from magic links
 * 
 * @param string $encrypted_id The encrypted user ID
 * @return int|false Decrypted user ID or false on failure
 */
function vesesslabs_vesessauth_decrypt_user_id($encrypted_id) {
    return Vesesslabs_Vesessauth_Crypto::decrypt_user_id($encrypted_id);
}

/**
 * Validate the security configuration of the plugin
 * 
 * @return array Array of security status and any issues
 */
function vesesslabs_vesessauth_validate_security() {
    $issues = [];
    $status = 'secure';
    
    // Check if crypto system is available
    if (!Vesesslabs_Vesessauth_Crypto::is_system_secure()) {
        $issues[] = 'Cryptographic system is not properly configured';
        $status = 'insecure';
    }
    
    // Check WordPress salts are defined and not default
    $salts = [
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'
    ];
    
    foreach ($salts as $salt) {
        if (!defined($salt) || constant($salt) === 'put your unique phrase here') {
            $issues[] = "WordPress salt '$salt' is not properly configured";
            $status = 'insecure';
        }
    }
    
    // Test basic encryption/decryption
    $test_data = 'test_encryption_' . time();
    $encrypted = Vesesslabs_Vesessauth_Crypto::encrypt_for_storage($test_data);
    $decrypted = Vesesslabs_Vesessauth_Crypto::decrypt_from_storage($encrypted);

    if ($decrypted !== $test_data) {
        $issues[] = 'Encryption/decryption test failed';
        $status = 'insecure';
    }
    
    return [
        'status' => $status,
        'issues' => $issues,
        'crypto_available' => Vesesslabs_Vesessauth_Crypto::is_system_secure()
    ];
}


