<?php
/**
 * Handles frontend UI integration.
 */
class My_Passwordless_Auth_Frontend {
    /**
     * Initialize the class and set its hooks.
     */
    public function init()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Enqueue scripts and styles for the plugin
     */
    public function enqueue_styles() {
    // Only enqueue styles when our shortcodes are present
    global $post;
    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $content = $post->post_content;
    
    // Common styles for all shortcodes (variables and shared styles)
    wp_register_style(
        'my-passwordless-auth-common-style',
        MY_PASSWORDLESS_AUTH_URL . 'public/css/common.css',
        array(),
        MY_PASSWORDLESS_AUTH_VERSION . '.' . time()
    );

    // Check for login form shortcode
    if (has_shortcode($content, 'passwordless_login')) {
        wp_enqueue_style(
            'my-passwordless-auth-login-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/login-form.css',
            array('my-passwordless-auth-common-style'),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time()
        );
        wp_enqueue_style('my-passwordless-auth-common-style');
    }

    // Check for registration form shortcode
    if (has_shortcode($content, 'passwordless_registration')) {
        wp_enqueue_style(
            'my-passwordless-auth-registration-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/registration-form.css',
            array('my-passwordless-auth-common-style'),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time()
        );
        wp_enqueue_style('my-passwordless-auth-common-style');
    }

    // Check for profile page shortcode
    if (has_shortcode($content, 'passwordless_profile')) {
        wp_enqueue_style(
            'my-passwordless-auth-profile-style',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/profile-page.css',
            array('my-passwordless-auth-common-style'),
            MY_PASSWORDLESS_AUTH_VERSION . '.' . time()
        );
        wp_enqueue_style('my-passwordless-auth-common-style');
    }
}

    /**
     * Render login form via shortcode.
     */    
    public function login_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // CSS is now handled by the enqueue_styles method
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render registration form via shortcode.
     */    
    public function registration_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already logged in.', 'my-passwordless-auth') . '</p>';
        }
        
        // CSS is now handled by the enqueue_styles method
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/registration-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render profile page via shortcode.
     */    
    public function profile_page_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view your profile.', 'my-passwordless-auth') . '</p>';
        }
        
        // CSS is now handled by the enqueue_styles method
        
        // Pass AJAX data directly to the template
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('passwordless-auth-nonce');
        $profile_nonce = wp_create_nonce('profile_nonce');
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/profile-page.php';
        return ob_get_clean();
    }
}
