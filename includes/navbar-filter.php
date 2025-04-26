<?php
/**
 * Navigation Menu Filter
 *
 * This file contains functions to filter navigation menu items based on user login status:
 * 1. Hide login/registration items when user is logged in
 * 2. Hide profile items when user is not logged in
 *
 * @package My_Passwordless_Auth
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Menu filter to hide login/registration items when user is logged in
 * And hide profile links when user is not logged in
 */
function my_passwordless_auth_navbar_filter($html) {
    // Return unchanged content if it's not a string or is empty
    if (!is_string($html) || empty($html)) {
        return $html;
    }

    // Check if WordPress functions exist
    if (!function_exists('is_user_logged_in')) {
        return $html;
    }
    
    // Different patterns based on login status
    if (is_user_logged_in()) {
        // When logged in: Hide login and registration links
        $patterns = array(
            // Match any list item containing login text
            '/<li[^>]*>\s*<a[^>]*>([^<]*login[^<]*)<\/a>.*?<\/li>/is',
            // Match any list item containing registration text
            '/<li[^>]*>\s*<a[^>]*>([^<]*registration[^<]*)<\/a>.*?<\/li>/is',
            // Match any list item containing register text
            '/<li[^>]*>\s*<a[^>]*>([^<]*register[^<]*)<\/a>.*?<\/li>/is',
            
            // Match menu items with login/registration/register in href
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/login\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/registration\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/register\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            
            // More inclusive patterns for various HTML structures
            '/<li[^>]*class="[^"]*\b(?:menu-item|page-item|nav-item)[^"]*"[^>]*>\s*<a[^>]*href="[^"]*\/(login|registration|register)\/[^"]*"[^>]*>.*?<\/li>/is',
            
            // Catch menu items that may have additional markup inside
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/(login|registration|register)\/[^"]*"[^>]*>.*?<\/a>(?:(?!<\/li>).)*<\/li>/is'
        );
    } else {
        // When not logged in: Hide profile links
        $patterns = array(
            // Match any list item containing profile text
            '/<li[^>]*>\s*<a[^>]*>([^<]*profile[^<]*)<\/a>.*?<\/li>/is',
            
            // Match menu items with profile in href
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/profile\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            
            // More inclusive patterns for various HTML structures
            '/<li[^>]*class="[^"]*\b(?:menu-item|page-item|nav-item)[^"]*"[^>]*>\s*<a[^>]*href="[^"]*\/profile\/[^"]*"[^>]*>.*?<\/li>/is',
            
            // Catch menu items that may have additional markup inside
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/profile\/[^"]*"[^>]*>.*?<\/a>(?:(?!<\/li>).)*<\/li>/is'
        );
    }
    
    // Apply each pattern
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
    
    return $html;
}

/**
 * Add CSS to hide menu items based on login status
 */
function my_passwordless_auth_navbar_css() {
    // Check if WordPress function exists
    if (!function_exists('is_user_logged_in')) {
        return;
    }
    
    // Different CSS based on login status
    if (is_user_logged_in()) {
        // When logged in: Hide login and registration links
        ?>
        <style type="text/css">
            /* Hide menu items with login or registration text when logged in */
            li a:contains("login"), li a:contains("Login"),
            li a:contains("registration"), li a:contains("Registration"),
            li a:contains("register"), li a:contains("Register"),
            li a[href*="/login/"], li a[href*="/registration/"], li a[href*="/register/"] {
                display: none !important;
            }
            
            /* Hide parent li elements */
            li:has(a[href*="/login/"]),
            li:has(a[href*="/registration/"]),
            li:has(a[href*="/register/"]),
            nav li a[href*="/login/"],
            nav li a[href*="/registration/"],
            nav li a[href*="/register/"],
            .menu li a[href*="/login/"],
            .menu li a[href*="/registration/"],
            .menu li a[href*="/register/"],
            .menu-item a[href*="/login/"],
            .menu-item a[href*="/registration/"],
            .menu-item a[href*="/register/"] {
                display: none !important;
            }
        </style>
        <?php
    } else {
        // When not logged in: Hide profile links
        ?>
        <style type="text/css">
            /* Hide menu items with profile text when not logged in */
            li a:contains("profile"), li a:contains("Profile"),
            li a[href*="/profile/"] {
                display: none !important;
            }
            
            /* Hide parent li elements */
            li:has(a[href*="/profile/"]),
            nav li a[href*="/profile/"],
            .menu li a[href*="/profile/"],
            .menu-item a[href*="/profile/"] {
                display: none !important;
            }
        </style>
        <?php
    }
    
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Get login status
        var isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
        
        // Terms to look for based on login status
        var termsToHide = isLoggedIn ? 
            ['login', 'registration', 'register'] : 
            ['profile'];
            
        var pathsToHide = isLoggedIn ? 
            ['/login/', '/registration/', '/register/'] : 
            ['/profile/'];
        
        function hideMenuItems() {
            var menuLinks = document.querySelectorAll('li a');
            
            for (var i = 0; i < menuLinks.length; i++) {
                var linkText = menuLinks[i].textContent.toLowerCase();
                var linkHref = menuLinks[i].getAttribute('href') || '';
                var shouldHide = false;
                
                // Check text content
                for (var j = 0; j < termsToHide.length; j++) {
                    if (linkText.indexOf(termsToHide[j]) !== -1) {
                        shouldHide = true;
                        break;
                    }
                }
                
                // Check href
                if (!shouldHide) {
                    for (var j = 0; j < pathsToHide.length; j++) {
                        if (linkHref.indexOf(pathsToHide[j]) !== -1) {
                            shouldHide = true;
                            break;
                        }
                    }
                }
                
                // Hide if needed
                if (shouldHide) {
                    var parentLi = menuLinks[i].closest('li');
                    if (parentLi) {
                        parentLi.style.display = 'none';
                    } else {
                        menuLinks[i].style.display = 'none';
                    }
                }
            }
        }
        
        // Execute immediately
        hideMenuItems();
        
        // Also run after a short delay to catch any dynamically loaded menus
        setTimeout(hideMenuItems, 500);
        
        // Set up a MutationObserver to watch for DOM changes
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
                hideMenuItems();
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
    </script>
    <?php
}

/**
 * Initialize menu filters
 */
function my_passwordless_auth_init_navbar_filters() {
    // Add CSS and JS to the head
    add_action('wp_head', 'my_passwordless_auth_navbar_css');
    
    // Apply filter to all potential menu outputs
    add_filter('wp_nav_menu_items', 'my_passwordless_auth_navbar_filter', 9999);
    add_filter('wp_page_menu', 'my_passwordless_auth_navbar_filter', 9999);
    add_filter('render_block', function($block_content, $block) {
        if ($block['blockName'] === 'core/navigation') {
            return my_passwordless_auth_navbar_filter($block_content);
        }
        return $block_content;
    }, 9999, 2);
    
    // Hook into all menu-related filters
    add_action('wp', 'my_passwordless_auth_register_all_navbar_filters');
}

/**
 * Register filter for all potential navigation hooks
 */
function my_passwordless_auth_register_all_navbar_filters() {
    global $wp_filter;
    
    // Get all filters
    foreach ($wp_filter as $tag => $filter) {
        // If the filter name contains 'menu', 'nav', or 'navigation', hook into it
        if (stripos($tag, 'menu') !== false || 
            stripos($tag, 'nav') !== false || 
            stripos($tag, 'navigation') !== false) {
            
            // Don't re-hook into filters we've already added
            if ($tag !== 'wp_nav_menu_items' && $tag !== 'wp_page_menu') {
                add_filter($tag, 'my_passwordless_auth_navbar_filter', 9999);
            }
        }
    }
}

// Initialize the navigation filters
my_passwordless_auth_init_navbar_filters();
