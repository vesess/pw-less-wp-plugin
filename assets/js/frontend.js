/**
 * Frontend JavaScript for Passwordless Authentication plugin
 * Plain JavaScript version (no jQuery)
 */
(function() {
    'use strict';

    // Helper function to validate email
    function isValidEmail(email) {
        const regex = /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        return regex.test(email);
    }
    
    // Helper function to show messages
    function showMessage(message, type, container) {
        container = container || document.querySelector('.messages');
        if (!container) return;
        
        container.innerHTML = `<div class="message ${type}">${message}</div>`;
        
        if (type === 'success') {
            container.querySelector('.message').classList.add('success-message');
        } else if (type === 'error') {
            container.querySelector('.message').classList.add('error-message');
        }
        
        container.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Helper function for AJAX requests
    function makeAjaxRequest(data, successCallback, errorCallback, completeCallback) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', passwordless_auth.ajax_url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    successCallback(response);
                } catch (e) {
                    errorCallback();
                }
            } else {
                errorCallback();
            }
            if (completeCallback) completeCallback();
        };
        
        xhr.onerror = function() {
            errorCallback();
            if (completeCallback) completeCallback();
        };
        
        let formData = '';
        for (const key in data) {
            if (formData !== '') formData += '&';
            formData += `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`;
        }
        
        xhr.send(formData);
    }

    // Login form handler
    function initLoginForm() {
        const loginForm = document.getElementById('my-passwordless-auth-login-form');
        
        if (!loginForm) return;
        
        const emailField = loginForm.querySelector('input[name="email"]');
        const codeField = loginForm.querySelector('input[name="code"]');
        const codeContainer = loginForm.querySelector('.code-container');
        const requestCodeBtn = loginForm.querySelector('.request-code-btn');
        const verifyCodeBtn = loginForm.querySelector('.verify-code-btn');
        const messages = loginForm.querySelector('.messages');
        
        requestCodeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const email = emailField.value;
            
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address', 'error', messages);
                return;
            }
            
            requestCodeBtn.disabled = true;
            requestCodeBtn.textContent = 'Sending...';
            
            makeAjaxRequest(
                {
                    action: 'send_login_code',
                    email: email,
                    nonce: passwordless_auth.login_nonce
                },
                function(response) {
                    if (response.success) {
                        showMessage(response.data, 'success', messages);
                        codeContainer.style.display = 'block';
                        requestCodeBtn.textContent = 'Resend Code';
                    } else {
                        showMessage(response.data, 'error', messages);
                        requestCodeBtn.disabled = true;
                    }
                },
                function() {
                    showMessage('Server error. Please try again.', 'error', messages);
                },
                function() {
                    requestCodeBtn.disabled = false;
                }
            );
        });
        
        verifyCodeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const email = emailField.value;
            const code = codeField.value;
            
            if (!code) {
                showMessage('Please enter the code sent to your email', 'error', messages);
                return;
            }
            
            verifyCodeBtn.disabled = true;
            verifyCodeBtn.textContent = 'Verifying...';
            
            makeAjaxRequest(
                {
                    action: 'verify_login_code',
                    email: email,
                    code: code,
                    nonce: passwordless_auth.login_nonce
                },
                function(response) {
                    if (response.success) {
                        showMessage('Login successful! Redirecting...', 'success', messages);
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        showMessage(response.data, 'error', messages);
                        verifyCodeBtn.disabled = false;
                        verifyCodeBtn.textContent = 'Verify Code';
                    }
                },
                function() {
                    showMessage('Server error. Please try again.', 'error', messages);
                    verifyCodeBtn.disabled = false;
                    verifyCodeBtn.textContent = 'Verify Code';
                }
            );
        });
    }
    
    // Registration form handler
    function initRegistrationForm() {
        const registrationForm = document.getElementById('my-passwordless-auth-registration-form');
        
        if (!registrationForm) return;
        
        const submitBtn = registrationForm.querySelector('.submit-btn');
        const messages = registrationForm.querySelector('.messages');
        
        registrationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.currentTarget;
            const email = form.querySelector('input[name="email"]').value;
            const username = form.querySelector('input[name="username"]').value;
            const displayName = form.querySelector('input[name="display_name"]').value;
            
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address', 'error', messages);
                return;
            }
            
            if (!username) {
                showMessage('Please enter a username', 'error', messages);
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.value = 'Registering...';
            
            makeAjaxRequest(
                {
                    action: 'register_new_user',
                    email: email,
                    username: username,
                    display_name: displayName || username,
                    nonce: passwordless_auth.registration_nonce
                },
                function(response) {
                    if (response.success) {
                        form.reset();
                        showMessage(response.data, 'success', messages);
                    } else {
                        showMessage(response.data, 'error', messages);
                    }
                },
                function() {
                    showMessage('Server error. Please try again.', 'error', messages);
                },
                function() {
                    submitBtn.disabled = false;
                    submitBtn.value = 'Register';
                }
            );
        });
    }
    
    // Profile form handler
    function initProfileForm() {
        const profileForm = document.getElementById('my-passwordless-auth-profile-form');
        const deleteAccountForm = document.getElementById('my-passwordless-auth-delete-account-form');
        
        if (profileForm) {
            const submitBtn = profileForm.querySelector('.submit-btn');
            const messages = profileForm.querySelector('.messages');
            
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = e.currentTarget;
                const email = form.querySelector('input[name="email"]').value;
                const displayName = form.querySelector('input[name="display_name"]').value;
                
                if (!isValidEmail(email)) {
                    showMessage('Please enter a valid email address', 'error', messages);
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.value = 'Updating...';
                
                makeAjaxRequest(
                    {
                        action: 'update_profile',
                        email: email,
                        display_name: displayName,
                        nonce: passwordless_auth.profile_nonce
                    },
                    function(response) {
                        if (response.success) {
                            showMessage(response.data, 'success', messages);
                        } else {
                            showMessage(response.data, 'error', messages);
                        }
                    },
                    function() {
                        showMessage('Server error. Please try again.', 'error', messages);
                    },
                    function() {
                        submitBtn.disabled = false;
                        submitBtn.value = 'Update Profile';
                    }
                );
            });
        }
        
        if (deleteAccountForm) {
            const requestCodeBtn = deleteAccountForm.querySelector('.request-code-btn');
            const deleteBtn = deleteAccountForm.querySelector('.delete-btn');
            const codeField = deleteAccountForm.querySelector('input[name="confirmation_code"]');
            const codeContainer = deleteAccountForm.querySelector('.code-container');
            const messages = deleteAccountForm.querySelector('.messages');
            
            requestCodeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                requestCodeBtn.disabled = true;
                requestCodeBtn.textContent = 'Sending...';
                
                makeAjaxRequest(
                    {
                        action: 'delete_account',
                        confirmation_code: '',
                        nonce: passwordless_auth.delete_account_nonce
                    },
                    function(response) {
                        if (response.success) {
                            showMessage(response.data, 'success', messages);
                            codeContainer.style.display = 'block';
                            requestCodeBtn.textContent = 'Resend Code';
                        } else {
                            showMessage(response.data, 'error', messages);
                        }
                    },
                    function() {
                        showMessage('Server error. Please try again.', 'error', messages);
                    },
                    function() {
                        requestCodeBtn.disabled = false;
                    }
                );
            });
            
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const code = codeField.value;
                
                if (!code) {
                    showMessage('Please enter the confirmation code', 'error', messages);
                    return;
                }
                
                if (!confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                    return;
                }
                
                deleteBtn.disabled = true;
                deleteBtn.textContent = 'Deleting...';
                
                makeAjaxRequest(
                    {
                        action: 'delete_account',
                        confirmation_code: code,
                        nonce: passwordless_auth.delete_account_nonce
                    },
                    function(response) {
                        if (response.success) {
                            showMessage(response.data, 'success', messages);
                            setTimeout(function() {
                                window.location.href = '/';
                            }, 2000);
                        } else {
                            showMessage(response.data, 'error', messages);
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = 'Delete Account';
                        }
                    },
                    function() {
                        showMessage('Server error. Please try again.', 'error', messages);
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete Account';
                    }
                );
            });
        }
    }
    
    // Initialize all forms when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        initLoginForm();
        initRegistrationForm();
        initProfileForm();
    });
    
})();
