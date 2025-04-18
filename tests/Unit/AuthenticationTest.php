<?php

use WP_Mock\Tools\TestCase;
use WP_Mock\Functions;
use Mockery;

// Define constants if not already defined (needed for class loading/testing)
if (!defined('MY_PASSWORDLESS_AUTH_PATH')) {
    // Adjust the path depth based on the test file location relative to the plugin root
    define('MY_PASSWORDLESS_AUTH_PATH', dirname(__DIR__, 2) . '/'); 
}
if (!defined('MY_PASSWORDLESS_AUTH_URL')) {
    define('MY_PASSWORDLESS_AUTH_URL', 'http://example.com/wp-content/plugins/pw-less-wp-plugin/');
}
if (!defined('MY_PASSWORDLESS_AUTH_VERSION')) {
    define('MY_PASSWORDLESS_AUTH_VERSION', '1.0.0');
}

// Manually include the class file and dependencies
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/helpers.php';
// require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-email.php'; // Ensure this is required for overload mock
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-authentication.php';

class AuthenticationTest extends TestCase {

    protected $authentication;

    public function setUp(): void {
        WP_Mock::setUp();
        
        // Mock helper functions commonly used by the Authentication class
        WP_Mock::userFunction('my_passwordless_auth_get_option', [
            'return' => function($key, $default) {
                // Provide specific mock values or return the default
                if ($key === 'code_expiration') return 15;
                if ($key === 'require_email_verification') return 'yes';
                return $default;
            }
        ]);
        WP_Mock::userFunction('my_passwordless_auth_is_email_verified', [
            'return' => true // Assume verified by default for simplicity
        ]);
        // WP_Mock::userFunction('my_passwordless_auth_log'); // Removed generic mock
        // WP_Mock::userFunction('apply_filters', [
        //     'return' => function($tag, $value) {
        //         // Return the original value by default for filters
        //         return $value;
        //     }
        // ]);
        WP_Mock::userFunction('home_url', [
            'return' => 'http://example.com'
        ]);

        $this->authentication = new My_Passwordless_Auth_Authentication();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        Mockery::close(); // Close Mockery expectations
    }

    /**
     * Test if the init method adds the expected WordPress actions.
     */
    public function test_init_adds_actions() {
        // Expect add_action to be called for each hook defined in init()
        WP_Mock::expectActionAdded('wp_ajax_nopriv_send_login_code', [$this->authentication, 'send_login_code']);
        WP_Mock::expectActionAdded('wp_ajax_nopriv_verify_login_code', [$this->authentication, 'verify_login_code']);
        WP_Mock::expectActionAdded('wp_ajax_send_login_code', [$this->authentication, 'send_login_code']);
        WP_Mock::expectActionAdded('my_passwordless_auth_after_login', [$this->authentication, 'after_login'], 10, 2);

        // Call the init method
        $this->authentication->init();

        // WP_Mock automatically asserts expectations in tearDown
        // You can add an explicit assertion here if preferred:
        $this->assertConditionsMet(); 
    }

    // --- Add more test methods below for other public methods --- 

    /**
     * Test the send_login_code method for a successful scenario.
     */
    public function test_send_login_code_success() {
        // 1. Set up $_POST variables
        $_POST['nonce'] = 'test_nonce';
        $_POST['email'] = 'test@example.com';

        // 2. Mock WordPress functions and helpers used within the method
        WP_Mock::userFunction('wp_verify_nonce')
            ->once()
            ->with('test_nonce', 'passwordless_login_nonce')
            ->andReturn(true);
            
        WP_Mock::userFunction('sanitize_email')
            ->once()
            ->with('test@example.com')
            ->andReturn('test@example.com');
            
        WP_Mock::userFunction('is_email')
            ->once()
            ->with('test@example.com')
            ->andReturn(true);
        
        // Mock the WP_User object
        $mock_user = Mockery::mock('WP_User');
        $mock_user->ID = 1;
        WP_Mock::userFunction('get_user_by')
            ->once()
            ->with('email', 'test@example.com')
            ->andReturn($mock_user);
        
        // Mock verification check (assuming it passes or is not required)
        // The setUp already mocks my_passwordless_auth_get_option and my_passwordless_auth_is_email_verified
        // We rely on the setUp mocks here, assuming 'require_email_verification' is 'yes' and the user is verified.
        
        // Explicitly expect the filter call using WP_Mock::onFilter
        WP_Mock::onFilter('my_passwordless_auth_code_length')
            ->with(6) // Expect the default value passed to apply_filters
            ->reply(6); // Define the return value for this filter

        WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->with(6, false, false) // Expect length from filter
            ->andReturn('123456'); // Mock generated code
            
        // Cannot mock internal time() function directly with WP_Mock.
        // We will rely on the actual time() function here.
        // To make this more deterministic, consider mocking the helper function
        // my_passwordless_auth_current_time() if you modify the main class.
        // For now, we calculate expected expiration based on the mocked option.
        $current_time = time(); // Use actual time
        $expiration_minutes = 15; // From setUp mock for my_passwordless_auth_get_option
        $expected_expiration = $current_time + ($expiration_minutes * 60);

        // Expect user meta updates - use Mockery::any() for time-based values
        WP_Mock::userFunction('update_user_meta')
            ->once()
            ->with(1, 'passwordless_login_code', '123456')
            ->andReturn(true);
        WP_Mock::userFunction('update_user_meta')
            ->once()
            // Check timestamp is roughly correct (within a small window) or use any()
            ->with(1, 'passwordless_login_code_timestamp', Mockery::any()) 
            ->andReturn(true);
        WP_Mock::userFunction('update_user_meta')
            ->once()
             // Check expiration is roughly correct (within a small window) or use any()
            ->with(1, 'passwordless_login_code_expiration', Mockery::any())
            ->andReturn(true);

        // 3. Mock the Email class method using 'overload'
        // Ensure class-email.php IS required_once at the top for overload to work correctly.
        $mock_email = Mockery::mock('overload:My_Passwordless_Auth_Email');
        $mock_email->shouldReceive('send_login_code')
            ->once()
            ->with(1, '123456') // Expect user ID and the generated code
            ->andReturn(true); // Simulate successful email sending

        // Set expectation for logging function ONLY within this test
        WP_Mock::userFunction('my_passwordless_auth_log')
            ->once()
            ->with("Login code requested for user ID 1", 'info');

        // 4. Expect success JSON response
        WP_Mock::userFunction('wp_send_json_success')
            ->once()
            ->with('Login code sent to your email');
            
        // Ensure error response is never called in success case
        WP_Mock::userFunction('wp_send_json_error')->never();

        // 5. Call the method
        $this->authentication->send_login_code();

        // 6. Assert conditions (WP_Mock does this automatically in tearDown)
        $this->assertConditionsMet(); // Optional explicit assertion
    }

    /**
     * Test the verify_login_code method for a successful scenario.
     */
    public function test_verify_login_code_success() {
        // 1. Set up $_POST variables
        $_POST['nonce'] = 'test_verify_nonce';
        $_POST['email'] = 'test@example.com';
        $_POST['code'] = '123456';

        // 2. Mock WordPress functions and helpers
        WP_Mock::userFunction('wp_verify_nonce')
            ->once()
            ->with('test_verify_nonce', 'passwordless_login_nonce')
            ->andReturn(true);

        WP_Mock::userFunction('sanitize_email')
            ->once()
            ->with('test@example.com')
            ->andReturn('test@example.com');

        WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with('123456')
            ->andReturn('123456');

        // Mock the WP_User object
        $mock_user = Mockery::mock('WP_User');
        $mock_user->ID = 1;
        WP_Mock::userFunction('get_user_by')
            ->once()
            ->with('email', 'test@example.com')
            ->andReturn($mock_user);

        // Mock user meta for code and expiration (ensure it's not expired)
        $current_time = time();
        $expiration_time = $current_time + (15 * 60); // 15 minutes in the future
        WP_Mock::userFunction('get_user_meta')
            ->once() // Expect it to be called once for the code
            ->with(1, 'passwordless_login_code', true)
            ->andReturn('123456'); // The correct code
        WP_Mock::userFunction('get_user_meta')
            ->once() // Expect it to be called once for the timestamp
            ->with(1, 'passwordless_login_code_timestamp', true)
            ->andReturn($current_time - 60); // Code generated 1 minute ago
        WP_Mock::userFunction('get_user_meta')
            ->once() // Expect it to be called once for the expiration
            ->with(1, 'passwordless_login_code_expiration', true)
            ->andReturn($expiration_time); // Code expires in the future

        // Mock login functions
        WP_Mock::userFunction('wp_clear_auth_cookie')->once();
        WP_Mock::userFunction('wp_set_current_user')->once()->with(1);
        WP_Mock::userFunction('wp_set_auth_cookie')->once()->with(1);

        // Mock meta cleanup
        WP_Mock::userFunction('delete_user_meta')->once()->with(1, 'passwordless_login_code');
        WP_Mock::userFunction('delete_user_meta')->once()->with(1, 'passwordless_login_code_timestamp');
        WP_Mock::userFunction('delete_user_meta')->once()->with(1, 'passwordless_login_code_expiration');

        // Mock last login time update
        WP_Mock::userFunction('update_user_meta')
            ->once()
            ->with(1, 'last_login_time', Mockery::any()) // Time is dynamic
            ->andReturn(true);

        // Mock logging
        WP_Mock::userFunction('my_passwordless_auth_log')
            ->once()
            ->with("User ID 1 successfully logged in", 'info');

        // Mock the after login action
        // Need to mock $_SERVER['REMOTE_ADDR'] if the action uses it directly
        // For simplicity, we assume it's available or mock it if needed.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; 
        WP_Mock::expectAction('my_passwordless_auth_after_login', 1, '127.0.0.1');

        // Mock redirect filter and home_url (home_url mocked in setUp)
        WP_Mock::onFilter('my_passwordless_auth_login_redirect')
            ->with('http://example.com', 1)
            ->reply('http://example.com/my-account'); // Example redirect URL

        // Expect success JSON response
        WP_Mock::userFunction('wp_send_json_success')
            ->once()
            ->with(array(
                'redirect_url' => 'http://example.com/my-account',
                'message' => 'Login successful!'
            ));

        // Ensure error response is never called
        WP_Mock::userFunction('wp_send_json_error')->never();

        // 3. Call the method
        $this->authentication->verify_login_code();

        // 4. Assert conditions (WP_Mock does this automatically in tearDown)
        $this->assertConditionsMet();
    }
}
