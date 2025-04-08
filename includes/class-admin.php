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
            'my_passwordless_auth_general',
            __('General Settings', 'my-passwordless-auth'),
            array($this, 'settings_section_callback_general'),
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
            'user_home_url',
            __('User Home URL', 'my-passwordless-auth'),
            array($this, 'render_user_home_url_field'),
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
        add_settings_section(
            'my_passwordless_auth_main',
            __('Main Settings', 'my-passwordless-auth'),
            array($this, 'settings_section_callback_main'),
            'my-passwordless-auth'
        );
        
        add_settings_field(
            'email_from_name',
            __('Email From Name', 'my-passwordless-auth'),
            array($this, 'email_from_name_callback'),
            'my-passwordless-auth',
            'my_passwordless_auth_main'
        );
        
        // To be added in the future
        // add_settings_field(
        //     'email_from_address',
        //     __('Email From Address', 'my-passwordless-auth'),
        //     array($this, 'email_from_address_callback'),
        //     'my-passwordless-auth',
        //     'my_passwordless_auth_main'
        // );
        
        add_settings_field(
            'code_expiration',
            __('Login Code Expiration (minutes)', 'my-passwordless-auth'),
            array($this, 'code_expiration_callback'),
            'my-passwordless-auth',
            'my_passwordless_auth_main'
        );
    }

    /**
     * Section description callback.
     */
    public function settings_section_callback_general() {
        echo '<p>' . esc_html__('Configure your passwordless authentication settings.', 'my-passwordless-auth') . '</p>';
    }
    public function settings_section_callback_main() {
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
     *  To be added in the future
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
     * Render user home URL field
     */
    public function render_user_home_url_field() {
        $options = get_option('my_passwordless_auth_options');
        $home_url = isset($options['user_home_url']) ? $options['user_home_url'] : home_url();
        ?>
        <input type="text" name="my_passwordless_auth_options[user_home_url]" value="<?php echo esc_attr($home_url); ?>" class="regular-text" />
        <p class="description"><?php _e('URL for the user\'s home page.', 'my-passwordless-auth'); ?></p>
        <?php
    }

    /**
     * Render email subject field
     */
    public function render_email_subject_field() {
        $options = get_option('my_passwordless_auth_options');
        $default_subject = sprintf(esc_html__('Login link for %s', 'my-passwordless-auth'), esc_html(get_bloginfo('name')));
        $subject = isset($options['email_subject']) ? sanitize_text_field($options['email_subject']) : $default_subject;
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
        $expiration_minutes = isset($options['code_expiration']) ? (int)$options['code_expiration'] : 15;
        $default_template = sprintf(
            __("Hello {display_name},\n\nClick the link below to log in:\n\n{login_link}\n\nThis link will expire in {expiration_minutes} minutes.\n\nIf you did not request this login link, please ignore this email.\n\nRegards,\n{site_name}", 'my-passwordless-auth'),
            $expiration_minutes
        );
        $template = isset($options['email_template']) ? $options['email_template'] : $default_template;
        ?>
        <textarea name="my_passwordless_auth_options[email_template]" rows="10" class="large-text code"><?php echo esc_textarea($template); ?></textarea>
        <p class="description">
            <?php _e('Available placeholders:', 'my-passwordless-auth'); ?><br>
            <code>{display_name}</code> - <?php _e('User\'s display name', 'my-passwordless-auth'); ?><br>
            <code>{login_link}</code> - <?php _e('The magic login link', 'my-passwordless-auth'); ?><br>
            <code>{site_name}</code> - <?php _e('Your site name', 'my-passwordless-auth'); ?><br>
            <code>{expiration_minutes}</code> - <?php _e('Link expiration time in minutes (from settings)', 'my-passwordless-auth'); ?>
        </p>
        <?php
    }
}
