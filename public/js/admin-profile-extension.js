/**
 * Admin profile extension JavaScript
 * Handles account deletion functionality in the WordPress admin profile
 */
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
            url: passwordlessAdminProfile.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'request_deletion_code',
                nonce: passwordlessAdminProfile.nonce
            },
            success: function(response) {
                if (response.success) {
                    messagesContainer.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    $('.delete-account-step2').show();
                } else {
                    messagesContainer.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                messagesContainer.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                // Restore button
                button.text('Send Deletion Code');
                button.prop('disabled', false);
            }
        });
    });
    
    // Confirm account deletion
    $('#confirm-delete-account-btn').on('click', function() {
        var button = $(this);
        var messagesContainer = $('.delete-account-messages');
        var codeField = $('#deletion-code');
        
        if (!codeField.val().trim()) {
            messagesContainer.html('<div class="notice notice-error"><p>Please enter the deletion code.</p></div>');
            return;
        }
        
        if (!confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
            return;
        }
        
        // Clear previous messages
        messagesContainer.html('');
        
        // Change button text and disable it while processing
        button.text('Deleting...');
        button.prop('disabled', true);
        
        $.ajax({
            url: passwordlessAdminProfile.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_account',
                confirmation_code: codeField.val().trim(),
                nonce: passwordlessAdminProfile.nonce
            },
            success: function(response) {
                if (response.success) {
                    messagesContainer.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = '/'; // Default to home page
                    }, 2000);
                } else {
                    messagesContainer.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                messagesContainer.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                // Restore button
                button.text('Delete My Account');
                button.prop('disabled', false);
            }
        });
    });
});
