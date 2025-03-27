<?php
/**
 * Plugin Name: My Passwordless Authentication
 * Plugin URI: https://example.com/my-passwordless-auth
 * Description: A WordPress plugin for passwordless authentication.
 * Version: 1.0.0
 * Author: Vesess
 * Author URI: https://example.com
 * Text Domain: my-passwordless-auth
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('MY_PASSWORDLESS_AUTH_VERSION', '1.0.0');
define('MY_PASSWORDLESS_AUTH_PATH', plugin_dir_path(__FILE__));
define('MY_PASSWORDLESS_AUTH_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-passwordless-auth.php';

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

// Run the plugin
run_my_passwordless_auth();

/**
 * Core functionality for Passwordless Authentication plugin
 */

// Include helper functions
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

// Include magic login handler
require_once plugin_dir_path(__FILE__) . 'includes/magic-login-handler.php';

/**
 * Initialize the plugin and set up hooks
 */
function my_passwordless_auth_init() {
    // Hook for processing email verification
    add_action('template_redirect', 'my_passwordless_auth_handle_verification');
    
    // Only enqueue on frontend pages, not the WordPress login page
    if (!my_passwordless_auth_is_login_page()) {
        // Enqueue styles for pages using the shortcode
        add_action('wp_enqueue_scripts', 'my_passwordless_auth_enqueue_login_styles');
        
        // Enqueue scripts for pages using the shortcode
        add_action('wp_enqueue_scripts', 'my_passwordless_auth_enqueue_login_scripts');
    }
}
add_action('init', 'my_passwordless_auth_init');

/**
 * Helper function to check if we're on the WordPress login page
 */
function my_passwordless_auth_is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}

/**
 * Enqueue styles for the login page
 */
function my_passwordless_auth_enqueue_login_styles() {
    wp_enqueue_style(
        'my-passwordless-auth-login', 
        MY_PASSWORDLESS_AUTH_URL . 'assets/css/passwordless-auth.css',
        array(),
        MY_PASSWORDLESS_AUTH_VERSION
    );
}

/**
 * Enqueue scripts for the login page
 */
function my_passwordless_auth_enqueue_login_scripts() {
    // Enqueue jQuery if not already
    wp_enqueue_script('jquery');
    
    // Create js directory if it doesn't exist
    if (!file_exists(MY_PASSWORDLESS_AUTH_PATH . 'assets/js')) {
        wp_mkdir_p(MY_PASSWORDLESS_AUTH_PATH . 'assets/js');
    }
    
    // Create the JavaScript file if it doesn't exist
    $js_file = MY_PASSWORDLESS_AUTH_PATH . 'assets/js/login-handler.js';
    if (!file_exists($js_file)) {
        // Copy from the provided JavaScript content
        $js_content = file_get_contents(MY_PASSWORDLESS_AUTH_PATH . 'assets/js/login-handler.js');
        file_put_contents($js_file, $js_content);
    }
    
    // Enqueue the script
    wp_enqueue_script(
        'my-passwordless-auth-login-handler',
        MY_PASSWORDLESS_AUTH_URL . 'assets/js/login-handler.js',
        array('jquery'),
        MY_PASSWORDLESS_AUTH_VERSION,
        true
    );
    
    // Localize script with needed data
    wp_localize_script(
        'my-passwordless-auth-login-handler',
        'my_passwordless_auth',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my-passwordless-auth-magic-link'),
            'login_url' => wp_login_url()
        )
    );
}

/**
 * Handle the email verification process
 */
function my_passwordless_auth_handle_verification() {
    if (isset($_GET['action']) && $_GET['action'] === 'verify_email') {
        my_passwordless_auth_log('Email verification request detected', 'info');
        
        if (!isset($_GET['user_id']) || !isset($_GET['code'])) {
            my_passwordless_auth_log('Missing required verification parameters', 'error', true);
            wp_redirect(home_url('/login/?verification=invalid'));
            exit;
        }
        
        $encrypted_user_id = sanitize_text_field($_GET['user_id']);
        $code = sanitize_text_field($_GET['code']);
        
        // Decrypt the user ID
        $user_id = my_passwordless_auth_decrypt_user_id($encrypted_user_id);
        
        if ($user_id === false) {
            my_passwordless_auth_log("Failed to decrypt user ID: $encrypted_user_id", 'error', true);
            wp_redirect(home_url('/login/?verification=invalid'));
            exit;
        }
        
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            my_passwordless_auth_log("Invalid user ID after decryption: $user_id", 'error');
            wp_redirect(home_url('/login/?verification=invalid_user'));
            exit;
        }
        
        // Process the verification
        $verified = my_passwordless_auth_process_email_verification();
        
        // Redirect to appropriate page based on verification result
        if ($verified) {
            my_passwordless_auth_log("Email verified successfully for user: {$user->user_email}", 'info', true);
            
            // Force user login after verification if configured to do so
            if (my_passwordless_auth_get_option('auto_login_after_verification', true)) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
                // Redirect to dashboard or custom page
                $redirect_url = my_passwordless_auth_get_option('verification_success_url', admin_url());
                wp_redirect($redirect_url);
                exit;
            }
            
            // Otherwise redirect to login page with success message
            $redirect_url = add_query_arg('verification', 'success', home_url('/login/'));
            wp_redirect($redirect_url);
            exit;
        } else {
            my_passwordless_auth_log("Email verification failed for user ID: $user_id", 'error', true);
            $redirect_url = add_query_arg('verification', 'failed', home_url('/login/'));
            wp_redirect($redirect_url);
            exit;
        }
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
            <p>
                <?php echo sprintf(
                    _n(
                        'Passwordless Authentication: There is %d error log entry in the last 24 hours.',
                        'Passwordless Authentication: There are %d error log entries in the last 24 hours.',
                        $error_count
                    ),
                    $error_count
                ); ?>
                <a href="<?php echo admin_url('options-general.php?page=my-passwordless-auth-logs'); ?>">
                    View logs
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
//planned for removal
// Process email verification function (placeholder - implement this based on your needs)
if (!function_exists('my_passwordless_auth_process_email_verification')) {
    function my_passwordless_auth_process_email_verification() {
        // Implementation would verify the code from $_GET['code'] against stored values
        // Return true if verified, false otherwise
        return true; // Replace with actual verification logic
    }
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

// Make sure assets directory exists
if (!file_exists(MY_PASSWORDLESS_AUTH_PATH . 'assets')) {
    wp_mkdir_p(MY_PASSWORDLESS_AUTH_PATH . 'assets');
}

// Create styles directory if it doesn't exist
if (!file_exists(MY_PASSWORDLESS_AUTH_PATH . 'assets/css')) {
    wp_mkdir_p(MY_PASSWORDLESS_AUTH_PATH . 'assets/css');
}

// Create CSS file if it doesn't exist
$css_file = MY_PASSWORDLESS_AUTH_PATH . 'assets/css/passwordless-auth.css';
if (!file_exists($css_file)) {
    $css_content = "
/* Passwordless Auth Styles */
.passwordless-login-container {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.passwordless-login-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.passwordless-login-form input[type=\"email\"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.passwordless-submit {
    margin-top: 10px;
}

.passwordless-error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 3px;
    border: 1px solid #f5c6cb;
}

.passwordless-success-message {
    background-color: #d4edda;
    color: #155724;
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 3px;
    border: 1px solid #c3e6cb;
}

.passwordless-info {
    margin-top: 15px;
    font-size: 0.9em;
    color: #666;
    text-align: center;
}

/* Button Styles */
.passwordless-login-form .button.button-primary {
    background-color: #0073aa;
    border-color: #0073aa;
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 3px;
    cursor: pointer;
    display: inline-block;
    font-size: 13px;
    line-height: 2.15384615;
    min-height: 30px;
    width: 100%;
}

.passwordless-login-form .button.button-primary:hover {
    background-color: #006799;
    border-color: #006799;
}
";
    file_put_contents($css_file, $css_content);
}

// Create templates directory if it doesn't exist
if (!file_exists(MY_PASSWORDLESS_AUTH_PATH . 'templates')) {
    wp_mkdir_p(MY_PASSWORDLESS_AUTH_PATH . 'templates');
}

/**
 * Filter the navigation menu items based on user login status
 */
function my_passwordless_auth_filter_nav_menu_items($items, $args) {
    // If user is logged in, remove login and registration links
    if (is_user_logged_in()) {
        // Convert items to DOM object for easier manipulation
        $dom = new DOMDocument();
        
        // Use @ to suppress warnings about HTML5 tags
        @$dom->loadHTML(mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8'));
        
        // Find all li elements with links
        $lis = $dom->getElementsByTagName('li');
        
        // Items to remove when user is logged in
        $pages_to_hide = array(
            '/login/',
            '/registration/'
        );
        
        // Loop through li elements in reverse order to safely remove nodes
        for ($i = $lis->length - 1; $i >= 0; $i--) {
            $li = $lis->item($i);
            
            // Find the anchor element within this li
            $anchors = $li->getElementsByTagName('a');
            if ($anchors->length > 0) {
                $href = $anchors->item(0)->getAttribute('href');
                
                // Check if this link should be hidden
                foreach ($pages_to_hide as $page) {
                    if (strpos($href, $page) !== false) {
                        // This is a login or registration link, remove it
                        $li->parentNode->removeChild($li);
                        break;
                    }
                }
            }
        }
        
        // Get the modified HTML (extract just the body part)
        $body = $dom->saveHTML($dom->documentElement);
        $body = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $body);
        
        // Return the filtered HTML
        return $body;
    }
    
    // If user is not logged in, return original items
    return $items;
}

// Add filter for wp_nav_menu_items to conditionally hide login/registration items
add_filter('wp_nav_menu_items', 'my_passwordless_auth_filter_nav_menu_items', 10, 2);

// Alternative approach for block themes
function my_passwordless_auth_filter_navigation_block($block_content, $block) {
    if ($block['blockName'] === 'core/navigation' && is_user_logged_in()) {
        // Convert content to DOM object
        $dom = new DOMDocument();
        
        // Use @ to suppress warnings about HTML5 tags
        @$dom->loadHTML(mb_convert_encoding($block_content, 'HTML-ENTITIES', 'UTF-8'));
        
        // Find all li elements with links
        $lis = $dom->getElementsByTagName('li');
        
        // Pages to hide when logged in
        $pages_to_hide = array(
            '/login/',
            '/registration/'
        );
        
        // Loop through li elements in reverse order to safely remove nodes
        for ($i = $lis->length - 1; $i >= 0; $i--) {
            $li = $lis->item($i);
            
            // Find the anchor element within this li
            $anchors = $li->getElementsByTagName('a');
            if ($anchors->length > 0) {
                $href = $anchors->item(0)->getAttribute('href');
                
                // Check if this link should be hidden
                foreach ($pages_to_hide as $page) {
                    if (strpos($href, $page) !== false) {
                        // This is a login or registration link, remove it
                        $li->parentNode->removeChild($li);
                        break;
                    }
                }
            }
        }
        
        // Get the modified HTML
        $body = $dom->saveHTML($dom->documentElement);
        $body = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $body);
        
        return $body;
    }
    
    return $block_content;
}

// Add filter for render_block to handle block theme navigation
add_filter('render_block', 'my_passwordless_auth_filter_navigation_block', 10, 2);
