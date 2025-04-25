<?php
/**
 * Handles frontend UI integration.
 */
class My_Passwordless_Auth_Frontend {    /**
     * Initialize the class and set its hooks.
     */
    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles for the plugin
     */
    public function enqueue_scripts()
    {
        // Get plugin options
        $options = get_option('my_passwordless_auth_options', array());
        $use_theme_styles = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes';
        
        // Enqueue the main CSS file
        wp_enqueue_style(
            'my-passwordless-auth-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/passwordless-auth.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION
        );
          // Add custom class to body when theme styles are enabled
        if ($use_theme_styles) {
            add_filter('body_class', function($classes) {
                $classes[] = 'theme-compat';
                return $classes;
            });
        }
    }/**
     * Render login form via shortcode.
     */    public function login_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // Ensure the CSS is loaded when the shortcode is used
        wp_enqueue_style(
            'my-passwordless-auth-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/passwordless-auth.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time() // Force cache refresh with time
        );
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }    /**
     * Render registration form via shortcode.
     */    public function registration_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // Ensure the CSS is loaded when the shortcode is used
        wp_enqueue_style(
            'my-passwordless-auth-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/passwordless-auth.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time() // Force cache refresh with time
        );
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/registration-form.php';
        return ob_get_clean();
    }    /**
     * Render profile page via shortcode.
     */    public function profile_page_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view your profile.', 'my-passwordless-auth') . '</p>';
        }
        
        // Ensure the CSS is loaded when the shortcode is used
        wp_enqueue_style(
            'my-passwordless-auth-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/passwordless-auth.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time() // Force cache refresh with time
        );
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        $profile_nonce = wp_create_nonce('profile_nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/profile-page.php';
        return ob_get_clean();
    }
}
