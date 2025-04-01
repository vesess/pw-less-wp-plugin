<?php
/**
 * Handles frontend UI integration.
 */
class My_Passwordless_Auth_Frontend {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        add_shortcode('passwordless_login', array($this, 'login_form_shortcode'));
        add_shortcode('passwordless_registration', array($this, 'registration_form_shortcode'));
        add_shortcode('passwordless_profile', array($this, 'profile_page_shortcode'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        // Ensure jQuery is enqueued
        wp_enqueue_script('jquery');
    
        // Define the localized data
        $localized_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_nonce' => wp_create_nonce('passwordless_login_nonce'),
            'registration_nonce' => wp_create_nonce('registration_nonce'),
            'profile_nonce' => wp_create_nonce('profile_nonce'),
            'delete_account_nonce' => wp_create_nonce('delete_account_nonce'),
        );
    
        // Output the localized data as an inline script
        wp_add_inline_script(
            'jquery', // Attach to jQuery, which is already enqueued
            'var passwordless_auth = ' . json_encode($localized_data) . ';',
            'before'
        );
    }

    /**
     * Render login form via shortcode.
     */
    public function login_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'templates/login-form.php';
        return ob_get_clean();
    }

    /**
     * Render registration form via shortcode.
     */
    public function registration_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'templates/registration-form.php';
        return ob_get_clean();
    }

    /**
     * Render profile page via shortcode.
     */
    public function profile_page_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view your profile.', 'my-passwordless-auth') . '</p>';
        }
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'templates/profile-page.php';
        return ob_get_clean();
    }
}
