<?php

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
	 * API key
	 * @var string
	 */
	private $_api_key = 'cb097f9ddff64676d5017df4c335d740ae7e9915';
	
	
	
	/**
	 * Array of user data from OID response
	 * @var array
	 */
	public $user = array();
	
	
	public function __construct() {
		
		parent::__construct(); //_environment is set here
		
		$this->_endpoint();
		
	}
	
	public static function factory() {
		
		return new SSO_Openid_Request();
	}
	
	public function auth_info($token = null) {
		
		if($token && strlen($token) == 40) {
			
			$response = $this->_set_action(__METHOD__)
								->_method('POST')
								->_post(array('token'			=> $token,
									 			'apiKey'		=> $this->_api_key,
									 			'format'		=> 'json',
									 			'extended'		=> 'true'))
								->_execute(true, 'json');
								
			$this->_user($response);
			
			//Get user's CIS profile data
			$profile = SSO_Profile_Request::factory()
											->search($this->user['email']);
											
											
			if(isset($profile['code']) && $profile['code'] == '404') {
					
				//User not found, create new SSO Profile user
				$new_user = SSO_Profile_Request::factory()->create($this->user);
				
				return ($this->_map($new_user->id)) ? $this->user : false;
						
			} else { //SSO user exists
				
				return $this->user;
			}
											
		} else { //No token
			
			SSO_Utils::view('error', array('msg'		=> 'There was an issue with the authentication provider, please try again.',
											'close_OID'	=> true));
		}
		
	}
	
	/**
	 * Maps a GUID to OpenID provider
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
						 ->_execute(true, 'json');
						 
		return ($response->stat == 'ok') ? true : false; 
		
	}
	
	
	protected function _set_action($method) {
		
		$method = str_replace(__CLASS__ . '::', '', $method);
		$this->_action = $this->_actions[$method];

		return $this;	
	}
	
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
	protected function _user(object $response) {
		
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
