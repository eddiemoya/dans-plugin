<?php

/*
Plugin Name: Sears SSO Login and Profile
Description: Integrates Wordpress with the Sears SSO and Profile web services.
Version: 0.1.0
Author: Dan Crimmins & Brian Greenacre
*/

/**
 * Sears Holdings SSO and Profile wordpress plugin.
 *
 * This plugin integrates the Sears SSO and Profile service into Wordpress.
 *
 * @package Shcsso
 * @author Dan Crimmins & Brian Greenace
 * @version $id$
 */
if ( ! defined('SHCSSO_VERSION'))
{
    define('SHCSSO_VERSION', '0.1.0');
    define('SHCSSO_PATH', WP_PLUGIN_DIR . '/shc-sso-profile/');
    define('SHCSSO_CONFIG_DIR', SHCSSO_PATH . 'config/');
    define('SHCSSO_CLASS_DIR', SHCSSO_PATH . 'class/');
    define('SHCSSO_FILE', SHCSSO_PATH . pathinfo(__FILE__, PATHINFO_BASENAME));
    define('SHCSSO_OPTION_PREFIX', 'shc_sso_');
	
    //Load classes
    require_once SHCSSO_CLASS_DIR . 'sso.php';
    require_once SHCSSO_CLASS_DIR . 'profile.php';
    require_once SHCSSO_CLASS_DIR . 'sso_admin.php';
    require_once SHCSSO_CLASS_DIR . 'openid_rpx.php';
    
    require_once SHCSSO_PATH . 'functions.php';
    
    add_action('init', array('SSO', 'init'));
    add_action('init', array('SSO_Admin', 'init'));
    register_activation_hook(SHCSSO_FILE, array('SSO_Admin', 'install'));
    register_deactivation_hook(SHCSSO_FILE, array('SSO_Admin', 'uninstall'));
}
