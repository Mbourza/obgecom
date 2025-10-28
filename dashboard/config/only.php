<?php 
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$GLOBALS['config'] = array(

	'mysql' => array(

		'host' => '',
		'user' => '',
		'pass' => '',
		'db'   => '' 
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

require_once ('../functions/escape.php'); ?>
