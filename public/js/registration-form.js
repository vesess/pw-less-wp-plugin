(function(){
      // Hide the h1 element to remove unnessary text
    document.querySelectorAll('h1').forEach(h1 => {
  if (h1.textContent.includes('sign-up')) {
    h1.style.display = 'none';
  }
});


document.addEventListener('DOMContentLoaded', function() {
    console.log('Registration form script loaded');
    const form = document.getElementById('vesess_easyauth-registration-form');
    if(!form) {
        console.error('Registration form not found!');
         return;
    }
    // Ensure the form exists before adding event listener
    form.addEventListener('submit', function(e) {
        e.preventDefault(); 

        const emailField = document.getElementById('email');
        const usernameField = document.getElementById('username');
        const displayNameField = document.getElementById('display_name');
        const messagesContainer = form.querySelector('.messages');
        
        if (!usernameField.value.trim()) {
            usernameField.value = emailField.value;
        }
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
          // Disable submit button during submission
        const submitBtn = form.querySelector('input[type="submit"]');
        submitBtn.value = 'Registering...';
        submitBtn.disabled = true;
        
        // Send AJAX request
        const formData = new FormData(form);
        // Add the specific registration nonce to the form data
        formData.append('registration_nonce', passwordlessAuth.registration_nonce);
        
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
            submitBtn.value = 'Register';
            submitBtn.disabled = false;
            
            if (response.success) {
                // Show success message
                messagesContainer.innerHTML = '<div class="message success-message">' + response.data + '</div>';
                
                // Update URL with registration success parameter and nonce for visual feedback
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('registered', '1');
                currentUrl.searchParams.set('_wpnonce', passwordlessAuth.registration_feedback_nonce);
                
                // Update URL without refreshing the page
                window.history.replaceState({}, '', currentUrl.toString());
                
                // Clear form inputs
                form.reset();
            } else {
                // Show error message
                messagesContainer.innerHTML = '<div class="message error-message">' + response.data + '</div>';
            }
        })
        .catch(error => {
            // Re-enable submit button
            submitBtn.value = 'Register';
            submitBtn.disabled = false;
            
            // Show error message
            messagesContainer.innerHTML = '<div class="message error-message">An unexpected error occurred. Please try again later.</div>';
            console.error('Registration error:', error);        });
    });
});


})();