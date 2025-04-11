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

<div class="passwordless-login-container">
    <form id="passwordless-login-form" class="passwordless-login-form">
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

<style>
.wp-block-post-title {
    display: none;
}

.passwordless-login-container {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.messages {
    margin-bottom: 20px;
}

.message {
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.button-primary {
    background-color: #0073aa;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 15px;
    width: 100%;
}

.button-primary:hover {
    background-color: #005a87;
}

.button-primary:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
}

.passwordless-submit {
    margin-top: 20px;
}

.passwordless-login-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.passwordless-login-form input[type="email"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.passwordless-info {
    margin-top: 15px;
    font-size: 0.9em;
    color: #666;
    text-align: center;
}

.login-register-link {
    margin-top: 20px;
    text-align: center;
}
</style>