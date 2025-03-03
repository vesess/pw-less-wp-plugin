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
 * @return string The URL.
 */
function my_passwordless_auth_get_template_url($template) {
    return add_query_arg('my_passwordless_auth_template', $template, home_url());
}

/**
 * Log authentication events for debugging.
 *
 * @param string $message The log message.
 * @param string $level The log level (info, warning, error).
 */
function my_passwordless_auth_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("My Passwordless Auth [{$level}]: {$message}");
    }
}
