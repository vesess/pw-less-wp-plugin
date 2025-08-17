<?php
/**
 * Handles user registration functionality.
 */
class Vesesslabs_Vesessauth_Registration {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        // Only register the AJAX handler for user registration
        add_action('wp_ajax_nopriv_vesesslabs_vesessauth_register_new_user', array($this, 'vesesslabs_vesessauth_register_new_user'));
    }

    /**
     * Register a new user.
     */
    public function vesesslabs_vesessauth_register_new_user() {
        // Check nonce FIRST - verify both the legacy nonce and the new registration-specific nonce
        // This check happens immediately before processing ANY data
        $is_valid_nonce = false;
        
        // Check legacy nonce field using wp_verify_nonce for consistent security
        if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'registration_nonce')) {
            $is_valid_nonce = true;
        }
        
        // Check registration-specific nonce (added in security enhancement)
        if (isset($_POST['registration_nonce']) && 
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['registration_nonce'])), 'passwordless-registration-nonce')) {
            $is_valid_nonce = true;
        }
        
        if (!$is_valid_nonce) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check user capabilities for registration
        if (!current_user_can('read')) {
            // For non-logged in users, we allow registration if WordPress allows it
            if (!get_option('users_can_register')) {
                wp_send_json_error('Registration is currently disabled.');
                return;
            }
        }

        // Check rate limiting
        $security = new Vesesslabs_Vesessauth_Security();
        $ip_address = Vesesslabs_Vesessauth_Security::get_client_ip();
        $block_time = $security->record_registration_attempt($ip_address);
        
        if ($block_time !== false) {
            $minutes = ceil($block_time / 60);
            wp_send_json_error(sprintf('Too many registration attempts. Please try again in %d minutes.', $minutes));
            return;
        }

        // Now process POST data with explicit nonce validation for each field
        // Re-verify nonce before accessing each POST variable for maximum security
        $email = '';
        $username = '';
        $display_name = '';
        
        // Validate and process email with nonce check
        if (isset($_POST['email']) && 
            ((isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'registration_nonce')) ||
             (isset($_POST['registration_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['registration_nonce'])), 'passwordless-registration-nonce')))) {
            $email = sanitize_email(wp_unslash($_POST['email']));
        }
        
        // Validate and process username with nonce check
        if (isset($_POST['username']) && 
            ((isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'registration_nonce')) ||
             (isset($_POST['registration_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['registration_nonce'])), 'passwordless-registration-nonce')))) {
            $username = sanitize_user(wp_unslash($_POST['username']));
        }
        
        // Validate and process display name with nonce check
        if (isset($_POST['display_name']) && 
            ((isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'registration_nonce')) ||
             (isset($_POST['registration_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['registration_nonce'])), 'passwordless-registration-nonce')))) {
            $display_name = sanitize_text_field(wp_unslash($_POST['display_name']));
        }

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
        update_user_meta($user_id, 'email_verified', false);        // Generate a simple verification code that's URL-safe and easy to transmit
        // Use only letters and numbers to avoid URL encoding issues (no special chars)
        $verification_code = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 20);
        
        // Ensure verification code is URL-safe
        $verification_code = preg_replace('/[^a-zA-Z0-9]/', '', $verification_code);
        
        // Log the generated code for debugging
        vesesslabs_vesessauth_log("Generated verification code for new user (ID: $user_id): $verification_code");
        
        // Make sure the code is stored exactly as it is
        vesesslabs_vesessauth_log("Storing verification code in user_meta for user ID $user_id: $verification_code", 'info');
        vesesslabs_vesessauth_log("Verification code length: " . strlen($verification_code), 'info');
        vesesslabs_vesessauth_log("Verification code hex: " . bin2hex($verification_code), 'info');
        
        // Store the verification code - make sure it's stored exactly as generated
        update_user_meta($user_id, 'email_verification_code', trim($verification_code));

        // Send verification email
        $email_class = new Vesesslabs_Vesessauth_Email();
        $email_sent = $email_class->send_verification_email($user_id, $verification_code);

        if ($email_sent) {
            wp_send_json_success('Registration successful. Please check your email to verify your account.');
        } else {
            wp_send_json_error('Registration successful but failed to send verification email.');
        }
    }
}
