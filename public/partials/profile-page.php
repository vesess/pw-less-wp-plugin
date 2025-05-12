<?php
/**
 * Template for the user profile page
 */
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
if (!($current_user instanceof WP_User)) {
    return;
}

// CSS is now loaded via class-frontend.php
?>
<!-- Styles now loaded from passwordless-auth.css -->
<?php
// Allow theme styling to override plugin styles if enabled in settings
$options = get_option('my_passwordless_auth_options', []);
$theme_compat_class = isset($options['use_theme_styles']) && $options['use_theme_styles'] === 'yes' ? 'theme-compat' : '';
?>
<div class="passwordless-container profile-page-container <?php echo esc_attr($theme_compat_class); ?>">
    <h2><?php _e('Your Profile', 'my-passwordless-auth'); ?></h2>
    
    <div class="profile-section profile-info-section">
        <h3><?php _e('Profile Information', 'my-passwordless-auth'); ?></h3>
        
        <div class="form-row">
            <p><strong><?php _e('Email Address:', 'my-passwordless-auth'); ?></strong> <span class="current_email_custom"><?php echo esc_html($current_user->user_email); ?></span></p>
        </div>
        
        <div class="form-row">
            <p><strong><?php _e('Display Name:', 'my-passwordless-auth'); ?></strong> <?php echo esc_html($current_user->display_name); ?></p>
        </div>
        
        <div class="form-row">
            <p><strong><?php _e('Username:', 'my-passwordless-auth'); ?></strong> <?php echo esc_html($current_user->user_login); ?> <em>(<?php _e('cannot be changed', 'my-passwordless-auth'); ?>)</em></p>
        </div>
        
        <div class="form-row">
            <p><strong><?php _e('Registration Date:', 'my-passwordless-auth'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($current_user->user_registered)); ?></p>
        </div>
    </div>
    
    <?php /* Profile update form and delete account section removed as per request */ ?>
    <?php /* These functionalities would typically move to a settings page. */ ?>

    <div class="profile-section logout-section">
        <h3><?php _e('Logout', 'my-passwordless-auth'); ?></h3>
        <p><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="logout-link"><?php _e('Log Out', 'my-passwordless-auth'); ?></a></p>
    </div>
</div>