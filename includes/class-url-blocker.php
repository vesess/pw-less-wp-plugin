<?php
/**
 * Handles URL blocking functionality.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class My_Passwordless_Auth_URL_Blocker {
    
    private $blocked_urls = array();
    
    private $options;
    private $redirect_url;

    /**
     * Initialize the URL blocker
     */
    public function init() {
        if (!function_exists('add_action')) {
            return;
        }
        add_action('template_redirect', array($this, 'check_blocked_urls'));
        $this->setup_blocked_urls();
    }

    /**
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

        $this->blocked_urls = array(
            $base_url . '/sample-page',
            $base_url . '/category/*',
            '*/private/*',
            $base_url . '/index.php/login',
            $base_url . '/index.php/registration',
            $base_url . '/login',
            $base_url . '/registration',
        );

        // Set default redirect URL
        $this->redirect_url = isset($this->options['login_redirect']) ? 
            $this->options['login_redirect'] : 
            $base_url . '/not-found';
        
        ////my_passwordless_auth_log("Blocked URLs set up: " . print_r($this->blocked_urls, true), 'info');
        ////my_passwordless_auth_log("Redirect URL set to: " . $this->redirect_url, 'info');
    }

    /**
     * Check if the current URL is in the blocked list
     */
    public function check_blocked_urls() {
        // Don't block admin pages
        if (function_exists('is_admin') && is_admin()) {
            //my_passwordless_auth_log("Admin page detected, not checking URL blocking", 'info');
            return;
        }
        
        // Only block URLs when the user is logged in
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            //my_passwordless_auth_log("User not logged in, skipping URL blocking", 'info');
            return;
        }
        
        // Get current URL
        $current_url = $this->get_current_url();
        
        // Log current URL for debugging
        //my_passwordless_auth_log("Current URL being checked: " . $current_url, 'info');
        
        // Check if current URL matches any blocked URL (considering wildcards)
        foreach ($this->blocked_urls as $blocked_url) {
            // Skip empty lines
            $blocked_url = trim($blocked_url);
            if (empty($blocked_url)) {
                continue;
            }
            
            $pattern = $this->convert_wildcard_to_regex($blocked_url);
            
            // Log the pattern for debugging
            //my_passwordless_auth_log("Checking URL against pattern: " . $pattern, 'info');
            
            if (preg_match($pattern, $current_url)) {
                //my_passwordless_auth_log("Blocked access to: " . $current_url . " (matched pattern: " . $blocked_url . ") for logged-in user", 'warning');
                
                if (function_exists('wp_redirect')) {
                    wp_redirect($this->redirect_url);
                    exit;
                } else {
                    header('Location: ' . $this->redirect_url);
                    exit;
                }
            }
        }
        
        //my_passwordless_auth_log("URL not blocked: " . $current_url, 'info');
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
        $protocol = function_exists('is_ssl') && is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Ensure URL doesn't have double slashes
        $url = $protocol . $host . $uri;
        
        // Normalize URL - remove trailing slash for consistency in matching
        $url = rtrim($url, '/');
        
        return $url;
    }
}