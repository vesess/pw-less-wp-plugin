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
                <label for="current_email"><?php _e('Current Email Address', 'my-passwordless-auth'); ?></label>
                <p><strong><?php echo esc_html($current_user->user_email); ?></strong></p>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to check email input and update UI accordingly
    function checkEmailInput() {
        const newEmail = document.getElementById('new_email').value.trim();
        const requestEmailCodeBtn = document.querySelector('.request-email-code-btn');
        const emailCodeContainer = document.querySelector('.email-code-container');
        const emailVerificationCodeInput = document.getElementById('email_verification_code');
        
        if (newEmail !== '') {
            requestEmailCodeBtn.disabled = false;
        } else {
            requestEmailCodeBtn.disabled = true;
            emailCodeContainer.style.display = 'none';
            emailVerificationCodeInput.value = '';
        }
    }
    
    // Set up constant asynchronous checking (every 500ms)
    setInterval(function() {
        checkEmailInput();
    }, 500);
    
    // Profile form submission
    document.getElementById('my-passwordless-auth-profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const displayName = document.getElementById('display_name').value;
        const newEmail = document.getElementById('new_email').value;
        const emailVerificationCode = document.getElementById('email_verification_code').value;
        const messagesContainer = this.querySelector('.messages');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        const data = new URLSearchParams({
            'action': 'update_profile',
            'display_name': displayName,
            'new_email': newEmail,
            'email_verification_code': emailVerificationCode,
            'nonce': passwordless_auth.profile_nonce
        }).toString();
        
        fetch(passwordless_auth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(response => {
            if (response.success) {
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                
                // If email was updated, update the displayed email and reset the form
                if (newEmail && emailVerificationCode) {
                    // Update only the first strong tag which contains the current email address
                    document.querySelector('.form-row:first-of-type p strong').textContent = newEmail;
                    document.getElementById('new_email').value = '';
                    document.getElementById('email_verification_code').value = '';
                    document.querySelector('.email-code-container').style.display = 'none';
                }
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An error occurred. Please try again.</div>';
        });
    });
    
    // Request email verification code
    document.querySelector('.request-email-code-btn').addEventListener('click', function() {
        const newEmail = document.getElementById('new_email').value;
        const messagesContainer = document.getElementById('my-passwordless-auth-profile-form').querySelector('.messages');
        const emailCodeContainer = document.querySelector('.email-code-container');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        if (!newEmail) {
            messagesContainer.innerHTML = '<div class="message error-message">Please enter a new email address.</div>';
            return;
        }
        
        const data = new URLSearchParams({
            'action': 'request_email_verification',
            'new_email': newEmail,
            'nonce': passwordless_auth.profile_nonce
        }).toString();
        
        fetch(passwordless_auth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(response => {
            if (response.success) {
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                emailCodeContainer.style.display = 'block';
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An error occurred. Please try again.</div>';
        });
    });
    
    // Delete account form handling - completely separated functionality
    
    // Function to request a deletion code
    function requestDeletionCode() {
        const messagesContainer = document.getElementById('my-passwordless-auth-delete-account-form').querySelector('.messages');
        const codeContainer = document.querySelector('.code-container');
        const requestCodeBtn = document.querySelector('.request-code-btn');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Change button text and disable it
        requestCodeBtn.textContent = 'Sending...';
        requestCodeBtn.disabled = true;
        
        const data = new URLSearchParams({
            'action': 'request_deletion_code',
            'nonce': passwordless_auth.delete_account_nonce
        }).toString();
        
        fetch(passwordless_auth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(response => {
            if (response.success) {
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                codeContainer.style.display = 'block';
                
                // Change button text to indicate waiting period
                requestCodeBtn.textContent = 'Request Again (30s)';
                
                // Start a countdown
                let secondsLeft = 30;
                const countdownInterval = setInterval(function() {
                    secondsLeft--;
                    if (secondsLeft > 0) {
                        requestCodeBtn.textContent = `Request Again (${secondsLeft}s)`;
                    } else {
                        requestCodeBtn.textContent = 'Request Deletion Code';
                        requestCodeBtn.disabled = false;
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
                // Restore button state in case of error
                requestCodeBtn.textContent = 'Request Deletion Code';
                requestCodeBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An error occurred. Please try again.</div>';
            // Restore button state in case of error
            requestCodeBtn.textContent = 'Request Deletion Code';
            requestCodeBtn.disabled = false;
        });
    }
    
    // Function to delete account
    function deleteAccount(confirmationCode) {
        const messagesContainer = document.getElementById('my-passwordless-auth-delete-account-form').querySelector('.messages');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        if (!confirmationCode) {
            messagesContainer.innerHTML = '<div class="message error-message">Please enter the confirmation code.</div>';
            return;
        }
        
        const data = new URLSearchParams({
            'action': 'delete_account',
            'confirmation_code': confirmationCode,
            'nonce': passwordless_auth.delete_account_nonce
        }).toString();
        
        fetch(passwordless_auth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(response => {
            if (response.success) {
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                // Redirect to home page after successful account deletion
                setTimeout(function() {
                    window.location.href = '/';
                }, 2000);
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An error occurred. Please try again.</div>';
        });
    }
    
    // Attach event listeners to buttons
    document.querySelector('.request-code-btn').addEventListener('click', function() {
        requestDeletionCode();
    });
    
    document.querySelector('.delete-btn').addEventListener('click', function() {
        const confirmationCode = document.getElementById('confirmation_code').value;
        deleteAccount(confirmationCode);
    });
});</script>
