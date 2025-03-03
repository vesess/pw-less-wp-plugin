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
 * Load plugin text domain.
 */
function my_passwordless_auth_load_textdomain() {
    load_plugin_textdomain('my-passwordless-auth', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'my_passwordless_auth_load_textdomain');

// Run the plugin
run_my_passwordless_auth();
