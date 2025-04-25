<?php
/**
 * Handles frontend UI integration.
 */
class My_Passwordless_Auth_Frontend {    /**
     * Initialize the class and set its hooks.
     */
    public function init()
    {
        // No need to enqueue scripts anymore as JS is included in templates
    }

    /**
     * Enqueue scripts and styles - empty function as scripts are now inline
     */
    public function enqueue_scripts()
    {
        // Scripts are now included directly in the template files
    }    /**
     * Render login form via shortcode.
     */
    public function login_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }    /**
     * Render registration form via shortcode.
     */
    public function registration_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/registration-form.php';
        return ob_get_clean();
    }    /**
     * Render profile page via shortcode.
     */
    public function profile_page_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view your profile.', 'my-passwordless-auth') . '</p>';
        }
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        $profile_nonce = wp_create_nonce('profile_nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/profile-page.php';
        return ob_get_clean();
    }
}
