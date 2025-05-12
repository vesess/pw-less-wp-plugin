<?php
/**
 * This class previously handled user profile functionality.
 * It has been completely refactored with all functionality moved to the WordPress admin profile.
 * Keeping the class for compatibility with other plugin components.
 */
class My_Passwordless_Auth_Profile {    /**
     * Initialize the class.
     * Most profile functionality has been removed, but account deletion handlers remain.
     */
    public function init() {
        // Profile page UI functionality has been completely removed
        // But we need to keep the AJAX handlers for backwards compatibility
        add_action('wp_ajax_delete_account', array($this, 'delete_account'));
        add_action('wp_ajax_request_deletion_code', array($this, 'request_deletion_code'));
    }/**
     * This method was previously used to update user profile information.
     * Profile functionality has been removed from the plugin.
     * Account management is now handled through the WordPress admin profile.
     */
    public function update_profile() {
        // Functionality removed
        wp_send_json_error('This feature has been removed. Please use the WordPress profile page.');
    }
    
    /**
     * This method was previously used to request email verification code.
     * Profile functionality has been removed from the plugin.
     * Email changes are now handled through the WordPress admin profile.
     */
    public function request_email_verification() {
        // Functionality removed
        wp_send_json_error('This feature has been removed. Please use the WordPress profile page.');
    }    /**
     * This method was previously used to delete a user account.
     * Account deletion is now handled through account-deletion-handlers.php
     * This method remains for backwards compatibility.
     * 
     * @see account-deletion-handlers.php
     */
    public function delete_account() {
        // Just pass the request to the new handler
        my_passwordless_auth_delete_account();
    }

    /**
     * This method was previously used to request account deletion code.
     * Account deletion is now handled through account-deletion-handlers.php
     * This method remains for backwards compatibility.
     * 
     * @see account-deletion-handlers.php
     */
    public function request_deletion_code() {
        // Just pass the request to the new handler
        my_passwordless_auth_request_deletion_code();
    }
}
