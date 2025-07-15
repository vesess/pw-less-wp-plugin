<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */

if (!defined('WPINC')) {
    die;
}

class My_Passwordless_Auth
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var My_Passwordless_Auth_Loader
     */
    protected $loader;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */    public function __construct()
    {
        $this->version = MY_PASSWORDLESS_AUTH_VERSION;
        $this->load_dependencies();
        $this->define_security_hooks();
        $this->define_registration_hooks();
        $this->define_profile_hooks();
        $this->define_frontend_hooks();
        $this->define_admin_hooks();
        $this->define_url_blocker_hooks();
        $this->define_login_integration_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-passwordless-auth-loader.php';

        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-registration.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-profile.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-frontend.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-admin.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-email.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-url-blocker.php'; // Include URL blocker class
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/helpers.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-security.php'; // Include Security class

        $this->loader = new My_Passwordless_Auth_Loader();
    }



    /**
     * Register security related hooks.
     */
    private function define_security_hooks()
    {
        $security = new My_Passwordless_Auth_Security();
        $this->loader->add_action('init', $security, 'init');
    }

    /**
     * Register registration related hooks.
     */
    private function define_registration_hooks()
    {
        $registration = new My_Passwordless_Auth_Registration();

        $this->loader->add_action('init', $registration, 'init');
    }    /**
     * Register profile related hooks.
     * 
     * Profile functionality has been removed.
     */
    private function define_profile_hooks()
    {
        // Profile functionality has been removed
        $profile = new My_Passwordless_Auth_Profile();
        $this->loader->add_action('init', $profile, 'init');
    }

    /**
     * Register frontend related hooks.
     */
    private function define_frontend_hooks()
    {
        $frontend = new My_Passwordless_Auth_Frontend();

        $this->loader->add_action('init', $frontend, 'init');        // Register shortcodes through the loader
        $this->loader->add_shortcode('passwordless_login', $frontend, 'login_form_shortcode');
        $this->loader->add_shortcode('passwordless_registration', $frontend, 'registration_form_shortcode');
    }

    /**
     * Register admin related hooks.
     */
    private function define_admin_hooks()
    {
        $admin = new My_Passwordless_Auth_Admin();

        $this->loader->add_action('init', $admin, 'init');
    }


    /**
     * Register URL blocker related hooks.
     */
    private function define_url_blocker_hooks()
    {
        $url_blocker = new My_Passwordless_Auth_URL_Blocker();

        $this->loader->add_action('init', $url_blocker, 'init');
    }

    /**
     * Define login integration related hooks.
     */
    private function define_login_integration_hooks()
    {
        if (class_exists('My_Passwordless_Auth_Login_Integration')) {
            $login_integration = new My_Passwordless_Auth_Login_Integration();
            $this->loader->add_action('init', $login_integration, 'init');
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();        // Add shortcode for the login form
        add_shortcode('passwordless_login_form', array($this, 'render_login_form'));

        // Handle AJAX form submission for passwordless login (legacy)
        add_action('wp_ajax_nopriv_process_login', array($this, 'handle_ajax_login'));
        add_action('wp_ajax_process_login', array($this, 'handle_ajax_login'));
        
        // Handle AJAX form submission for passwordless login (new implementation)
        add_action('wp_ajax_nopriv_process_passwordless_login', array($this, 'handle_passwordless_login'));
        add_action('wp_ajax_process_passwordless_login', array($this, 'handle_passwordless_login'));

        // Handle magic login links
        add_action('init', array($this, 'process_magic_login'));
    }

    /**
     * Handle AJAX login form submission (Legacy - Email Only).
     * Processes the request, validates input, finds the user, and triggers the magic link sending process.
     *
     * @since 1.0.0
     */    public function handle_ajax_login() 
    {
        // Verify the primary login nonce
        check_ajax_referer('passwordless-login-nonce', 'passwordless_login_nonce');
        
        // Also verify the redirect nonce if it's provided
        if (isset($_POST['redirect_to']) && isset($_POST['redirect_nonce'])) {
            $redirect_nonce = sanitize_text_field(wp_unslash($_POST['redirect_nonce']));
            if (!wp_verify_nonce($redirect_nonce, 'passwordless_redirect')) {
                wp_send_json_error('Invalid redirect request.');
                return;
            }
        }

        $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        
        if (empty($user_email)) {
            wp_send_json_error('Please enter your email address.');
        }

        if (!is_email($user_email)) {
            wp_send_json_error('Please enter a valid email address.');
        }

        $user = get_user_by('email', $user_email);
        if (!$user) {
            // Log this attempt for monitoring, but provide a generic error to the user.
            my_passwordless_auth_log("Login attempt failed: No user found for email: $user_email", 'info');
            wp_send_json_error('No user found with that email address.');
        }

        // Delegate to shared magic link handling
        $this->handle_magic_link_sending($user, $user_email);
    }

    /**
     * Handle AJAX passwordless login form submission with support for both username and email.
     * Processes the request, validates input, finds user by username or email,
     * and triggers the magic link sending process.
     *
     * @since 1.1.0
     */    public function handle_passwordless_login() 
    {
        // Add error logging to debug
        my_passwordless_auth_log("handle_passwordless_login called", 'info');
        
        try {
            check_ajax_referer('passwordless-login-nonce', 'passwordless_login_nonce');
        } catch (Exception $e) {
            my_passwordless_auth_log("Nonce verification failed: " . $e->getMessage(), 'error');
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }
        
        // Get user input (could be either username or email)        // Initialize user input variable
        $user_input = '';
        
        // Safely get and process the user input if it exists and is a string
        if (isset($_POST['user_input']) && is_string($_POST['user_input'])) {
            $user_input = sanitize_text_field(wp_unslash($_POST['user_input']));
        }
        
        my_passwordless_auth_log("User input received: " . $user_input, 'info');
        
        // Check if we received user input
        if (empty($user_input)) {
            my_passwordless_auth_log("Empty user input provided", 'error');
            wp_send_json_error('Please enter your username or email address.');
            return;
        }
        
        // Find the user and their email
        $user = null;
        $user_email = '';
        
        // Check if input is an email address
        if (is_email($user_input)) {
            // Input is an email, use it directly
            $user_email = sanitize_email($user_input);
            $user = get_user_by('email', $user_email);
            my_passwordless_auth_log("Input is email: " . $user_email, 'info');
        } else {
            // Input is potentially a username
            $username = sanitize_user($user_input, true);
            $user = get_user_by('login', $username);
            if ($user) {
                $user_email = $user->user_email;
                my_passwordless_auth_log("Input is username: " . $username . ", email: " . $user_email, 'info');
            }
        }

        // Verify that we found a user
        if (!$user || empty($user_email)) {
            // Log this attempt for monitoring, but provide a generic error to the user.
            my_passwordless_auth_log("Login attempt failed: No user found for input: $user_input", 'info');
            wp_send_json_error('No user found with that username or email address.');
            return;
        }        my_passwordless_auth_log("User found, proceeding to send magic link", 'info');
        
        // Delegate to shared magic link handling
        try {
            $this->handle_magic_link_sending($user, $user_email);
        } catch (Exception $e) {
            my_passwordless_auth_log("Error in handle_magic_link_sending: " . $e->getMessage(), 'error');
            wp_send_json_error('An error occurred while sending the login link: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Handles the shared logic for sending magic links and rate limiting.
     * This is a private helper method used by handle_ajax_login and handle_passwordless_login.
     *
     * @since 1.2.0
     * @param WP_User $user The WordPress user object
     * @param string $user_email The user's email address
     */
    private function handle_magic_link_sending($user, $user_email) 
    {
        // Check rate limiting for login requests
        $security = new My_Passwordless_Auth_Security();
        $ip_address = My_Passwordless_Auth_Security::get_client_ip();
        $block_time = $security->record_login_request($ip_address, $user_email);
        
        if ($block_time !== false) {
            $minutes = ceil($block_time / 60);
            wp_send_json_error(sprintf(
                'Too many login link requests. Please try again in %d minutes.', 
                $minutes
            ));
        }

        // Generate and send magic login link
        $email_class = new My_Passwordless_Auth_Email();
        $sent = $email_class->send_magic_link($user_email);

        if ($sent === false) {
            my_passwordless_auth_log("Failed to send login link to user email: $user_email (User ID: {$user->ID})", 'error');
            wp_send_json_error('Failed to send the login link. Please try again later.');
        } elseif ($sent === 'unverified') {
            wp_send_json_error('Your email address has not been verified yet. Please check your inbox for a verification email or register again.');        } elseif ($sent !== true) {
            // Use more appropriate error logging without var_export
            $error_value = is_string($sent) ? $sent : json_encode($sent);
            my_passwordless_auth_log("Unknown error sending login link to user email: $user_email (User ID: {$user->ID}). Return value: " . $error_value, 'error');
            wp_send_json_error('An unknown error occurred while trying to send the login link. Please try again later.');
        }

        my_passwordless_auth_log("Login link successfully sent to user email: $user_email (User ID: {$user->ID})", 'info');
        wp_send_json_success('Login link sent! Please check your email.');
    }    /**
     * Process magic login requests
     */
    public function process_magic_login()
    {
        if (!isset($_GET['action'])) {
            return;
        }

        // Sanitize the action parameter
        $action = sanitize_text_field(wp_unslash($_GET['action']));
        
        if ($action !== 'magic_login') {
            return;
        }
          // Verify nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'magic_login_nonce')) {
            my_passwordless_auth_log("Magic login failed - invalid nonce", 'error');
            wp_die(
                esc_html('Security check failed. Please request a new login link.'),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        my_passwordless_auth_log("Magic login process initiated from class-passwordless-auth.php");

        // Check rate limiting for magic login attempts
        $security = new My_Passwordless_Auth_Security();
        $ip_address = My_Passwordless_Auth_Security::get_client_ip();
        $block_time = $security->is_ip_blocked($ip_address);
          if ($block_time !== false) {            $minutes = ceil($block_time / 60);            $error_message = sprintf('Too many login attempts. Please try again in %d minutes.', $minutes);
            wp_die(
                esc_html($error_message),
                'Login Failed',
                array('response' => 429, 'back_link' => true)
            );
            return;
        }        // Process the magic login directly instead of calling an external function
        if (!isset($_GET['uid']) || !isset($_GET['token'])) {
            my_passwordless_auth_log("Magic login failed - missing uid or token parameters", 'error');
            return false;
        }

        my_passwordless_auth_log("=== MAGIC LOGIN DEBUG START ===", 'info');
        my_passwordless_auth_log("Processing magic login request with uid: " . sanitize_text_field(wp_unslash($_GET['uid'])), 'info');

        $uid = sanitize_text_field(wp_unslash($_GET['uid']));
        $token_param = sanitize_text_field(wp_unslash($_GET['token']));
        
        my_passwordless_auth_log("Raw UID parameter: $uid", 'info');
        my_passwordless_auth_log("Raw token parameter: $token_param", 'info');
        
        $user_id = my_passwordless_auth_decrypt_user_id($uid);
        
        my_passwordless_auth_log("User ID decryption result: " . ($user_id === false ? 'FAILED' : $user_id), 'info');if ($user_id === false) {
            if (isset($_SESSION)) {
                $_SESSION['passwordless_auth_failed_attempts'] = isset($_SESSION['passwordless_auth_failed_attempts']) ? (int) $_SESSION['passwordless_auth_failed_attempts'] + 1 : 1;
            }
            
            my_passwordless_auth_log("Magic login failed - could not decrypt user ID from: $uid", 'error');
            $error = new WP_Error('invalid_user', 'Invalid login link. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }        my_passwordless_auth_log("Successfully decrypted user ID: $user_id");

        // Get stored token data for this user
        $stored_data = get_user_meta($user_id, 'passwordless_auth_login_token', true);

        if (!$stored_data || !is_array($stored_data)) {
            if (isset($_SESSION)) {
                $_SESSION['passwordless_auth_failed_attempts'] = isset($_SESSION['passwordless_auth_failed_attempts']) ? (int) $_SESSION['passwordless_auth_failed_attempts'] + 1 : 1;
            }
            
            my_passwordless_auth_log("Magic login failed - no token stored for user ID: $user_id", 'error');
            $error = new WP_Error('invalid_token', 'Invalid login link. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }        // Decrypt token from URL
        $token_param = sanitize_text_field(wp_unslash($_GET['token']));
        
        
        $token = my_passwordless_auth_decrypt_token_from_url($token_param);
        
       
        if ($token !== false) {
            my_passwordless_auth_log("Decrypted token preview: " . substr($token, 0, 20) . '...', 'info');
        }if (!$token) {
            if (isset($_SESSION)) {
                $_SESSION['passwordless_auth_failed_attempts'] = isset($_SESSION['passwordless_auth_failed_attempts']) ? (int) $_SESSION['passwordless_auth_failed_attempts'] + 1 : 1;
            }
            
            my_passwordless_auth_log("Magic login failed - could not decrypt token from URL", 'error');
            $error = new WP_Error('invalid_token', 'Invalid login link. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }        // Check if token data is properly formatted
        if (!isset($stored_data['token']) || !isset($stored_data['expiration'])) {
            if (isset($_SESSION)) {
                $_SESSION['passwordless_auth_failed_attempts'] = isset($_SESSION['passwordless_auth_failed_attempts']) ? (int) $_SESSION['passwordless_auth_failed_attempts'] + 1 : 1;
            }
            
            $error = new WP_Error('invalid_token', 'Invalid login link. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }        // Decrypt the stored token for comparison
        $stored_token = my_passwordless_auth_decrypt_token_from_storage($stored_data['token']);
        
       

        // Check if token matches by comparing the decrypted tokens directly
        if ($stored_token === false || $stored_token !== $token) {
            $error = new WP_Error('invalid_token', 'Invalid login link. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }        // Check if token has expired
        if (time() > $stored_data['expiration']) {
            my_passwordless_auth_log("Magic login failed - token expired for user", 'error');
            my_passwordless_auth_log("Current time: " . time() . ", Token expiration: " . $stored_data['expiration'], 'info');
            delete_user_meta($user_id, 'passwordless_auth_login_token');
            $error = new WP_Error('expired_token', 'This login link has expired. Please request a new one.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Get the user
        $user = get_user_by('id', $user_id);
        if (!$user) {
            my_passwordless_auth_log("Magic login failed - user ID $user_id not found", 'error');
            $error = new WP_Error('invalid_user', 'User not found. Please try again.');
            wp_die(
                esc_html($error->get_error_message()),
                'Login Failed',
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Delete the token as it's no longer needed
        delete_user_meta($user_id, 'passwordless_auth_login_token');

        // Log the user in
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        my_passwordless_auth_log("User ID: $user_id successfully logged in via magic link");

        // Fire action for other plugins
        do_action('my_passwordless_auth_after_magic_login', $user);        // Success! Redirect to requested page or default
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        if (empty($redirect_to)) {
            $redirect_to = my_passwordless_auth_get_option('login_redirect', home_url());
        }

        $redirect_to = apply_filters('my_passwordless_auth_login_redirect', $redirect_to);
        my_passwordless_auth_log("Redirecting to: $redirect_to after successful login");
        wp_redirect($redirect_to);
        exit;
    }


    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return My_Passwordless_Auth_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader(): My_Passwordless_Auth_Loader
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
