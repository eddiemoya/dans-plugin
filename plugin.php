<?php
/*
Plugin Name: Sears SSO Login and Profile
Description: Integrates Wordpress with the Sears SSO and Profile web services.
Version: 2.0
Author: Dan Crimmins




/**
 * Sears Holdings SSO and Profile wordpress plugin.
 *
 * This plugin integrates the Sears SSO and Profile service into Wordpress.
 *
 * @package Shcsso
 * @author Dan Crimmins
 * @version $id$
 */
if ( ! defined('SHCSSO_VERSION'))
{
    define('SHCSSO_VERSION', '0.1.0');
    define('SHCSSO_PATH', WP_PLUGIN_DIR . '/shc-sso-profile/');
    define('SHCSSO_CONFIG_DIR', SHCSSO_PATH . 'config/');
    define('SHCSSO_CLASS_DIR', SHCSSO_PATH . 'class/');
    define('SHCSSO_VIEWS_DIR', SHCSSO_PATH . '/views/');
    define('SHCSSO_FILE', SHCSSO_PATH . pathinfo(__FILE__, PATHINFO_BASENAME));
    define('SHCSSO_OPTION_PREFIX', 'shc_sso_');
     
    //SSO querystring param used to control actions
    define('SHCSSO_QUERYSTRING_PARAM', 'sso_action');
    
    //Public path and files
    define('SHCSSO_PUBLIC_URL', WP_PLUGIN_URL . '/shc-sso-profile/public/');
	define('SHCSSO_SERVICE_URL', SHCSSO_PUBLIC_URL . 'service.php');
	define('SHCSSO_LOGIN_URL' , SHCSSO_PUBLIC_URL . 'login.php');
	
	require_once SHCSSO_CLASS_DIR . 'sso_utils.php';
	require_once SHCSSO_PATH . 'functions.php';
	
	//Register autoload function
    spl_autoload_register(array('SSO_Utils', 'autoload'));
    
    //Init
    add_action('init', array('SSO_Utils', 'init'));
    add_action('init', array('SSO_Admin', 'init'));
    
    //Install/ Uninstall
    register_activation_hook(SHCSSO_FILE, array('SSO_Admin', 'install'));
    register_deactivation_hook(SHCSSO_FILE, array('SSO_Admin', 'uninstall'));
}
