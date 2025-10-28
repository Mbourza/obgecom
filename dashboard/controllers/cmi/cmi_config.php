<?php

// Merchant credentials (provided by CMI/Attijari)
define('CMI_MERCHANT_ID', '810001004'); #
define('CMI_SECRET_KEY', 'Oi_@2025091819'); #

// Gateway URL
define('CMI_GATEWAY_URL', 'https://attijari-payment.cmi.co.ma/fim/est3Dgate'); 
#

$base_url = 'https://obgecom.com';

define('OK_URL', $base_url . '/payment_result');
define('FAIL_URL', $base_url . '/payment_result');
define('CALLBACK_URL', 'https://obgecom.com/callback.php');
define('SHOP_URL', $base_url);

// Currency
define('CURRENCY_CODE', '504'); 

// Test mode flag
define('CMI_TEST_MODE', false); ?>