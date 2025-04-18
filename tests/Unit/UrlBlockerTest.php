<?php

use WP_Mock\Tools\TestCase;
use WP_Mock\Functions;
use Mockery;

// Define constants if not already defined (needed for class loading/testing)
if (!defined('MY_PASSWORDLESS_AUTH_PATH')) {
    define('MY_PASSWORDLESS_AUTH_PATH', dirname(__DIR__, 2) . '/');
}
if (!defined('MY_PASSWORDLESS_AUTH_URL')) {
    define('MY_PASSWORDLESS_AUTH_URL', 'http://example.com/wp-content/plugins/pw-less-wp-plugin/');
}
if (!defined('MY_PASSWORDLESS_AUTH_VERSION')) {
    define('MY_PASSWORDLESS_AUTH_VERSION', '1.0.0');
}
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/helpers.php';
require_once MY_PASSWORDLESS_AUTH_PATH . 'includes/class-url-blocker.php';

class UrlBlockerTest extends TestCase {
    protected $url_blocker;

    public function setUp(): void {
        WP_Mock::setUp();
        $this->url_blocker = new My_Passwordless_Auth_URL_Blocker();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function test_init_adds_action_and_sets_up_blocked_urls() {
        WP_Mock::userFunction('add_action')
            ->once()
            ->with('template_redirect', [$this->url_blocker, 'check_blocked_urls']);
        // Mock setup_blocked_urls
        $mock = Mockery::mock($this->url_blocker)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('setup_blocked_urls')->once();
        $mock->init();
        $this->assertConditionsMet();
    }

    public function test_setup_blocked_urls_sets_urls_and_redirect() {
        WP_Mock::userFunction('home_url', ['return' => 'http://example.com']);
        WP_Mock::userFunction('get_option', ['return' => [
            'user_home_url' => 'http://example.com',
            'login_redirect' => 'http://example.com/redirect-here',
        ]]);
        $this->url_blocker->setup_blocked_urls();
        $reflection = new \ReflectionClass($this->url_blocker);
        $blocked_urls = $reflection->getProperty('blocked_urls');
        $blocked_urls->setAccessible(true);
        $redirect_url = $reflection->getProperty('redirect_url');
        $redirect_url->setAccessible(true);
        $this->assertContains('http://example.com/sample-page', $blocked_urls->getValue($this->url_blocker));
        $this->assertEquals('http://example.com/redirect-here', $redirect_url->getValue($this->url_blocker));
    }

    public function test_check_blocked_urls_does_not_block_admin() {
        WP_Mock::userFunction('is_admin', ['return' => true]);
        $mock = Mockery::mock($this->url_blocker)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldNotReceive('get_current_url');
        $mock->check_blocked_urls();
        $this->assertConditionsMet();
    }

    public function test_check_blocked_urls_does_not_block_if_not_logged_in() {
        WP_Mock::userFunction('is_admin', ['return' => false]);
        WP_Mock::userFunction('is_user_logged_in', ['return' => false]);
        $mock = Mockery::mock($this->url_blocker)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldNotReceive('get_current_url');
        $mock->check_blocked_urls();
        $this->assertConditionsMet();
    }

    public function test_check_blocked_urls_redirects_on_blocked_url() {
        WP_Mock::userFunction('is_admin', ['return' => false]);
        WP_Mock::userFunction('is_user_logged_in', ['return' => true]);
        $mock = Mockery::mock($this->url_blocker)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('get_current_url')->andReturn('http://example.com/sample-page');
        $mock->shouldReceive('convert_wildcard_to_regex')->andReturn('/^http:\/\/example\.com\/sample\-page$/i');
        $reflection = new \ReflectionClass($mock);
        $blocked_urls = $reflection->getProperty('blocked_urls');
        $blocked_urls->setAccessible(true);
        $blocked_urls->setValue($mock, ['http://example.com/sample-page']);
        $redirect_url = $reflection->getProperty('redirect_url');
        $redirect_url->setAccessible(true);
        $redirect_url->setValue($mock, 'http://example.com/redirect-here');
        WP_Mock::userFunction('wp_redirect')
            ->once()
            ->with('http://example.com/redirect-here')
            ->andReturnUsing(function() { throw new \Exception('redirect'); });
        try {
            $mock->check_blocked_urls();
        } catch (\Exception $e) {
            $this->assertEquals('redirect', $e->getMessage());
        }
    }

    public function test_convert_wildcard_to_regex() {
        $method = new \ReflectionMethod($this->url_blocker, 'convert_wildcard_to_regex');
        $method->setAccessible(true);
        $this->assertEquals('/^http:\/\/example\.com\/category\/.*$/i', $method->invoke($this->url_blocker, 'http://example.com/category/*'));
        $this->assertEquals('/^.*\/private\/.*$/i', $method->invoke($this->url_blocker, '*/private/*'));
    }

    public function test_get_current_url() {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/foo/bar';
        WP_Mock::userFunction('is_ssl', ['return' => false]);
        $method = new \ReflectionMethod($this->url_blocker, 'get_current_url');
        $method->setAccessible(true);
        $this->assertEquals('http://example.com/foo/bar', $method->invoke($this->url_blocker));
    }
}
