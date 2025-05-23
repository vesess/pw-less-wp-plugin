<?php
/**
 * Integrates passwordless login with the standard WordPress login form (wp-login.php)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle integration with the standard WordPress login form
 */
class My_Passwordless_Auth_Login_Integration {
    /**
     * Initialize the class and set its hooks
     */
    public function init() {
        // Revert to login_form hook and we'll use CSS to position it correctly
        add_action('login_form', array($this, 'add_passwordless_login_button'));
        
        // Add the passwordless login option to the lost password form removed for now
        // add_action('lostpassword_form', array($this, 'add_passwordless_login_button_lostpw'));
        
        // Add inline login form with AJAX functionality to the login page
        add_action('login_footer', array($this, 'add_inline_login_form'));
        
        // Add admin setting
        add_action('admin_init', array($this, 'add_admin_settings'));
        
        // Add custom CSS to position the passwordless login after standard login button
        add_action('login_enqueue_scripts', array($this, 'add_custom_login_css'));
    }
    
    /**
     * Add admin settings for the integration
     */
    public function add_admin_settings() {        add_settings_field(
            'enable_wp_login_integration',
            'Enable Admin Login Integration',
            array($this, 'render_wp_login_integration_field'),
            'my-passwordless-auth',
            'my_passwordless_auth_general'
        );
        
        // Make sure it's registered in the whitelist
        register_setting('my_passwordless_auth_options', 'my_passwordless_auth_options', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize the plugin's settings
     *
     * @param array $input The submitted settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize enable_wp_login_integration checkbox
        if (isset($input['enable_wp_login_integration'])) {
            $sanitized['enable_wp_login_integration'] = ($input['enable_wp_login_integration'] === 'yes') ? 'yes' : 'no';
        } else {
            $sanitized['enable_wp_login_integration'] = 'no';
        }
        
        // Preserve other existing settings
        $existing_options = get_option('my_passwordless_auth_options', array());
        foreach ($existing_options as $key => $value) {
            if (!isset($sanitized[$key])) {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render the admin setting field
     */
    public function render_wp_login_integration_field() {
        $options = get_option('my_passwordless_auth_options');
        $checked = isset($options['enable_wp_login_integration']) ? $options['enable_wp_login_integration'] === 'yes' : true;
        ?>
        <input type="checkbox" name="my_passwordless_auth_options[enable_wp_login_integration]" value="yes" <?php checked($checked); ?> />
        <p class="description">Add passwordless login option to the WordPress login screen (wp-login.php)</p>
        <?php
    }
      /**
     * Check if admin login integration is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public function is_integration_enabled() {
        $options = get_option('my_passwordless_auth_options', []);
        
        // If the option doesn't exist yet (first installation), default to true
        if (!isset($options['enable_wp_login_integration'])) {
            return true;
        }
        
        // Otherwise, return true only if it's explicitly set to 'yes'
        return $options['enable_wp_login_integration'] === 'yes';
    }
      /**
     * Add passwordless login button to the WordPress login form
     */
    public function add_passwordless_login_button() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Create a nonce for security
        $nonce = wp_create_nonce('passwordless-login-nonce');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <div class="pwless-login-container" style="text-align: center; margin: 15px 0;">            <p>or</p>
            <button type="button" id="pwless-login-btn" class="button button-primary" style="display: block; width: 100%; text-align: center; padding: 10px 0; margin-bottom: 10px;">
                Log In with Email Code
            </button>
            <p class="pwless-description" style="font-size: 13px; color: #666;">
                No password needed: receive a login link or code via email.
            </p>
            
            <!-- Hidden form fields for the passwordless login -->
            <input type="hidden" name="passwordless_login_nonce" id="passwordless_login_nonce" value="<?php echo esc_attr($nonce); ?>">
            <div id="pwless-messages" style="margin-top: 10px;"></div>
        </div>
        <?php
    }
      /**
     * Add passwordless login option to the lost password form
     */
    public function add_passwordless_login_button_lostpw() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        // Create a nonce for security
        $nonce = wp_create_nonce('passwordless-login-nonce');
        ?>
        <div class="pwless-login-container" style="text-align: center; margin: 15px 0;">            <p>or</p>
            <button type="button" id="pwless-login-btn-lost" class="button button-primary" style="display: block; width: 100%; text-align: center; padding: 10px 0;">
                Use Passwordless Login Instead
            </button>
            <input type="hidden" name="passwordless_login_nonce_lost" id="passwordless_login_nonce_lost" value="<?php echo esc_attr($nonce); ?>">
            <div id="pwless-messages-lost" style="margin-top: 10px;"></div>
        </div>
        <?php
    }
      /**
     * Add inline JavaScript for passwordless login to wp-login.php
     */    public function add_inline_login_form() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handler to the passwordless login button on main login form
            var pwlessBtn = document.querySelector('#pwless-login-btn');
            var usernameField = document.querySelector('#user_login');
            var messagesContainer = document.querySelector('#pwless-messages');
              if (pwlessBtn && usernameField) {
                pwlessBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get username or email from the username field
                    var userInput = usernameField.value.trim();
                    var nonce = document.querySelector('#passwordless_login_nonce').value;
                    
                    if (!userInput) {
                        // Show error if field is empty
                        messagesContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">Please enter your username or email address in the field above.</div>';
                        return;
                    }
                    
                    // Disable button                    var originalBtnText = pwlessBtn.textContent;
                    pwlessBtn.textContent = 'Sending...';
                    pwlessBtn.disabled = true;
                    
                    // Create form data
                    var data = new URLSearchParams({
                        'action': 'process_passwordless_login',
                        'passwordless_login_nonce': nonce,
                        'user_input': userInput
                    }).toString();
                    
                    // Send AJAX request
                    fetch('<?php echo esc_url($ajax_url); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: data
                    })
                    .then(response => response.json())
                    .then(response => {
                        // Re-enable button
                        pwlessBtn.textContent = originalBtnText;
                        pwlessBtn.disabled = false;
                        
                        if (response.success) {
                            messagesContainer.innerHTML = '<div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px;">' + response.data + '</div>';
                        } else {
                            messagesContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">' + response.data + '</div>';
                        }
                    })
                    .catch(error => {
                        pwlessBtn.textContent = originalBtnText;
                        pwlessBtn.disabled = false;
                        messagesContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">An error occurred. Please try again.</div>';
                        console.error('Error:', error);
                    });
                });
            }
            
            // Add click handler to the lost password button
            var pwlessBtnLost = document.querySelector('#pwless-login-btn-lost');
            var usernameLostField = document.querySelector('#user_login');  // On lost password page, the field is the same
            var messagesLostContainer = document.querySelector('#pwless-messages-lost');
              if (pwlessBtnLost && usernameLostField) {
                pwlessBtnLost.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get username or email from the username field
                    var userInput = usernameLostField.value.trim();
                    var nonce = document.querySelector('#passwordless_login_nonce_lost').value;
                    
                    if (!userInput) {
                        // Show error if field is empty
                        messagesLostContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">Please enter your username or email address in the field above.</div>';
                        return;
                    }
                    
                    // Disable button                    var originalBtnText = pwlessBtnLost.textContent;
                    pwlessBtnLost.textContent = 'Sending...';
                    pwlessBtnLost.disabled = true;
                    
                    // Create form data
                    var data = new URLSearchParams({
                        'action': 'process_passwordless_login',
                        'passwordless_login_nonce': nonce,
                        'user_input': userInput
                    }).toString();
                    
                    // Send AJAX request
                    fetch('<?php echo esc_url($ajax_url); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: data
                    })
                    .then(response => response.json())
                    .then(response => {
                        // Re-enable button
                        pwlessBtnLost.textContent = originalBtnText;
                        pwlessBtnLost.disabled = false;
                        
                        if (response.success) {
                            messagesLostContainer.innerHTML = '<div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px;">' + response.data + '</div>';
                        } else {
                            messagesLostContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">' + response.data + '</div>';
                        }
                    })
                    .catch(error => {
                        pwlessBtnLost.textContent = originalBtnText;
                        pwlessBtnLost.disabled = false;
                        messagesLostContainer.innerHTML = '<div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">An error occurred. Please try again.</div>';
                        console.error('Error:', error);
                    });
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add custom CSS to properly position the passwordless login button
     * after the standard WordPress login button
     */
    public function add_custom_login_css() {
        if (!$this->is_integration_enabled()) {
            return;
        }
        ?>
        <style type="text/css">
            /* Move the passwordless login form content below the submit button */
            #loginform .pwless-login-container {
                order: 100; /* High value ensures it appears after other elements */
                margin-top: 20px !important;
            }
            
            /* Make the form use flexbox for ordering elements */
            #loginform {
                display: flex;
                flex-direction: column;
            }
            
            /* Ensure the submit button appears before our content */
            #loginform .submit {
                order: 90;
            }
            
            /* Ensure our content is not hidden if login form has overflow hidden */
            #loginform {
                overflow: visible !important;
            }
        </style>
        <?php
    }
}
