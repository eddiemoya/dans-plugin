<?php

class OpenID_RPX {
	
	private $_endpoints = array('production'	=> 'https://rpxnow.com/api/v2/',
								'qa'			=> 'https://rpxnow.com/api/v2/',
								'integration'	=> 'https://rpxnow.com/api/v2/');
	
	private $_environment = 'production';
	
	private $_endpoint = 'https://rpxnow.com/api/v2/';
	
	private $_api_key = 'f6a74858c2c73195905a60579116293b9f5eb7fd';
	
	private $_actions = array('auth_info'		=> 'auth_info',
							  'get_mappings' 	=> 'mappings',
							  'map'				=> 'map');
	
	private $_query = array();
	
	private $_post;
	
	private $_method;
	
	private $_action;
	
	private $_sso_profile = null; //SSO_Profile Object
	
	public $user = array();
	
	public $token_url;
	
	
	public function OpenID_RPX() {
		
		$this->__construct();
		
	}
	
	public function __construct() {
		
		//Get and set Plugin options
		$this->set_options();
		
		//Set endpoint to use based on environment option
		$this->_endpoint = $this->_endpoints[$this->_environment];
		
		//Create and set new profile object
		$this->_sso_profile = new SSO_Profile;
		
		$this->token_url = $this->url_append_qs('openid_auth&origin=' . urlencode($this->get_current_url()), $this->get_current_url());
		
	}
	
	
	public function auth_info($token = null) {
		
		/*var_dump($token);
		exit;*/
		
		if($token && strlen($token) == 40) {
			
			$response = $this->set_action(__METHOD__)
							->set_method('POST')
							->set_post(array('token'	=> $token,
									 	'apiKey'		=> $this->_api_key,
									 	'format'		=> 'json',
									 	'extended'		=> 'true'))
							->execute();
							
							

				/*echo 'Token: ' . $token . '<br>';
				echo 'Endpoint: ' . $this->_endpoint . '<br>';
				echo 'API key: ' . $this->_api_key . '<br>';
				echo 'Action: ' . $this->_action . '<br>';
				echo 'Method: ' . $this->_method . '<br>';
				echo 'URL: ' . $this->create_url() . '<br>';
				exit;*/
							
				
				
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
			
			/*echo $guid->id;
			exit;*/
			
				if(isset($guid->code) && $guid->code == '404') {
					
					/*var_dump($this->user);
					exit;*/
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
	
	//Retrieve WP plugin options for openID
	private function set_options() {
		
		$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
		
		if(is_array($options)){
			
			$this->_environment = $options['environment'];
			
				if(! empty($options['oid_api_key'])) {
					
					$this->_api_key = $options['oid_api_key'];
					
				}
		}
		
	}
	
	private function get_mappings($guid) {
		
		$response = $this->set_action(__METHOD__)
						 ->set_method('POST')
						 ->set_post(array('apiKey'		=> $this->_api_key,
					 					  'primaryKey'	=> $guid))
						 ->execute();
						 
			 return ($response['stat'] == 'ok') ? $response['identifiers'] : false;
		
	}
	
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
	
	
	private function set_action($method) {
		
		$method = ltrim($method, __CLASS__ . '::');
		$this->_action = $this->_actions[$method];
		
		return $this;
	}
	
	
	private function set_query($key, $value) {
		
		$this->_query[$key] = $value;
		
		return $this;
	}
	
	private function set_post($data) {
		
		$this->_post = $data;
		
		return $this;
	}
	
	private function set_method($verb) {
		
		$this->_method = $verb;
		
		return $this;
	}
	
	private function create_url() {
		
		return $this->_endpoint . $this->_action . ((count($this->_query)) ?  '?' . $this->create_querystring() : '');
	}
	
	private function get_query() {
		
		return $this->_query;
	} 
	
	private function create_querystring() {
		
		$qs = '';
		
		foreach ($this->get_query() as $key => $value) {
			
			$qs .= $key . '=' . $value . '&';
		}
		
		return rtrim($qs, '&');
	}
	
		
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
        		
        		/*echo '<pre>';
        		var_dump($options[CURLOPT_POSTFIELDS]);
        		exit;*/
        		
        
       
        
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
			
		} else {
			
			//There was an issue, send them to login page, with error
			$this->redirect_to_login('Authentication Failed. Please enter a valid username and password.');
		}
		
	}
	
	private function redirect_to_login($msg) {
		
		header('Location: ' . $this->url_append_qs('error=' . urlencode($msg), $this->_sso_profile->login_page));
		exit;
	}
	
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
	
	private function get_current_url() {
		
		return (! empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
	}

	
}