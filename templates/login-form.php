<?php
/**
 * Passwordless login form template
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if there are any error messages to display
$error_message = '';
if (isset($_GET['error']) && !empty($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_email':
            $error_message = __('Please enter your email address.', 'my-passwordless-auth');
            break;
        case 'invalid_email':
            $error_message = __('Please enter a valid email address.', 'my-passwordless-auth');
            break;
        case 'user_not_found':
            $error_message = __('No user found with that email address.', 'my-passwordless-auth');
            break;
        case 'email_failed':
            $error_message = __('Failed to send the login link. Please try again later.', 'my-passwordless-auth');
            break;
        default:
            $error_message = __('An unknown error occurred. Please try again.', 'my-passwordless-auth');
            break;
    }
}

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
    <?php if (!empty($error_message)) : ?>
        <div class="passwordless-error-message">
            <?php echo esc_html($error_message); ?>
        </div>
    <?php endif; ?>
    
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
                <a href="<?php echo esc_url(home_url('/index.php/registration')); ?>"><?php _e('Register here', 'my-passwordless-auth'); ?></a>
            </p>
        </form>
        
        <p class="passwordless-info">
            <?php _e('Enter your email address and we\'ll send you a link to log in.', 'my-passwordless-auth'); ?>
        </p>
    <?php endif; ?>
</div>

<style>
    .passwordless-login-container {
        max-width: 400px;
        margin: 0 auto;
        padding: 20px;
        background: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    .passwordless-submit {
        margin-top: 10px;
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
