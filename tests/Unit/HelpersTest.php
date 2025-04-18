<?php

namespace MyPasswordlessAuth\Tests\Unit;

use WP_Mock;
use WP_Mock\Tools\TestCase; // Use WP_Mock's TestCase for easier integration
use Mockery; // Import Mockery
use stdClass; // Import stdClass

// Ensure helper functions are loaded. Adjust path as necessary.
require_once dirname(__DIR__, 2) . '/includes/helpers.php'; 

// Define WordPress constants if not already defined (e.g., by bootstrap)
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
}
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false); // Default to false unless overridden
}
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', false);
}

class HelpersTest extends TestCase
{
    /**
     * Set up the test environment before each test.
     */
    public function setUp(): void
    {
        WP_Mock::setUp();
        // Clear any potential global state from env loading between tests
        unset($GLOBALS['my_passwordless_env']); 
    }

    /**
     * Tear down the test environment after each test.
     */
    public function tearDown(): void
    {
        WP_Mock::tearDown();
        // Clear any potential global state from env loading between tests
        unset($GLOBALS['my_passwordless_env']);
    }

    /**
     * Test my_passwordless_auth_get_option()
     */
    public function test_my_passwordless_auth_get_option()
    {
        $options = ['test_key' => 'test_value'];

        // Mock get_option to return our test options
        WP_Mock::userFunction('get_option', [
            'args' => ['my_passwordless_auth_options'],
            'return' => $options,
        ]);

        // Test getting an existing key
        $this->assertEquals('test_value', my_passwordless_auth_get_option('test_key'));

        // Test getting a non-existent key with a default value
        $this->assertEquals('default_val', my_passwordless_auth_get_option('non_existent_key', 'default_val'));

        // Test getting a non-existent key without a default value (should return empty string)
        $this->assertEquals('', my_passwordless_auth_get_option('another_non_existent_key'));

        // Test when get_option returns false (no options saved yet)
        WP_Mock::userFunction('get_option', [
            'args' => ['my_passwordless_auth_options'],
            'return' => false, // Simulate option not existing
        ]);
        $this->assertEquals('fallback', my_passwordless_auth_get_option('any_key', 'fallback'));
        $this->assertEquals('', my_passwordless_auth_get_option('any_key_no_default'));
    }

    /**
     * Test my_passwordless_auth_is_email_verified()
     */
    public function test_my_passwordless_auth_is_email_verified()
    {
        $admin_user_id = 1;
        $verified_user_id = 2;
        $unverified_user_id = 3;

        // Use simple stdClass objects for WP_User mocks
        $admin_user = new stdClass(); 
        $admin_user->ID = $admin_user_id;

        $non_admin_user = new stdClass();
        // ID not strictly needed here, but can add if necessary for other tests
        // $non_admin_user->ID = $verified_user_id; 

        // Mock get_user_by for admin check
        WP_Mock::userFunction('get_user_by', [
            'args' => ['id', $admin_user_id],
            'return' => $admin_user,
        ]);
        // Mock user_can for admin check
        WP_Mock::userFunction('user_can', [
            'args' => [$admin_user, 'administrator'],
            'return' => true,
        ]);

        // Mock get_user_by for non-admin users
        WP_Mock::userFunction('get_user_by', [
            'args' => ['id', $verified_user_id],
            'return' => $non_admin_user,
        ]);
         WP_Mock::userFunction('get_user_by', [
            'args' => ['id', $unverified_user_id],
            'return' => $non_admin_user, // Same mock user object is fine
        ]);
        // Mock user_can for non-admin users
        WP_Mock::userFunction('user_can', [
            'args' => [$non_admin_user, 'administrator'],
            'return' => false,
        ]);

        // Mock get_user_meta for verification status
        WP_Mock::userFunction('get_user_meta', [
            'args' => [$verified_user_id, 'email_verified', true],
            'return' => true, // Verified
        ]);
        WP_Mock::userFunction('get_user_meta', [
            'args' => [$unverified_user_id, 'email_verified', true],
            'return' => false, // Not verified
        ]);

        // Test admin user (should always return true)
        $this->assertTrue(my_passwordless_auth_is_email_verified($admin_user_id));

        // Test verified non-admin user
        $this->assertTrue(my_passwordless_auth_is_email_verified($verified_user_id));

        // Test unverified non-admin user
        $this->assertFalse(my_passwordless_auth_is_email_verified($unverified_user_id));
    }

    /**
     * Test my_passwordless_auth_generate_token()
     * Note: This test is basic, just checking format. True randomness is hard to test.
     */
    public function test_my_passwordless_auth_generate_token()
    {
        $token = my_passwordless_auth_generate_token();
        $this->assertIsString($token);
        // bin2hex(random_bytes(16)) produces a 32-character hex string
        $this->assertEquals(32, strlen($token)); 
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
    }

    /**
     * Test my_passwordless_auth_get_template_url()
     */
    public function test_my_passwordless_auth_get_template_url()
    {
        $home_url = 'http://example.com';
        WP_Mock::userFunction('home_url', [
            'return' => $home_url,
        ]);

        // Mock add_query_arg - we expect it to be called with specific args
        WP_Mock::userFunction('add_query_arg')
            ->times(2) // Expect it twice
            ->andReturnUsing(function ($args, $url) use ($home_url) {
                // Basic simulation of add_query_arg
                $this->assertEquals($home_url, $url);
                return $url . '?' . http_build_query($args);
            });

        // Test with only template name
        $expected_url_1 = $home_url . '?my_passwordless_auth_template=login';
        $this->assertEquals($expected_url_1, my_passwordless_auth_get_template_url('login'));

        // Test with additional args
        $args = ['redirect_to' => '/dashboard', 'key' => 'value'];
        $expected_query_args = array_merge(['my_passwordless_auth_template' => 'register'], $args);
        $expected_url_2 = $home_url . '?' . http_build_query($expected_query_args);
        $this->assertEquals($expected_url_2, my_passwordless_auth_get_template_url('register', $args));
    }

    /**
     * Test my_passwordless_auth_log() - focusing on transient storage
     */
    public function test_my_passwordless_auth_log_transient()
    {
        $message = 'Test log message';
        $level = 'warning';
        $first_time = '2025-04-18 10:00:00';
        $second_message = "Another message";
        $second_level = 'info';
        $second_time = '2025-04-18 10:00:01';

        // Ensure WP_DEBUG is true for error_log call
        // REMOVED: WP_Mock::userFunction('defined', [...]);
        // REMOVED: WP_Mock::userFunction('constant', [...]);
        
        // Mock transient functions
        // First call to get_transient returns false
        WP_Mock::userFunction('get_transient')
            ->once() // First call expectation
            ->with('my_passwordless_auth_logs')
            ->andReturn(false);

        // Second call to get_transient returns the first log entry
        $first_log_entry_array = [
            [
                'message' => $message,
                'level'   => $level,
                'time'    => $first_time
            ]
        ];
        WP_Mock::userFunction('get_transient')
            ->once() // Second call expectation
            ->with('my_passwordless_auth_logs')
            ->andReturn($first_log_entry_array); // Return the log from the first call

        // Mock current_time to be called twice with different return values
        WP_Mock::userFunction('current_time')
            ->twice()
            ->with('mysql')
            ->andReturn($first_time, $second_time);

        // Expect set_transient to be called twice with specific arguments each time
        // Expectation for the first call
        WP_Mock::userFunction('set_transient')
            ->once() // First call expectation
            ->with(
                'my_passwordless_auth_logs',
                Mockery::on(function ($logs) use ($message, $level, $first_time) {
                    // Check if the logs array has one entry and it matches the first log data
                    return is_array($logs) && count($logs) === 1 &&
                           $logs[0]['message'] === $message &&
                           $logs[0]['level'] === $level &&
                           $logs[0]['time'] === $first_time;
                }),
                DAY_IN_SECONDS
            );

        // Expectation for the second call
        WP_Mock::userFunction('set_transient')
            ->once() // Second call expectation
            ->with(
                'my_passwordless_auth_logs',
                Mockery::on(function ($logs) use ($message, $level, $first_time, $second_message, $second_level, $second_time) {
                    // Check if the logs array has two entries and they match our data
                    return is_array($logs) && count($logs) === 2 &&
                           // First entry
                           $logs[0]['message'] === $message &&
                           $logs[0]['level'] === $level &&
                           $logs[0]['time'] === $first_time &&
                           // Second entry
                           $logs[1]['message'] === $second_message &&
                           $logs[1]['level'] === $second_level &&
                           $logs[1]['time'] === $second_time;
                }),
                DAY_IN_SECONDS
            );


        // Mock add_action to prevent errors when display=true
        // Pass a simple anonymous function to satisfy the callable type hint
        $dummy_callable = function() {};
        WP_Mock::expectActionAdded('wp_footer', $dummy_callable);
        WP_Mock::expectActionAdded('admin_footer', $dummy_callable);
        WP_Mock::expectActionAdded('admin_notices', $dummy_callable);
        WP_Mock::userFunction('is_admin')
            ->andReturn(true) // Explicitly set return value
            ->zeroOrMoreTimes(); // Allow it to be called zero or more times

        // Call the function (without display first)
        my_passwordless_auth_log($message, $level, false);

        // Call again with display=true to ensure actions are added
        my_passwordless_auth_log($second_message, $second_level, true);

        // Assertions are handled by WP_Mock expectations
        $this->assertTrue(true); // Keep PHPUnit happy
    }
    
    /**
     * Test my_passwordless_auth_create_login_link()
     */
    public function test_my_passwordless_auth_create_login_link()
    {
        $user_email = 'test@example.com';
        $user_id = 5;
        $mock_user = new stdClass(); // Use simple stdClass
        $mock_user->ID = $user_id;
        $generated_token = 'encrypted_url_token_string';
        $encrypted_user_id = 'encrypted_user_id_string';
        $home_url = 'http://mytestsite.com';

        // Mock get_user_by
        WP_Mock::userFunction('get_user_by')
            ->with('email', $user_email) // Use with() instead of calledWith()
            ->andReturn($mock_user)
            ->once(); // Expect it once for the success case

        // Mock the token generation function (we test this separately)
        WP_Mock::userFunction('my_passwordless_auth_generate_login_token')
            ->with($user_id) // Use with()
            ->andReturn($generated_token)
            ->once();

        // Mock the user ID encryption function (we test this separately)
        WP_Mock::userFunction('my_passwordless_auth_encrypt_user_id')
            ->with($user_id) // Use with()
            ->andReturn($encrypted_user_id)
            ->once();
            
        // Mock home_url
        WP_Mock::userFunction('home_url')
            ->andReturn($home_url)
            ->once();

        // Mock esc_url_raw (just return the input for simplicity in test)
        WP_Mock::userFunction('esc_url_raw')
            ->andReturnUsing(function($url) { return $url; })
            ->once();
            
        // Mock logging function
        WP_Mock::userFunction('my_passwordless_auth_log')->atLeast()->once();

        // --- Test Success Case ---
        $expected_link = $home_url . '?action=magic_login&uid=' . $encrypted_user_id . '&token=' . $generated_token;
        $actual_link = my_passwordless_auth_create_login_link($user_email);
        $this->assertEquals($expected_link, $actual_link);

        // --- Test Failure Case (User Not Found) ---
        $non_existent_email = 'nouser@example.com';
        WP_Mock::userFunction('get_user_by')
            ->with('email', $non_existent_email) // Use with()
            ->andReturn(false) // Simulate user not found
            ->once();

        $this->assertFalse(my_passwordless_auth_create_login_link($non_existent_email));
    }

    // --- Tests for Encryption/Decryption ---
    // Note: These tests mock openssl functions. They verify the *logic* 
    // (calling openssl correctly, base64 encoding/decoding, using env vars)
    // but don't perform actual encryption.

    private function mock_env_vars() {
         // Mock getenv and $GLOBALS access
        WP_Mock::userFunction('my_passwordless_auth_get_env')
            ->zeroOrMoreTimes() // Allow multiple calls
            ->andReturnUsing(function($key, $default) {
                $vars = [
                    'PWLESS_DB_KEY' => 'PwLessWpAuthPluginSecretKey123!',
                    'PWLESS_DB_IV' => 'PwLessWpAuthIv16----',
                    'PWLESS_URL_KEY' => 'UrlTokenEncryptionKey456!',
                    'PWLESS_URL_IV' => 'UrlTokenIv16Val--',
                    'PWLESS_UID_KEY' => 'PwLessWpAuthPluginSecretKey123!', // Same as DB for test simplicity
                    'PWLESS_UID_IV' => 'PwLessWpAuthIv16----',     // Same as DB for test simplicity
                ];
                return $vars[$key] ?? $default;
            });
    }
    
    /**
     * Test encryption/decryption round trip for tokens (URL format)
     */
    public function test_token_url_encryption_decryption_round_trip() {
        $this->mock_env_vars();
        $plain_token = 'mysecrettokenstring1234567890';
        
        // Call the actual encryption function
        $encrypted_url_token = my_passwordless_auth_encrypt_token_for_url($plain_token);
        
        // We can't deterministically predict the exact encrypted output without running openssl,
        // so we'll focus on the round trip. We can check it's not empty and is a string.
        $this->assertIsString($encrypted_url_token);
        $this->assertNotEmpty($encrypted_url_token);
        $this->assertNotEquals($plain_token, $encrypted_url_token); // Ensure it's actually changed

        // Call the actual decryption function
        $decrypted_token = my_passwordless_auth_decrypt_token_from_url($encrypted_url_token);
        $this->assertEquals($plain_token, $decrypted_token);
    }
       /**
     * Test encryption/decryption round trip for User IDs
     * 
     * Note: Since we can't mock internal PHP functions like openssl_encrypt/decrypt,
     * we'll test the actual encryption/decryption functionality
     */
    public function test_user_id_encryption_decryption_round_trip() {
        // Clear any potential mocks to ensure we're using real functions
        WP_Mock::tearDown();
        WP_Mock::setUp();
        
        $user_id = 123;
        
        // Create a custom environment for testing
        $GLOBALS['my_passwordless_env'] = [
            'PWLESS_UID_KEY' => 'TestKey1234567890TestKey1234567890',
            'PWLESS_UID_IV' => 'TestIV1234567890'
        ];
        
        // Allow logging
        WP_Mock::userFunction('my_passwordless_auth_log')->zeroOrMoreTimes();
        
        // Perform actual encryption (no mocking)
        $encrypted_uid = my_passwordless_auth_encrypt_user_id($user_id);
        
        // Validate encrypted output format
        $this->assertIsString($encrypted_uid);
        $this->assertNotEmpty($encrypted_uid);
        
        // Now decrypt and verify
        $decrypted_id = my_passwordless_auth_decrypt_user_id($encrypted_uid);
        $this->assertEquals($user_id, $decrypted_id);
        $this->assertIsInt($decrypted_id);
        
        // Clean up
        unset($GLOBALS['my_passwordless_env']);
    }
    
    /**
     * Test decryption failure for User ID (invalid input)
     */
    public function test_user_id_decryption_failure_invalid_input() { // Renamed for clarity
        $this->mock_env_vars();
        $invalid_encrypted_uid = 'this_is_not_valid_base64_or_encrypted_data!';
        WP_Mock::userFunction('my_passwordless_auth_log')->zeroOrMoreTimes();

        // Call the actual decryption function with invalid input
        $decrypted_id = my_passwordless_auth_decrypt_user_id($invalid_encrypted_uid);
        $this->assertFalse($decrypted_id, "Decryption should fail for invalid input.");
    }
      // --- Tests for Environment Variable Handling ---

   
}
