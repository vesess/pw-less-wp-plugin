<?php
/**
 * Adds account deletion functionality to the WordPress admin profile page.
 */


if (!defined('WPINC')) {
    die;
}

/**
 * Enqueue CSS and JS for the admin profile delete account section.
 */
function my_passwordless_auth_admin_profile_scripts() {
    $screen = get_current_screen();
    // Only load on the profile.php or user-edit.php pages
    if (isset($screen->id) && ($screen->id === 'profile' || $screen->id === 'user-edit')) {
        wp_enqueue_style(
            'my-passwordless-auth-admin-profile',
            plugin_dir_url(dirname(__FILE__)) . 'public/css/admin-profile-extension.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION
        );
        
        // Enqueue admin profile JavaScript
        wp_enqueue_script(
            'my-passwordless-auth-admin-profile',
            plugin_dir_url(dirname(__FILE__)) . 'public/js/admin-profile-extension.js',
            array('jquery'),
            MY_PASSWORDLESS_AUTH_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'my-passwordless-auth-admin-profile',
            'passwordlessAdminProfile',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('account_deletion_nonce')
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'my_passwordless_auth_admin_profile_scripts');

/**
 * Add account deletion section to the WordPress admin profile page.
 */
function my_passwordless_auth_add_profile_fields() {
    // Only show to the user viewing their own profile, not administrators editing other profiles
    if (!current_user_can('edit_user', get_current_user_id())) {
        return;
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    if (!($current_user instanceof WP_User)) {
        return;
    }
      // Add the account deletion section
    ?>
    <h2>Account Management</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="account_deletion">Delete Your Account</label></th>
            <td>
                <div id="my-passwordless-auth-delete-account">
                    <p class="description">Deleting your account is permanent and cannot be undone. All your personal data will be removed from the site.</p>
                    
                    <div class="delete-account-messages"></div>
                      <div class="delete-account-step1">
                        <button type="button" id="request-deletion-code-btn" class="button button-secondary">Request Verification Code</button>
                    </div>
                    
                    <div class="delete-account-step2" style="display:none; margin-top: 15px;">
                        <p>Enter the verification code sent to your email address:</p>
                        <input type="text" id="deletion-confirmation-code" name="deletion_confirmation_code" class="regular-text" placeholder="Verification code" />
                        <button type="button" id="confirm-delete-account-btn" class="button button-primary" style="background-color: #dc3545; border-color: #dc3545;">Delete My Account</button>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

// Add the account deletion section to the WordPress profile page
add_action('show_user_profile', 'my_passwordless_auth_add_profile_fields');
