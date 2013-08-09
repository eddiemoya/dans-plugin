<?php
/**
 * SSO_Openid_Request (Controller)
 * 
 * Controller used to process OpenID Provider logins.
 * 
 * @author Dan Crimmins
 * @uses SSO_Base_Request
 *
 */
class SSO_Openid_Request extends SSO_Base_Request {
	
	/**
	 * Array of environment endpoint URLS
	 * @var array
	 */
	protected $_endpoints = array('production'	=> 'https://signin.shld.net/api/v2/',
									'qa'			=> 'https://rpxnow.com/api/v2/',
									'integration'	=> 'https://rpxnow.com/api/v2/');
	
	/**
	 * Array of api keys based on environment
	 * @var array
	 */
	private $_api_keys = array('production'		=> 'cb097f9ddff64676d5017df4c335d740ae7e9915',
								'qa'			=> 'f6a74858c2c73195905a60579116293b9f5eb7fd',
								'integration'	=> 'f6a74858c2c73195905a60579116293b9f5eb7fd'); 
	
	/**
	 * Action to URI mappings
	 * @var array
	 */
	private $_actions = array('auth_info'		=> 'auth_info',
							  'get_mappings' 	=> 'mappings',
							  'map'				=> 'map');
	
	/**
	 * API key - defaults to production key
	 * @var string
	 */
	private $_api_key = 'cb097f9ddff64676d5017df4c335d740ae7e9915';
	
	
	/**
	 * Array of user data from OID response
	 * @var array
	 */
	public $user = array();
	
	
	/**
	 * Constructor
	 * 
	 * Calls parent constructor, sets $_endpoint based on $_environment (inherited),
	 * sets $_api_key based on $_environment.
	 */
	public function __construct() {
		
		parent::__construct(); 
		
		$this->_endpoint();
		$this->_api_key = $this->_api_keys[$this->_environment];
		
	}
	
	/**
	 * Factory
	 * 
	 * @access public
	 * @param void
	 * @return instance of this class
	 */
	public static function factory() {
		
		return new SSO_Openid_Request();
	}
	
	/**
	 * Handles token ($_POST['token']) returned from initial OpenID provider 
	 * login request.
	 * 
	 * @access public
	 * @param string $token
	 * @return array - $user property
	 */
	public function auth_info($token = null) {
		
		if($token && strlen($token) == 40) {
			
			$response = $this->_set_action(__METHOD__)
								->_method('POST')
								->_post(array('token'			=> $token,
									 			'apiKey'		=> $this->_api_key,
									 			'format'		=> 'json',
									 			'extended'		=> 'true'))
								->_url()
								->_execute(true, 'json');
							
			
			//Set $user property with data from response
			$this->_user($response);
			
			//Get user's CIS profile data
			$profile = SSO_Profile_Request::factory()
											->search($this->user['email']);

			if(isset($profile['code']) && $profile['code'] == '') {
				
				SSO_Utils::view('error', array('msg'		=> 'CAS did not respond to request.',
												'close_OID'	=> true));
				exit;
				
			}
				
			if(isset($profile['code']) && $profile['code'] == '404') {
				
				//User not found, create new SSO Profile user
				$new_user = SSO_Profile_Request::factory()->create($this->user);
				
				return ($this->_map((string) $new_user->id)) ? $this->user : false;
						
			} else { //SSO user exists
				
				return $this->user;
			}
											
		} else { //No token
			
			SSO_Utils::view('error', array('msg'		=> 'No token received from OpenID provider.',
											'close_OID'	=> true));
			exit;
		}
		
	}
	
	/**
	 * Maps a GUID to OpenID provider.
	 * 
	 * @param string - SSO GUID
	 * @return bool
	 */
	protected function _map($guid) {
		
		$response = $this->_set_action(__METHOD__)
						 ->_method('POST')
						 ->_query('apiKey', $this->_api_key)
						 ->_query('identifier', $this->user['openid_id'])
						 ->_query('primaryKey', $guid)
						 ->_query('overwrite', 'false')
						 ->_url()
						 ->_execute(true, 'json');
						 
		return ($response->stat == 'ok') ? true : false; 
		
	}
	
	/**
	 * Sets $_action based on $method.
	 * 
	 * @param string $method - the method where this is called from.
	 * @return object - instance of this object.
	 */
	protected function _set_action($method) {
		
		$method = str_replace(__CLASS__ . '::', '', $method);
		$this->_action = $this->_actions[$method];

		return $this;	
	}
	
	/**
	 * Sets $_endpoint property based on $_envronment. (inherited)
	 * 
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
	/**
	 * Receives data from validation call from OpenID and puts
	 * relavent data into $user property  (array)
	 * 
	 * @access protected 
	 * @param stdClass $response
	 * @return void
	 */
	protected function _user(stdClass $response) {
		
		if($response->stat == 'ok') { //Got a valid response, set user data
			
			//User's Name
			$fullname =  explode(' ', $response->profile->name->formatted);
			
			if(count($fullname) > 2) {
				
				$this->user['first_name'] = $fullname[0];
				$this->user['last_name'] = $fullname[2];
				$this->user['middle_name'] = $fullname[1];
				
			} else {
				
				$this->user['first_name'] = $fullname[0];
				$this->user['last_name'] = $fullname[1];
			}
			
			$this->user['email'] = $response->profile->email;
			$this->user['openid_provider'] = rtrim(strtolower($response->profile->providerName), '!');
			$this->user['openid_id'] = $response->profile->identifier;
			
		} else { //Not a valid response, send error to login modal and close OID window
			
			SSO_Utils::view('error', array('msg' 		=> 'Authentication failed with the provider. Please enter a valid username and password.',
											'close_OID' => true));
			exit;
		}
	
	}
	
}
