<?php
/**
 * @author Dan Crimmins
 * 
 * 
 * Production values: 
 * 		api key:  cb097f9ddff64676d5017df4c335d740ae7e9915 
 * 		endpoint URL: https://signin.shld.net/api/v2/
 * 
 * QA values: 
 * 		api_key: f6a74858c2c73195905a60579116293b9f5eb7fd
 * 		endpoint URL: https://rpxnow.com/api/v2/
 *
 */
class OpenID_RPX {
	
	/**
	 * Array of environment endpoint URLS
	 * @var array
	 */
	private $_endpoints = array('production'	=> 'https://signin.shld.net/api/v2/',
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
	 * API key
	 * @var string
	 */
	private $_api_key = 'cb097f9ddff64676d5017df4c335d740ae7e9915';
	
	/**
	 * Environment
	 * @var string
	 */
	private $_environment = 'production';
	
	/**
	 * Endpoint URL
	 * @var string
	 */
	private $_endpoint = 'https://rpxnow.com/api/v2/';
	
	/**
	 * Action to URI mappings
	 * @var array
	 */
	private $_actions = array('auth_info'		=> 'auth_info',
							  'get_mappings' 	=> 'mappings',
							  'map'				=> 'map');
	
	/**
	 * Holds querystring params/values
	 * @var array
	 */
	private $_query = array();
	
	/**
	 * Holds post param/values
	 * @var array
	 */
	private $_post;
	
	/**
	 * The HTTP method to use: GET,POST,PUT, etc.
	 * @var string
	 */
	private $_method;
	
	/**
	 * The action part of the URI
	 * @var string
	 */
	private $_action;
	
	/**
	 * Instance of SSO_Profile object
	 * @var unknown_type
	 */
	private $_sso_profile = null; 
	
	/**
	 * Holds user data from response from api call
	 * @var array
	 */
	public $user = array();
	
	/**
	 * The URL on our site that JanRain sends response to
	 * @var string
	 */
	public $token_url;
	
	/**
	 * PHP4 Constructor
	 */
	public function OpenID_RPX() {
		
		$this->__construct();
		
	}
	
	/**
	 * PHP5 constructor
	 */
	public function __construct() {
		
		//Get and set Plugin options
		$this->set_options();
		
		//Set endpoint to use based on environment option
		$this->_endpoint = $this->_endpoints[$this->_environment];
		
		//Set API key
		$this->_api_key = $this->_api_keys[$this->_environment];
		
		//Create and set new profile object
		$this->_sso_profile = new SSO_Profile;
		
		$this->token_url = $this->url_append_qs('openid_auth&origin=' . urlencode(get_permalink(get_page_by_path('refresh'))), $this->get_current_url());
		
	}
	
	/**
	 * Handles token sent from JanRain
	 * @param string $token
	 */
	public function auth_info($token = null) {
		
		
		if($token && strlen($token) == 40) {
			
			$response = $this->set_action(__METHOD__)
							->set_method('POST')
							->set_post(array('token'	=> $token,
									 	'apiKey'		=> $this->_api_key,
									 	'format'		=> 'json',
									 	'extended'		=> 'true'))
							->execute();
							
				
				/*echo '<pre>';		
				var_dump($response);
				exit;*/
														
			//If response OK, sets 'user' property (array)
			//, else sends to login page with message				
			$this->handle_auth_response($response);
			
			/*echo '<pre>';
			var_dump($this->user);
			exit;*/
			
			
			//Search (CIS) profile
			$guid = $this->_sso_profile->search($this->user['email']); 
			
			
			/*echo '<pre>';
			var_dump($guid);
			exit;*/
			
				if(isset($guid['code']) && $guid['code'] == '404') {
				
					//User not found, create new SSO Profile user
					$guid = $this->_sso_profile->create($this->user);
					
					/*var_dump($guid);
					exit;*/
					
					
					
					return ($this->map($guid->id)) ? $this->user : false;
					
				} else { //SSO user exists
					
					//Map SSO GUID to user
					//return ($this->map($guid->id)) ? $this->user : false;
					return $this->user;
					
				}
				
		} else {
			
			$this->redirect_to_login('There was an issue with the authentication provider, please try again.');
		}
	}
	
	/**
	 * Retrieve WP plugin options for openID, and sets properties
	 * @param void
	 */
	private function set_options() {
		
		$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
		
		if(is_array($options)){
			
			$this->_environment = $options['environment'];
			
				/*if(! empty($options['oid_api_key'])) {
					
					$this->_api_key = $options['oid_api_key'];
					
				}*/
		}
		
	}
	
	/**
	 * Gets OpenID mappings by GUID
	 * @param string $guid
	 */
	private function get_mappings($guid) {
		
		$response = $this->set_action(__METHOD__)
						 ->set_method('POST')
						 ->set_post(array('apiKey'		=> $this->_api_key,
					 					  'primaryKey'	=> $guid))
						 ->execute();
						 
			 return ($response['stat'] == 'ok') ? $response['identifiers'] : false;
		
	}
	
	/**
	 * Maps a GUID to OpenID provider
	 * @param string - SSO GUID
	 */
	private function map($guid) {
		
		$response = $this->set_action(__METHOD__)
						 ->set_method('POST')
						 ->set_query('apiKey', $this->_api_key)
						 ->set_query('identifier', $this->user['openid_id'])
						 ->set_query('primaryKey', $guid)
						 ->set_query('overwrite', 'false')
						 ->execute();
						 
			/*echo '<pre>';
			 var_dump($response);
			 exit;*/
						 
			 return ($response->stat == 'ok') ? true : false; 
	}
	
	/**
	 * Sets action based on method calling it.
	 * 
	 * @param string $method
	 */
	private function set_action($method) {
		
		$method = ltrim($method, __CLASS__ . '::');
		$this->_action = $this->_actions[$method];
		
		return $this;
	}
	
	/**
	 * Adds key/value pair to query property (array)
	 * 
	 * @param string $key
	 * @param string $value
	 */
	private function set_query($key, $value) {
		
		$this->_query[$key] = $value;
		
		return $this;
	}
	
	/**
	 * Sets post property
	 * @param array $data
	 */
	private function set_post($data) {
		
		$this->_post = $data;
		
		return $this;
	}
	
	/**
	 * Sets method property
	 * @param string $verb
	 */
	private function set_method($verb) {
		
		$this->_method = $verb;
		
		return $this;
	}
	
	/**
	 * Sets endpoint property
	 * @param void
	 */
	private function create_url() {
		
		return $this->_endpoint . $this->_action . ((count($this->_query)) ?  '?' . $this->create_querystring() : '');
	}
	
	/**
	 * Returns query property - the querystring
	 * @param void
	 * @return string -- query property
	 */
	private function get_query() {
		
		return $this->_query;
	} 
	
	/**
	 * Creates querystring string from query property
	 * @return string - the querystring
	 */
	private function create_querystring() {
		
		$qs = '';
		
		foreach ($this->get_query() as $key => $value) {
			
			$qs .= $key . '=' . $value . '&';
		}
		
		return rtrim($qs, '&');
	}
	
	/**
	 * Makes cURL request, and returns response as object, or throws exception
	 * 
	 * @throws Exception
	 * @return object - the response data
	 */
	private function execute() {
		
		$url = $this->create_url();
		
		
		$options = array(
			
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_ENCODING        => "", 
            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_CUSTOMREQUEST	=> $this->_method,
            CURLOPT_URL				=> $url,
            CURLOPT_FOLLOWLOCATION	=> 0
        );
        
        //If this is a POST set post data 
        if($this->_method == 'POST') {
        	
        	$options[CURLOPT_POSTFIELDS] = $this->_post;
        			
        }
        		
  
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
	        
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ( ! $response)
        {
            throw new Exception('Encountered an error trying to send request. cURL Error: ' . $code);
            
        } else {
        	
	       return json_decode($response);
	       
        }
	}
	
	/**
	 * Takes repsonse object and sets user property (array), 
	 * or redirects to login page with error
	 * 
	 * @param object $response
	 */
	private function handle_auth_response($response) {
		
		
		if($response->stat == 'ok') {
			
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
			
			/*echo '<pre>';
			var_dump($this->user);
			exit;*/
			
		} else {
			
			//There was an issue, send them to login page, with error
			$this->redirect_to_login('Authentication failed with the provider. Please enter a valid username and password.');
		}
		
	}
	
	/**
	 * Redirects user to login page with error message
	 * 
	 * @param string $msg - the error message 
	 */
	private function redirect_to_login($msg) {
		
		header('Location: ' . $this->url_append_qs('error=' . urlencode($msg) . '&origin=' . $_GET['origin'], $this->_sso_profile->login_page));
		exit;
	}
	
	/**
	 * Takes a URL and adds querystring params to existing params
	 * 
	 * @param string $qs
	 * @param string $url
	 */
	private function url_append_qs($qs, $url) {
	
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
	
	/**
	 * Returns the current URL
	 * @return string 
	 */
	private function get_current_url() {
		
		return (! empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
	}

	
}