<?php
/**
 * Plugin Name: EasyAuth
 * Plugin URI: https://vesess.com
 * Description: A WordPress plugin for passwordless authentication.
 * Version: 1.0.0
 * Author: Vesess
 * Author URI: https://vesess.com
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('MY_PASSWORDLESS_AUTH_VERSION', '1.0.0');
define('MY_PASSWORDLESS_AUTH_PATH', plugin_dir_path(__FILE__));
define('MY_PASSWORDLESS_AUTH_URL', plugin_dir_url(__FILE__));

// Initialize global environment variable array
$GLOBALS['my_passwordless_env'] = array();

// Include helper functions
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

// Include admin profile extensions and account deletion functionality
require_once plugin_dir_path(__FILE__) . 'includes/admin-profile-extension.php';
require_once plugin_dir_path(__FILE__) . 'includes/account-deletion-handlers.php';

// Try to load environment variables - first from WordPress root directory
$env_paths = array(
    ABSPATH . '.env',                        // WordPress root
    dirname(ABSPATH) . '/.env',              // One level above WordPress root
    MY_PASSWORDLESS_AUTH_PATH . '.env',      // Plugin directory
    dirname(MY_PASSWORDLESS_AUTH_PATH) . '/.env'  // One level above plugin directory
);

// Try each path until we find a .env file
foreach ($env_paths as $path) {
    if (my_passwordless_auth_load_env($path)) {
        // Log successful loading of .env file if debugging is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Passwordless Auth: Loaded environment variables from $path");
        }
        break;
    }
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-passwordless-auth.php';

/**
 * Load the wp-login.php integration
 */
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/login-form-integration.php';

/**
 * Load the navbar filter functionality
 */
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/navbar-filter.php';

/**
 * Begins execution of the plugin.
 */
function run_my_passwordless_auth() {
    $plugin = new My_Passwordless_Auth();
    $plugin->run();
}

/**
 * Load plugin text domain. This is commented out for now since languages folder is not included.
 */
// function my_passwordless_auth_load_textdomain() {
//     load_plugin_textdomain('my-passwordless-auth', false, dirname(plugin_basename(__FILE__)) . '/languages/');
// }
// add_action('plugins_loaded', 'my_passwordless_auth_load_textdomain');

/**
 * Add action links to the Plugins page
 */
function my_passwordless_auth_plugin_action_links($links) {    $settings_link = '<a href="' . admin_url('options-general.php?page=my-passwordless-auth') . '">Settings</a>';
    $docs_link = '<a href="https://vesess.com" target="_blank">Documentation</a>';
    $support_link = '<a href="https://vesess.com" target="_blank">Support</a>';
    
    // Add links to the beginning of the actions list
    array_unshift($links, $settings_link, $docs_link, $support_link);
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'my_passwordless_auth_plugin_action_links');

// Run the plugin
run_my_passwordless_auth();

/**
 * Core functionality for Passwordless Authentication plugin
 */

/**
 * Initialize the plugin and set up hooks
 */
function my_passwordless_auth_init() {
    // Hook for processing email verification
    add_action('template_redirect', 'my_passwordless_auth_handle_verification');
    
}
add_action('init', 'my_passwordless_auth_init');

/**
 * Helper function to check if we're on the WordPress login page
 */
function my_passwordless_auth_is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}





/**
 * Handle the email verification process - Centralized handler for all verification requests
 */
function my_passwordless_auth_handle_verification() {
    if (isset($_GET['action']) && $_GET['action'] === 'verify_email') {
        my_passwordless_auth_log('Email verification request detected', 'info');
        
        if (!isset($_GET['user_id']) || !isset($_GET['code'])) {
            my_passwordless_auth_log('Missing required verification parameters', 'error', true);
            wp_safe_redirect(home_url('/login/?verification=invalid'));
            exit;
        }
        
        $encrypted_user_id = sanitize_text_field($_GET['user_id']);
        $code = sanitize_text_field($_GET['code']);
        
        // Decrypt the user ID
        $user_id = my_passwordless_auth_decrypt_user_id($encrypted_user_id);
        
        if ($user_id === false) {
            my_passwordless_auth_log("Failed to decrypt user ID: $encrypted_user_id", 'error', true);
            wp_safe_redirect(home_url('/login/?verification=invalid'));
            exit;
        }
        
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            my_passwordless_auth_log("Invalid user ID after decryption: $user_id", 'error');
            wp_safe_redirect(home_url('/login/?verification=invalid_user'));
            exit;
        }
        
        // Get stored verification code
        $stored_code = get_user_meta($user_id, 'email_verification_code', true);
        
        // Verify the code matches
        if (empty($stored_code) || $stored_code !== $code) {
            my_passwordless_auth_log("Invalid verification code for user ID: $user_id", 'error');
            wp_safe_redirect(home_url('/login/?verification=failed'));
            exit;
        }
        
        // Update the user as verified
        update_user_meta($user_id, 'email_verified', true);
        delete_user_meta($user_id, 'email_verification_code');
        
        my_passwordless_auth_log("Email verified successfully for user: {$user->user_email}", 'info', true);
        
        // Force user login after verification if configured to do so
        if (my_passwordless_auth_get_option('auto_login_after_verification', true)) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Redirect to dashboard or custom page
            $redirect_url = my_passwordless_auth_get_option('verification_success_url', admin_url());
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        // Otherwise redirect to login page with success message
        $redirect_url = add_query_arg('verification', 'success', home_url('/login/'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}

/**
 * Add a new admin page to view logs
 */
function my_passwordless_auth_add_admin_page() {
    // Check if the current user has sufficient permissions
    if (!current_user_can('manage_options')) {
        return;
    }
    
    add_submenu_page(
        'options-general.php',       // Parent slug (Settings menu)
        'Passwordless Auth Logs',    // Page title
        'Auth Logs',                 // Menu title
        'manage_options',            // Capability required
        'my-passwordless-auth-logs', // Menu slug
        'my_passwordless_auth_logs_page' // Callback function
    );
}
// Hook into the admin menu with the correct priority
add_action('admin_menu', 'my_passwordless_auth_add_admin_page', 99);

/**
 * Render the logs admin page
 */
function my_passwordless_auth_logs_page() {
    // Check if the required function exists
    if (!function_exists('get_transient')) {
        echo '<div class="error"><p>Error: WordPress core functions not available.</p></div>';
        return;
    }
    
    $logs = get_transient('my_passwordless_auth_logs') ?: [];
    ?>
    <div class="wrap">
        <h1>Passwordless Authentication Logs</h1>
        
        <?php if (empty($logs)): ?>
            <p>No logs found.</p>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Level</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['time']); ?></td>
                            <td><?php echo esc_html(ucfirst($log['level'])); ?></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <form method="post">
                <p>
                    <button name="clear_logs" class="button button-secondary">Clear Logs</button>
                </p>
                <?php wp_nonce_field('clear_auth_logs', 'auth_logs_nonce'); ?>
            </form>
            
            <?php
            // Handle clearing logs
            if (isset($_POST['clear_logs']) && isset($_POST['auth_logs_nonce']) && wp_verify_nonce($_POST['auth_logs_nonce'], 'clear_auth_logs')) {
                delete_transient('my_passwordless_auth_logs');
                echo '<div class="updated"><p>Logs cleared.</p></div>';
                echo '<script>window.location.reload();</script>';
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
}

// Add script to display console logs on the frontend login page
function my_passwordless_auth_add_login_debug() {
    if (isset($_GET['verification'])) {
        $status = sanitize_text_field($_GET['verification']);
        
        echo '<script>
            console.group("Passwordless Auth - Verification Process");
            console.log("Verification status: ' . esc_js($status) . '");
            ' . ($status === 'success' ? 'console.log("Email successfully verified!");' : '') . '
            ' . ($status === 'failed' ? 'console.error("Email verification failed. Invalid or expired code.");' : '') . '
            ' . ($status === 'invalid' ? 'console.error("Invalid verification request.");' : '') . '
            ' . ($status === 'invalid_user' ? 'console.error("User not found.");' : '') . '
            console.groupEnd();
        </script>';
        
        // Add visual feedback
        if ($status === 'success') {
            echo '<div class="my-passwordless-auth-notice my-passwordless-auth-notice-success">
                Email successfully verified! You can now log in.
            </div>';
        } elseif ($status === 'failed' || $status === 'invalid' || $status === 'invalid_user') {
            echo '<div class="my-passwordless-auth-notice my-passwordless-auth-notice-error">
                Email verification failed. Please request a new verification link.
            </div>';
        }
    }
}
add_action('login_footer', 'my_passwordless_auth_add_login_debug');
add_action('wp_footer', 'my_passwordless_auth_add_login_debug');

// Add admin notification for easier log access
function my_passwordless_auth_admin_notices() {
    $screen = get_current_screen();
    if ($screen->id === 'settings_page_my-passwordless-auth-logs') {
        return; // Don't show on the logs page itself
    }
    
    // Check if there are any recent error logs
    $logs = get_transient('my_passwordless_auth_logs') ?: [];
    $error_count = 0;
    
    // Count errors from the last 24 hours
    foreach ($logs as $log) {
        if ($log['level'] === 'error' && strtotime($log['time']) > (time() - 86400)) {
            $error_count++;
        }
    }
    
    if ($error_count > 0) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>                <?php 
                    $message = $error_count === 1 
                        ? 'Passwordless Authentication: There is 1 error log entry in the last 24 hours.'
                        : sprintf('Passwordless Authentication: There are %d error log entries in the last 24 hours.', $error_count); 
                    echo esc_html($message); 
                ?>                <a href="<?php echo esc_url(admin_url('options-general.php?page=my-passwordless-auth-logs')); ?>">
                    <?php echo esc_html('View logs'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'my_passwordless_auth_admin_notices');

// Register activation hook to create initial options
register_activation_hook(__FILE__, 'my_passwordless_auth_activate');
function my_passwordless_auth_activate() {
    // Create default options if they don't exist
    if (!get_option('my_passwordless_auth_options')) {
        add_option('my_passwordless_auth_options', [
            'auto_login_after_verification' => true,
            'verification_success_url' => admin_url(),
        ]);
    }
    
    // Create an initial log entry
    my_passwordless_auth_log('Plugin activated', 'info');
}

// Helper function to get plugin options with defaults
if (!function_exists('my_passwordless_auth_get_option')) {
    function my_passwordless_auth_get_option($key, $default = null) {
        $options = get_option('my_passwordless_auth_options', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }
}

// Helper function for logging (in case it's not in helpers.php)
if (!function_exists('my_passwordless_auth_log')) {
    function my_passwordless_auth_log($message, $level = 'info', $force = false) {
        $logs = get_transient('my_passwordless_auth_logs') ?: [];
        $logs[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'level' => $level,
        ];
        
        // Keep only the last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        set_transient('my_passwordless_auth_logs', $logs, 30 * DAY_IN_SECONDS);
    }
}


// Navigation menu filtering has been moved to includes/navbar-filter.php
