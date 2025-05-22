<?php
/**
 * Handles URL blocking functionality.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class My_Passwordless_Auth_URL_Blocker {
    
    private $logged_in_blocked_urls = array();
    private $logged_out_blocked_urls = array();
    
    private $options;
    private $login_redirect_url;
    private $profile_redirect_url;

    /**
     * Initialize the URL blocker
     */
    public function init() {
        if (!function_exists('add_action')) {
            return;
        }
        add_action('template_redirect', array($this, 'check_blocked_urls'));
        $this->setup_blocked_urls();
    }    /**
     * Set up blocked URLs and redirect URL
     */
    public function setup_blocked_urls() {
        if (!function_exists('home_url')) {
            //my_passwordless_auth_log("home_url function not available", 'error');
            return;
        }

        // Load options and get user_home_url
        if (function_exists('get_option')) {
            $this->options = get_option('my_passwordless_auth_options');
            $base_url = isset($this->options['user_home_url']) ? $this->options['user_home_url'] : home_url();
            //my_passwordless_auth_log("Using base URL for blocked URLs: " . $base_url, 'info');
        } else {
            $base_url = home_url();
            //my_passwordless_auth_log("Fallback to home_url(): " . $base_url, 'info');
        }

        // URLs to block when user is logged in
        $this->logged_in_blocked_urls = array(
            $base_url . '/category/*',
            '*/private/*',
            $base_url . '/index.php/login',
            $base_url . '/index.php/sign-up',
            $base_url . '/login',
            $base_url . '/sign-up',
        );

        // URLs to block when user is NOT logged in
        $this->logged_out_blocked_urls = array(
            $base_url . '/profile',
            $base_url . '/index.php/profile',
            '*/profile/*',
        );        // Set default redirect URLs
        $this->login_redirect_url =  $base_url; // Redirect to home URL
            
        // For logged out users trying to access profile, redirect to login page
        $this->profile_redirect_url = $base_url . '/login'; // Redirect to login URL
        
        ////my_passwordless_auth_log("Blocked URLs set up for logged in users: " . print_r($this->logged_in_blocked_urls, true), 'info');
        ////my_passwordless_auth_log("Blocked URLs set up for logged out users: " . print_r($this->logged_out_blocked_urls, true), 'info');
    }    /**
     * Check if the current URL is in the blocked list
     */
    public function check_blocked_urls() {
        // Don't block admin pages
        if (function_exists('is_admin') && is_admin()) {
            //my_passwordless_auth_log("Admin page detected, not checking URL blocking", 'info');
            return;
        }
        
        // Get current URL
        $current_url = $this->get_current_url();
        
        // Log current URL for debugging
        //my_passwordless_auth_log("Current URL being checked: " . $current_url, 'info');
        
        // Check if user is logged in
        $is_logged_in = function_exists('is_user_logged_in') && is_user_logged_in();
        
        if ($is_logged_in) {
            // User is logged in - check against logged in blocked URLs
            $this->check_url_against_patterns($current_url, $this->logged_in_blocked_urls, $this->login_redirect_url);
        } else {
            // User is NOT logged in - check against logged out blocked URLs
            $this->check_url_against_patterns($current_url, $this->logged_out_blocked_urls, $this->profile_redirect_url);
        }
        
        //my_passwordless_auth_log("URL not blocked: " . $current_url, 'info');
    }
    
    /**
     * Check a URL against an array of patterns and redirect if matched
     */
    private function check_url_against_patterns($url, $patterns, $redirect_url) {
        // Check if current URL matches any blocked URL (considering wildcards)
        foreach ($patterns as $blocked_url) {
            // Skip empty lines
            $blocked_url = trim($blocked_url);
            if (empty($blocked_url)) {
                continue;
            }
            
            $pattern = $this->convert_wildcard_to_regex($blocked_url);
            
            // Log the pattern for debugging
            //my_passwordless_auth_log("Checking URL against pattern: " . $pattern, 'info');
            
            if (preg_match($pattern, $url)) {
                $is_logged_in = function_exists('is_user_logged_in') && is_user_logged_in();
                $user_status = $is_logged_in ? "logged-in" : "logged-out";
                //my_passwordless_auth_log("Blocked access to: " . $url . " (matched pattern: " . $blocked_url . ") for " . $user_status . " user", 'warning');
                
                if (function_exists('wp_redirect')) {
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    header('Location: ' . $redirect_url);
                    exit;
                }
            }
        }
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
     */    private function get_current_url() {
        $protocol = function_exists('is_ssl') && is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        
        // Ensure URL doesn't have double slashes
        $url = $protocol . $host . $uri;
        
        // Normalize URL - remove trailing slash for consistency in matching
        $url = rtrim($url, '/');
        
        return $url;
    }
}