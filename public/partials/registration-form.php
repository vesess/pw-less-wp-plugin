<?php
/**
 * Template for the registration form
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!get_option('users_can_register')) {
    echo '<p>' . esc_html__('Registration is currently disabled.', 'my-passwordless-auth') . '</p>';
    return;
}
?>
<!-- Styles now loaded from passwordless-auth.css -->
<?php
// Allow theme styling to override plugin styles if enabled in settings
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>
<div class="my-passwordless-auth-container registration-form-container <?php echo esc_attr($theme_compat_class); ?>">
    <form id="my-passwordless-auth-registration-form" class="my-passwordless-auth-form <?php echo esc_attr($theme_compat_class); ?>">
        <h2><?php _e('Register New Account', 'my-passwordless-auth'); ?></h2>
        
        <div class="messages"></div>
        
        <div class="form-row">
            <label for="email"><?php _e('Email Address', 'my-passwordless-auth'); ?> <span class="required">*</span></label>
            <input type="email" name="email" id="email" required />
            <p class="description"><?php _e('You\'ll use this email to log in.', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row">
            <label for="username"><?php _e('Username', 'my-passwordless-auth'); ?></label>
            <input type="text" name="username" id="username" />
            <p class="description"><?php _e('Leave this field empty to use your email address as username.', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row">
            <label for="display_name"><?php _e('Display Name', 'my-passwordless-auth'); ?></label>
            <input type="text" name="display_name" id="display_name" />
            <p class="description"><?php _e('This is how your name will be shown on the site.', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row button-row">
            <input type="submit" value="<?php esc_attr_e('Register', 'my-passwordless-auth'); ?>" class="submit-btn" />
        </div>
        
        <p class="login-register-link">
            <?php _e('Already have an account?', 'my-passwordless-auth'); ?>
            <!-- change the url if it is broken to /index.php/ -->
            <a href="<?php echo esc_url(home_url('/login')); ?>"><?php _e('Login here', 'my-passwordless-auth'); ?></a>
        </p>
        <input type="hidden" name="action" value="register_new_user" />
        <?php wp_nonce_field('registration_nonce', 'nonce'); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('my-passwordless-auth-registration-form');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault(); 

        const emailField = document.getElementById('email');
        const usernameField = document.getElementById('username');
        const displayNameField = document.getElementById('display_name');
        const messagesContainer = form.querySelector('.messages');
        
        if (!usernameField.value.trim()) {
            usernameField.value = emailField.value;
        }
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Disable submit button during submission
        const submitBtn = form.querySelector('.submit-btn');
        submitBtn.value = 'Registering...';
        submitBtn.disabled = true;
        
        // Send AJAX request
        const formData = new FormData(form);
        const data = new URLSearchParams();
        for (const pair of formData) {
            data.append(pair[0], pair[1]);
        }
          fetch('<?php echo esc_url($ajax_url); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-WP-Nonce': '<?php echo esc_attr($nonce); ?>'
            },
            body: data
        })
        .then(response => response.json())
        .then(response => {
            // Re-enable submit button
            submitBtn.value = 'Register';
            submitBtn.disabled = false;
            
            if (response.success) {
                // Show success message
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                // Clear form inputs
                form.reset();
            } else {
                // Show error message
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            // Re-enable submit button
            submitBtn.value = 'Register';
            submitBtn.disabled = false;
            
            // Show error message
            messagesContainer.innerHTML = '<div class="message error-message">An unexpected error occurred. Please try again later.</div>';
            console.error('Registration error:', error);        });
    });
});
</script>
