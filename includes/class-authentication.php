<?php
/**
 * Handles passwordless authentication functionality. This code is most likely not used ignore this.
 * This work is done by helper functions
 */
class My_Passwordless_Auth_Authentication {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        add_action('wp_ajax_nopriv_send_login_code', array($this, 'send_login_code'));
        add_action('wp_ajax_nopriv_verify_login_code', array($this, 'verify_login_code'));
        
        // Also handle login for logged-in users who may want to get a code for another device
        add_action('wp_ajax_send_login_code', array($this, 'send_login_code'));
        
        // Add a custom action for plugins to hook into after successful login
        add_action('my_passwordless_auth_after_login', array($this, 'after_login'), 10, 2);
    }    
    /**
     * Send a login code to the user's email.
     */    
    public function send_login_code() {
        // Check nonces - accept either the standard nonce field or the passwordless-specific nonce
        $is_valid_nonce = false;
        
        // Check the standard nonce field first
        if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'passwordless_login_nonce')) {
            $is_valid_nonce = true;
        }
        
        // Also check for the login-specific nonce (from JS)
        if (isset($_POST['passwordless_login_nonce']) && 
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['passwordless_login_nonce'])), 'passwordless-login-nonce')) {
            $is_valid_nonce = true;
        }
        
        if (!$is_valid_nonce) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }
        
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        // Validate the email format
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
            return;
        }

        // Check rate limiting for this IP address
        $security = new My_Passwordless_Auth_Security();
        $ip_address = My_Passwordless_Auth_Security::get_client_ip();
        $block_time = $security->record_login_request($ip_address, $email);
        
        if ($block_time !== false) {
            $minutes = ceil($block_time / 60);
            wp_send_json_error(sprintf('Too many login attempts. Please try again in %d minutes.', $minutes));
            return;
        }
        
        $user = get_user_by('email', $email);
        if (!$user) {
            // Don't reveal whether a user exists or not for security
            wp_send_json_error('If this email is registered, a login code will be sent.');
            return;
        }

        // Check if email is verified (if verification is required)
        if (!my_passwordless_auth_is_email_verified($user->ID) && my_passwordless_auth_get_option('require_email_verification', 'yes') === 'yes') {
            wp_send_json_error('Please verify your email address before logging in. Check your inbox for verification instructions.');
            return;
        }

        // Generate a login code
        $code_length = apply_filters('my_passwordless_auth_code_length', 6);
        $login_code = wp_generate_password($code_length, false, false);
        
        // Store the code with an expiration time from settings
        $expiration_minutes = (int) my_passwordless_auth_get_option('code_expiration', 15);
        $expiration_time = time() + ($expiration_minutes * 60);
        
        update_user_meta($user->ID, 'passwordless_login_code', $login_code);
        update_user_meta($user->ID, 'passwordless_login_code_timestamp', time());
        update_user_meta($user->ID, 'passwordless_login_code_expiration', $expiration_time);

        // Send the code via email
        $email_class = new My_Passwordless_Auth_Email();
        $email_sent = $email_class->send_login_code($user->ID, $login_code);

        // Log the attempt
        my_passwordless_auth_log("Login code requested for user ID {$user->ID}", 'info');

        if ($email_sent) {
            wp_send_json_success('Login code sent to your email');
        } else {
            wp_send_json_error('Failed to send login code. Please try again later.');
        }
    }

    /**
     * Verify a login code and authenticate the user.
     */    
    public function verify_login_code() {
        // Check nonces - accept either standard nonce or passwordless-specific nonce
        $is_valid_nonce = false;
        
        // Check the standard nonce field first
        if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'passwordless_login_nonce')) {
            $is_valid_nonce = true;
        }
        
        // Also check for the login-specific nonce (from JS)
        if (isset($_POST['passwordless_login_nonce']) && 
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['passwordless_login_nonce'])), 'passwordless-login-nonce')) {
            $is_valid_nonce = true;
        }
        
        if (!$is_valid_nonce) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }
        
        // Also verify the redirect nonce if it's provided
        if (isset($_POST['redirect_to']) && isset($_POST['redirect_nonce'])) {
            $redirect_nonce = sanitize_text_field(wp_unslash($_POST['redirect_nonce']));
            if (!wp_verify_nonce($redirect_nonce, 'passwordless_redirect')) {
                wp_send_json_error('Invalid redirect request.');
                return;
            }
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error('Invalid email or code');
            return;
        }

        $stored_code = get_user_meta($user->ID, 'passwordless_login_code', true);
        $code_timestamp = get_user_meta($user->ID, 'passwordless_login_code_timestamp', true);
        $expiration_time = get_user_meta($user->ID, 'passwordless_login_code_expiration', true);

        // Check code validity
        if (empty($stored_code) || $stored_code !== $code) {
            my_passwordless_auth_log("Invalid login code attempt for user ID {$user->ID}", 'warning');
            wp_send_json_error('Invalid login code. Please try again.');
            return;
        }

        // Check if the code is expired
        if (time() > $expiration_time) {
            delete_user_meta($user->ID, 'passwordless_login_code');
            delete_user_meta($user->ID, 'passwordless_login_code_timestamp');
            delete_user_meta($user->ID, 'passwordless_login_code_expiration');
            
            my_passwordless_auth_log("Expired login code attempt for user ID {$user->ID}", 'warning');
            wp_send_json_error('Login code has expired. Please request a new code.');
            return;
        }

        // Log in the user
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        // Clean up
        delete_user_meta($user->ID, 'passwordless_login_code');
        delete_user_meta($user->ID, 'passwordless_login_code_timestamp');
        delete_user_meta($user->ID, 'passwordless_login_code_expiration');

        // Track login time
        update_user_meta($user->ID, 'last_login_time', time());
        
        // Log the successful login        
        my_passwordless_auth_log("User ID {$user->ID} successfully logged in", 'info');
          
        // Action for other plugins to hook into
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        do_action('my_passwordless_auth_after_login', $user->ID, $ip_address);

        // Get redirect URL from request with security validation
        $redirect_to = home_url();        if (isset($_POST['redirect_to'])) {
            // Properly sanitize input for security while preserving URL structure
            // Use esc_url_raw since we're dealing with a URL
            $raw_redirect = esc_url_raw(wp_unslash($_POST['redirect_to']));
            $is_valid_redirect = false;
            
            // If a nonce is provided with the redirect, verify it
            if (isset($_POST['redirect_nonce']) && 
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['redirect_nonce'])), 'passwordless_redirect')) {
                $is_valid_redirect = true;
            } 
            // If no nonce is provided, only allow internal URLs for backward compatibility
            else if (strpos($raw_redirect, 'http') !== 0 || strpos($raw_redirect, home_url()) === 0) {
                // If it's a relative URL or starts with home_url, consider it safe
                $is_valid_redirect = true;
            }
              if ($is_valid_redirect) {
                // We already sanitized the URL with esc_url_raw earlier, so just assign it
                $redirect_to = $raw_redirect;
            } else {
                // Log suspicious redirect attempt (already sanitized)
                my_passwordless_auth_log("Suspicious redirect attempt for user ID {$user->ID}: " . $raw_redirect, 'warning');
            }
        }
        
        // Allow filtering of the redirect URL
        $redirect_to = apply_filters('my_passwordless_auth_login_redirect', $redirect_to, $user->ID);

        wp_send_json_success(array(
            'redirect_url' => $redirect_to,
            'message' => 'Login successful!'
        ));
    }
    
    /**
     * Run actions after successful login.
     * 
     * @param int $user_id The user ID.
     * @param string $ip_address The IP address of the login.
     */
    public function after_login($user_id, $ip_address) {
        // This could be extended with additional functionality in the future
        $device_type = $this->get_device_type();
        update_user_meta($user_id, 'last_login_device', $device_type);
        update_user_meta($user_id, 'last_login_ip', $ip_address);
    }
    
    /**
     * Get the device type based on user agent.
     * 
     * @return string The device type.
     */    
    private function get_device_type() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',
         $user_agent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
          substr($user_agent, 0, 4))) {
            return 'mobile';
        }
        
        if (preg_match('/tablet|ipad|playbook|silk|android(?!.*mobile)/i', $user_agent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }
}
