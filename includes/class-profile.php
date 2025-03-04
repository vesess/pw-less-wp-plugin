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
        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }

        // Check if the email is already in use by another user
        $existing_user = get_user_by('email', $email);
        if ($existing_user && $existing_user->ID != $user_id) {
            wp_send_json_error('Email already in use by another user');
        }

        $args = array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $email,
        );

        $update_result = wp_update_user($args);

        if (is_wp_error($update_result)) {
            wp_send_json_error($update_result->get_error_message());
        } else {
            wp_send_json_success('Profile updated successfully');
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
