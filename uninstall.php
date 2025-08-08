<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('vesess_auth_options');

// Remove plugin user meta
$users = get_users();

foreach ($users as $user) {
    delete_user_meta($user->ID, 'vesess_auth_login_code');
    delete_user_meta($user->ID, 'vesess_auth_login_code_timestamp');
    delete_user_meta($user->ID, 'email_verified');
    delete_user_meta($user->ID, 'email_verification_code');
}

// Clear any scheduled hooks
$timestamp = wp_next_scheduled('vesess_auth_cleanup_codes');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'vesess_auth_cleanup_codes');
}
