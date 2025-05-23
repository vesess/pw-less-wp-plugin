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
if (isset($_GET['sent'])) {
    $sent_value = sanitize_text_field(wp_unslash($_GET['sent']));
    if ($sent_value === '1') {
        $success_message = 'Login link sent! Please check your email.';
    }
}

// Get redirect URL after successful login
$redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
if (empty($redirect_to)) {
    $redirect_to = home_url();
}

// Define theme compatibility class at the beginning where it's needed
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';

// CSS is now loaded via class-frontend.php
?>

<div class="passwordless-container passwordless-login-container <?php echo esc_attr($theme_compat_class); ?>">
    <h1 class="login-title">Log In</h1>
    <form id="passwordless-login-form" class="passwordless-form <?php echo esc_attr($theme_compat_class); ?>">
        <div class="messages"></div>
          <div class="form-row">
            <label for="user_email">Email Address</label>
            <input type="email" name="user_email" id="user_email" required />
        </div>
        
        <div class="form-row passwordless-submit">            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
            <input type="hidden" name="action" value="process_login" />
            <?php wp_nonce_field('passwordless-login-nonce', 'passwordless_login_nonce'); ?>
            <button type="submit" name="wp-submit" id="wp-submit" class="button-primary">
                Send Login Link
            </button>
        </div>
        <?php /* Moved login-register-link outside the form */ ?>
    </form>
      <p class="passwordless-info">
        Enter your email address and we'll send you a link to log in.
    </p>

    <div class="login-separator"></div>

    <p class="login-register-link">
        Don't have an account?
        <a href="<?php echo esc_url(home_url('/sign-up')); ?>">Sign Up</a>
    </p>
</div>