<?php
/**
 * Integrates passwordless login with the standard WordPress login form (wp-login.php)
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle integration with the standard WordPress login form
 */
class My_Passwordless_Auth_Login_Integration {
    /**
     * Initialize the class and set its hooks
     */
    public function init() {
        // Add passwordless login button to login form
        add_action('login_form', array($this, 'add_vesess_easyauth_login_button'));
        
        // Add passwordless login option to lost password form
        add_action('lostpassword_form', array($this, 'add_vesess_easyauth_login_button_lostpw'));
        
        // Add inline login form with AJAX functionality to the login page
        add_action('login_footer', array($this, 'add_inline_login_form'));
        
        // Add custom CSS for login form positioning
        add_action('login_head', array($this, 'add_custom_login_css'));
        
        // Add admin settings
        add_action('admin_init', array($this, 'add_admin_settings'));
        
        // Handle the AJAX request for passwordless login
        add_action('wp_ajax_nopriv_process_vesess_easyauth_login', array($this, 'handle_vesess_easyauth_login_ajax'));
        add_action('wp_ajax_process_vesess_easyauth_login', array($this, 'handle_vesess_easyauth_login_ajax'));
    }

    /**
     * Add admin settings for the integration
     */
    public function add_admin_settings() {
        add_settings_field(
            'enable_wp_login_integration',
            'Enable Admin Login Integration',
            array($this, 'render_wp_login_integration_field'),
            'vesess_easyauth',
            'my_passwordless_auth_general'
        );
    }

    /**
     * Render the admin setting field
     */
    public function render_wp_login_integration_field() {
        $options = get_option('vesess_easyauth_options');
        $checked = isset($options['enable_wp_login_integration']) ? $options['enable_wp_login_integration'] === 'yes' : true;
        ?>
        <input type="checkbox" name="vesess_easyauth_options[enable_wp_login_integration]" value="yes" <?php checked($checked); ?> />
        <p class="description">Add passwordless login option to the WordPress login screen (wp-login.php)</p>
        <?php
    }

    /**
     * Check if admin login integration is enabled
     */
    public function is_integration_enabled() {
        $options = get_option('vesess_easyauth_options', []);
        
        // If the option doesn't exist yet (first installation), default to true
        if (!isset($options['enable_wp_login_integration'])) {
            return true;
        }
        
        return $options['enable_wp_login_integration'] === 'yes';
    }

    /**
     * Add passwordless login button to the login form
     */
    public function add_vesess_easyauth_login_button() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Create a nonce for security
        $nonce = wp_create_nonce('passwordless-login-nonce');
        ?>
        <div class="pwless-login-container" style="text-align: center; margin: 15px 0;">
            <p>or</p>
            <button type="button" id="pwless-login-btn" class="button button-primary" style="display: block; width: 100%; text-align: center; padding: 10px 0;">
                Log In with Email Code
            </button>
            
            <!-- Hidden form fields for the passwordless login -->
            <input type="hidden" name="vesess_easyauth_login_nonce" id="vesess_easyauth_login_nonce" value="<?php echo esc_attr($nonce); ?>">
            <div id="pwless-messages" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    /**
     * Add passwordless login option to the lost password form
     */
    public function add_vesess_easyauth_login_button_lostpw() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Create a nonce for security
        $nonce = wp_create_nonce('passwordless-login-nonce');
        ?>
        <div class="pwless-login-container" style="text-align: center; margin: 15px 0;">
            <p>or</p>
            <button type="button" id="pwless-login-btn-lost" class="button button-primary" style="display: block; width: 100%; text-align: center; padding: 10px 0;">
                Use Passwordless Login Instead
            </button>
            <input type="hidden" name="vesess_easyauth_login_nonce_lost" id="vesess_easyauth_login_nonce_lost" value="<?php echo esc_attr($nonce); ?>">
            <div id="pwless-messages-lost" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    /**
     * Add inline login form for passwordless authentication.
     */
    public function add_inline_login_form() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Enqueue the login form integration script
        wp_enqueue_script(
            'vesess_easyauth-login-integration',
            MY_PASSWORDLESS_AUTH_URL . 'public/js/login-form-integration.js',
            array('jquery'),
            MY_PASSWORDLESS_AUTH_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'vesess_easyauth-login-integration',
            'passwordlessLoginIntegration',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('passwordless-login-nonce')
            )
        );
    }

    /**
     * Add custom CSS to properly position the passwordless login button
     * after the standard WordPress login button
     */
    public function add_custom_login_css() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Enqueue login form integration styles
        wp_enqueue_style(
            'vesess_easyauth-login-integration',
            MY_PASSWORDLESS_AUTH_URL . 'public/css/login-form-integration.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION
        );
    }

    /**
     * Handle AJAX request for passwordless login
     */
    public function handle_vesess_easyauth_login_ajax() {
        // Verify nonce
        if (!isset($_POST['vesess_easyauth_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vesess_easyauth_login_nonce'])), 'passwordless-login-nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get and validate user input
        if (!isset($_POST['user_input']) || empty($_POST['user_input'])) {
            wp_send_json_error('Please enter your email address or username');
            return;
        }

        $user_input = sanitize_text_field(wp_unslash($_POST['user_input']));
        
        // Try to find user by email first, then by username
        $user = get_user_by('email', $user_input);
        if (!$user) {
            $user = get_user_by('login', $user_input);
        }

        if (!$user) {
            wp_send_json_error('User not found');
            return;
        }

        // Generate and send login link
        $login_link = my_passwordless_auth_create_login_link($user->user_email);
        
        if ($login_link) {
            // Send email with login link
            $subject = get_bloginfo('name') . ' - Your Login Link';
            $message = "Click here to log in: " . $login_link;
            
            if (wp_mail($user->user_email, $subject, $message)) {
                wp_send_json_success('Login link sent to your email address');
            } else {
                wp_send_json_error('Failed to send email. Please try again.');
            }
        } else {
            wp_send_json_error('Failed to create login link. Please try again.');
        }
    }
}
