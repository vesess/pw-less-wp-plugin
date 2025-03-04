<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
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
