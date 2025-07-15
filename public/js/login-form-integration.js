/**
 * Login form integration JavaScript
 * Handles passwordless login button functionality on WordPress login page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add click handler to the passwordless login button on main login form
    var pwlessBtn = document.querySelector('#pwless-login-btn');
    var usernameField = document.querySelector('#user_login');
    var messagesContainer = document.querySelector('#pwless-messages');
    
    if (pwlessBtn && usernameField) {
        pwlessBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get username or email from the username field
            var userInput = usernameField.value.trim();
            var nonce = document.querySelector('#passwordless_login_nonce').value;
            
            if (!userInput) {
                // Show error if field is empty
                showMessage('Please enter your username or email address in the field above.', 'error');
                return;
            }
            
            // Save button text before changing it
            var btnTextBeforeSending = 'Log In with Email Code';
            
            // Disable button
            pwlessBtn.textContent = 'Sending...';
            pwlessBtn.disabled = true;
            
            // Set a timeout to re-enable button as a fallback
            var timeoutId = setTimeout(function() {
                pwlessBtn.textContent = btnTextBeforeSending;
                pwlessBtn.disabled = false;
            }, 10000); // 10 seconds timeout
            
            // Create form data
            var data = new URLSearchParams({
                'action': 'process_passwordless_login',
                'passwordless_login_nonce': nonce,
                'user_input': userInput
            }).toString();
            
            // Send AJAX request
            fetch(passwordlessLoginIntegration.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: data
            })
            .then(response => {
                // Clear the timeout since we got a response
                clearTimeout(timeoutId);
                
                // Always re-enable button first, regardless of response
                pwlessBtn.textContent = btnTextBeforeSending;
                pwlessBtn.disabled = false;
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text(); // Get as text first to debug
            })
            .then(responseText => {
                try {
                    var response = JSON.parse(responseText);
                    
                    if (response.success) {
                        showMessage(response.data, 'success');
                    } else {
                        showMessage(response.data, 'error');
                    }
                } catch (parseError) {
                    showMessage('Server response error. Please try again.', 'error');
                }
            })
            .catch(error => {
                // Clear the timeout since we're handling the error
                clearTimeout(timeoutId);
                // Ensure button is always re-enabled
                pwlessBtn.textContent = btnTextBeforeSending;
                pwlessBtn.disabled = false;
                showMessage('An error occurred. Please try again.', 'error');
            });
        });
    }
    
    // Add click handler to the lost password button
    var pwlessBtnLost = document.querySelector('#pwless-login-btn-lost');
    var usernameLostField = document.querySelector('#user_login');  // On lost password page, the field is the same
    var messagesLostContainer = document.querySelector('#pwless-messages-lost');
    
    if (pwlessBtnLost && usernameLostField) {
        pwlessBtnLost.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get username or email from the username field
            var userInput = usernameLostField.value.trim();
            var nonce = document.querySelector('#passwordless_login_nonce_lost').value;
            
            if (!userInput) {
                // Show error if field is empty
                showLostMessage('Please enter your username or email address in the field above.', 'error');
                return;
            }
            
            // Save button text before changing it
            var lostBtnTextBeforeSending = pwlessBtnLost.textContent;
            
            // Disable button
            pwlessBtnLost.textContent = 'Sending...';
            pwlessBtnLost.disabled = true;
            
            // Create form data
            var data = new URLSearchParams({
                'action': 'process_passwordless_login',
                'passwordless_login_nonce': nonce,
                'user_input': userInput
            }).toString();
            
            // Send AJAX request
            fetch(passwordlessLoginIntegration.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: data
            })
            .then(response => {
                // Always re-enable button first
                pwlessBtnLost.textContent = lostBtnTextBeforeSending;
                pwlessBtnLost.disabled = false;
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(response => {
                if (response.success) {
                    showLostMessage(response.data, 'success');
                } else {
                    showLostMessage(response.data, 'error');
                }
            })
            .catch(error => {
                // Ensure button is always re-enabled
                pwlessBtnLost.textContent = lostBtnTextBeforeSending;
                pwlessBtnLost.disabled = false;
                showLostMessage('An error occurred. Please try again.', 'error');
            });
        });
    }
    
    function showMessage(message, type) {
        if (messagesContainer) {
            messagesContainer.innerHTML = '<div style="background-color: ' + 
                (type === 'success' ? '#d4edda; color: #155724; border: 1px solid #c3e6cb;' : '#f8d7da; color: #721c24; border: 1px solid #f5c6cb;') + 
                ' padding: 10px; margin-bottom: 15px;">' + message + '</div>';
        }
    }
    
    function showLostMessage(message, type) {
        if (messagesLostContainer) {
            messagesLostContainer.innerHTML = '<div style="background-color: ' + 
                (type === 'success' ? '#d4edda; color: #155724; border: 1px solid #c3e6cb;' : '#f8d7da; color: #721c24; border: 1px solid #f5c6cb;') + 
                ' padding: 10px; margin-bottom: 15px;">' + message + '</div>';
        }
    }
});
