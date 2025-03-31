<?php
/**
 * Template for the registration form
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!get_option('users_can_register')) {
    echo '<p>' . esc_html__('Registration is currently disabled.', 'my-passwordless-auth') . '</p>';
    return;
}
?>
<div class="my-passwordless-auth-container registration-form-container">
    <form id="my-passwordless-auth-registration-form" class="my-passwordless-auth-form">
        <h2><?php _e('Register New Account', 'my-passwordless-auth'); ?></h2>
        
        <div class="messages"></div>
        
        <div class="form-row">
            <label for="email"><?php _e('Email Address', 'my-passwordless-auth'); ?> <span class="required">*</span></label>
            <input type="email" name="email" id="email" required />
            <p class="description"><?php _e('You\'ll use this email to log in', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row">
            <label for="username"><?php _e('Username', 'my-passwordless-auth'); ?></label>
            <input type="text" name="username" id="username" />
            <p class="description"><?php _e('Leave empty to use your email address as username', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row">
            <label for="display_name"><?php _e('Display Name', 'my-passwordless-auth'); ?></label>
            <input type="text" name="display_name" id="display_name" />
            <p class="description"><?php _e('How your name will be displayed on the site', 'my-passwordless-auth'); ?></p>
        </div>
        
        <div class="form-row button-row">
            <input type="submit" value="<?php esc_attr_e('Register', 'my-passwordless-auth'); ?>" class="submit-btn" />
        </div>
        
        <p class="login-register-link">
            <?php _e('Already have an account?', 'my-passwordless-auth'); ?>
            <a href="<?php echo esc_url(home_url('/index.php/login/')); ?>"><?php _e('Login here', 'my-passwordless-auth'); ?></a>
        </p>
        <input type="hidden" name="action" value="register_new_user" />
        <?php wp_nonce_field('registration_nonce', 'nonce'); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('my-passwordless-auth-registration-form');
    
    form.addEventListener('submit', function(e) {
        const emailField = document.getElementById('email');
        const usernameField = document.getElementById('username');
        
        if (!usernameField.value.trim()) {
        
            usernameField.value = emailField.value;
        }
    });
});
</script>

<style>
    .my-passwordless-auth-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .my-passwordless-auth-form h2 {
        margin-bottom: 20px;
        text-align: center;
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
    
    .form-row .required {
        color: red;
    }
    
    .form-row .description {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    .button-row {
        margin-top: 20px;
    }
    
    input[type="submit"] {
        background-color: #0073aa;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 15px;
        width: 100%;
    }
    
    input[type="submit"]:hover {
        background-color: #005a87;
    }
    
    input[type="submit"]:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }
    
    .login-register-link {
        margin-top: 20px;
        text-align: center;
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
