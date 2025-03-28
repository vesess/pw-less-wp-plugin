<?php
/**
 * Handles email functionality.
 */
class My_Passwordless_Auth_Email {
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

        $to = $user->user_email;
        $subject = sprintf(esc_html__('[%s] Your Login Code', 'my-passwordless-auth'), esc_html(get_bloginfo('name')));
        
        $message = sprintf(
            esc_html__("Hello %s,\n\nYour login code is: %s\n\nThis code will expire in 15 minutes.\n\nBest regards,\n%s", 'my-passwordless-auth'),
            esc_html($user->display_name),
            esc_html($login_code),
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . esc_html($this->get_from_name()) . ' <' . sanitize_email($this->get_from_email()) . '>',
        );

        return $this->send_email($to, $subject, $message, $headers, 'login_code');
    }

    /**
     * Send a verification email to a newly registered user.
     *
     * @param int $user_id The user ID.
     * @param string $verification_code The verification code.
     * @return bool Whether the email was sent successfully.
     */
    public function send_verification_email($user_id, $verification_code) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Fix the URL encoding issue by using a direct approach instead of add_query_arg
        $base_url = home_url();
        $verification_url = esc_url_raw(
            $base_url . '?action=verify_email' . 
            '&user_id=' . my_passwordless_auth_encrypt_user_id($user_id) . 
            '&code=' . $verification_code
        );

        $to = $user->user_email;
        $subject = sprintf(esc_html__('[%s] Verify Your Email', 'my-passwordless-auth'), esc_html(get_bloginfo('name')));
        
        $message = sprintf(
            esc_html__("Hello %s,\n\nThank you for registering! Please verify your email address by clicking the link below:\n\n%s\n\nBest regards,\n%s", 'my-passwordless-auth'),
            esc_html($user->display_name),
            $verification_url,
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . esc_html($this->get_from_name()) . ' <' . sanitize_email($this->get_from_email()) . '>',
        );
       
        return $this->send_email($to, $subject, $message, $headers, 'verification');
    }

    /**
     * Send account deletion confirmation code.
     *
     * @param int $user_id The user ID.
     * @param string $confirmation_code The confirmation code.
     * @return bool Whether the email was sent successfully.
     */
    public function send_deletion_confirmation($user_id, $confirmation_code) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $to = $user->user_email;
        $subject = sprintf(esc_html__('[%s] Confirm Account Deletion', 'my-passwordless-auth'), esc_html(get_bloginfo('name')));
        
        $message = sprintf(
            esc_html__("Hello %s,\n\nWe received a request to delete your account. To confirm, use the following code:\n\n%s\n\nIf you did not request this, please ignore this email.\n\nBest regards,\n%s", 'my-passwordless-auth'),
            esc_html($user->display_name),
            esc_html($confirmation_code),
            esc_html(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . esc_html($this->get_from_name()) . ' <' . sanitize_email($this->get_from_email()) . '>',
        );

        return $this->send_email($to, $subject, $message, $headers, 'deletion');
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
     */
    private function send_email($to, $subject, $message, $headers, $type = 'general') {
        // Log email attempt
        $log_message = sprintf(
            'Attempting to send %s email to %s via WP Mail SMTP',
            $type,
            $to
        );
        
        my_passwordless_auth_log($log_message);
        
        // Debugging: Log the full email content
        my_passwordless_auth_log("Email headers: " . json_encode($headers));
        my_passwordless_auth_log("Email subject: " . $subject);
        my_passwordless_auth_log("Email message: " . substr($message, 0, 100) . "..."); // Log first 100 chars
        
        // Send the email using wp_mail
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Log the result
        if ($result) {
            my_passwordless_auth_log(sprintf('Email send successful for %s email to %s', $type, $to));
        } else {
            my_passwordless_auth_log(sprintf('Email send failed for %s email to %s', $type, $to), 'error');
            
            // Try to get any error information
            global $phpmailer;
            if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                my_passwordless_auth_log('Email error: ' . $phpmailer->ErrorInfo, 'error');
            }
        }
        
        return $result;
    }

    /**
     * Get the "from" name for emails.
     *
     * @return string
     */
    private function get_from_name() {
        $options = get_option('my_passwordless_auth_options');
        return isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
    }

    /**
     * Get the "from" email address.
     *
     * @return string
     */
    private function get_from_email() {
        $options = get_option('my_passwordless_auth_options');
        return isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
    }
}
