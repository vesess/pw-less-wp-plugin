(function(){
    document.addEventListener('DOMContentLoaded', function() {

           // Check if the profile form exists (user is logged in)
    if (!document.getElementById('passwordless-profile-form')) {
        console.log('Profile form not found. User might not be logged in.');
        return;
    }

    // Function to check email input and update UI accordingly
    function checkEmailInput() {
        const email = document.getElementById('new_email');
        const newEmail = email.value.trim();
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

           // Check if the profile form exists (user is logged in)
    if (!document.getElementById('passwordless-profile-form')) {
        console.log('Profile form not found. User might not be logged in.');
        return;
    }
    
        checkEmailInput();
    }, 500);
    
    // Profile form submission
    document.getElementById('passwordless-profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const displayName = document.getElementById('display_name').value;
        const newEmail = document.getElementById('new_email').value;
        const emailVerificationCode = document.getElementById('email_verification_code').value;
        const messagesContainer = this.querySelector('.messages');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';          const data = new URLSearchParams({
            'action': 'update_profile',
            'display_name': displayName,
            'new_email': newEmail,
            'email_verification_code': emailVerificationCode,
            'nonce': passwordlessAuth.profile_nonce
        }).toString();
        
        fetch(passwordlessAuth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-WP-Nonce': passwordlessAuth.nonce
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
                    document.querySelector('.current_email_custom').textContent = newEmail;
                    document.getElementById('new_email').value = '';
                    document.getElementById('email_verification_code').value = '';
                    document.querySelector('.email-code-container').style.display = 'none';
                }
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            console.error('Unexpected error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An unexpected error occurred. Please try again later.</div>';
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
        }          const data = new URLSearchParams({
            'action': 'request_email_verification',
            'new_email': newEmail,
            'nonce': passwordlessAuth.profile_nonce
        }).toString();
        
        fetch(passwordlessAuth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-WP-Nonce': passwordlessAuth.nonce
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
        const messagesContainer = document.getElementById('passwordless-delete-account-form').querySelector('.messages');
        const codeContainer = document.querySelector('.code-container');
        const requestCodeBtn = document.querySelector('.request-code-btn');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Change button text and disable it while sending
        requestCodeBtn.textContent = 'Sending...';
        requestCodeBtn.disabled = true;          const data = new URLSearchParams({
            'action': 'request_deletion_code',
            'nonce': passwordlessAuth.profile_nonce
        }).toString();
        
        fetch(passwordlessAuth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-WP-Nonce': passwordlessAuth.nonce
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
                console.log('Code sent successfully!');
                // Restore the button immediately instead of showing countdown
                requestCodeBtn.textContent = 'Request Deletion Code';
                requestCodeBtn.disabled = false;
            } else {
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
                console.log('Error sending code:', response.data);
                // Restore button state in case of error
                requestCodeBtn.textContent = 'Request Deletion Code';
                requestCodeBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesContainer.innerHTML = '<div class="message error-message">An error occurred. Please try again.</div>';
            console.log('Error:', error);
            // Restore button state in case of error
            requestCodeBtn.textContent = 'Request Deletion Code';
            requestCodeBtn.disabled = false;
        });
    }
      // Function to delete account
    function deleteAccount(confirmationCode) {
        const messagesContainer = document.getElementById('passwordless-delete-account-form').querySelector('.messages');
        console.log('Confirmation code:', confirmationCode);
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        if (!confirmationCode) {
            messagesContainer.innerHTML = '<div class="message error-message">Please enter the confirmation code.</div>';
            return;
        }
          const data = new URLSearchParams({
            'action': 'delete_account',
            'confirmation_code': confirmationCode,
            'nonce': passwordlessAuth.profile_nonce
        }).toString();
        
        fetch(passwordlessAuth.ajax_url, {
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
});
})();