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
// CSS is now loaded via class-frontend.php
// Define theme compatibility class at the beginning where it's needed
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>
<div class="passwordless-container registration-form-container <?php echo esc_attr($theme_compat_class); ?>">
    <h1 class="registration-title"><?php _e('Sign Up', 'my-passwordless-auth'); ?></h1>
    <form id="passwordless-registration-form" class="passwordless-form <?php echo esc_attr($theme_compat_class); ?>">
        <div class="messages"></div>
        
        <div class="form-row">
            <?php /* <label for="email"><?php _e('Email Address', 'my-passwordless-auth'); ?> <span class="required">*</span></label> */ ?>
            <input type="email" name="email" id="email" placeholder="<?php esc_attr_e('Email', 'my-passwordless-auth'); ?>" required />
            <?php /* <p class="description"><?php _e('You\'ll use this email to log in.', 'my-passwordless-auth'); ?></p> */ ?>
        </div>
        
        <div class="form-row">
            <?php /* <label for="username"><?php _e('Username', 'my-passwordless-auth'); ?></label> */ ?>
            <input type="text" name="username" id="username" placeholder="<?php esc_attr_e('Username (optional)', 'my-passwordless-auth'); ?>" />
            <?php /* <p class="description"><?php _e('Leave this field empty to use your email address as username.', 'my-passwordless-auth'); ?></p> */ ?>
        </div>
        
        <div class="form-row">
            <?php /* <label for="display_name"><?php _e('Display Name', 'my-passwordless-auth'); ?></label> */ ?>
            <input type="text" name="display_name" id="display_name" placeholder="<?php esc_attr_e('Display Name (optional)', 'my-passwordless-auth'); ?>" />
            <?php /* <p class="description"><?php _e('This is how your name will be shown on the site.', 'my-passwordless-auth'); ?></p> */ ?>
        </div>
        
        <div class="form-row button-row">
            <input type="submit" value="<?php esc_attr_e('Sign Up', 'my-passwordless-auth'); ?>" class="button-primary" />
        </div>
        
        <?php /* <p class="login-register-link">
            <?php _e('Already have an account?', 'my-passwordless-auth'); ?>
            <a href="<?php echo esc_url(home_url('/login')); ?>"><?php _e('Login here', 'my-passwordless-auth'); ?></a>
        </p> */ ?>
        <input type="hidden" name="action" value="register_new_user" />
        <?php wp_nonce_field('registration_nonce', 'nonce'); ?>
    </form>
    <p class="login-link">
        <?php _e('Already have an account?', 'my-passwordless-auth'); ?>
        <a href="<?php echo esc_url(home_url('/login')); ?>"><?php _e('Log In', 'my-passwordless-auth'); ?></a>
    </p>
</div>

