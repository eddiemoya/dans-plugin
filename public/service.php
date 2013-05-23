<?php //Handles service calls

$file = substr(__FILE__, 0, (stripos(__FILE__, 'wp-content/'))) . 'wp-load.php';

require_once $file;

SSO_Auth_Request::factory()->process();


