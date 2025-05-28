<?php
/**
 * Adds account deletion functionality to the WordPress admin profile page.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Enqueue CSS for the admin profile delete account section.
 */
function my_passwordless_auth_admin_profile_styles() {
    $screen = get_current_screen();
    // Only load on the profile.php or user-edit.php pages
    if (isset($screen->id) && ($screen->id === 'profile' || $screen->id === 'user-edit')) {
        wp_enqueue_style(
            'my-passwordless-auth-admin-profile',
            plugin_dir_url(dirname(__FILE__)) . 'public/css/admin-profile-extension.css',
            array(),
            MY_PASSWORDLESS_AUTH_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'my_passwordless_auth_admin_profile_styles');

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
    <script>
        jQuery(document).ready(function($) {
            // Request deletion code
            $('#request-deletion-code-btn').on('click', function() {
                var button = $(this);
                var messagesContainer = $('.delete-account-messages');
                
                // Clear previous messages
                messagesContainer.html('');
                  // Change button text and disable it while sending
                button.text('Sending...');
                button.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',                    data: {
                        action: 'request_deletion_code',
                        nonce: '<?php echo esc_js(wp_create_nonce('delete_account_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            messagesContainer.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            $('.delete-account-step2').show();
                        } else {
                            messagesContainer.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        }
                          // Restore button
                        button.text('Request Verification Code');
                        button.prop('disabled', false);
                    },
                    error: function() {
                        messagesContainer.html('<div class="notice notice-error inline"><p>An error occurred. Please try again.</p></div>');
                        
                        // Restore button
                        button.text('Request Verification Code');
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Confirm account deletion
            $('#confirm-delete-account-btn').on('click', function() {
                var button = $(this);
                var messagesContainer = $('.delete-account-messages');
                var confirmationCode = $('#deletion-confirmation-code').val();
                
                // Validate code
                if (!confirmationCode) {                    messagesContainer.html('<div class="notice notice-error inline"><p>Please enter the verification code.</p></div>');
                    return;
                }
                
                // Confirm deletion
                if (!confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!')) {
                    return;
                }
                
                // Clear previous messages
                messagesContainer.html('');
                  // Change button text and disable it while processing
                button.text('Deleting...');
                button.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',                    data: {
                        action: 'delete_account',
                        confirmation_code: confirmationCode,
                        nonce: '<?php echo esc_js(wp_create_nonce('delete_account_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            messagesContainer.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            
                            // Redirect to home page after successful deletion
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url(home_url()); ?>';
                            }, 2000);
                        } else {
                            messagesContainer.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                              // Restore button
                            button.text('Delete My Account');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        messagesContainer.html('<div class="notice notice-error inline"><p>An error occurred. Please try again.</p></div>');
                        
                        // Restore button
                        button.text('Delete My Account');
                        button.prop('disabled', false);
                    }
                });
            });
        });
    </script>
    <style>
        #my-passwordless-auth-delete-account .button-primary {
            color: #fff;
        }
        #my-passwordless-auth-delete-account .button-primary:hover {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
        }
        .delete-account-messages {
            margin: 10px 0;
        }
    </style>
    <?php
}

// Add the account deletion section to the WordPress profile page
add_action('show_user_profile', 'my_passwordless_auth_add_profile_fields');
