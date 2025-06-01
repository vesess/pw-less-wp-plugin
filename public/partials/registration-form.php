<?php
/**
 * Template for the registration form
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!get_option('users_can_register')) {
    echo '<p>' . esc_html('Registration is currently disabled.') . '</p>';
    return;
}

// Check if a success message should be displayed for registration feedback
$success_message = '';
$raw_registered = '';

if (isset($_GET['registered'])) {
    // Verify nonce if provided, otherwise only allow safe parameter
    $is_valid_request = false;
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'passwordless_registration_feedback')) {
        $is_valid_request = true;
        $raw_registered = sanitize_text_field(wp_unslash($_GET['registered']));
    } else {
        // Still allow the "registered" parameter without nonce if it only contains '1' (safe value)
        // Properly sanitize the input
        $raw_registered = sanitize_text_field(wp_unslash($_GET['registered']));
        if ($raw_registered === '1') {
            $is_valid_request = true;
        }
    }
      if ($is_valid_request) {
        if ($raw_registered === '1') {
            $success_message = 'Registration successful! Please check your email for verification instructions.';
        }
    }
}

$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>
<div class="passwordless-container registration-form-container <?php echo esc_attr($theme_compat_class); ?>">
    <h1 class="registration-title">Sign Up</h1>
    
    <?php if (!empty($success_message)) : ?>
    <div class="message success-message"><?php echo esc_html($success_message); ?></div>
    <?php endif; ?>
    
    <form id="passwordless-registration-form" class="passwordless-form <?php echo esc_attr($theme_compat_class); ?>">
        <div class="messages"></div>
          <div class="form-row">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" name="email" id="email" required />
            <p class="description">You'll use this email to log in.</p>
        </div>
          <div class="form-row">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" />
            <p class="description">Leave this field empty to use your email address as username.</p>
        </div>
        
        <div class="form-row">
            <label for="display_name">Display Name</label>
            <input type="text" name="display_name" id="display_name" />
            <p class="description">This is how your name will be shown on the site.</p>
        </div>
        
        <div class="form-row button-row">
            <input type="submit" value="<?php echo esc_attr('Sign Up'); ?>" class="button-primary" />
        </div>
        
        <input type="hidden" name="action" value="register_new_user" />
        <?php wp_nonce_field('registration_nonce', 'nonce'); ?>
    </form>    <p class="login-link">
        Already have an account?
        <a href="<?php echo esc_url(home_url('/login')); ?>">Log In</a>
    </p>
</div>

