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
// CSS is now loaded via class-frontend.php
// Define theme compatibility class at the beginning where it's needed
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>
<div class="passwordless-container registration-form-container <?php echo esc_attr($theme_compat_class); ?>">
    <h1 class="registration-title">Sign Up</h1>
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
        
        <?php /* <p class="login-register-link">
            <?php _e('Already have an account?', 'my-passwordless-auth'); ?>
            <a href="<?php echo esc_url(home_url('/login')); ?>"><?php _e('Login here', 'my-passwordless-auth'); ?></a>
        </p> */ ?>
        <input type="hidden" name="action" value="register_new_user" />
        <?php wp_nonce_field('registration_nonce', 'nonce'); ?>
    </form>    <p class="login-link">
        Already have an account?
        <a href="<?php echo esc_url(home_url('/login')); ?>">Log In</a>
    </p>
</div>

