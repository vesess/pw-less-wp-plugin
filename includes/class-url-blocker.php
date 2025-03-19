<?php
/**
 * Handles URL blocking functionality.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class My_Passwordless_Auth_URL_Blocker {
    
    // Hardcoded list of blocked URLs
    private $blocked_urls = array(
        'http://localhost/wordpress/sample-page/',
        'http://localhost/wordpress/category/*',
        '*/private/*',
        'http://localhost/wordpress/index.php/login/',
        'http://localhost/wordpress/index.php/registration/',
        // Add more URLs to block here
    );
    
    // Specific redirect URL
    private $redirect_url = 'http://localhost/wordpress';
    
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        // Log initialization
        my_passwordless_auth_log("URL Blocker initialized", 'info');
        
        // Execute the check directly during init
        $this->check_blocked_urls();
    }

    /**
     * Check if the current URL is in the blocked list
     */
    public function check_blocked_urls() {
        // Don't block admin pages
        if (is_admin()) {
            my_passwordless_auth_log("Admin page detected, not checking URL blocking", 'info');
            return;
        }
        
        // Only block URLs when the user is logged in
        if (!is_user_logged_in()) {
            my_passwordless_auth_log("User not logged in, skipping URL blocking", 'info');
            return;
        }
        
        // Get current URL
        $current_url = $this->get_current_url();
        
        // Log current URL for debugging
        my_passwordless_auth_log("Current URL being checked: " . $current_url, 'info');
        
        // Check if current URL matches any blocked URL (considering wildcards)
        foreach ($this->blocked_urls as $blocked_url) {
            // Skip empty lines
            $blocked_url = trim($blocked_url);
            if (empty($blocked_url)) {
                continue;
            }
            
            $pattern = $this->convert_wildcard_to_regex($blocked_url);
            
            // Log the pattern for debugging
            my_passwordless_auth_log("Checking URL against pattern: " . $pattern, 'info');
            
            if (preg_match($pattern, $current_url)) {
                my_passwordless_auth_log("Blocked access to: " . $current_url . " (matched pattern: " . $blocked_url . ") for logged-in user", 'warning');
                
                // Use the hardcoded redirect URL instead of home_url()
                if (function_exists('wp_redirect')) {
                    wp_redirect($this->redirect_url);
                    exit;
                } else {
                    header('Location: ' . $this->redirect_url);
                    exit;
                }
            }
        }
        
        my_passwordless_auth_log("URL not blocked: " . $current_url, 'info');
    }

    /**
     * Convert wildcard string to regex pattern
     */
    private function convert_wildcard_to_regex($wildcard_string) {
        $regex = preg_quote($wildcard_string, '/');
        $regex = str_replace('\*', '.*', $regex); // Convert * to .*
        
        // Handle case where URL might end with / or not
        if (substr($regex, -1) === '/') {
            $regex = rtrim($regex, '/') . '/?';
        }
        
        return '/^' . $regex . '$/i';
    }

    /**
     * Get the current URL
     */
    private function get_current_url() {
        $protocol = (is_ssl()) ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Ensure URL doesn't have double slashes
        $url = $protocol . $host . $uri;
        
        // Normalize URL - remove trailing slash for consistency in matching
        $url = rtrim($url, '/');
        
        return $url;
    }
}