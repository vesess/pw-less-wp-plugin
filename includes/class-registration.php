<?php
/**
 * Handles user registration functionality.
 */
class My_Passwordless_Auth_Registration {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        // Only register the AJAX handler for user registration
        add_action('wp_ajax_nopriv_register_new_user', array($this, 'register_new_user'));
    }

    /**
     * Register a new user.
     */
    public function register_new_user() {
        // Check nonce
        if (!check_ajax_referer('registration_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }

        // Check rate limiting
        $security = new My_Passwordless_Auth_Security();
        $ip_address = My_Passwordless_Auth_Security::get_client_ip();
        $block_time = $security->record_registration_attempt($ip_address);
        
        if ($block_time !== false) {
            $minutes = ceil($block_time / 60);
            wp_send_json_error(sprintf('Too many registration attempts. Please try again in %d minutes.', $minutes));
            return;
        }        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';

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
}
