<?php
/**
 * Handles user registration functionality.
 */
class My_Passwordless_Auth_Registration {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        add_action('wp_ajax_nopriv_register_new_user', array($this, 'register_new_user'));
        add_action('wp_ajax_nopriv_verify_email', array($this, 'verify_email'));
    }

    /**
     * Register a new user.
     */
    public function register_new_user() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'registration_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $email = sanitize_email($_POST['email']);
        $username = sanitize_user($_POST['username']);
        $display_name = sanitize_text_field($_POST['display_name']);

        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }

        if (email_exists($email)) {
            wp_send_json_error('Email already registered');
        }

        if (username_exists($username)) {
            wp_send_json_error('Username already exists');
        }

        // Create the user
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => $display_name,
            'user_pass' => wp_generate_password(), // Random password as it won't be used
            'user_status' => 0,
            'role' => 'subscriber'
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Mark the user as unverified
        update_user_meta($user_id, 'email_verified', false);

        // Generate verification code
        $verification_code = wp_generate_password(32, false);
        update_user_meta($user_id, 'email_verification_code', $verification_code);

        // Send verification email
        $email_class = new My_Passwordless_Auth_Email();
        $email_sent = $email_class->send_verification_email($user_id, $verification_code);

        if ($email_sent) {
            wp_send_json_success('Registration successful. Please check your email to verify your account.');
        } else {
            wp_send_json_error('Registration successful but failed to send verification email.');
        }
    }

    /**
     * Verify a user's email address.
     */
    public function verify_email() {
        $encrypted_user_id = sanitize_text_field($_GET['user_id']);
        $code = sanitize_text_field($_GET['code']);

        // Decrypt the user ID
        $user_id = my_passwordless_auth_decrypt_user_id($encrypted_user_id);
        
        if ($user_id === false) {
            wp_die('Invalid verification link');
        }

        $stored_code = get_user_meta($user_id, 'email_verification_code', true);

        if (empty($stored_code) || $stored_code !== $code) {
            wp_die('Invalid verification link');
        }

        // Update the user as verified
        update_user_meta($user_id, 'email_verified', true);
        delete_user_meta($user_id, 'email_verification_code');

        // Redirect to login page with success message
        wp_redirect(add_query_arg('verified', 'success', wp_login_url()));
        exit;
    }
}
