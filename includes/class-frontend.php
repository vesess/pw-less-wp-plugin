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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
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
    }    // Profile page shortcode has been removed
}

    /**
     * Enqueue JavaScript files for the plugin
     */
    public function enqueue_scripts() {
        // Only enqueue scripts when our shortcodes are present
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $content = $post->post_content;
        
        // Check for login form shortcode
        if (has_shortcode($content, 'passwordless_login')) {
            wp_enqueue_script(
                'my-passwordless-auth-login-script',
                MY_PASSWORDLESS_AUTH_URL . 'public/js/login-form.js',
                array('jquery'),
                MY_PASSWORDLESS_AUTH_VERSION . '.' . time(),
                true
            );
              // Pass AJAX URL and nonces to script
            wp_localize_script(
                'my-passwordless-auth-login-script',
                'passwordlessAuth',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('passwordless-auth-nonce'),
                    'login_nonce' => wp_create_nonce('passwordless-login-nonce'),
                    'redirect_nonce' => wp_create_nonce('passwordless_redirect'),
                    'feedback_nonce' => wp_create_nonce('passwordless_login_feedback')
                )
            );
        }

        // Check for registration form shortcode
        if (has_shortcode($content, 'passwordless_registration')) {
            wp_enqueue_script(
                'my-passwordless-auth-registration-script',
                MY_PASSWORDLESS_AUTH_URL . 'public/js/registration-form.js',
                array('jquery'),
                MY_PASSWORDLESS_AUTH_VERSION . '.' . time(),
                true
            );
            
            // Pass AJAX URL and nonce to script
            wp_localize_script(
                'my-passwordless-auth-registration-script',
                'passwordlessAuth',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('passwordless-auth-nonce'),
                    'registration_nonce' => wp_create_nonce('passwordless-registration-nonce'),
                    'registration_feedback_nonce' => wp_create_nonce('passwordless_registration_feedback')
                )
            );
        }        // Profile page shortcode has been removed
    }    /**
     * Render login form via shortcode.
     */      public function login_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html('You are already logged in.') . '</p>';
        }
        
        // CSS and JS are now handled by the enqueue methods
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/login-form.php';
        return ob_get_clean();
    }
      /**
     * Render registration form via shortcode.
     */      public function registration_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>' . esc_html('You are already logged in.') . '</p>';
        }
        
        // CSS and JS are now handled by the enqueue methods
        
        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'public/partials/registration-form.php';
        return ob_get_clean();
    }      /**
     * Profile page functionality has been removed
     */
}
