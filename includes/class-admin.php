<?php
/**
 * Handles admin functionality.
 */
class My_Passwordless_Auth_Admin {
    /**
     * Initialize the class and set its hooks.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Add menu items to the admin panel.
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
     * Enqueue admin styles.
     */
    public function enqueue_styles($hook) {
        if ('settings_page_my-passwordless-auth' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'my-passwordless-auth-admin',
            MY_PASSWORDLESS_AUTH_URL . 'assets/css/admin.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting('my_passwordless_auth_options', 'my_passwordless_auth_options');
        
        add_settings_section(
            'my_passwordless_auth_main',
            __('General Settings', 'my-passwordless-auth'),
            array($this, 'settings_section_callback'),
            'my-passwordless-auth'
        );
        
        add_settings_field(
            'email_from_name',
            __('Email From Name', 'my-passwordless-auth'),
            array($this, 'email_from_name_callback'),
            'my-passwordless-auth',
            'my_passwordless_auth_main'
        );
        
        add_settings_field(
            'email_from_address',
            __('Email From Address', 'my-passwordless-auth'),
            array($this, 'email_from_address_callback'),
            'my-passwordless-auth',
            'my_passwordless_auth_main'
        );
        
        add_settings_field(
            'code_expiration',
            __('Login Code Expiration (minutes)', 'my-passwordless-auth'),
            array($this, 'code_expiration_callback'),
            'my-passwordless-auth',
            'my_passwordless_auth_main'
        );
        
        // Removed URL blocker section
    }

    /**
     * Section description callback.
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure your passwordless authentication settings.', 'my-passwordless-auth') . '</p>';
    }

    /**
     * Email From Name field callback.
     */
    public function email_from_name_callback() {
        $options = get_option('my_passwordless_auth_options');
        $value = isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
        echo '<input type="text" name="my_passwordless_auth_options[email_from_name]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Email From Address field callback.
     */
    public function email_from_address_callback() {
        $options = get_option('my_passwordless_auth_options');
        $value = isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
        echo '<input type="email" name="my_passwordless_auth_options[email_from_address]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Code Expiration field callback.
     */
    public function code_expiration_callback() {
        $options = get_option('my_passwordless_auth_options');
        $value = isset($options['code_expiration']) ? $options['code_expiration'] : 15;
        echo '<input type="number" name="my_passwordless_auth_options[code_expiration]" value="' . esc_attr($value) . '" min="1" max="60" step="1" class="small-text">';
    }
    
    // Removed blocked_urls_callback

    /**
     * Render the settings page.
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
        </div>
        <?php
    }
}
