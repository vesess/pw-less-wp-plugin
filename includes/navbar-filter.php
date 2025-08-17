<?php
/**
 * Navigation Menu Filter
 *
 * This file contains functions to filter navigation menu items based on user login status:
 * 1. Hide login/sign-up items when user is logged in
 * 2. Hide profile items when user is not logged in
 *
 * @package VESESSLABS_VESESSAUTH
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Menu filter to hide login/sign-up items when user is logged in
 * And hide profile links when user is not logged in
 */
function vesesslabs_vesessauth_navbar_filter($html) {
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
        // When logged in: Hide login and sign-up links
        $patterns = array(
            // Match any list item containing login text
            '/<li[^>]*>\s*<a[^>]*>([^<]*login[^<]*)<\/a>.*?<\/li>/is',
            // Match any list item containing sign-up text
            '/<li[^>]*>\s*<a[^>]*>([^<]*sign-up[^<]*)<\/a>.*?<\/li>/is',
            // Match any list item containing register text
            '/<li[^>]*>\s*<a[^>]*>([^<]*register[^<]*)<\/a>.*?<\/li>/is',
            
            // Match menu items with login/sign-up/register in href
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/login\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/sign-up\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/register\/[^"]*"[^>]*>.*?<\/a>.*?<\/li>/is',
            
            // More inclusive patterns for various HTML structures
            '/<li[^>]*class="[^"]*\b(?:menu-item|page-item|nav-item)[^"]*"[^>]*>\s*<a[^>]*href="[^"]*\/(login|sign-up|register)\/[^"]*"[^>]*>.*?<\/li>/is',
            
            // Catch menu items that may have additional markup inside
            '/<li[^>]*>\s*<a[^>]*href="[^"]*\/(login|sign-up|register)\/[^"]*"[^>]*>.*?<\/a>(?:(?!<\/li>).)*<\/li>/is'
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
function vesesslabs_vesessauth_navbar_css() {
    // Check if WordPress function exists
    if (!function_exists('is_user_logged_in')) {
        return;
    }
    
    // Enqueue navbar filter CSS
    wp_enqueue_style(
        'vesesslabs_vesessauth-navbar-filter',
        VESESSLABS_VESESSAUTH_URL . 'public/css/navbar-filter.css',
        array(),
        VESESSLABS_VESESSAUTH_VERSION
    );
    
    // Add appropriate body class for CSS targeting
    $body_class = is_user_logged_in() ? 'logged-in-hide-logout' : 'logged-out-hide-profile';
    
    // Enqueue navbar filter JavaScript for additional dynamic handling
    wp_enqueue_script(
        'vesesslabs_vesessauth-navbar-filter',
        VESESSLABS_VESESSAUTH_URL . 'public/js/navbar-filter.js',
        array(),
        VESESSLABS_VESESSAUTH_VERSION,
        true
    );
    
    // Use inline script to add the body class
    wp_add_inline_script(
        'vesesslabs_vesessauth-navbar-filter',
        'document.addEventListener("DOMContentLoaded", function() { document.body.classList.add("' . esc_js($body_class) . '"); });'
    );
}

/**
 * Initialize menu filters
 */
function vesesslabs_vesessauth_init_navbar_filters() {
    // Add CSS and JS to the head
    add_action('wp_head', 'vesesslabs_vesessauth_navbar_css');
    
    // Apply filter to all potential menu outputs
    add_filter('wp_nav_menu_items', 'vesesslabs_vesessauth_navbar_filter', 9999);
    add_filter('wp_page_menu', 'vesesslabs_vesessauth_navbar_filter', 9999);
    add_filter('render_block', function($block_content, $block) {
        if ($block['blockName'] === 'core/navigation') {
            return vesesslabs_vesessauth_navbar_filter($block_content);
        }
        return $block_content;
    }, 9999, 2);
    
    // Hook into all menu-related filters
    add_action('wp', 'vesesslabs_vesessauth_register_all_navbar_filters');
}

/**
 * Register filter for all potential navigation hooks
 */
function vesesslabs_vesessauth_register_all_navbar_filters() {
    global $wp_filter;
    
    // Get all filters
    foreach ($wp_filter as $tag => $filter) {
        // If the filter name contains 'menu', 'nav', or 'navigation', hook into it
        if (stripos($tag, 'menu') !== false || 
            stripos($tag, 'nav') !== false || 
            stripos($tag, 'navigation') !== false) {
            
            // Don't re-hook into filters we've already added
            if ($tag !== 'wp_nav_menu_items' && $tag !== 'wp_page_menu') {
                add_filter($tag, 'vesesslabs_vesessauth_navbar_filter', 9999);
            }
        }
    }
}

// Initialize the navigation filters
vesesslabs_vesessauth_init_navbar_filters();
