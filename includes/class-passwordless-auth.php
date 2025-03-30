<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */

// If this file is called directly, abort.
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
     */
    public function __construct()
    {
        $this->version = MY_PASSWORDLESS_AUTH_VERSION;
        $this->load_dependencies();
        // This is commented out since this seems to be legacy code and not used in the current context.
        // $this->define_authentication_hooks();
        $this->define_registration_hooks();
        $this->define_profile_hooks();
        $this->define_frontend_hooks();
        $this->define_admin_hooks();
        $this->define_email_hooks();
        $this->define_url_blocker_hooks(); // Add URL blocker hooks
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

        /**
         * The classes that handle core plugin functionality.
         */
        // This is commented out since this seems to be legacy code and not used in the current context.
        // require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-authentication.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-registration.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-profile.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-frontend.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-admin.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-email.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-url-blocker.php'; // Include URL blocker class
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/helpers.php';

        $this->loader = new My_Passwordless_Auth_Loader();
    }

    /**
     * Register authentication related hooks.
     */
    private function define_authentication_hooks()
    {
        $authentication = new My_Passwordless_Auth_Authentication();

        $this->loader->add_action('init', $authentication, 'init');
    }

    /**
     * Register registration related hooks.
     */
    private function define_registration_hooks()
    {
        $registration = new My_Passwordless_Auth_Registration();

        $this->loader->add_action('init', $registration, 'init');
    }

    /**
     * Register profile related hooks.
     */
    private function define_profile_hooks()
    {
        $profile = new My_Passwordless_Auth_Profile();

        $this->loader->add_action('init', $profile, 'init');
    }

    /**
     * Register frontend related hooks.
     */
    private function define_frontend_hooks()
    {
        $frontend = new My_Passwordless_Auth_Frontend();

        $this->loader->add_action('init', $frontend, 'init');

        // Register shortcodes through the loader
        $this->loader->add_shortcode('passwordless_login', $frontend, 'login_form_shortcode');
        $this->loader->add_shortcode('passwordless_registration', $frontend, 'registration_form_shortcode');
        $this->loader->add_shortcode('passwordless_profile', $frontend, 'profile_page_shortcode');
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
     * Register email related hooks.
     */
    private function define_email_hooks()
    {
        // No direct hooks needed for the email class as it's used by other classes
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
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        $this->loader->run();

        // Add shortcode for the login form
        add_shortcode('passwordless_login_form', array($this, 'render_login_form'));

        // Handle form submission for passwordless login
        add_action('init', array($this, 'process_login_form'));

        // Handle magic login links
        add_action('init', array($this, 'process_magic_login'));

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Render the login form
     */
    public function render_login_form($atts)
    {
        $args = shortcode_atts(array(
            'redirect' => '',
        ), $atts);

        // Set redirect URL if provided
        if (!empty($args['redirect'])) {
            $_REQUEST['redirect_to'] = $args['redirect'];
        }

        // Enqueue the necessary scripts and styles specifically for this form
        wp_enqueue_style('my-passwordless-auth-style', MY_PASSWORDLESS_AUTH_URL . 'assets/css/passwordless-auth.css', array(), MY_PASSWORDLESS_AUTH_VERSION);
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'my-passwordless-auth-login-handler',
            MY_PASSWORDLESS_AUTH_URL . 'assets/js/login-handler.js',
            array('jquery'),
            MY_PASSWORDLESS_AUTH_VERSION,
            true
        );

        wp_localize_script(
            'my-passwordless-auth-login-handler',
            'my_passwordless_auth',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('my-passwordless-auth-magic-link'),
                'login_url' => wp_login_url()
            )
        );

        ob_start();
        include MY_PASSWORDLESS_AUTH_PATH . 'templates/login-form.php';
        return ob_get_clean();
    }

    /**
     * Process the login form submission
     */
    public function process_login_form()
    {
        // Check if this is our form submission
        if (!isset($_POST['passwordless_login_nonce']) || !wp_verify_nonce($_POST['passwordless_login_nonce'], 'passwordless-login-nonce')) {
            return;
        }

        $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : '';
        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';

        // Check for empty email
        if (empty($user_email)) {
            wp_redirect(add_query_arg('error', 'empty_email', wp_get_referer()));
            exit;
        }

        // Validate email format
        if (!is_email($user_email)) {
            wp_redirect(add_query_arg('error', 'invalid_email', wp_get_referer()));
            exit;
        }

        // Check if user exists
        $user = get_user_by('email', $user_email);
        if (!$user) {
            wp_redirect(add_query_arg('error', 'user_not_found', wp_get_referer()));
            exit;
        }

        // Generate and send magic login link using the Email class for consistent "From" headers
        $email_class = new My_Passwordless_Auth_Email();
        $sent = $email_class->send_magic_link($user_email);

        // Handle different error scenarios
        if ($sent === false) {
            // Email sending failed
            my_passwordless_auth_log("Failed to send login link to user email: $user_email", 'error');
            wp_redirect(add_query_arg('error', 'email_failed', wp_get_referer()));
            exit;
        } elseif ($sent === 'unverified') {
            // Email is not yet verified
            wp_redirect(add_query_arg('error', 'email_unverified', wp_get_referer()));
            exit;
        } elseif ($sent !== true) {
            // Any other error
            wp_redirect(add_query_arg('error', 'unknown_error', wp_get_referer()));
            exit;
        }

        // Success! Redirect with success message
        my_passwordless_auth_log("Login link successfully sent to user email: $user_email", 'info');
        $redirect_url = add_query_arg('sent', '1', wp_get_referer());
        if (!empty($redirect_to)) {
            $redirect_url = add_query_arg('redirect_to', urlencode($redirect_to), $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Process magic login requests
     */
    public function process_magic_login()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'magic_login') {
            return;
        }

        my_passwordless_auth_log("Magic login process initiated from class-passwordless-auth.php");

        // Process the magic login directly instead of calling an external function
        // Check if this is a magic login request
        if (!isset($_GET['uid']) || !isset($_GET['token'])) {
            return false;
        }

        my_passwordless_auth_log("Processing magic login request with uid: " . sanitize_text_field($_GET['uid']));

        $uid = sanitize_text_field($_GET['uid']);
        $encrypted_token = sanitize_text_field($_GET['token']);

        $user_id = my_passwordless_auth_decrypt_user_id($uid);

        if ($user_id === false) {
            my_passwordless_auth_log("Magic login failed - could not decrypt user ID from: $uid", 'error');
            $error = new WP_Error('invalid_user', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        my_passwordless_auth_log("Successfully decrypted user ID: $user_id");

        // Get stored token data for this user
        $stored_data = get_user_meta($user_id, 'passwordless_auth_login_token', true);

        if (empty($stored_data)) {
            my_passwordless_auth_log("Magic login failed - no token stored for user ID: $user_id", 'error');
            $error = new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Decrypt the token from the URL
        $token = my_passwordless_auth_decrypt_token_from_url($encrypted_token);
        if ($token === false) {
            my_passwordless_auth_log("Magic login failed - could not decrypt token from URL", 'error');
            $error = new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Check if stored data has correct format
        if (!is_array($stored_data) || !isset($stored_data['token']) || !isset($stored_data['expiration'])) {
            my_passwordless_auth_log("Magic login failed - token data format invalid for user ID: $user_id", 'error');
            $error = new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Encrypt the decrypted URL token using the database encryption method for comparison
        $encrypted_token_for_comparison = my_passwordless_auth_encrypt_token($token);

        // Check if token matches the stored encrypted token
        if ($stored_data['token'] !== $encrypted_token_for_comparison) {
            my_passwordless_auth_log("Magic login failed - token mismatch for user ID: $user_id", 'error');
            $error = new WP_Error('invalid_token', __('Invalid login link. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Check if token has expired
        if (time() > $stored_data['expiration']) {
            my_passwordless_auth_log("Magic login failed - token expired for user ID: $user_id", 'error');
            delete_user_meta($user_id, 'passwordless_auth_login_token');
            $error = new WP_Error('expired_token', __('This login link has expired. Please request a new one.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Get the user
        $user = get_user_by('id', $user_id);
        if (!$user) {
            my_passwordless_auth_log("Magic login failed - user ID $user_id not found", 'error');
            $error = new WP_Error('invalid_user', __('User not found. Please try again.', 'my-passwordless-auth'));
            wp_die(
                $error->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
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
        do_action('my_passwordless_auth_after_magic_login', $user);

        // Success! Redirect to requested page or default
        $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
        if (empty($redirect_to)) {
            $redirect_to = my_passwordless_auth_get_option('login_redirect', home_url());
        }

        $redirect_to = apply_filters('my_passwordless_auth_login_redirect', $redirect_to);
        my_passwordless_auth_log("Redirecting to: $redirect_to after successful login");
        wp_redirect($redirect_to);
        exit;
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('my-passwordless-auth-style', MY_PASSWORDLESS_AUTH_URL . 'assets/css/passwordless-auth.css', array(), MY_PASSWORDLESS_AUTH_VERSION);
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
