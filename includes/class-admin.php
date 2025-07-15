<?php
/**
 * Handles admin functionality.
 */
class Vesess_Easyauth_Admin {
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
            'Passwordless Authentication',
            'Passwordless Auth',
            'manage_options',
            'vesess_easyauth',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin styles.
     */
    public function enqueue_styles($hook) {
        if ('settings_page_vesess_easyauth' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'vesess_easyauth-admin',
            VESESS_EASYAUTH_URL . 'assets/css/admin.css',
            array(),
            VESESS_EASYAUTH_VERSION
        );
    }    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'vesess_easyauth_options', 
            'vesess_easyauth_options',
            array($this, 'sanitize_options')
        );
        
        add_settings_section(
            'vesess_easyauth_general',
            'General Settings',
            array($this, 'settings_section_callback_general'),
            'vesess_easyauth'
        );
        
        add_settings_field(
            'login_redirect',
            'Redirect After Login',
            array($this, 'render_login_redirect_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );

        add_settings_field(
            'user_home_url',
            'User Home URL',
            array($this, 'render_user_home_url_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );
        
        add_settings_field(
            'email_subject',
            'Email Subject',
            array($this, 'render_email_subject_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );
        
        add_settings_field(
            'email_template',
            'Email Template',
            array($this, 'render_email_template_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );
        add_settings_section(
            'vesess_easyauth_main',
            'Main Settings',
            array($this, 'settings_section_callback_main'),
            'vesess_easyauth'
        );
        
        add_settings_field(
            'email_from_name',
            'Email From Name',
            array($this, 'email_from_name_callback'),
            'vesess_easyauth',
            'vesess_easyauth_main'
        );
        
          add_settings_field(
            'code_expiration',
            'Login Code Expiration (minutes)',
            array($this, 'code_expiration_callback'),
            'vesess_easyauth',
            'vesess_easyauth_main'
        );
        
        // Theme compatibility setting
        add_settings_field(
            'use_theme_styles',
            'Use Theme Styling',
            array($this, 'render_theme_styles_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );
        
        // Auth Logs menu visibility setting
        add_settings_field(
            'show_auth_logs_menu',
            'Show Auth Logs Menu',
            array($this, 'render_show_auth_logs_field'),
            'vesess_easyauth',
            'vesess_easyauth_general'
        );
    }

    /**
     * Section description callback.
     */    public function settings_section_callback_general() {
        echo '<p>Configure your passwordless authentication settings.</p>';
    }
    public function settings_section_callback_main() {
        echo '<p>Configure your passwordless authentication settings.</p>';
    }
    /**
     * Email From Name field callback.
     */
    public function email_from_name_callback() {
        $options = get_option('vesess_easyauth_options');
        $value = isset($options['email_from_name']) ? $options['email_from_name'] : get_bloginfo('name');
        echo '<input type="text" name="vesess_easyauth_options[email_from_name]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Email From Address field callback.
     *  To be added in the future
     */
    public function email_from_address_callback() {
        $options = get_option('vesess_easyauth_options');
        $value = isset($options['email_from_address']) ? $options['email_from_address'] : get_bloginfo('admin_email');
        echo '<input type="email" name="vesess_easyauth_options[email_from_address]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Code Expiration field callback.
     */
    public function code_expiration_callback() {
        $options = get_option('vesess_easyauth_options');
        $value = isset($options['code_expiration']) ? $options['code_expiration'] : 15;
        echo '<input type="number" name="vesess_easyauth_options[code_expiration]" value="' . esc_attr($value) . '" min="1" max="60" step="1" class="small-text">';
    }
    
    /**
     * Render the settings page.
     */    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check security status
        $security_status = vesess_easyauth_validate_security();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($security_status['status'] !== 'secure'): ?>
            <div class="notice notice-error">
                <h3>Security Issues Detected</h3>
                <ul>
                    <?php foreach ($security_status['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Please address these issues before using this plugin in production.</strong></p>
            </div>
        
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('vesess_easyauth_options');
                do_settings_sections('vesess_easyauth');
                submit_button();
                ?>
            </form>
            <hr>
            
            <h2>How to Use</h2>
            <p>Add the login form to any page or post using this shortcode:</p>
            <p><code>[vesess_easyauth_login_form]</code></p>
            
            <p>To specify a custom redirect after login:</p>
            <p><code>[vesess_easyauth_login_form redirect="/dashboard/"]</code></p>
        </div>
        <?php
    }

    /**
     * Render login redirect field
     */
    public function render_login_redirect_field() {
        $options = get_option('vesess_easyauth_options');
        $redirect = isset($options['login_redirect']) ? $options['login_redirect'] : admin_url();
        ?>
        <input type="text" name="vesess_easyauth_options[login_redirect]" value="<?php echo esc_attr($redirect); ?>" class="regular-text" />
        <p class="description">URL to redirect users after successful login.</p>
        <?php
    }

    /**
     * Render user home URL field
     */
    public function render_user_home_url_field() {
        $options = get_option('vesess_easyauth_options');
        $home_url = isset($options['user_home_url']) ? $options['user_home_url'] : home_url();
        ?>
        <input type="text" name="vesess_easyauth_options[user_home_url]" value="<?php echo esc_attr($home_url); ?>" class="regular-text" />
        <p class="description">URL for the user's home page.</p>
        <?php
    }    /**
     * Render email subject field
     */    public function render_email_subject_field() {
        $options = get_option('vesess_easyauth_options');
        $default_subject = 'Login link for ' . get_bloginfo('name');
        $subject = isset($options['email_subject']) ? sanitize_text_field($options['email_subject']) : $default_subject;
        ?>
        <input type="text" name="vesess_easyauth_options[email_subject]" value="<?php echo esc_attr($subject); ?>" class="regular-text" />
        <p class="description">Subject line for the login link email.</p>
        <?php
    }

    /**
     * Render email template field
     */
    public function render_email_template_field() {
        $options = get_option('vesess_easyauth_options');
        $expiration_minutes = isset($options['code_expiration']) ? (int)$options['code_expiration'] : 15;        $default_template = "Hello {display_name},\n\nClick the link below to log in:\n\n{login_link}\n\nThis link will expire in {expiration_minutes} minutes.\n\nIf you did not request this login link, please ignore this email.\n\nRegards,\n{site_name}";
        $template = isset($options['email_template']) ? $options['email_template'] : $default_template;
        ?>
        <textarea name="vesess_easyauth_options[email_template]" rows="10" class="large-text code"><?php echo esc_textarea($template); ?></textarea>
        <p class="description">
            Available placeholders:<br>
            <code>{display_name}</code> - User's display name<br>
            <code>{login_link}</code> - The magic login link<br>
            <code>{site_name}</code> - Your site name<br>
            <code>{expiration_minutes}</code> - Link expiration time in minutes (from settings)
        </p>
        <?php
    }    /**
     * Sanitize plugin options
     * 
     * This function properly handles checkboxes by explicitly setting them to 'no'
     * when they are not checked, rather than removing them from the options array
     * 
     * @param array $input The raw input from the form
     * @return array The sanitized options
     */
    public function sanitize_options($input) {
        // Get the existing options
        $existing_options = get_option('vesess_easyauth_options', array());
        
        // Process the checkbox options that might be missing when unchecked
        $checkbox_options = array(
            'enable_wp_login_integration',
            'use_theme_styles',
            'show_auth_logs_menu'
        );
        
        // For each known checkbox option, set it to 'no' if it's not present in the input
        foreach ($checkbox_options as $checkbox) {
            if (!isset($input[$checkbox])) {
                $input[$checkbox] = 'no';
            }
        }
        
        // Preserve other existing settings that aren't in the current input
        foreach ($existing_options as $key => $value) {
            if (!isset($input[$key])) {
                $input[$key] = $value;
            }
        }
        
        // Merge with existing options and return
        return is_array($input) ? $input : $existing_options;
    }

    /**
     * Render theme styles toggle field
     */
    public function render_theme_styles_field() {
        $options = get_option('vesess_easyauth_options');
        $checked = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes';
        ?>
        <input type="checkbox" name="vesess_easyauth_options[use_theme_styles]" value="yes" <?php checked($checked); ?> />
        <p class="description">Enable this to use your theme's styling instead of plugin default styles.</p>
        <?php
    }

    /**
     * Render show auth logs menu toggle field
     */
    public function render_show_auth_logs_field() {
        $options = get_option('vesess_easyauth_options');
        $checked = isset($options['show_auth_logs_menu']) && $options['show_auth_logs_menu'] === 'yes';
        ?>
        <input type="checkbox" name="vesess_easyauth_options[show_auth_logs_menu]" value="yes" <?php checked($checked); ?> />
        <p class="description">Enable this to show the Auth Logs menu in the settings. When disabled, authentication logging is also disabled.</p>
        <?php
    }
}
