<?php
/**
 * Handles user profile functionality.
 */
class My_Passwordless_Auth_Profile {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        add_action('wp_ajax_update_profile', array($this, 'update_profile'));
        add_action('wp_ajax_delete_account', array($this, 'delete_account'));
        add_action('wp_ajax_request_email_verification', array($this, 'request_email_verification'));
    }

    /**
     * Update user profile information.
     */
    public function update_profile() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'profile_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $display_name = sanitize_text_field($_POST['display_name']);
        $new_email = isset($_POST['new_email']) ? sanitize_email($_POST['new_email']) : '';
        $verification_code = isset($_POST['email_verification_code']) ? sanitize_text_field($_POST['email_verification_code']) : '';
        
        // Just update display name if no email change is requested
        if (empty($new_email)) {
            $args = array(
                'ID' => $user_id,
                'display_name' => $display_name,
            );
            
            $update_result = wp_update_user($args);
            
            if (is_wp_error($update_result)) {
                wp_send_json_error($update_result->get_error_message());
            } else {
                wp_send_json_success('Profile updated successfully');
            }
            return;
        }
        
        // For email changes, verify the code
        if (!is_email($new_email)) {
            wp_send_json_error('Invalid email address');
        }

        // Check if the email is already in use by another user
        $existing_user = get_user_by('email', $new_email);
        if ($existing_user && $existing_user->ID != $user_id) {
            wp_send_json_error('Email already in use by another user');
        }

        // Verify the email change code
        $stored_code = get_user_meta($user_id, 'email_change_verification_code', true);
        $pending_email = get_user_meta($user_id, 'pending_email_change', true);
        
        if (empty($stored_code) || empty($verification_code) || $stored_code !== $verification_code || $pending_email !== $new_email) {
            wp_send_json_error('Invalid or expired verification code');
            return;
        }
        
        // Update user email and display name
        $args = array(
            'ID' => $user_id,
            'user_email' => $new_email,
            'display_name' => $display_name,
        );

        $update_result = wp_update_user($args);

        if (is_wp_error($update_result)) {
            wp_send_json_error($update_result->get_error_message());
        } else {
            // Clean up meta data
            delete_user_meta($user_id, 'email_change_verification_code');
            delete_user_meta($user_id, 'pending_email_change');
            
            wp_send_json_success('Profile and email updated successfully');
        }
    }
    
    /**
     * Request email verification code
     */
    public function request_email_verification() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'profile_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $new_email = isset($_POST['new_email']) ? sanitize_email($_POST['new_email']) : '';
        
        if (empty($new_email)) {
            wp_send_json_error('Email address is required');
            return;
        }
        
        if (!is_email($new_email)) {
            wp_send_json_error('Invalid email address');
            return;
        }
        
        // Check if the email is already in use by another user
        $existing_user = get_user_by('email', $new_email);
        if ($existing_user && $existing_user->ID != $user_id) {
            wp_send_json_error('Email already in use by another user');
            return;
        }
        
        // Generate a verification code
        $verification_code = wp_generate_password(8, false);
        
        // Store the code and pending email
        update_user_meta($user_id, 'email_change_verification_code', $verification_code);
        update_user_meta($user_id, 'pending_email_change', $new_email);
        
        // Send verification email to the new address
        $email_class = new My_Passwordless_Auth_Email();
        $email_sent = $email_class->send_email_change_verification($user_id, $new_email, $verification_code);
        
        if ($email_sent) {
            wp_send_json_success('Verification code sent to your new email');
        } else {
            delete_user_meta($user_id, 'email_change_verification_code');
            delete_user_meta($user_id, 'pending_email_change');
            wp_send_json_error('Failed to send verification code');
        }
    }

    /**
     * Delete user account.
     */
    public function delete_account() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'delete_account_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        // Confirm deletion with a verification code
        $confirmation_code = sanitize_text_field($_POST['confirmation_code']);
        $stored_code = get_user_meta($user_id, 'account_deletion_code', true);

        if (empty($stored_code)) {
            // Generate a code and send it if one doesn't exist
            $confirmation_code = wp_generate_password(8, false);
            update_user_meta($user_id, 'account_deletion_code', $confirmation_code);
            
            $email_class = new My_Passwordless_Auth_Email();
            $email_sent = $email_class->send_deletion_confirmation($user_id, $confirmation_code);
            
            if ($email_sent) {
                wp_send_json_success('Confirmation code sent to your email');
            } else {
                wp_send_json_error('Failed to send confirmation code');
            }
        } else if ($stored_code === $confirmation_code) {
            // Process account deletion
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($user_id);
            
            if ($deleted) {
                wp_logout();
                wp_send_json_success('Account deleted successfully');
            } else {
                wp_send_json_error('Failed to delete account');
            }
        } else {
            wp_send_json_error('Invalid confirmation code');
        }
    }
}
