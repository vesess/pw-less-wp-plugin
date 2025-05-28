<?php

/**
 * Helper functions for the passwordless authentication plugin.
 */

/**
 * Get the plugin option with a fallback default value.
 *
 * @param string $key The option key.
 * @para        my_passwordless_auth_log("Generated login token for user ID: $user_id, expires: " . gmdate('Y-m-d H:i:s', $expiration)); mixed $default The default value.
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
 * @return bool Whether the email is verified or user is an admin (admins bypass verification).
 */
function my_passwordless_auth_is_email_verified($user_id)
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
        $token = my_passwordless_auth_generate_login_token($user->ID);        // Create a login URL directly to avoid encoding issues
        $base_url = home_url();
        $login_link = esc_url_raw(
            $base_url . '?action=magic_login' .
            '&uid=' . my_passwordless_auth_encrypt_user_id($user->ID) .
            '&token=' . $token .
            '&_wpnonce=' . wp_create_nonce('magic_login_nonce')
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
        ];        update_user_meta($user_id, 'passwordless_auth_login_token', $token_data);

        my_passwordless_auth_log("Generated login token for user ID: $user_id, expires: " . gmdate('Y-m-d H:i:s', $expiration));

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
    // Get encryption key and IV from environment variables with fallbacks
    $encryption_key = my_passwordless_auth_get_env('PWLESS_DB_KEY', 'PwLessWpAuthPluginSecretKey123!');
    $iv = substr(my_passwordless_auth_get_env('PWLESS_DB_IV', 'PwLessWpAuthIv16----'), 0, 16);
    
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
    // Get encryption key and IV from environment variables with fallbacks
    $encryption_key = my_passwordless_auth_get_env('PWLESS_URL_KEY', 'UrlTokenEncryptionKey456!');
    $iv = substr(my_passwordless_auth_get_env('PWLESS_URL_IV', 'UrlTokenIv16Val--'), 0, 16);
    
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
    // Get encryption key and IV from environment variables with fallbacks - must match encryption
    $encryption_key = my_passwordless_auth_get_env('PWLESS_URL_KEY', 'UrlTokenEncryptionKey456!');
    $iv = substr(my_passwordless_auth_get_env('PWLESS_URL_IV', 'UrlTokenIv16Val--'), 0, 16);
    
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
 * Encrypt user ID for use in magic links
 * 
 * @param int $user_id The user ID to encrypt
 * @return string Encrypted user ID
 */
function my_passwordless_auth_encrypt_user_id($user_id)
{
    // Get encryption key and IV from environment variables with fallbacks
    $encryption_key = my_passwordless_auth_get_env('PWLESS_UID_KEY', 'PwLessWpAuthPluginSecretKey123!');
    $iv = substr(my_passwordless_auth_get_env('PWLESS_UID_IV', 'PwLessWpAuthIv16----'), 0, 16);

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
    // Get encryption key and IV from environment variables with fallbacks - must match encryption
    $encryption_key = my_passwordless_auth_get_env('PWLESS_UID_KEY', 'PwLessWpAuthPluginSecretKey123!');
    $iv = substr(my_passwordless_auth_get_env('PWLESS_UID_IV', 'PwLessWpAuthIv16----'), 0, 16);

    // Log the encrypted ID for debugging
    my_passwordless_auth_log("Attempting to decrypt user ID: " . $encrypted_id, 'info');
    
    try {
        // Expand the attempts array to cover more possible URL encoding scenarios
        $attempts = [
            $encrypted_id,                       // As received
            str_replace(' ', '+', $encrypted_id), // Common URL encoding issue fix for spaces converted to +
            rawurldecode($encrypted_id),         // Standard URL decode
            str_replace(' ', '+', rawurldecode($encrypted_id)), // Combined approach
            urldecode($encrypted_id),            // Alternative URL decode
            str_replace('_', '/', str_replace('-', '+', $encrypted_id)), // Manual Base64URL to Base64 conversion
            base64_encode(base64_decode(strtr($encrypted_id, '-_,', '+/='))), // Double decode/encode to normalize
            trim($encrypted_id),                 // Trim whitespace 
        ];
        
        // Add debug logs for each attempt
        foreach ($attempts as $index => $attempt) {
            my_passwordless_auth_log("Decryption attempt #" . ($index + 1) . ": " . substr($attempt, 0, 40) . (strlen($attempt) > 40 ? '...' : ''), 'info');
        }
        
        // Try each approach in sequence
        foreach ($attempts as $index => $attempt) {
            try {
                // URL-safe base64 decoding
                $encrypted_data = base64_decode(strtr($attempt, '-_,', '+/='));
                if ($encrypted_data === false) {
                    my_passwordless_auth_log("Attempt #" . ($index + 1) . " failed: Invalid base64 encoding", 'info');
                    continue;
                }
                
                // Try to decrypt
                $decrypted = openssl_decrypt(
                    $encrypted_data,
                    'AES-128-CBC',
                    $encryption_key,
                    0,
                    $iv
                );
                
                if ($decrypted === false) {
                    my_passwordless_auth_log("Attempt #" . ($index + 1) . " failed: " . openssl_error_string(), 'info');
                    continue;
                }
                
                // Log success
                my_passwordless_auth_log("Decryption attempt #" . ($index + 1) . " succeeded: " . $decrypted, 'info');
                
                // Validate that we got a numeric result
                if (!is_numeric($decrypted)) {
                    my_passwordless_auth_log("Decryption result is not numeric: " . $decrypted, 'error');
                    continue;
                }
                
                // Success - return the decrypted user ID
                return (int)$decrypted;
            } catch (Exception $e) {
                my_passwordless_auth_log("Exception in attempt #" . ($index + 1) . ": " . $e->getMessage(), 'error');
                continue;
            }
        }
        
        // If we get here, all attempts failed
        my_passwordless_auth_log("All decryption attempts failed for: " . $encrypted_id, 'error');
        return false;
    } catch (Exception $e) {
        my_passwordless_auth_log("Exception during decryption process: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Load environment variables from a .env file
 * 
 * @param string $path Path to .env file
 * @return bool True if the file was loaded, false otherwise
 */
function my_passwordless_auth_load_env($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse line
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Set as environment variable and in our custom global array
            putenv("{$name}={$value}");
            $GLOBALS['my_passwordless_env'][$name] = $value;
        }
    }
    
    return true;
}

/**
 * Get an environment variable value with fallback
 * 
 * @param string $key Environment variable key
 * @param mixed $default Default value if not found
 * @return mixed Environment variable value or default
 */
function my_passwordless_auth_get_env($key, $default = null) {
    // Check our custom global array first
    if (isset($GLOBALS['my_passwordless_env'][$key])) {
        return $GLOBALS['my_passwordless_env'][$key];
    }
    
    // Try getenv()
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    // Try WordPress salts as fallback for encryption keys
    if (strpos($key, 'PWLESS_KEY') !== false && defined('SECURE_AUTH_KEY')) {
        return SECURE_AUTH_KEY;
    }
    if (strpos($key, 'PWLESS_IV') !== false && defined('SECURE_AUTH_SALT')) {
        return substr(SECURE_AUTH_SALT, 0, 16);
    }
    
    return $default;
}
