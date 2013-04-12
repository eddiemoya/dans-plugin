<?php

class SSO_Auth_Request extends SSO_Base_Request {
	
	
	protected $_request_type;
	
	protected $_endpoints = array('production'		=> 'https://sso.shld.net/shccas/',
									'integration'	=> 'http://toad.ecom.sears.com:8080/shccas/',
									'qa'			=> 'https://phoenix.ecom.sears.com:1443/shccas/');
	
	protected $_actions = array('_login' 			=> 'shcLogin',
							  	'_login_check'		=> 'shcLogin',
							 	'_register' 		=> 'shcRegistration',
								'_logout'			=> 'logout',
							  	'_validate'			=> 'serviceValidate',
							  	'_openid'			=> 'shcOpenIdLogin');
	
	
	
	public function __construct() {
		
		parent::__construct();
		$this->_set_action();
		
	}
	
	public static function factory() {
		
		return new SSO_Auth_Request;
	}
	
	public function process() {
		
		$this->{$this->request_type}();
		
	}
	
	protected function _set_action() {
		
		$this->_request_type = (isset($_REQUEST[SHCSSO_QUERYSTRING_PARAM])) ? $_REQUEST[SHCSSO_QUERYSTRING_PARAM] : '_login';
		$this->_action = (isset($this->_actions[$this->_request_type])) ? $this->_actions[$this->_request_type] : $this->_actions['_login'];
		
	}
	
	
	protected function _login() {
		
		$this->_url();
		
		SSO_Utils::view('login', array('url'				=> $this->url,
										'logonPassword'		=> $_REQUEST['logonPassword'],
										'loginId'			=> $_REQUEST['loginId'],
										'service'			=> SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate',
										'sourceSiteid'		=> $_REQUEST['sourceSiteid']
										));
		
	}
	
	protected function _openid() {
		
	}
	
	protected function _validate() {
		
		if(isset($_POST['errorCode']) && ! empty($_POST['errorCode'])) {
			
			$this->_error();
			
		} else {
			
			if(isset($_POST['ticket'])) {
				
				$response = $this->_query('ticket', $_POST['ticket'])
				 				->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate')
			 					->_url()
				 				->_execute(true);	
				 				
 				//Use SSO_User
 				
				 //1. add create() to SSO_User for new users -- also add functionality to pass object to constructor of SSO_User
				 //2. add password_gen to SSO Users
				 //3. add login() to SSO_User
				 //4. Add something to remove iframe -- probably out put some JS
				
			} else {
				
				//Error - no ticket
			}
			
		}
			
	}
	
	protected function _register() {
		
	}
	
	protected function _logout() {
		
	}
	
	protected function _action() {
		
	}
	
	protected function _error() {
		
		SSO_Utils::view('error', array('msg' => SSO_Utils::config('errors', $_POST['errorCode'])));
		
	}
	
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
	
}