<?php 
// /www/wwwroot/obgecom/dashboard/core/init.php

ob_start();
session_start();

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define base path if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

$GLOBALS['config'] = array(
    'mysql' => array(

		'host' => '127.0.0.1',
		'user' => 'obg',
		'pass' => 'YnAGCnWCCm6pTYHR',
		'db'   => 'obg' 
	),
    'remember' => array(
        'cookie_name' => 'hash',
        'cookie_expiry' => 604800
    ),
    'session' => array(
        'session_name' => 'user',
        'token_name' => ''
    )
);

// Use absolute paths for autoloader
spl_autoload_register(function($class){
    $classPath = BASE_PATH . 'classes/' . $class . '.php';
    if (file_exists($classPath)) {
        require_once($classPath);
    } else {
        // Try alternative path
        $altPath = __DIR__ . '/../../classes/' . $class . '.php';
        if (file_exists($altPath)) {
            require_once($altPath);
        }
    }
});

// Use absolute path for escape.php
$escapePath = BASE_PATH . 'functions/escape.php';
if (file_exists($escapePath)) {
    require_once($escapePath);
} else {
    // Try alternative path
    $altEscapePath = __DIR__ . '/../../functions/escape.php';
    if (file_exists($altEscapePath)) {
        require_once($altEscapePath);
    } else {
        // If escape.php doesn't exist, create a simple escape function
        if (!function_exists('escape')) {
            function escape($string) {
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            }
        }
    }
} ?>