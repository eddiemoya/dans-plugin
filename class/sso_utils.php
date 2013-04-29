<?php

class SSO_Utils {
	
	
	/**
	 * $_option_name - the plugin options name
	 * @var string
	 */
	public static $_option_name;
	
	/**
	 * $_option_defaults - default option values
	 * @var array
	 */
	public static $_option_defaults =  array('environment'			=> 'production',
											'profile_key'			=> 'YWCHJTDwxjHlZXro3NggLiWKu_I',
											'profile_site_id'		=> 41,
											'sso_site_id' 			=> 41,
											'sso_role' 				=> 'Subscriber',
											'sso_login_page_uri'	=> '',
											'sso_reg_page_uri'		=> '',
											'profile_pwd_reset_page'=> '',
											/*'oid_api_key'			=> 'f6a74858c2c73195905a60579116293b9f5eb7fd'*/);
	
	/** 
	 * $_classes - Array of classes to load on init 
	 * @var array
	 * @see init()
	 */
	public static $_classes = array();
	/**
	 * _option_name() - Sets $_option_name
	 * 
	 * @param void
	 * @return void
	 */
	public static function _option_name() {
		
		self::$_option_name = SHCSSO_OPTION_PREFIX . 'settings';
	}
	
	/**
	 * autoload()
	 * 
	 * Plugins's class autoloader. Only allows for one level deep
	 * from root class directory.
	 * 
	 * @param string $class
	 * @return void
	 */
	public static function autoload($class) {
		
		$class_dir = SHCSSO_CLASS_DIR;
		$file = strtolower(trim($class)) . '.php';
		
		//Check class root dir first
		if(file_exists($class_dir . $file)) {
			
			require_once $class_dir . $file;
			
		} else {
			
			//Get all sub-dirs in class root dir
			$dirs = scandir($class_dir);
			
			if($dirs) {
				
				$exclude = array('...', '..', '.');
				
				foreach($dirs as $dir) {
					
					if(is_dir($class_dir . $dir) && ! in_array($dir, $exclude)) {
						
						if(is_file($class_dir . $dir . '/' . $file)) {
							
							require_once $class_dir . $dir . '/' . $file;
							return;
						}
					}
						
				}
			}
		
		}
	
	}
	
	/**
	 * options() - Sets and gets plugin options.
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return mixed - [array | string | NULL] 
	 */
	public static function options($name = null, $value = null) {
		
		//Set option prefix property
		self::_option_name();
		
		//Get plugin options
		$options = get_option(self::$_option_name);
		
		//Return entire settings array
		if($name === null && $value === null && $options) {
			
			return $options;
		}
		
		//Get a specific element from options
		if((($name !== null && ! is_array($name)) && $value === null) && isset($options[$name]) ) {
			
			return $options[$name];
		}
		
		//Set plugin options - all
		if(($name !== null && is_array($name)) && $value === null) {
			
			return update_option($this->_option_name, $value);
		}
		
		//Set, update value of one element of options array
		if($name !== null && $value !== null) {
			
			$options[$name] = $value;
			
			return update_option($this->_option_name, $options);
		}
		
		return null;
	}
	
	/**
	 * view() - Passes args, includes and echoes/returns view
	 * 
	 * @param string $view - path the view file in view dir
	 * @param array $args - assoc. array of vars that view will use.
	 * @return void
	 */
	public static function view($view, array $args = null, $return = false) {
		
		$file = SHCSSO_VIEWS_DIR . $view . '.php';
		
		
		if($args !== null)
			extract($args, EXTR_SKIP);
			
		ob_start();
		
		if(is_file($file)) {
			
			include $file;
		}
		
		if(! $return) {
			
			echo ob_get_clean();
			
		} else {
			
			return ob_get_clean();
		}
		
	}
	
	public static function config($file, $key=null) {
		
		$file = SHCSSO_CONFIG_DIR . $file . '.php';
		
		if(is_file($file)) {
			
			$out = require_once $file;
			
			if($key) {
				
				return (is_array($out) && isset($out[$key])) ? $out[$key] : null;
				
			} else {
				
				return $out;
			}
		}
		
		return null;
	}
	
	/**
	 * init() - Used to instantiate objects of classes with init hooks (ie. Admin stuff)
	 * 
	 * @param void
	 * @return void
	 */
	public static function init() {
		
		foreach(self::$_classes as $var=>$class) {
			
			$$var = new $class();
		}
		
		//Load OpenID JS - JSON with service URL
		if(! is_user_logged_in()) {
				
			add_action('wp_head', array('SSO_Utils', 'load_openid_js'));
		} 
		
		//Enqueue scripts
		self::enqueue();
			
	}
	
	/*public static function load_widgets() {
		
		$widgets = scandir(SHC_RR_WIDGETS);
		
		if($widgets) {
			
			ob_start();
			
			$exclude = array('...', '..', '.');
			
			foreach($widgets as $widget) {
				
				if(is_file(SHC_RR_WIDGETS . $widget) && ! in_array($widget, $exclude))
					
					require_once SHC_RR_WIDGETS . $widget;
			}
			
			$out = ob_get_clean();
			return $out;
		}
		
	}*/
	
	public static function load_openid_js() {
		
		echo '<script type="text/javascript">var OID = '. json_encode(array('token_url' => SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_openid')) .';</script>';
	}
	
	public static function enqueue() {
		
		wp_register_script('sso-js', plugins_url('assets/js/', dirname(__FILE__)) . 'sso-js.js', array('jquery', 'shcJSL', 'moodle'), '1.0');
		wp_enqueue_script('sso-js');
	}
	
	public static function install() {
			
		update_option(SHC_RR_PREFIX . 'settings', self::$_option_defaults);
	}
	
	public static function uninstall() {
			
		delete_option(SHC_PRODUCTS_PREFIX . 'settings');
	}
	
	
}
