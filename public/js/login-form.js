(function() {
    // Hide the h1 element to remove unnessary text
    document.querySelectorAll('h1').forEach(h1 => {
  if (h1.textContent.includes('login')) {
    h1.style.display = 'none';
  }
});

    document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordless-login-form');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const emailField = document.getElementById('user_email');
        const submitBtn = document.getElementById('wp-submit');
        const messagesContainer = form.querySelector('.messages');
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Disable submit button during submission
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;
          // Send AJAX request
        const formData = new FormData(form);
        
        // Add the specific login nonce to the form data
        formData.append('vesess_auth_login_nonce', passwordlessAuth.login_nonce);
        
        const data = new URLSearchParams();
        for (const pair of formData) {
            data.append(pair[0], pair[1]);
        }
        
        fetch(passwordlessAuth.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-WP-Nonce': passwordlessAuth.nonce
            },
            body: data
        })
        .then(response => response.json())
        .then(response => {
            // Re-enable submit button
            submitBtn.textContent = 'Send Login Link';
            submitBtn.disabled = false;
              if (response.success) {
                // Show success message
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                
                // Update URL with sent parameter and nonce for visual feedback
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('sent', '1');
                currentUrl.searchParams.set('_wpnonce', passwordlessAuth.feedback_nonce);
                
                // Update URL without refreshing the page
                window.history.replaceState({}, '', currentUrl.toString());
                
                // Clear form
                form.reset();
            } else {
                // Show error message
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            // Re-enable submit button
            submitBtn.textContent = 'Send Login Link';
            submitBtn.disabled = false;
            
            // Show error message
            messagesContainer.innerHTML = '<div class="message error-message">An unexpected error occurred. Please try again later.</div>';
            console.error('Login error:', error);
        });
    });
});
})();