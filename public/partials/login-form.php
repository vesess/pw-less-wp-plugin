<?php
/**
 * Passwordless login form template
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Error messages will be handled by JavaScript

// Check if a success message should be displayed
$success_message = '';
if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $success_message = __('Login link sent! Please check your email.', 'my-passwordless-auth');
}

// Get redirect URL after successful login
$redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
if (empty($redirect_to)) {
    $redirect_to = home_url();
}
?>

<div class="passwordless-login-container <?php echo esc_attr($theme_compat_class); ?>">
    <form id="passwordless-login-form" class="passwordless-login-form <?php echo esc_attr($theme_compat_class); ?>">
        <div class="messages"></div>
        
        <p>
            <label for="user_email"><?php _e('Email Address', 'my-passwordless-auth'); ?></label>
            <input type="email" name="user_email" id="user_email" class="input" required />
        </p>
        
        <p class="passwordless-submit">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
            <input type="hidden" name="action" value="process_login" />
            <?php wp_nonce_field('passwordless-login-nonce', 'passwordless_login_nonce'); ?>
            <button type="submit" name="wp-submit" id="wp-submit" class="button button-primary">
                <?php _e('Send Login Link', 'my-passwordless-auth'); ?>
            </button>
        </p>
        
        <p class="login-register-link">
            <?php _e('First time user?', 'my-passwordless-auth'); ?>
            <a href="<?php echo esc_url(home_url('/registration')); ?>"><?php _e('Register here', 'my-passwordless-auth'); ?></a>
        </p>
    </form>
    
    <p class="passwordless-info">
        <?php _e('Enter your email address and we\'ll send you a link to log in.', 'my-passwordless-auth'); ?>
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordless-login-form');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const emailField = document.getElementById('user_email');
        const submitBtn = document.getElementById('wp-submit');
        const messagesContainer = form.querySelector('.messages');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Disable submit button during submission
        submitBtn.textContent = 'Sending...';
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
            submitBtn.textContent = 'Send Login Link';
            submitBtn.disabled = false;
            
            if (response.success) {
                // Show success message
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                // Clear form
                form.reset();
            } else {
                // Show error message
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            // Re-enable submit button
            submitBtn.textContent = 'Send Login Link';
            submitBtn.disabled = false;
            
            // Show error message
            messagesContainer.innerHTML = '<div class="message error-message">An unexpected error occurred. Please try again later.</div>';
            console.error('Login error:', error);
        });
    });
});
</script>

<!-- Styles now loaded from passwordless-auth.css -->
<?php
// Allow theme styling to override plugin styles if enabled in settings
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>