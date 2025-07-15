<?php
/**
 * Handles email functionality.
 */
class Vesess_Easyauth_Email {
    /**
     * Send a login code via email.
     *
     * @param int $user_id The user ID.
     * @param string $login_code The login code to send.
     * @return bool Whether the email was sent successfully.
     */
    public function send_login_code($user_id, $login_code) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Get configured expiration time
        $expiration_minutes = (int) vesess_easyauth_get_option('code_expiration', 15);        $to = $user->user_email;
        $subject = '[' . esc_html(get_bloginfo('name')) . '] Your Login Code';
          $message = sprintf(
            "Hello %s,\n\nYour login code is: %s\n\nThis code will expire in %d minutes.\n\nBest regards,\n%s",
            esc_html($user->display_name),
            esc_html($login_code),
            $expiration_minutes,
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
        );

        return $this->send_email($to, $subject, $message, $headers, 'login_code');
    }

    /**
     * Send a verification email to a newly registered user.
     *
     * @param int $user_id The user ID.
     * @param string $verification_code The verification code.
     * @return bool Whether the email was sent successfully.
     */    public function send_verification_email($user_id, $verification_code) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }        
       
        $base_url = home_url();        
        $encrypted_user_id = vesess_easyauth_encrypt_user_id($user_id);
        
        // Log full details for debugging
        vesess_easyauth_log("Generating verification link - User ID: $user_id", 'info');
        vesess_easyauth_log("Encrypted user ID: $encrypted_user_id", 'info');
        $base_url = rtrim($base_url, '/');
        
        // Start with the action parameter
        $verification_url = $base_url . '/?action=verify_email';
        
        // Add nonce for security
        $verification_url .= '&_wpnonce=' . wp_create_nonce('verify_email_nonce');
        
        // Add user ID and verification code with consistent encoding
        // Use rawurlencode to ensure + signs and other special chars are properly encoded
        $verification_url .= '&user_id=' . rawurlencode($encrypted_user_id);
        $verification_url .= '&code=' . rawurlencode($verification_code);
        
        $to = $user->user_email;
        $subject = '[' . esc_html(get_bloginfo('name')) . '] Verify Your Email';
          $message = sprintf(
            "Hello %s,\n\nThank you for registering! Please verify your email address by clicking the link below:\n\n%s\n\nBest regards,\n%s",
            esc_html($user->display_name),
            $verification_url,
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
        );
       
        return $this->send_email($to, $subject, $message, $headers, 'verification');
    }

    /**
     * Send email change verification code.
     *
     * @param int $user_id The user ID.
     * @param string $new_email The new email address.
     * @param string $verification_code The verification code.
     * @return bool Whether the email was sent successfully.
     */
    public function send_email_change_verification($user_id, $new_email, $verification_code) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Get configured expiration time
        $expiration_minutes = (int) vesess_easyauth_get_option('code_expiration', 15);        // Send to the current email address, not the new one
        $to = $user->user_email;
        $subject = '[' . esc_html(get_bloginfo('name')) . '] Verify Your Email Change';
          $message = sprintf(
            "Hello %s,\n\nWe received a request to change your email address on %s from %s to %s.\n\nTo verify this change, please use the following verification code:\n\n%s\n\nThis code will expire in %d minutes.\n\nIf you did not request this change, please ignore this email or contact support.\n\nBest regards,\n%s",
            esc_html($user->display_name),
            esc_html(get_bloginfo('name')),
            esc_html($user->user_email),
            esc_html($new_email),
            esc_html($verification_code),
            $expiration_minutes,
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
        );
        
        return $this->send_email($to, $subject, $message, $headers, 'email_change_verification');
    }

    /**
     * Send a magic login link to a user.
     *
     * @param string $user_email The user's email address.
     * @return bool|string Whether the email was sent successfully.
     */    public function send_magic_link($user_email) {
        vesess_easyauth_log("Attempting to send magic link to email: $user_email", 'info');
        
        $user = get_user_by('email', $user_email);
        if (!$user) {
            vesess_easyauth_log("Failed to send magic link: User with email $user_email not found", 'error');
            return false;
        }
        
        vesess_easyauth_log("Found user with ID: {$user->ID}", 'info');
        
        // Check if email is verified (use the helper function to respect admin bypass)
        if (!vesess_easyauth_is_email_verified($user->ID)) {
            vesess_easyauth_log("Cannot send login link: Email not verified for user ID {$user->ID}", 'error');
            return 'unverified';
        }
        
        vesess_easyauth_log("Email is verified, proceeding to create login link", 'info');
          $login_link = vesess_easyauth_create_login_link($user_email);
        
        if (!$login_link) {
            vesess_easyauth_log("Failed to create login link for user email: $user_email", 'error');
            return false;
        }
        
        vesess_easyauth_log("Login link created successfully: " . substr($login_link, 0, 50) . "...", 'info');

        // Get configured expiration time
        $expiration_minutes = (int) vesess_easyauth_get_option('code_expiration', 15);        
        // Get email subject from options or use default
        $options = get_option('vesess_easyauth_options', []);
        $subject = isset($options['email_subject']) ? sanitize_text_field($options['email_subject']) : '';
        if (empty($subject)) {
            $subject = 'Login link for ' . esc_html(get_bloginfo('name'));
        }
        
        // Get email template from options or use default
        $template = isset($options['email_template']) ? sanitize_textarea_field($options['email_template']) : '';
        if (empty($template)) {
            $message = sprintf(
                "Hello %s,\n\nClick the link below to log in:\n\n%s\n\nThis link will expire in %d minutes.\n\nIf you did not request this login link, please ignore this email.\n\nRegards,\n%s",
                esc_html($user->display_name),
                $login_link,
                $expiration_minutes,
                esc_html(get_bloginfo('name'))
            );        } else {
            // Replace placeholders in the template
            $message = str_replace(
                ['{display_name}', '{login_link}', '{site_name}', '{expiration_minutes}'],
                [
                    esc_html($user->display_name), 
                    $login_link, 
                    esc_html(get_bloginfo('name')),
                    $expiration_minutes
                ],
                $template
            );
        }
          $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
        );
        
        // Apply filters to allow customization
        $subject = apply_filters('vesess_easyauth_email_subject', $subject, $user);
        $message = apply_filters('vesess_easyauth_email_message', $message, $user, $login_link);
        $headers = apply_filters('vesess_easyauth_email_headers', $headers, $user);
        
        // Convert line breaks to <br> for HTML emails
        $message = nl2br($message);
        
        $to = $user->user_email;
        return $this->send_email($to, $subject, $message, $headers, 'magic_link');
    }

    /**
     * Helper method to send emails with logging.
     * 
     * @param string $to Email recipient.
     * @param string $subject Email subject.
     * @param string $message Email message.
     * @param array $headers Email headers.
     * @param string $type Email type for logging.
     * @return bool Whether the email was sent successfully.
     */    private function send_email($to, $subject, $message, $headers, $type = 'general') {
        // Log email attempt
        $log_message = sprintf(
            'Attempting to send %s email to %s via WP Mail SMTP',
            sanitize_text_field($type),
            sanitize_email($to)
        );
        
        vesess_easyauth_log($log_message);
        
        // Debugging: Log the full email content
        vesess_easyauth_log("Email headers: " . json_encode($headers));
        vesess_easyauth_log("Email subject: " . $subject);
        vesess_easyauth_log("Email message: " . substr($message, 0, 100) . "..."); // Log first 100 chars
        
        try {
            // Send the email using wp_mail
            $result = wp_mail($to, $subject, $message, $headers);
            
            // Log the result
            if ($result) {
                vesess_easyauth_log(sprintf('Email send successful for %s email to %s', sanitize_text_field($type), sanitize_email($to)));
            } else {
                vesess_easyauth_log(sprintf('Email send failed for %s email to %s', sanitize_text_field($type), sanitize_email($to)), 'error');
                
                // Try to get any error information
                global $phpmailer;
                if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                    vesess_easyauth_log('Email error: ' . $phpmailer->ErrorInfo, 'error');
                } else {
                    vesess_easyauth_log('Email send failed but no error information available', 'error');
                }
            }
            
            return $result;
        } catch (Exception $e) {
            vesess_easyauth_log('Exception when sending email: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get the "from" name for emails.
     *
     * @return string
     */
    private function get_from_name() {
        $options = get_option('vesess_easyauth_options');
        return isset($options['email_from_name']) ? esc_html($options['email_from_name']) : esc_html(get_bloginfo('name'));
    }

    /**
     * Get the "from" email address.
     *
     * @return string
     */
    private function get_from_email() {
        $options = get_option('vesess_easyauth_options');
        return isset($options['email_from_address']) ? sanitize_email($options['email_from_address']) : sanitize_email(get_bloginfo('admin_email'));
    }
}
