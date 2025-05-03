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
    
    <div class="profile-section">
        <form id="passwordless-profile-form" class="passwordless-form">
            <h3><?php _e('Update Profile Information', 'my-passwordless-auth'); ?></h3>
            
            <div class="messages"></div>
            
            <div class="form-row">
                <label for="current_email"><?php _e('Current Email Address', 'my-passwordless-auth'); ?></label>
                <p><strong class="current_email_custom"><?php echo esc_html($current_user->user_email); ?></strong></p>
            </div>
            
            <div class="form-row">
                <label for="new_email"><?php _e('New Email Address', 'my-passwordless-auth'); ?></label>
                <input type="email" name="new_email" id="new_email" placeholder="<?php esc_attr_e('Enter new email address', 'my-passwordless-auth'); ?>" />
            </div>
            
            <div class="form-row button-row email-buttons">
                <button type="button" class="request-email-code-btn" disabled><?php _e('Request Verification Code', 'my-passwordless-auth'); ?></button>
            </div>
              <div class="form-row email-code-container" style="display: none;">
                <label for="email_verification_code"><?php _e('Verification Code', 'my-passwordless-auth'); ?></label>
                <input type="text" name="email_verification_code" id="email_verification_code" placeholder="<?php esc_attr_e('Enter the code sent to your new email', 'my-passwordless-auth'); ?>" />
            </div>
            
            <div class="form-row">
                <label for="display_name"><?php _e('Display Name', 'my-passwordless-auth'); ?></label>
                <input type="text" name="display_name" id="display_name" value="<?php echo esc_attr($current_user->display_name); ?>" />
            </div>
            
            <div class="form-row">
                <p><strong><?php _e('Username:', 'my-passwordless-auth'); ?></strong> <?php echo esc_html($current_user->user_login); ?> <em>(<?php _e('cannot be changed', 'my-passwordless-auth'); ?>)</em></p>
            </div>
            
            <div class="form-row">
                <p><strong><?php _e('Registration Date:', 'my-passwordless-auth'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($current_user->user_registered)); ?></p>
            </div>
            
            <div class="form-row button-row">
                <input type="submit" value="<?php esc_attr_e('Update Profile', 'my-passwordless-auth'); ?>" class="button-primary" />
            </div>
        </form>
    </div>
      <div class="profile-section danger-zone">
        <form id="passwordless-delete-account-form" class="passwordless-form">
            <h3><?php _e('Delete Account', 'my-passwordless-auth'); ?></h3>
            
            <div class="messages"></div>
            
            <div class="warning">
                <p><?php _e('Warning: Account deletion is permanent and cannot be undone.', 'my-passwordless-auth'); ?></p>
                <p><?php _e('All your personal data will be removed from the site.', 'my-passwordless-auth'); ?></p>
            </div>
            
            <div class="form-row button-row">
                <button type="button" class="request-code-btn danger-btn"><?php _e('Request Deletion Code', 'my-passwordless-auth'); ?></button>
            </div>
            
            <div class="form-row code-container" style="display: none;">
                <label for="confirmation_code"><?php _e('Confirmation Code', 'my-passwordless-auth'); ?></label>
                <input type="text" name="confirmation_code" id="confirmation_code" placeholder="<?php esc_attr_e('Enter the code sent to your email', 'my-passwordless-auth'); ?>" />
                <button type="button" class="delete-btn danger-btn"><?php _e('Delete My Account', 'my-passwordless-auth'); ?></button>
            </div>
        </form>
    </div>
    
    <div class="profile-section">
        <h3><?php _e('Logout', 'my-passwordless-auth'); ?></h3>
        <p><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="logout-link"><?php _e('Log Out', 'my-passwordless-auth'); ?></a></p>
    </div>
</div>