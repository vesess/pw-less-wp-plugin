<?php
/**
 * Account deletion AJAX handlers
 * 
 * Handles the AJAX requests for account deletion functionality
 * Provides the backend functionality that was previously in the class-profile.php file
 */


if (!defined('WPINC')) {
    die;
}

/**
 * Initialize the account deletion handlers
 */
function my_passwordless_auth_init_account_deletion() {
    add_action('wp_ajax_request_deletion_code', 'my_passwordless_auth_request_deletion_code');
    add_action('wp_ajax_delete_account', 'my_passwordless_auth_delete_account');
}
add_action('init', 'my_passwordless_auth_init_account_deletion');

/**
 * Handle AJAX request for account deletion code
 * Sends a verification code to the user's email
 */
function my_passwordless_auth_request_deletion_code() {

    if (!check_ajax_referer('delete_account_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return;
    }
    

    $current_user = wp_get_current_user();
    if (!($current_user instanceof WP_User)) {
        wp_send_json_error('You must be logged in to delete your account.');
        return;
    }
    
    // Generate a random confirmation code
    $confirmation_code = wp_generate_password(8, false);
    
    // Store code in user meta
    update_user_meta($current_user->ID, 'account_deletion_code', $confirmation_code);
    update_user_meta($current_user->ID, 'account_deletion_code_timestamp', time());
    
    // Send email with confirmation code
    $email_class = new My_Passwordless_Auth_Email();
    $email_sent = $email_class->send_deletion_confirmation($current_user->ID, $confirmation_code);
    
    if ($email_sent) {
        my_passwordless_auth_log("Account deletion code sent to user ID: {$current_user->ID}", 'info');
        wp_send_json_success('Verification code sent to your email address. Please check your inbox.');
    } else {
        my_passwordless_auth_log("Failed to send account deletion code to user ID: {$current_user->ID}", 'error');
        wp_send_json_error('Failed to send verification code. Please try again later.');
    }
}

/**
 * Handle AJAX request to delete a user account
 * Verifies the confirmation code and then deletes the account
 */
function my_passwordless_auth_delete_account() {

    if (!check_ajax_referer('delete_account_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return;
    }

    $confirmation_code = isset($_POST['confirmation_code']) ? sanitize_text_field(wp_unslash($_POST['confirmation_code'])) : '';
    if (empty($confirmation_code)) {
        wp_send_json_error('Please provide the verification code.');
        return;
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    if (!($current_user instanceof WP_User)) {
        wp_send_json_error('You must be logged in to delete your account.');
        return;
    }
    
    // Get stored code and timestamp
    $stored_code = get_user_meta($current_user->ID, 'account_deletion_code', true);
    $code_timestamp = get_user_meta($current_user->ID, 'account_deletion_code_timestamp', true);
    

    if (empty($stored_code)) {
        wp_send_json_error('No verification code found. Please request a new code.');
        return;
    }
    
    // Check if code is expired (30 minutes)
    $expiration_time = $code_timestamp + (30 * 60);
    if (time() > $expiration_time) {
        delete_user_meta($current_user->ID, 'account_deletion_code');
        delete_user_meta($current_user->ID, 'account_deletion_code_timestamp');
        wp_send_json_error('Verification code has expired. Please request a new code.');
        return;
    }
    
    // Check if code matches
    if ($stored_code !== $confirmation_code) {
        wp_send_json_error('Invalid verification code. Please try again.');
        return;
    }
    
    // Everything checks out, delete the account
    $user_id = $current_user->ID;
    $user_email = $current_user->user_email;
    
    // Clear user metadata first
    delete_user_meta($user_id, 'account_deletion_code');
    delete_user_meta($user_id, 'account_deletion_code_timestamp');
    
    // Log the deletion for reference
    my_passwordless_auth_log("User account deleted: ID {$user_id}, email {$user_email}", 'info');
    
    // Load WordPress user administration functions if not already available
    // This is the WordPress-compliant way per WordPress Plugin Guidelines
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    
    $deleted = wp_delete_user($user_id);
    
    if ($deleted) {
        // Clear cookies and session
        wp_clear_auth_cookie();
        wp_destroy_current_session();
        
        wp_send_json_success('Your account has been deleted. You will be redirected to the home page.');
    } else {
        wp_send_json_error('Failed to delete your account. Please contact site administrator.');
    }
}
