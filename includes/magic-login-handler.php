<?php
/**
 * Handler for processing magic login links
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Add a template page for the login form
 */
function my_passwordless_auth_add_login_template() {
    // Define the template path
    $login_template = MY_PASSWORDLESS_AUTH_PATH . 'templates/page-login.php';
    
    // Create directory if it doesn't exist
    if (!file_exists(dirname($login_template))) {
        wp_mkdir_p(dirname($login_template));
    }

    // Create the template if it doesn't exist
    if (!file_exists($login_template)) {
        $template_content = file_get_contents(MY_PASSWORDLESS_AUTH_PATH . 'templates/page-login.php');
        file_put_contents($login_template, $template_content);
    }
}
add_action('init', 'my_passwordless_auth_add_login_template');

/**
 * Add the page template to the templates list
 */
function my_passwordless_auth_add_page_template($templates) {
    $templates['templates/page-login.php'] = __('Passwordless Login Page', 'my-passwordless-auth');
    return $templates;
}
add_filter('theme_page_templates', 'my_passwordless_auth_add_page_template');

/**
 * Register a login page on plugin activation
 */
function my_passwordless_auth_create_login_page() {
    // Check if the login page already exists
    $existing_page = get_page_by_path('login');
    if ($existing_page) {
        return;
    }
    
    // Create the login page
    $page_id = wp_insert_post(array(
        'post_title'     => __('Login', 'my-passwordless-auth'),
        'post_content'   => '[passwordless_login_form]',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_name'      => 'login',
        'comment_status' => 'closed',
    ));
    
    if ($page_id && !is_wp_error($page_id)) {
        // Store the page ID in the options
        $options = get_option('my_passwordless_auth_options', array());
        $options['login_page_id'] = $page_id;
        update_option('my_passwordless_auth_options', $options);
        
        my_passwordless_auth_log("Login page created with ID: $page_id", 'info');
    }
}

// Register activation hook for creating the login page
register_activation_hook(MY_PASSWORDLESS_AUTH_PATH . 'my-passwordless-auth.php', 'my_passwordless_auth_create_login_page');

/**
 * Initialize the magic login handler
 */
function my_passwordless_auth_init_magic_login_handler() {
    // Check for magic login request on init (early)
    add_action('init', 'my_passwordless_auth_check_for_magic_login', 10);
    
    // We're removing this hook - no longer adding the form to the standard WordPress login
    // add_action('login_form', 'my_passwordless_auth_login_form_hook');
    
    add_action('wp_login_failed', 'my_passwordless_auth_handle_failed_login', 10, 2);
    
    // Register AJAX handlers
    add_action('wp_ajax_my_passwordless_auth_request_magic_link', 'my_passwordless_auth_ajax_request_magic_link');
    add_action('wp_ajax_nopriv_my_passwordless_auth_request_magic_link', 'my_passwordless_auth_ajax_request_magic_link');
}
add_action('plugins_loaded', 'my_passwordless_auth_init_magic_login_handler');

/**
 * Check for magic login requests and process them
 */
function my_passwordless_auth_check_for_magic_login() {
    if (isset($_GET['action']) && $_GET['action'] === 'magic_login') {
        $result = my_passwordless_auth_process_magic_login();
        
        if ($result === true) {
            // Success! Redirect to wherever you want after successful login
            $redirect = apply_filters('my_passwordless_auth_login_redirect', admin_url());
            wp_redirect($redirect);
            exit;
        } elseif (is_wp_error($result)) {
            // Something went wrong
            wp_die($result->get_error_message(), __('Login Failed', 'my-passwordless-auth'), [
                'response' => 403,
                'back_link' => true,
            ]);
        }
    }
}

/**
 * Add custom fields to login form for magic login
 * 
 * This function is no longer hooked into login_form, but kept for future reference
 */
function my_passwordless_auth_login_form_hook() {
    // Function content kept for reference but not used on the WordPress login page
}

/**
 * Handle failed login attempts and offer magic link alternative
 */
function my_passwordless_auth_handle_failed_login($username, $error) {
    if (empty($username)) {
        return;
    }
    
    // If the option is enabled, automatically send a magic link on failed password attempts
    if (my_passwordless_auth_get_option('send_magic_link_on_failed_login', false)) {
        $user = get_user_by('login', $username) ?: get_user_by('email', $username);
        
        if ($user) {
            my_passwordless_auth_send_magic_link($user->user_email);
            add_filter('login_message', function($message) {
                return $message . '<p class="message">' . 
                    __('We\'ve sent you an email with a magic login link.', 'my-passwordless-auth') . 
                    '</p>';
            });
        }
    }
}

/**
 * AJAX handler for requesting a magic login link
 */
function my_passwordless_auth_ajax_request_magic_link() {
    // Make sure we have a valid nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my-passwordless-auth-magic-link')) {
        wp_send_json_error([
            'message' => __('Security check failed. Please refresh the page and try again.', 'my-passwordless-auth')
        ]);
        return;
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!is_email($email)) {
        wp_send_json_error([
            'message' => __('Please enter a valid email address.', 'my-passwordless-auth')
        ]);
        return;
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    if (!$user) {
        wp_send_json_error([
            'message' => __('No account found with that email address.', 'my-passwordless-auth')
        ]);
        return;
    }
    
    // Send the magic link
    $result = my_passwordless_auth_send_magic_link($email);
    
    if ($result) {
        // Log successful sending
        my_passwordless_auth_log("Magic login link sent successfully to {$email}", 'info');
        
        wp_send_json_success([
            'message' => __('Login link sent! Please check your email inbox and click the link to log in.', 'my-passwordless-auth')
        ]);
    } else {
        // Log failure
        my_passwordless_auth_log("Failed to send magic login link to {$email}", 'error');
        
        wp_send_json_error([
            'message' => __('Failed to send the login link. Please try again or contact support.', 'my-passwordless-auth')
        ]);
    }
}

/**
 * Add login link to the site
 */
function my_passwordless_auth_add_login_menu_item($items, $args) {
    // Make sure we have theme_location property
    if (!isset($args->theme_location) || $args->theme_location != 'primary') {
        return $items;
    }
    
    if (is_user_logged_in()) {
        // Add logout link if user is logged in
        $items .= '<li class="menu-item"><a href="' . wp_logout_url(home_url()) . '">' . __('Log Out', 'my-passwordless-auth') . '</a></li>';
    } else {
        // Add login link if user is not logged in
        $options = get_option('my_passwordless_auth_options', array());
        $login_page_id = isset($options['login_page_id']) ? $options['login_page_id'] : '';
        
        if ($login_page_id) {
            $login_url = get_permalink($login_page_id);
            $items .= '<li class="menu-item"><a href="' . esc_url($login_url) . '">' . __('Log In', 'my-passwordless-auth') . '</a></li>';
        }
    }
    
    return $items;
}
add_filter('wp_nav_menu_items', 'my_passwordless_auth_add_login_menu_item', 10, 2);
