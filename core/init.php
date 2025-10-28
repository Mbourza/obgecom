<?php 

ob_start();
session_start();

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$GLOBALS['config'] = array(

	'mysql' => array(

		'host' => 'srv1580.hstgr.io',
		'user' => 'u256132326_platform',
		'pass' => 'ObgA11Azerty;;',
		'db'   => 'u256132326_platform' 
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

spl_autoload_register(function($class){

	require_once('./dashboard/classes/'. $class .'.php');
});

require_once ('./dashboard/functions/escape.php');


?>