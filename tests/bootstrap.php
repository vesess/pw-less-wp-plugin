<?php
require_once __DIR__ . '/../vendor/autoload.php';



define('WPINC', true);
define('MY_PASSWORDLESS_AUTH_VERSION', '1.0.0');
define('MY_PASSWORDLESS_AUTH_PATH', __DIR__ . '/../');
define('MY_PASSWORDLESS_AUTH_URL', 'http://example.com/wp-content/plugins/pw-less-wp-plugin/');

// Enable Patchwork for better function mocking
WP_Mock::setUsePatchwork(true);

// Enable strict mode
WP_Mock::activateStrictMode();

// Bootstrap WP_Mock
WP_Mock::bootstrap();