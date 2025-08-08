<?php
/**
 * Plugin Name: VesessAuth
 * Description: A WordPress plugin for passwordless authentication.
 * Version: 1.0.0
 * Author: Vesess
 * Author URI: https://vesess.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('WPINC')) {
    die;
}

define('VESESS_AUTH_VERSION', '1.0.0');
define('VESESS_AUTH_PATH', plugin_dir_path(__FILE__));
define('VESESS_AUTH_URL', plugin_dir_url(__FILE__));

// Include helper functions
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

// Include secure crypto class
require_once plugin_dir_path(__FILE__) . 'includes/class-crypto.php';


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once VESESS_AUTH_PATH . 'includes/class-passwordless-auth.php';

/**
 * Load the wp-login.php integration
 */
require_once VESESS_AUTH_PATH . 'includes/login-form-integration.php';

/**
 * Load the navbar filter functionality
 */
require_once VESESS_AUTH_PATH . 'includes/navbar-filter.php';

/**
 * Begins execution of the plugin.
 */
function vesess_auth_run() {
    $plugin = new VESESS_AUTH();
    $plugin->run();
}

/**
 * Load plugin text domain. This is commented out for now since languages folder is not included.
 */
// function vesess_auth_load_textdomain() {
//     load_plugin_textdomain('vesess_auth', false, dirname(plugin_basename(__FILE__)) . '/languages/');
// }
// add_action('plugins_loaded', 'vesess_auth_load_textdomain');

/**
 * Add action links to the Plugins page
 */
function vesess_auth_plugin_action_links($links) {    $settings_link = '<a href="' . admin_url('options-general.php?page=vesess_auth') . '">Settings</a>';
    $docs_link = '<a href="https://vesess.com" target="_blank">Documentation</a>';
    $support_link = '<a href="https://vesess.com" target="_blank">Support</a>';
    
    // Add links to the beginning of the actions list
    array_unshift($links, $settings_link, $docs_link, $support_link);
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'vesess_auth_plugin_action_links');

// Run the plugin
vesess_auth_run();

/**
 * Core functionality for Passwordless Authentication plugin
 */

/**
 * Initialize the plugin and set up hooks
 */
function vesess_auth_init() {
    // Hook for processing email verification
    add_action('template_redirect', 'vesess_auth_handle_verification');
    
}
add_action('init', 'vesess_auth_init');

/**
 * Helper function to check if we're on the WordPress login page
 */
function vesess_auth_is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}





/**
 * Handle the email verification process - Centralized handler for all verification requests
 */
function vesess_auth_handle_verification() {
    if (isset($_GET['action'])) {
        $action = sanitize_text_field(wp_unslash($_GET['action']));
        
        // Only handle our specific verify_email action
        if ($action === 'verify_email') {
            vesess_auth_log('Email verification request detected', 'info');
            
            // Verify nonce for security - only for our verify_email action
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'verify_email_nonce')) {
                vesess_auth_log("Email verification failed - invalid nonce", 'error');
                wp_safe_redirect(add_query_arg(
                    array(
                        'verification' => 'invalid',
                        '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                    ),
                    home_url('/login/')
                ));
                exit;
            }
            
            if (!isset($_GET['user_id']) || !isset($_GET['code'])) {
                vesess_auth_log('Missing required verification parameters', 'error', true);
                wp_safe_redirect(add_query_arg(
                    array(
                        'verification' => 'invalid',
                        '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                    ),
                    home_url('/login/')
                ));
                exit;
            }
          $encrypted_user_id = sanitize_text_field(wp_unslash($_GET['user_id']));
        $code = sanitize_text_field(wp_unslash($_GET['code']));
          // Add debug output for encrypted user ID
        vesess_auth_log("Processing encrypted user ID: $encrypted_user_id", 'info');
        
        // Decrypt the user ID - handle potential URL encoding issues
        $user_id = vesess_auth_decrypt_user_id($encrypted_user_id);
          
        if ($user_id === false) {
            // Try with common URL encoding replacements
            $modified_id = str_replace(' ', '+', $encrypted_user_id);
            vesess_auth_log("Retrying with modified user ID: $modified_id", 'info');
            $user_id = vesess_auth_decrypt_user_id($modified_id);
            
            if ($user_id === false) {
                vesess_auth_log("Failed to decrypt user ID: $encrypted_user_id", 'error', true);
                wp_safe_redirect(add_query_arg(
                    array(
                        'verification' => 'invalid',
                        'debug' => 'decrypt_failed',
                        '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                    ),
                    home_url('/login/')
                ));
                exit;
            }
        }
        
        vesess_auth_log("Successfully decrypted user ID: $user_id", 'info');
        
        $user = get_user_by('id', $user_id);
          if (!$user) {
            vesess_auth_log("Invalid user ID after decryption: $user_id", 'error');
            wp_safe_redirect(add_query_arg(
                array(
                    'verification' => 'invalid_user',
                    '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                ),
                home_url('/login/')
            ));
            exit;
        }          // Get stored verification code        
        $stored_code = get_user_meta($user_id, 'email_verification_code', true);
          
        // Add extensive debug logging to diagnose the issue
        vesess_auth_log("==========================================", 'info');
        vesess_auth_log("VERIFICATION ATTEMPT DETAILS:", 'info');        
        vesess_auth_log("User ID: $user_id", 'info');
        vesess_auth_log("Username: {$user->user_login}", 'info');
        vesess_auth_log("Email: {$user->user_email}", 'info');
        vesess_auth_log("Received verification code: '$code'", 'info');
        vesess_auth_log("Stored verification code: '$stored_code'", 'info');
        vesess_auth_log("Code length - Received: " . strlen($code) . ", Stored: " . strlen($stored_code), 'info');
        
        // Create a tool to manually fix verification issues
        vesess_auth_log("VERIFICATION DIAGNOSIS TOOL:", 'info');
        vesess_auth_log("1. If verification fails, try these SQL commands to verify the code in the database:", 'info');
        vesess_auth_log("   SELECT * FROM wp_usermeta WHERE user_id = $user_id AND meta_key = 'email_verification_code';", 'info');
        vesess_auth_log("2. To manually override verification for this user:", 'info');
        vesess_auth_log("   UPDATE wp_usermeta SET meta_value = '1' WHERE user_id = $user_id AND meta_key = 'email_verified';", 'info');
        vesess_auth_log("   DELETE FROM wp_usermeta WHERE user_id = $user_id AND meta_key = 'email_verification_code';", 'info');
        
        // Create debug strings showing exact code representations
        $hex_received = bin2hex($code);
        $hex_stored = bin2hex($stored_code);
        vesess_auth_log("Received code (hex): $hex_received", 'info');
        vesess_auth_log("Stored code (hex): $hex_stored", 'info');
        
        // Check for encoding issues
        $urlencoded_received = urlencode($code);
        if ($urlencoded_received !== $code) {
            vesess_auth_log("Received code contains characters that need URL encoding: $urlencoded_received", 'info');
        }
        vesess_auth_log("==========================================", 'info');
        
        // Enhanced code comparison
        // Make sure we're comparing trimmed values to prevent whitespace issues
        $trimmed_code = trim($code);
        $trimmed_stored_code = trim($stored_code);
        
        // Check URL encoding/decoding issues with verification code
        $url_decoded_code = urldecode($trimmed_code);
        vesess_auth_log("URL-decoded verification code: $url_decoded_code", 'info');
          // More intensive code comparison
        $code_match = false;
        
        // Add character-by-character comparison for debugging
        if (!empty($trimmed_stored_code) && !empty($trimmed_code)) {
            vesess_auth_log("Character comparison:", 'info');
            $max_length = max(strlen($trimmed_stored_code), strlen($trimmed_code));
            for ($i = 0; $i < $max_length; $i++) {
                $stored_char = isset($trimmed_stored_code[$i]) ? $trimmed_stored_code[$i] : '[none]';
                $received_char = isset($trimmed_code[$i]) ? $trimmed_code[$i] : '[none]';
                $match = ($stored_char === $received_char) ? 'MATCH' : 'DIFFER';
                vesess_auth_log("Pos $i: Stored='$stored_char', Received='$received_char' - $match", 'info');
            }
        }
        
        // Try various matching approaches
        // Try direct match first
        if (!empty($trimmed_stored_code) && $trimmed_stored_code === $trimmed_code) {
            vesess_auth_log("Verification code matches exactly", 'info');
            $code_match = true;
        }
        // Try case-insensitive match
        elseif (!empty($trimmed_stored_code) && strcasecmp($trimmed_stored_code, $trimmed_code) === 0) {
            vesess_auth_log("Verification code matches with case-insensitive comparison", 'info');
            $code_match = true;
        }
        // Try URL-decoded match
        elseif (!empty($trimmed_stored_code) && $trimmed_stored_code === $url_decoded_code) {
            vesess_auth_log("Verification code matches after URL decoding", 'info');
            $code_match = true;
        }
        // Try with stripped whitespace in both
        elseif (!empty($trimmed_stored_code) && str_replace(' ', '', $trimmed_stored_code) === str_replace(' ', '', $trimmed_code)) {
            vesess_auth_log("Verification code matches after stripping all whitespace", 'info');
            $code_match = true;
        }if (!$code_match) {
            vesess_auth_log("Invalid verification code for user ID: $user_id", 'error');
            vesess_auth_log("Received (trimmed): '$trimmed_code', Stored (trimmed): '$trimmed_stored_code'", 'error');
            // Redirect with error
            wp_safe_redirect(add_query_arg(
                array(
                    'verification' => 'failed',
                    'debug' => 'code_mismatch',
                    '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                ),
                home_url('/login/')
            ));
            exit;        
        } else {
            // Code matches, update user as verified
            vesess_auth_log("Verification code matches for user ID: $user_id - proceeding with verification", 'info');
            
            // Update the user as verified
            update_user_meta($user_id, 'email_verified', true);
            delete_user_meta($user_id, 'email_verification_code');
            
            vesess_auth_log("Email verified successfully for user ID: $user_id", 'info', true);
            
            // Force user login after verification if configured to do so
            if (vesess_auth_get_option('auto_login_after_verification', true)) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
                // Get redirect URL from options or use admin dashboard
                $redirect_url = vesess_auth_get_option('verification_success_url', admin_url());
                
                // Redirect to dashboard or custom page
                wp_safe_redirect($redirect_url);
                exit;
            }
            
            // Otherwise redirect to login page with success message
            $redirect_url = add_query_arg(
                array(
                    'verification' => 'success',
                    '_wpnonce' => wp_create_nonce('verification_debug_nonce')
                ),
                home_url('/login/')
            );
              wp_safe_redirect($redirect_url);           
              exit;
            }
        }
    }
}

/**
 * Add a new admin page to view logs
 */
function vesess_auth_add_admin_page() {
    // Check if the current user has sufficient permissions
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get plugin options to check if auth logs menu should be shown
    $options = get_option('vesess_auth_options', array());
    $show_auth_logs = isset($options['show_auth_logs_menu']) && $options['show_auth_logs_menu'] === 'yes';
    
    // Only add the menu if the setting is enabled
    if (!$show_auth_logs) {
        return;
    }
    
    add_submenu_page(
        'options-general.php',       // Parent slug (Settings menu)
        'Passwordless Auth Logs',    // Page title
        'Auth Logs',                 // Menu title
        'manage_options',            // Capability required
        'vesess_auth-auth-logs', // Menu slug
        'vesess_auth_logs_page' // Callback function
    );
}
// Hook into the admin menu with the correct priority
add_action('admin_menu', 'vesess_auth_add_admin_page', 99);

/**
 * Render the logs admin page
 */
function vesess_auth_logs_page() {
    // Check if the required function exists
    if (!function_exists('get_transient')) {
        echo '<div class="error"><p>Error: WordPress core functions not available.</p></div>';
        return;
    }
    
    $logs = get_transient('vesess_auth_logs') ?: [];
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
            
            <?php            // Handle clearing logs
            if (isset($_POST['clear_logs']) && isset($_POST['auth_logs_nonce'])) {
                $nonce = sanitize_text_field(wp_unslash($_POST['auth_logs_nonce']));
                
                if (wp_verify_nonce($nonce, 'clear_auth_logs')) {
                    delete_transient('vesess_auth_logs');
                    echo '<div class="updated"><p>Logs cleared.</p></div>';
                    // Use wp_add_inline_script instead of direct echo
                    add_action('admin_footer', function() {
                        wp_enqueue_script('jquery');
                        wp_add_inline_script('jquery', 'window.location.reload();');
                    });
                }
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
}

// Add script to display console logs on the frontend login page
function vesess_auth_add_login_debug() {
    if (isset($_GET['verification'])) {
        $status = sanitize_text_field(wp_unslash($_GET['verification']));
          // Check for nonce - only display debug info if nonce is valid
        // Maintain backward compatibility by allowing admin users to see debug info without nonce
        $nonce_valid = false;
        if (isset($_GET['_wpnonce'])) {
            $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'verification_debug_nonce');
        }
        
        // Only show debug info if:
        // 1. Nonce is valid, OR
        // 2. User is an admin (for backward compatibility with existing links)
        if ($nonce_valid || current_user_can('manage_options')) {
            // Enqueue debug script
            wp_enqueue_script(
                'vesess_auth-console-debug',
                VESESS_AUTH_URL . 'public/js/console-debug.js',
                array(),
                VESESS_AUTH_VERSION,
                true
            );
            
            // Localize the debug data
            wp_localize_script(
                'vesess_auth-console-debug',
                'vesessauth',
                array(
                    'status' => $status
                )
            );
        }
        
        // Add visual feedback
        if ($status === 'success') {
            echo '<div class="vesess_auth-auth-notice vesess_auth-notice-success">
                Email successfully verified! You can now log in.
            </div>';
        } elseif ($status === 'failed' || $status === 'invalid' || $status === 'invalid_user') {
            echo '<div class="vesess_auth-auth-notice vesess_auth-notice-error">
                Email verification failed. Please request a new verification link.
            </div>';
        }
    }
}
add_action('login_footer', 'vesess_auth_add_login_debug');
add_action('wp_footer', 'vesess_auth_add_login_debug');

// Add admin notification for easier log access
function Vesess_Auth_Admin_notices() {
    $screen = get_current_screen();
    if ($screen->id === 'settings_page_vesess_auth-logs') {
        return; // Don't show on the logs page itself
    }
    
    // Check if there are any recent error logs
    $logs = get_transient('vesess_auth_logs') ?: [];
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
                ?>                <a href="<?php echo esc_url(admin_url('options-general.php?page=vesess_auth-auth-logs')); ?>">
                    <?php echo esc_html('View logs'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'Vesess_Auth_Admin_notices');

// Register activation hook to create initial options
register_activation_hook(__FILE__, 'vesess_auth_activate');
function vesess_auth_activate() {
    // Create default options if they don't exist
    if (!get_option('vesess_auth_options')) {
        add_option('vesess_auth_options', [
            'auto_login_after_verification' => true,
            'verification_success_url' => admin_url(),
        ]);
    }
    
    // Create an initial log entry
    vesess_auth_log('Plugin activated', 'info');
}

// Helper function to get plugin options with defaults
if (!function_exists('vesess_auth_get_option')) {
    function vesess_auth_get_option($key, $default = null) {
        $options = get_option('vesess_auth_options', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }
}
