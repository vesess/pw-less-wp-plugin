<?php
/**
 * Template for the passwordless login form
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="my-passwordless-auth-container login-form-container">
    <form id="my-passwordless-auth-login-form" class="my-passwordless-auth-form">
        <h2><?php _e('Passwordless Login', 'my-passwordless-auth'); ?></h2>
        
        <div class="messages"></div>
        
        <div class="form-row">
            <label for="email"><?php _e('Email Address', 'my-passwordless-auth'); ?></label>
            <input type="email" name="email" id="email" required />
        </div>
        
        <div class="form-row button-row">
            <button type="button" class="request-code-btn"><?php _e('Request Login Code', 'my-passwordless-auth'); ?></button>
        </div>
        
        <div class="form-row code-container" style="display: none;">
            <label for="code"><?php _e('Enter Login Code', 'my-passwordless-auth'); ?></label>
            <input type="text" name="code" id="code" placeholder="<?php esc_attr_e('Enter the code sent to your email', 'my-passwordless-auth'); ?>" />
            <button type="button" class="verify-code-btn"><?php _e('Verify Code & Login', 'my-passwordless-auth'); ?></button>
        </div>
        
        <?php if (!get_option('users_can_register')) : ?>
            <p class="login-register-link">
                <?php _e('New user?', 'my-passwordless-auth'); ?>
                <?php _e('Registration is currently disabled.', 'my-passwordless-auth'); ?>
            </p>
        <?php else : ?>
            <p class="login-register-link">
                <?php _e('New user?', 'my-passwordless-auth'); ?>
                <a href="<?php echo esc_url(add_query_arg('action', 'register')); ?>"><?php _e('Register here', 'my-passwordless-auth'); ?></a>
            </p>
        <?php endif; ?>
    </form>
</div>

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
    
    .button-row {
        margin-top: 20px;
    }
    
    button {
        background-color: #0073aa;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 15px;
    }
    
    button:hover {
        background-color: #005a87;
    }
    
    button:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }
    
    .code-container {
        margin-top: 20px;
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
