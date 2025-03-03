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
?>
<div class="my-passwordless-auth-container profile-page-container">
    <h2><?php _e('Your Profile', 'my-passwordless-auth'); ?></h2>
    
    <div class="profile-section">
        <form id="my-passwordless-auth-profile-form" class="my-passwordless-auth-form">
            <h3><?php _e('Update Profile Information', 'my-passwordless-auth'); ?></h3>
            
            <div class="messages"></div>
            
            <div class="form-row">
                <label for="email"><?php _e('Email Address', 'my-passwordless-auth'); ?></label>
                <input type="email" name="email" id="email" value="<?php echo esc_attr($current_user->user_email); ?>" required />
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
                <input type="submit" value="<?php esc_attr_e('Update Profile', 'my-passwordless-auth'); ?>" class="submit-btn" />
            </div>
        </form>
    </div>
    
    <div class="profile-section danger-zone">
        <form id="my-passwordless-auth-delete-account-form" class="my-passwordless-auth-form">
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

<style>
    .my-passwordless-auth-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .my-passwordless-auth-container h2 {
        margin-bottom: 20px;
        text-align: center;
    }
    
    .profile-section {
        background: #f9f9f9;
        border-radius: 5px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .profile-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    .form-row {
        margin-bottom: 15px;
    }
    
    .form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-row input[type="email"],
    .form-row input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .button-row {
        margin-top: 20px;
    }
    
    input[type="submit"], button {
        background-color: #0073aa;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 15px;
    }
    
    input[type="submit"]:hover, button:hover {
        background-color: #005a87;
    }
    
    input[type="submit"]:disabled, button:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }
    
    .logout-link {
        display: inline-block;
        background-color: #f0f0f0;
        color: #333;
        text-decoration: none;
        padding: 8px 15px;
        border-radius: 4px;
    }
    
    .logout-link:hover {
        background-color: #e0e0e0;
    }
    
    .danger-zone {
        border: 1px solid #dc3545;
    }
    
    .danger-zone h3 {
        color: #dc3545;
    }
    
    .danger-btn {
        background-color: #dc3545;
    }
    
    .danger-btn:hover {
        background-color: #c82333;
    }
    
    .warning {
        background-color: #fff3cd;
        color: #856404;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .code-container {
        margin-top: 20px;
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
</style>
