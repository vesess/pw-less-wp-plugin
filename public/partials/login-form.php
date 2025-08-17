<?php
/**
 * Passwordless login form template
 */

if (!defined('WPINC')) {
    die;
}

// Error messages will be handled by JavaScript

// Check if a success message should be displayed
$success_message = '';
if (isset($_GET['sent'])) {
    // Verify nonce if provided, otherwise only allow safe "sent" parameter
    $is_valid_request = false;
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'vesesslabs_vesessauth_login_feedback')) {
        $is_valid_request = true;
        $sent_value = sanitize_text_field(wp_unslash($_GET['sent']));
    } else {
        // Still allow the "sent" parameter without nonce if it only contains '1' (safe value)
        // Sanitize input first
        $raw_sent = sanitize_text_field(wp_unslash($_GET['sent']));
        if ($raw_sent === '1') {
            $is_valid_request = true;
            $sent_value = '1';
        }
    }
    
    if ($is_valid_request && $sent_value === '1') {
        $success_message = 'Login link sent! Please check your email.';
    }
}

// Get redirect URL after successful login
$redirect_to = '';

// Get and validate redirect URL with nonce verification when provided
if (isset($_REQUEST['redirect_to'])) {
    // Always require nonce verification for redirect URL processing
    $is_valid_redirect = false;
    if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'passwordless_redirect')) {
        $is_valid_redirect = true;
        // Sanitize the redirect URL
        $raw_redirect = esc_url_raw(wp_unslash($_REQUEST['redirect_to']));
        
        // Additional validation: only allow internal URLs
        if (strpos($raw_redirect, 'http') !== 0 || strpos($raw_redirect, home_url()) === 0) {
            $redirect_to = $raw_redirect;
        }
    }
}

// Default to home URL if empty or invalid
if (empty($redirect_to)) {
    $redirect_to = home_url();
}

// Define theme compatibility class at the beginning where it's needed
$options = get_option('vesesslabs_vesessauth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';

?>

<div class="passwordless-container passwordless-login-container <?php echo esc_attr($theme_compat_class); ?>">
    <h1 class="login-title">Log In</h1>
    <form id="passwordless-login-form" class="passwordless-form <?php echo esc_attr($theme_compat_class); ?>">
        <div class="messages"></div>
          <div class="form-row">
            <label for="user_email">Email Address</label>
            <input type="email" name="user_email" id="user_email" required />
        </div>          <div class="form-row passwordless-submit">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
            <input type="hidden" name="action" value="process_login" />
            <input type="hidden" name="redirect_nonce" value="<?php echo esc_attr(wp_create_nonce('passwordless_redirect')); ?>" />
            <?php wp_nonce_field('passwordless-login-nonce', 'vesesslabs_vesessauth_login_nonce'); ?>
            <button type="submit" name="wp-submit" id="wp-submit" class="button-primary">
                Send Login Link
            </button>
        </div>
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