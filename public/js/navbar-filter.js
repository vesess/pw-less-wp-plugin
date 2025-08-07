/**
 * Navbar filter JavaScript
 * Adds dynamic body classes and handles menu item filtering based on login status
 */
document.addEventListener('DOMContentLoaded', function() {
    // This script is mainly for fallback and additional dynamic handling
    
    // Additional dynamic menu filtering for edge cases
    function filterMenuItems() {
        var isLoggedIn = document.body.classList.contains('logged-in-hide-logout');
        var isLoggedOut = document.body.classList.contains('logged-out-hide-profile');
        
        if (isLoggedIn) {
            // Hide login, signup, register links
            var loginLinks = document.querySelectorAll('a[href*="/login/"], a[href*="/sign-up/"], a[href*="/register/"]');
            loginLinks.forEach(function(link) {
                var parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.style.display = 'none';
                }
            });
        }
        
        if (isLoggedOut) {
            // Hide profile links
            var profileLinks = document.querySelectorAll('a[href*="/profile/"]');
            profileLinks.forEach(function(link) {
                var parentLi = link.closest('li');
                if (parentLi) {
                    parentLi.style.display = 'none';
                }
            });
        }
    }
    
    // Execute immediately
    filterMenuItems();
    
    // Also run after a short delay to catch any dynamically loaded menus
    setTimeout(filterMenuItems, 500);
    
    // Set up a MutationObserver to watch for DOM changes
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function() {
            filterMenuItems();
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});
