<?php
/**
 * SSO_Auth_Request - Handles SSO (CAS) login, registration, Open ID login,
 * and logout. (Controller)
 * 
 * @author Dan Crimmins
 * @uses SSO_Base_Request (inherits from)
 *
 */
class SSO_Auth_Request extends SSO_Base_Request {
	
	/**
	 * Sets the type of request being made based on the 
	 * 'sso_action' QS param that is being passed.
	 * 
	 * @var string
	 * @access protected
	 */
	protected $_request_type;
	
	/**
	 * Array map of SHC CAS endpoints based on environment.
	 * 
	 * @var array 
	 * @access protected
	 */
	protected $_endpoints = array('production'		=> 'https://sso.shld.net/shccas/',
									'integration'	=> 'http://toad.ecom.sears.com:8080/shccas/',
									'qa'			=> 'https://phoenix.ecom.sears.com:1443/shccas/');
	
	/**
	 * Array map of request types to CAS URI.
	 * 
	 * @var array 
	 * @access protected
	 */
	protected $_actions = array('_login' 			=> 'shcLogin',
							  	'_login_check'		=> 'shcLogin',
							 	'_register' 		=> 'shcRegistration',
								'_logout'			=> 'logout',
							  	'_validate'			=> 'serviceValidate',
							  	'_openid'			=> 'shcOpenIdLogin',
								'_logout_execute'	=> '');
	
	
	
	/**
	 * Constructor - Sets $_request_type and $_endpoint (inherited).
	 * 
	 * @param void
	 * @return void
	 * @access public
	 */
	public function __construct() {
		 
		parent::__construct();
		$this->_set_action();
		$this->_endpoint();
		
	}
	
	/**
	 * Factory
	 * 
	 * @param void
	 * @return object - instance of this class.
	 */
	public static function factory() {
		
		return new SSO_Auth_Request;
	}
	
	/**
	 * Handles SSO(CAS) requests. Calls appropriate method based 
	 * on $_request_type
	 * 
	 * @access public
	 * @param void
	 * @return void
	 */
	public function process() {
		
		$this->{$this->_request_type}();
		
	}
	
	/**
	 * Sets $_request_type and $_action.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _set_action() {
		
		$this->_request_type = (isset($_REQUEST[SHCSSO_QUERYSTRING_PARAM])) ? $_REQUEST[SHCSSO_QUERYSTRING_PARAM] : '_login';
		$this->_action = (isset($this->_actions[$this->_request_type])) ? $this->_actions[$this->_request_type] : $this->_actions['_login'];
		
	}
	
	/**
	 * Handles CAS login.
	 * 
	 * @accesss protected
	 * @param void
	 * @return void
	 */
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
										'sourceSiteid'		=> SSO_Utils::options('sso_site_id')
										));
		
	}
	
	/**
	 * Handles Open ID login requests.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _openid() {
		
		$user = SSO_Openid_Request::factory()->auth_info($_REQUEST['token']);
		
		if($user) { //User data present proceed...
			
			$profile = SSO_Profile_Request::factory();
			
			$cas = $this->_query('loginId', $user['email'])
						 ->_query('ts', $profile->timestamp)
				 		 ->_query('sourceSiteId', SSO_Utils::options('sso_site_id'))
				 		 ->_query('renew', 'false')
				 		 ->_query('gateway', 'true')
				 		 ->_query('service', urlencode(SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate&openid'))
				 		 ->_query('sig', $profile->digital_signature)
				 		 ->_method('POST')
				 		 ->_url(false)
				 		 ->_execute(false);	

				 		
			//Response must be echoed
	 		 echo $cas;
	 		 exit;
			
		} else { //No user data present, throw error
			
			SSO_Utils::view('error', array('msg'	=> 'Did not receive user data from OpenID provider.',
											'close_OID'	=> true));
			exit;
		}
		
	}
	
	/**
	 * Validates ticket ($_POST['ticket']) returned from CAS for login/ registration. Also,
	 * handles new user creation and WP login.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _validate() {
		
		//Is this a validation for an openID login?
		$openid = isset($_REQUEST['openid']);
		
		if(isset($_POST['errorCode']) && ! empty($_POST['errorCode'])) {
						
			$this->_error($openid);
			
		} else {
			
			if(isset($_POST['ticket'])) { //Validate ticket
				
				
				$response = $this->_query('ticket', $_POST['ticket'])
				 				->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate' . ((isset($_REQUEST['openid'])) ? '&openid' : null) . ((isset($_REQUEST['sso-registration'])) ? '&sso-registration' : null) . (isset($_REQUEST['sso_check']) ? '&sso_check' : null))
			 					->_url()
				 				->_execute(true);
				
 				//Use SSO_User
 				$user = SSO_User::factory($response);
 					
 				//If not a new user, check for location and screen name updates...
 				if(! $user->is_new) {
 					
 					$user->update_screen_name();
 					$user->update_location();
 				}
 				
 				//if there was problem creating new user...
 				if($user->is_new && ! $user->created) {
 					
 					SSO_Utils::view('error', array('msg' => 'There was an issue creating new user.',
 													'close_OID'	=> $openid));
 					exit;
 				}
 				
 				//Save data
 				$user->save();
 				
 				//If there was an issue saving the user data...
 				if(! $user->is_saved) {
 					
 					SSO_Utils::view('error', array('msg' => 'There was an issue saving user data.',
 													'close_OID' => $openid));
 					exit;
 				}
 				
 				//If this is a new SSO registration, send to responsys
 				if(isset($_REQUEST['sso-registration'])) {
 					
 					SSO_Responsys_Request::factory($user->email, $user->user_id)->send();
 				}
 				
 				//Log user in (WP)
 				$user->login();
 				
 				//echo view (JS) to refresh parent
 				SSO_Utils::view('refresh', array('close_OID' => $openid));
 					
			} else { //No ticket, error time...
				
				if(isset($_REQUEST['sso_check'])) {
					
					SSO_Utils::view('close', array());
					
				} else {
					
					SSO_Utils::view('error', array('msg' => 'A ticket was not received from CAS.',
												'close_OID'	=> $openid));
					exit;
				}
				
			}
			
		}
			
	}
	
	/**
	 * Handles registration of new CAS user account.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _register() {
		
		if(empty($_REQUEST['loginId']) || empty($_REQUEST['logonPassword']) || empty($_REQUEST['zipcode'])) {
			
			SSO_Utils::view('error', array('msg' => 'Please enter your e-mail, password, and zipcode.'));
			exit;
		}
		
		$this->_url();
		
		
		SSO_Utils::view('register', array('url'				=> $this->url,
										'logonPassword'		=> $_REQUEST['logonPassword'],
										'loginId'			=> $_REQUEST['loginId'],
										'zipcode'			=> SSO_Utils::truncate_zipcode($_REQUEST['zipcode']),
										'service'			=> SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate&sso-registration',
										'sourceSiteid'		=> SSO_Utils::options('sso_site_id')
										));
		
	}
	
	/**
	 * Checks if user has a current SSO session
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _session_check() {
		
		$this->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_validate&sso_check')
			->_query('sourceSiteid', SSO_Utils::options('sso_site_id'))
			->_query('gateway','true')
			->_url();
			
			
		SSO_Utils::view('redirect', array('url' => $this->url));
	}
	
	
	/**
	 * Handles initiation of CAS logout.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _logout() {
		
		$this->_query('service', SHCSSO_SERVICE_URL . '?' . SHCSSO_QUERYSTRING_PARAM . '=_logout_execute')
			->_url();
		
		SSO_Utils::view('redirect', array('url' => $this->url));
		
	}
	
	/**
	 * Handles response from CAS logout request. Performs WP logout.
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _logout_execute() {
		
		wp_logout();
		
		SSO_Utils::view('refresh', array());
	}
	
	/**
	 * Handles error returned from CAS login/registration request.
	 * 
	 * @param bool $openid - Is this from an OpenID login request?
	 * @return void
	 */
	protected function _error($openid=false) {
		
		SSO_Utils::view('error', array('msg' => SSO_Utils::config('errors', $_POST['errorCode']),
																	'close_OID'	=> $openid));
		
	}
	
	/**
	 * Sets $_endpoint property based on $_environment (inherited)
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
}