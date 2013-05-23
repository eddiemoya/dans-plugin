<?php //Handles service calls

if(stripos(__FILE__, 'wordpress/') !== false) {
        $file =  substr(__FILE__, 0, (stripos(__FILE__, 'wordpress/') + 10)) . 'wp-load.php';
} else {
        $file = substr(__FILE__, 0, (stripos(__FILE__, 'wp-content'))) . 'wp-load.php';
}

require_once $file;

SSO_Auth_Request::factory()->process();


