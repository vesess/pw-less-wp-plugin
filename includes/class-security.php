<?php
/**
 * Handles global security functionality.
 */
class My_Passwordless_Auth_Security {
    const MAX_LOGIN_ATTEMPTS = 5; // Maximum login attempts per time window
    const MAX_REGISTRATION_ATTEMPTS = 3; // Maximum registration attempts per time window
    const MAX_LOGIN_REQUESTS = 3; // Maximum login link requests per time window
    const LOCKOUT_DURATION = 300; // 30 minutes in seconds
    const ATTEMPT_WINDOW = 900; // 15 minutes in seconds for counting attempts

    /**
     * Initialize the security class
     */
    public function init() {
        // Clean up old attempts periodically
        add_action('wp_login', array($this, 'cleanup_old_attempts'));
        add_action('admin_init', array($this, 'cleanup_old_attempts'));
    }

    /**
     * Check if an IP is blocked from login attempts
     *
     * @param string $ip_address The IP address to check
     * @return bool|int Returns false if not blocked, or seconds remaining if blocked
     */
    public function is_ip_blocked($ip_address) {
        $blocked_ips = get_transient('passwordless_auth_blocked_ips');
        if (!$blocked_ips || !is_array($blocked_ips)) {
            return false;
        }

        if (isset($blocked_ips[$ip_address])) {
            $block_expires = $blocked_ips[$ip_address];
            if (time() < $block_expires) {
                return $block_expires - time();
            }
            // Block expired, remove it
            unset($blocked_ips[$ip_address]);
            set_transient('passwordless_auth_blocked_ips', $blocked_ips, DAY_IN_SECONDS);
        }

        return false;
    }

    /**
     * Record a login attempt
     *
     * @param string $ip_address The IP address making the attempt
     * @param string $email The email address being used
     * @param bool $success Whether the attempt was successful
     * @return bool|int Returns false if allowed to continue, or seconds remaining if blocked
     */
    public function record_login_attempt($ip_address, $email, $success = false) {
        // Check if IP is already blocked
        $block_time = $this->is_ip_blocked($ip_address);
        if ($block_time !== false) {
            return $block_time;
        }

        $attempts = get_transient('passwordless_auth_login_attempts');
        if (!$attempts) {
            $attempts = array();
        }

        if (!isset($attempts[$ip_address])) {
            $attempts[$ip_address] = array(
                'count' => 0,
                'first_attempt' => time(),
                'email_attempts' => array()
            );
        }

        // Add this attempt
        $attempts[$ip_address]['count']++;
        if (!isset($attempts[$ip_address]['email_attempts'][$email])) {
            $attempts[$ip_address]['email_attempts'][$email] = 0;
        }
        $attempts[$ip_address]['email_attempts'][$email]++;

        // If successful, clear the attempts
        if ($success) {
            unset($attempts[$ip_address]);
            set_transient('passwordless_auth_login_attempts', $attempts, self::ATTEMPT_WINDOW);
            return false;
        }

        // Check if we should block this IP
        if ($attempts[$ip_address]['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $this->block_ip($ip_address);
            return self::LOCKOUT_DURATION;
        }

        // Save attempts
        set_transient('passwordless_auth_login_attempts', $attempts, self::ATTEMPT_WINDOW);
        return false;
    }

    /**
     * Record a login link request
     *
     * @param string $ip_address The IP address making the request
     * @param string $email The email address being used
     * @return bool|int Returns false if allowed to continue, or seconds remaining if blocked
     */
    public function record_login_request($ip_address, $email) {
        // Check if IP is already blocked
        $block_time = $this->is_ip_blocked($ip_address);
        if ($block_time !== false) {
            return $block_time;
        }

        $requests = get_transient('passwordless_auth_login_requests');
        if (!$requests) {
            $requests = array();
        }

        if (!isset($requests[$ip_address])) {
            $requests[$ip_address] = array(
                'count' => 0,
                'first_request' => time(),
                'email_requests' => array()
            );
        }

        // Add this request
        $requests[$ip_address]['count']++;
        if (!isset($requests[$ip_address]['email_requests'][$email])) {
            $requests[$ip_address]['email_requests'][$email] = 0;
        }
        $requests[$ip_address]['email_requests'][$email]++;

        // Check if we should block this IP
        if ($requests[$ip_address]['count'] >= self::MAX_LOGIN_REQUESTS) {
            $this->block_ip($ip_address);
            return self::LOCKOUT_DURATION;
        }

        // Save requests
        set_transient('passwordless_auth_login_requests', $requests, self::ATTEMPT_WINDOW);
        return false;
    }

    /**
     * Record a registration attempt
     *
     * @param string $ip_address The IP address making the attempt
     * @return bool|int Returns false if allowed to continue, or seconds remaining if blocked
     */
    public function record_registration_attempt($ip_address) {
        // Check if IP is already blocked
        $block_time = $this->is_ip_blocked($ip_address);
        if ($block_time !== false) {
            return $block_time;
        }

        $attempts = get_transient('passwordless_auth_registration_attempts');
        if (!$attempts) {
            $attempts = array();
        }

        if (!isset($attempts[$ip_address])) {
            $attempts[$ip_address] = array(
                'count' => 0,
                'first_attempt' => time()
            );
        }

        // Add this attempt
        $attempts[$ip_address]['count']++;

        // Check if we should block this IP
        if ($attempts[$ip_address]['count'] >= self::MAX_REGISTRATION_ATTEMPTS) {
            $this->block_ip($ip_address);
            return self::LOCKOUT_DURATION;
        }

        // Save attempts
        set_transient('passwordless_auth_registration_attempts', $attempts, self::ATTEMPT_WINDOW);
        return false;
    }

    /**
     * Block an IP address
     *
     * @param string $ip_address The IP address to block
     */
    private function block_ip($ip_address) {
        $blocked_ips = get_transient('passwordless_auth_blocked_ips');
        if (!$blocked_ips || !is_array($blocked_ips)) {
            $blocked_ips = array();
        }

        $blocked_ips[$ip_address] = time() + self::LOCKOUT_DURATION;
        set_transient('passwordless_auth_blocked_ips', $blocked_ips, DAY_IN_SECONDS);

        my_passwordless_auth_log("IP address {$ip_address} has been blocked for " . (self::LOCKOUT_DURATION / 60) . " minutes", 'warning');
    }

    /**
     * Clean up old attempt records
     */
    public function cleanup_old_attempts() {
        $cutoff_time = time() - self::ATTEMPT_WINDOW;

        // Clean up login attempts
        $attempts = get_transient('passwordless_auth_login_attempts');
        if ($attempts) {
            foreach ($attempts as $ip => $data) {
                if ($data['first_attempt'] < $cutoff_time) {
                    unset($attempts[$ip]);
                }
            }
            set_transient('passwordless_auth_login_attempts', $attempts, self::ATTEMPT_WINDOW);
        }

        // Clean up login requests
        $requests = get_transient('passwordless_auth_login_requests');
        if ($requests) {
            foreach ($requests as $ip => $data) {
                if ($data['first_request'] < $cutoff_time) {
                    unset($requests[$ip]);
                }
            }
            set_transient('passwordless_auth_login_requests', $requests, self::ATTEMPT_WINDOW);
        }

        // Clean up registration attempts
        $reg_attempts = get_transient('passwordless_auth_registration_attempts');
        if ($reg_attempts) {
            foreach ($reg_attempts as $ip => $data) {
                if ($data['first_attempt'] < $cutoff_time) {
                    unset($reg_attempts[$ip]);
                }
            }
            set_transient('passwordless_auth_registration_attempts', $reg_attempts, self::ATTEMPT_WINDOW);
        }

        // Clean up expired IP blocks
        $blocked_ips = get_transient('passwordless_auth_blocked_ips');
        if ($blocked_ips) {
            foreach ($blocked_ips as $ip => $expiry) {
                if (time() > $expiry) {
                    unset($blocked_ips[$ip]);
                }
            }
            set_transient('passwordless_auth_blocked_ips', $blocked_ips, DAY_IN_SECONDS);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */    public static function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $header_value = sanitize_text_field(wp_unslash($_SERVER[$header]));
                $ip = trim(explode(',', $header_value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }
}