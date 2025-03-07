/**
 * Login handler script for passwordless login
 */
jQuery(document).ready(function($) {
    console.log("Passwordless Auth: Login handler script loaded");
    
    // Toggle between password login and passwordless login
    $('#toggle-passwordless').on('click', function(e) {
        console.log("Toggle clicked");
        e.preventDefault();
        var $container = $('#passwordless-login-container');
        var $loginform = $('#loginform p:not(.forgetmenot):not(.submit)');
        
        if ($container.is(':visible')) {
            console.log("Switching to password login");
            $container.hide();
            $loginform.show();
            $(this).text('Login with Email Link Instead');
        } else {
            console.log("Switching to passwordless login");
            $container.show();
            $loginform.hide();
            $(this).text('Login with Password Instead');
        }
    });
    
    // Handle magic link request
    $('#send-magic-link-btn').on('click', function() {
        console.log("Send magic link button clicked");
        var email = $('#passwordless_email').val();
        if (!email) {
            $('#passwordless-message')
                .text('Please enter your email address.')
                .css({'color': '#dc3232', 'display': 'block'})
                .show();
            return;
        }
        
        console.log("Sending AJAX request for email: " + email);
        $(this).prop('disabled', true).text('Sending...');
        $('#passwordless-message').hide();
        
        // Use the ajaxurl from localized script or the one available in admin
        var ajax_url = (typeof my_passwordless_auth !== 'undefined' && my_passwordless_auth.ajaxurl) ? 
            my_passwordless_auth.ajaxurl : ajaxurl;
        
        $.post(ajax_url, {
            action: 'my_passwordless_auth_request_magic_link',
            email: email,
            nonce: (typeof my_passwordless_auth !== 'undefined') ? 
                my_passwordless_auth.nonce : $('#passwordless-login-nonce').val()
        }, function(response) {
            console.log("AJAX response received:", response);
            if (response.success) {
                $('#passwordless-message')
                    .html(response.data.message)
                    .css({'color': '#46b450', 'display': 'block'})
                    .show();
                
                $('#passwordless_email').val('');
                $('#send-magic-link-btn').prop('disabled', false).text('Send Login Link');
            } else {
                $('#passwordless-message')
                    .html(response.data.message)
                    .css({'color': '#dc3232', 'display': 'block'})
                    .show();
                    
                $('#send-magic-link-btn').prop('disabled', false).text('Send Login Link');
            }
        }).fail(function(xhr, status, error) {
            console.error("AJAX error:", status, error);
            $('#passwordless-message')
                .text('An error occurred. Please try again.')
                .css({'color': '#dc3232', 'display': 'block'})
                .show();
                
            $('#send-magic-link-btn').prop('disabled', false).text('Send Login Link');
        });
    });

    // Also handle Enter key on email field
    $('#passwordless_email').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#send-magic-link-btn').click();
        }
    });
});
