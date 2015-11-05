<?php

define('REQUEST_MICROTIME', microtime(true));

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
define('PUBLIC_DIRECTORY', __DIR__);

define('APP_PRODUCTION', 'production');
define('APP_DEVELOPMENT', 'development');
define('APP_TESTING', 'testing');

define('APP_ENV', getenv('APP_ENV') ?: APP_PRODUCTION);

/**
 * This makes our life easier when dealing with paths.
 * Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

define('CORE_DIRECTORY', getcwd());

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)))
{
    return false;
}

// Setup autoloading
require 'init_autoloader.php';

// Run the application!
Zend\Mvc\Application::init(require 'config/application.config.php')->run();
