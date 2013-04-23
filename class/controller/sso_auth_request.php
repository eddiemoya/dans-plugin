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
							  	'_openid'			=> 'shcOpenIdLogin',
								'_logout_execute'	=> '');
	
	
	
	public function __construct() {
		
		parent::__construct();
		$this->_set_action();
		$this->_endpoint();
		
	}
	
	public static function factory() {
		
		return new SSO_Auth_Request;
	}
	
	public function process() {
		
		$this->{$this->_request_type}();
		
	}
	
	protected function _set_action() {
		
		$this->_request_type = (isset($_REQUEST[SHCSSO_QUERYSTRING_PARAM])) ? $_REQUEST[SHCSSO_QUERYSTRING_PARAM] : '_login';
		$this->_action = (isset($this->_actions[$this->_request_type])) ? $this->_actions[$this->_request_type] : $this->_actions['_login'];
		
	}
	
	
	protected function _login() {
		
		//Make sure user submitted username and password.
		if(empty($_REQUEST['logonPassword']) || empty($_REQUEST['loginId'])) {
			
			SSO_Utils::view('error', array('msg' => 'Please enter both a username and a password.'));
			exit;
		}
		
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
			
			if(isset($_POST['ticket'])) { //Validate ticket
				
				$response = $this->_query('ticket', $_POST['ticket'])
				 				->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate')
			 					->_url()
				 				->_execute(true);	
				 				
 				//Use SSO_User
 				$user = SSO_User::factory($response);
 				
 				//If not a new user, check for location and screen name updates
 				if(! $user->is_new) {
 					
 					$user->update_screen_name();
 					$user->update_location();
 				}
 				
 				//if there was problem creating new user
 				if($user->is_new && ! $user->created) {
 					
 					SSO_Utils::view('error', array('msg' => 'There was an issue creating new user.'));
 					exit;
 				}
 				
 				//Save data
 				$user->save();
 				
 				//If there was an issue saving the user data
 				if(! $user->is_saved) {
 					
 					SSO_Utils::view('error', array('msg' => 'There was an issue saving user data.'));
 					exit;
 				}
 				
 				//Log user in (WP)
 				$user->login();
 				
 				//echo view (JS) to refresh parent
 				SSO_Utils::view('refresh', array());
 				exit;
				
			} else { //No ticket, spawn error
				
				SSO_Utils::view('error', array('msg' => 'A ticket was not recieved from CAS.'));
				exit;
			}
			
		}
			
	}
	
	protected function _register() {
		
		if(empty($_REQUEST['loginId']) || empty($_REQUEST['logonPassword']) || empty($_REQUEST['zipcode'])) {
			
			SSO_Utils::view('error', array('msg' => 'Please enter your e-mail, password, and zipcode.'));
			exit;
		}
		
		$this->_url();
		
		SSO_Utils::view('register', array('url'				=> $this->url,
										'logonPassword'		=> $_REQUEST['logonPassword'],
										'loginId'			=> $_REQUEST['loginId'],
										'zipcode'			=> $_REQUEST['zipcode'],
										'service'			=> SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate',
										'sourceSiteid'		=> $_REQUEST['sourceSiteid']
										));
		
		
		
	}
	
	protected function _logout() {
		
		$this->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_logout_execute')
			->_url();
		
		SSO_Utils('redirect', array('url' => $this->url));
		
	}
	
	protected function _logout_execute() {
		
		wp_logout();
		
		SSO_Utils::view('refresh', array());
	}
	
	protected function _error() {
		
		SSO_Utils::view('error', array('msg' => SSO_Utils::config('errors', $_POST['errorCode'])));
		
	}
	
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
	
}