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
		$this->_set_endpoint();
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
	
	protected function _set_endpoint() {
		
		$this->endpoint = $this->_endpoints[$this->environment];
	}
	
	/**
	 * Given a URL and a querystring returns URL with querystring appended to URL. 
	 * Will include port number if present in url. If a querystring is present in $url, will
	 * cleanly append $qs to end of current querystring.
	 * 
	 * Usage: url_append_qs('http://mydomain.com/', 'var1=something'), outputs: http://mydomain.com/?var1=something
	 * 			 url_append_qs('http://mydomain.com/?var1=something', 'var2=somethingelse'), outputs: http://mydomain.com/?var1=something&var2=somethingelse
	 * 
	 * @param string $qs - Examples: '&var1=val1&var2=val2' --leading ampersand is optional
	 * @param string $url
	 * @return string - $url with appended  $qs
	 * @access private
	 */
	public function url_append_qs($qs, $url) {
	
	$url_parts = parse_url($url);
	
	if(count($url_parts)) {
	
		$url_out = $url_parts['scheme'] . '://' . $url_parts['host'];
		
			//Is there a port number?
			if(isset($url_parts['port'])) {
				
				$url_out .= ':' . $url_parts['port'];
			}
			
			//Is there a uri?
			if(isset($url_parts['path'])) {
				
				$url_out .= $url_parts['path'];
			} 
			
			//Is there a querystring
			if(isset($url_parts['query'])) {
				
				$url_out .= '?' . $url_parts['query'] . '&' . ltrim($qs, '&');
				
			} else {
				
				$url_out .= '?' . ltrim($qs, '&');
			}
			
				return $url_out;
				
		} else { //$url was not a URL, return it
			
			return $url;
		}
	}
	
	
}