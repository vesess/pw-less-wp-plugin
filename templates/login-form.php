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

// Get form action URL (current page by default)
$form_action = isset($form_action) ? $form_action : '';
if (empty($form_action)) {
    $form_action = esc_url(add_query_arg(null, null));
}

// Get redirect URL after successful login
$redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
if (empty($redirect_to)) {
    $redirect_to = home_url();
}
?>

<div class="passwordless-login-container">
    <div id="error-message" class="passwordless-error-message" style="display: none;"></div>
    
    <?php if (!empty($success_message)) : ?>
        <div class="passwordless-success-message">
            <?php echo esc_html($success_message); ?>
        </div>
    <?php else : ?>
        <form id="passwordless-login-form" class="passwordless-login-form" method="post" action="<?php echo esc_url($form_action); ?>">
            <p>
                <label for="user_email"><?php _e('Email Address', 'my-passwordless-auth'); ?></label>
                <input type="email" name="user_email" id="user_email" value="<?php echo esc_attr(isset($_POST['user_email']) ? $_POST['user_email'] : ''); ?>" class="input" required />
            </p>
            
            <p class="passwordless-submit">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                <input type="hidden" name="passwordless_login_nonce" value="<?php echo wp_create_nonce('passwordless-login-nonce'); ?>" />
                <button type="submit" name="wp-submit" id="wp-submit" class="button button-primary">
                    <?php _e('Send Login Link', 'my-passwordless-auth'); ?>
                </button>
            </p>
            
            <p class="login-register-link">
                <?php _e('First time user?', 'my-passwordless-auth'); ?>
                <!-- change the url if it is broken to /index.php/ -->
                <a href="<?php echo esc_url(home_url('/registration')); ?>"><?php _e('Register here', 'my-passwordless-auth'); ?></a>
            </p>
        </form>
        
        <p class="passwordless-info">
            <?php _e('Enter your email address and we\'ll send you a link to log in.', 'my-passwordless-auth'); ?>
        </p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const errorMessage = urlParams.get('error_message');
    const errorDiv = document.getElementById('error-message');
    
    if (errorMessage) {
        errorDiv.textContent = decodeURIComponent(errorMessage);
        errorDiv.style.display = 'block';
    }
});
</script>

<style>
/* Remove this .wp-block-post-title if it interfere with other styles */
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

.passwordless-error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 3px;
    border: 1px solid #f5c6cb;
}

.passwordless-success-message {
    background-color: #d4edda;
    color: #155724;
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 3px;
    border: 1px solid #c3e6cb;
}

.passwordless-info {
    margin-top: 15px;
    font-size: 0.9em;
    color: #666;
    text-align: center;
}
</style>