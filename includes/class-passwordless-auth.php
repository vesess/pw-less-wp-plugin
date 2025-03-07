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

class My_Passwordless_Auth {

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
    public function __construct() {
        $this->version = MY_PASSWORDLESS_AUTH_VERSION;
        $this->load_dependencies();
        $this->define_authentication_hooks();
        $this->define_registration_hooks();
        $this->define_profile_hooks();
        $this->define_frontend_hooks();
        $this->define_admin_hooks();
        $this->define_email_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-passwordless-auth-loader.php';

        /**
         * The classes that handle core plugin functionality.
         */
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-authentication.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-registration.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-profile.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-frontend.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-admin.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-email.php';
        require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/helpers.php';

        $this->loader = new My_Passwordless_Auth_Loader();
    }

    /**
     * Register authentication related hooks.
     */
    private function define_authentication_hooks() {
        $authentication = new My_Passwordless_Auth_Authentication();
        
        $this->loader->add_action('init', $authentication, 'init');
    }

    /**
     * Register registration related hooks.
     */
    private function define_registration_hooks() {
        $registration = new My_Passwordless_Auth_Registration();
        
        $this->loader->add_action('init', $registration, 'init');
    }

    /**
     * Register profile related hooks.
     */
    private function define_profile_hooks() {
        $profile = new My_Passwordless_Auth_Profile();
        
        $this->loader->add_action('init', $profile, 'init');
    }

    /**
     * Register frontend related hooks.
     */
    private function define_frontend_hooks() {
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
    private function define_admin_hooks() {
        $admin = new My_Passwordless_Auth_Admin();
        
        $this->loader->add_action('init', $admin, 'init');
    }

    /**
     * Register email related hooks.
     */
    private function define_email_hooks() {
        // No direct hooks needed for the email class as it's used by other classes
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
        
        // Add shortcode for the login form
        add_shortcode('passwordless_login_form', array($this, 'render_login_form'));
        
        // Handle form submission for passwordless login
        add_action('init', array($this, 'process_login_form'));
        
        // Handle magic login links
        add_action('init', array($this, 'process_magic_login'));
        
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add plugin settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Render the login form
     */
    public function render_login_form($atts) {
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
    public function process_login_form() {
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
        
        // Generate and send magic login link
        $sent = my_passwordless_auth_send_magic_link($user_email);
        
        if (!$sent) {
            wp_redirect(add_query_arg('error', 'email_failed', wp_get_referer()));
            exit;
        }
        
        // Success! Redirect with success message
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
    public function process_magic_login() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'magic_login') {
            return;
        }
        
        $result = my_passwordless_auth_process_magic_login();
        
        if ($result === true) {
            // Success! Redirect to requested page or default
            $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
            if (empty($redirect_to)) {
                $redirect_to = my_passwordless_auth_get_option('login_redirect', admin_url());
            }
            
            $redirect_to = apply_filters('my_passwordless_auth_login_redirect', $redirect_to);
            wp_redirect($redirect_to);
            exit;
        } elseif (is_wp_error($result)) {
            // Error occurred during login
            wp_die(
                $result->get_error_message(),
                __('Login Failed', 'my-passwordless-auth'),
                array(
                    'response' => 403,
                    'back_link' => true,
                )
            );
        }
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('my-passwordless-auth-style', MY_PASSWORDLESS_AUTH_URL . 'assets/css/passwordless-auth.css', array(), MY_PASSWORDLESS_AUTH_VERSION);
    }

    /**
     * Add plugin settings page to admin
     */
    public function add_admin_menu() {
        add_options_page(
            __('Passwordless Authentication', 'my-passwordless-auth'),
            __('Passwordless Auth', 'my-passwordless-auth'),
            'manage_options',
            'my-passwordless-auth',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('my_passwordless_auth_options', 'my_passwordless_auth_options');
        
        add_settings_section(
            'my_passwordless_auth_general',
            __('General Settings', 'my-passwordless-auth'),
            array($this, 'settings_section_callback'),
            'my-passwordless-auth'
        );
        
        add_settings_field(
            'login_redirect',
            __('Redirect After Login', 'my-passwordless-auth'),
            array($this, 'render_login_redirect_field'),
            'my-passwordless-auth',
            'my_passwordless_auth_general'
        );
        
        add_settings_field(
            'email_subject',
            __('Email Subject', 'my-passwordless-auth'),
            array($this, 'render_email_subject_field'),
            'my-passwordless-auth',
            'my_passwordless_auth_general'
        );
        
        add_settings_field(
            'email_template',
            __('Email Template', 'my-passwordless-auth'),
            array($this, 'render_email_template_field'),
            'my-passwordless-auth',
            'my_passwordless_auth_general'
        );
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure your passwordless authentication settings here.', 'my-passwordless-auth') . '</p>';
    }

    /**
     * Render login redirect field
     */
    public function render_login_redirect_field() {
        $options = get_option('my_passwordless_auth_options');
        $redirect = isset($options['login_redirect']) ? $options['login_redirect'] : admin_url();
        ?>
        <input type="text" name="my_passwordless_auth_options[login_redirect]" value="<?php echo esc_attr($redirect); ?>" class="regular-text" />
        <p class="description"><?php _e('URL to redirect users after successful login.', 'my-passwordless-auth'); ?></p>
        <?php
    }

    /**
     * Render email subject field
     */
    public function render_email_subject_field() {
        $options = get_option('my_passwordless_auth_options');
        $subject = isset($options['email_subject']) ? $options['email_subject'] : sprintf(__('Login link for %s', 'my-passwordless-auth'), get_bloginfo('name'));
        ?>
        <input type="text" name="my_passwordless_auth_options[email_subject]" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
        <p class="description"><?php _e('Subject line for the login link email.', 'my-passwordless-auth'); ?></p>
        <?php
    }

    /**
     * Render email template field
     */
    public function render_email_template_field() {
        $options = get_option('my_passwordless_auth_options');
        $default_template = __("Hello {display_name},\n\nClick the link below to log in:\n\n{login_link}\n\nThis link will expire in 15 minutes.\n\nIf you did not request this login link, please ignore this email.\n\nRegards,\n{site_name}", 'my-passwordless-auth');
        $template = isset($options['email_template']) ? $options['email_template'] : $default_template;
        ?>
        <textarea name="my_passwordless_auth_options[email_template]" rows="10" class="large-text code"><?php echo esc_textarea($template); ?></textarea>
        <p class="description">
            <?php _e('Available placeholders:', 'my-passwordless-auth'); ?><br>
            <code>{display_name}</code> - <?php _e('User\'s display name', 'my-passwordless-auth'); ?><br>
            <code>{login_link}</code> - <?php _e('The magic login link', 'my-passwordless-auth'); ?><br>
            <code>{site_name}</code> - <?php _e('Your site name', 'my-passwordless-auth'); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('my_passwordless_auth_options');
                do_settings_sections('my-passwordless-auth');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php _e('How to Use', 'my-passwordless-auth'); ?></h2>
            <p><?php _e('Add the login form to any page or post using this shortcode:', 'my-passwordless-auth'); ?></p>
            <p><code>[passwordless_login_form]</code></p>
            
            <p><?php _e('To specify a custom redirect after login:', 'my-passwordless-auth'); ?></p>
            <p><code>[passwordless_login_form redirect="/dashboard/"]</code></p>
        </div>
        <?php
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return My_Passwordless_Auth_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
