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
function my_passwordless_auth_get_option($key, $default = '') {
    $options = get_option('my_passwordless_auth_options');
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Check if a user's email is verified.
 *
 * @param int $user_id The user ID.
 * @return bool Whether the email is verified.
 */
function my_passwordless_auth_is_email_verified($user_id) {
    return (bool) get_user_meta($user_id, 'email_verified', true);
}

/**
 * Generate a unique token for various auth operations.
 *
 * @return string A unique token.
 */
function my_passwordless_auth_generate_token() {
    return bin2hex(random_bytes(16));
}

/**
 * Get the URL for a template file.
 *
 * @param string $template The template name.
 * @param array $args Optional. Additional query arguments.
 * @return string The URL.
 */
function my_passwordless_auth_get_template_url($template, $args = []) {
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
function my_passwordless_auth_get_verification_url($user_id, $code) {
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
function my_passwordless_auth_process_email_verification() {
    // Check if this is a verification request
    if (isset($_GET['action']) && $_GET['action'] === 'verify_email' && 
        isset($_GET['user_id']) && isset($_GET['code'])) {
        
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
function my_passwordless_auth_log($message, $level = 'info', $display = false) {
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
        $console_action = function() use ($message, $level) {
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
        add_action('admin_notices', function() use ($message, $notice_type) {
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
        
        if (!is_admin()) {
            // For frontend
            add_action('wp_footer', function() use ($message, $notice_type) {
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