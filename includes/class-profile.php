<?php
/**
 * This class previously handled user profile functionality.
 * It has been completely refactored with all functionality moved to the WordPress admin profile.
 * Keeping the class for compatibility with other plugin components.
 */
class Vesess_Easyauth_Profile {    /**
     * Initialize the class.
     * Profile functionality has been completely removed.
     */
    public function init() {
        // All profile functionality has been removed
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
    }
}
