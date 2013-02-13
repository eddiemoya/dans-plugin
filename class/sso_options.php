<?php

class SSO_Options {
	
	protected $_endpoints = array('production'		=> 'https://sso.shld.net/shccas/',
									'integration'	=> 'http://toad.ecom.sears.com:8080/shccas/',
									'qa'			=> 'https://phoenix.ecom.sears.com:1443/shccas/');
	protected $_options;
	
	public $data;
	
	public function __construct() {
		
		$this->_get_options();
		$this->_set_service_url();
	}
	
	public function factory() {
		
		return new SSO_Options;
	}
	
	public function __get($key) {
		
		return $this->data[$key];
	}
	
	public function __set($key, $value) {
		
		$this->data[$key] = $value;
	}
	
	public function __isset($key) {
		
		return isset($this->data[$key]);
	}
	
	protected function _get_options() {
		
		$this->_options = get_option(SHCSSO_OPTION_PREFIX . 'settings', false);
		
		if($this->_options)
		
			foreach($this->_options as $key=>$value) {
				
				$this->{$key} = $value;
			}
				
	}
	
	protected function _set_service_url() {
		
		$this->service = (! empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}
	
	
}