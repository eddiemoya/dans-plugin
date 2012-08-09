<?php

class SSO_Profile {
	
	/**
	 * Array containing mapping of environment to endpoint to use for CIS 
	 * Profile requests
	 *  
	 * @var array
	 */
	private $_endpoints = array('production'	=> 'https://accounts.ch4.intra.sears.com/universalservices/v3/',
								'integration'	=> 'http://toad.ecom.sears.com:8180/universalservices/v3/',
								'qa'			=> 'http://151.149.119.44:8180/universalservices/v3/');
	
	/**
	 * Selected environment for request. Plugin option used to set this - 
	 * defaults to production.
	 * 
	 * @var string
	 */
	private $_environment = 'production';
	
	/**
	 * SSO/Profile siteID parameter. Set by Plugin options.
	 * 
	 * @var int
	 */
	private $_sid = 41;
	
	/**
	 * HTTP request method to use. Set by set_method().
	 * Will be either GET or POST - defaults to GET.
	 * 
	 * @see set_method()
	 * @var string
	 */
	private $_method = 'GET';
	
	/**
	 * An array containing querystring parameters used in request.
	 * Set by set_query()
	 * 
	 * @see set_query()
	 * @var array
	 */
	private $_query = array();
	
	/**
	 * Contains any data to POST with request. This could be
	 * either an array of key/value POST data, XML string, or NULL (default).
	 * 
	 * @var mixed
	 */
	private $_post = null;
	
	/**
	 * An array mapping various methods of this class to URI values to use
	 * for different requests to CIS server. Used by set_action().
	 * 
	 * @see set_action()
	 * @var array
	 */
	private $_actions = array('update'			=> 'user/',
							  'get'				=> 'user/',
				 			  'search' 			=> 'user/search/',
							  'reset_password' 	=> 'user/reset/',
							  'authorize_reset' => 'user/auth/',
							  'change_password' => 'user/changepass/',
							  'create'			=> 'user/');
	
	/**
	 * The key provided by CIS to use in creation of the digital signature.
	 * Set by Plugin option.
	 * 
	 * @var string
	 */
	private $_key = 'YWCHJTDwxjHlZXro3NggLiWKu_I';
	
	/**
	 * The digital signature to use in the request to CIS.
	 * The digital signature is a hash of 'sid=<sid>ts=<timestamp>'. 
	 * It is only valid for 30 seconds from the time timestamp is created.
	 * 
	 * @var string
	 */
	
	public $digital_signature;
	
	/**
	 * Current timestamp. Set by set_timestamp()
	 * 
	 * @see set_timestamp()
	 * @var string date/time
	 */
	public $timestamp;
	
	/**
	 * The endpoint (CIS server) to send request to. This is set in Constructor, based on
	 * plugin's environment option.
	 * 
	 * @var string - CIS server URL
	 */
	private $_endpoint;
	
	/**
	 * The URI to append to $_endpoint for appropriate request.
	 * This is set by set_action() based on method where set_action()
	 * is called via $_actions array.
	 * 
	 * @see set_action()
	 * @see $_actions
	 * @var unknown_type
	 */
	private $_action;
	
	/**
	 * Contains URL of page to use in Reset Password e-mail that CIS will send to user
	 * for Forgot password process.
	 * 
	 * @see reset_password()
	 * @var string - URL to reset password page.
	 */
	private $_reset_pwd_page;
	
	/**
	 * Contains permalink to default login page.
	 *  @var string
	 */
	public $login_page;
	
	
	/**
	 * PHP4 constructor
	 * 
	 * Calls __construct()
	 * @param void
	 */
	public function SSO_Profile () {
		
		$this->__construct();
	}
	
	/**
	 * Constructor
	 * 
	 * @param void
	 */
	public function __construct() {
		
		//Set Options
		$this->set_options();
		
		//Set endpoint
		$this->_endpoint = isset($this->_endpoints[$this->_environment]) ? $this->_endpoints[$this->_environment] : $this->_endpoints['production'];
		
		//Set current timestamp
		$this->set_timestamp();
		
		//Create and set digital signature
		$this->create_digital_signature();
		
	}
	
	/**
	 * Gets and sets properties from Plugin options (environment, site id, key, etc.)
	 * 
	 * @param void
	 * @return void
	 * @see __construct()
	 */
 	private function set_options() {
		
		$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
		
		if(is_array($options)){
			
			$this->_environment = $options['environment'];
			$this->_sid = $options['profile_site_id'];
			$this->_key = $options['profile_key'];
			$this->login_page = get_permalink($options['sso_login_page_uri']);
			$this->_reset_pwd_page = get_permalink($options['profile_pwd_reset_page']);
		}
	}

	/**
	 * Retrieves SSO Profile User data given a SSO GUID. Returns an array of user attributes, or if invalid
	 * guid, will return with a single element (error) containing error message.
	 * @param int|string $sso_guid
	 * @return array (contains: id, status, name (first & last), screenname, dob, gender, email)
	 * @access public
	 */
	
	public function get($sso_guid) {
		
	$xml = $this->set_query('sid', $this->_sid)
			->set_query('ts', $this->timestamp)
			->set_query('sig', $this->digital_signature)
			->set_query('id', $sso_guid)
			->set_action(__METHOD__)
			->set_method('GET')
			->execute();
			
			/*echo 'sid='. $this->_sid  . '<br>';
			echo 'ts=' . $this->_timestamp . '<br>';
			echo 'sig=' . $this->_digital_signature . '<br>';
			echo 'action=' . $this->_action . '<br>';
			echo 'method=' .$this->_method .'<br>'; 
			echo 'url = ' . $this->create_url();*/
			
			try {
				
				//Convert XML string to SimpleXMLElement Object
				$user = $this->handle_response($xml);
				
				//Check for error from CIS server
				if(isset($user->code)) {
					
					return array('error' => $user->message);
				}
				
				
			} catch (Exception $e) {
				
				return array('error' => $e->message);
			}
			
			//Package user attributes into array and return it.
			$profile = array('id'		=> $user->id,
							'status'	=> $user->status,
							'name'		=> isset($user->name->first) ? $user->name->first . ' ' . $user->name->last : null,
							'sreenname'	=> isset($user->{'screen-names'}->{'screen-name'}->name) ? $user->{'screen-names'}->{'screen-name'}->name : null,
							'dob'		=> isset($user->birthdate) ? $user->birthdate : null,
							'zipcode'	=> $user->zipcode,
							'gender'	=> isset($user->gender) ? $user->gender : null,
							'email'		=> isset($user->emails->email) ? $user->emails->email : null);
			
			return $profile;
			
	}
	 
	/**
	 * Use to change SSO user's password
	 * 
	 * @param int $sso_guid - SSO User's GUID
	 * @param string $old_pwd - SSO User's current pasword
	 * @param string $new_pwd - SSO User's new password
	 * 
	 * @return array  - An array containing two elements (code: the repsonse code; message: description of response)
	 * @access public
	 */
	
	public function change_password($sso_guid, $old_pwd, $new_pwd) {
		
		$xml = $this->set_query('sid', $this->_sid)
					->set_query('ts', $this->timestamp)
					->set_query('sig', $this->digital_signature)
					->set_post(array('pd' => $old_pwd, 'npd' => $new_pwd, 'id' => $sso_guid))
					->set_action(__METHOD__)
					->set_method('POST')
					->execute();
					
		try {
				
				$user = $this->handle_response($xml);
				
			} catch (Exception $e) {
				
				return array('message' => 'Request issue.');
			}
			
				if(isset($user->code)) {
					
					//include error file
					require_once SHCSSO_CONFIG_DIR . 'errors.php';
					
					return array('code' => $user->code, 'message' => $sso_errors[$user->code]);
				}
				
					return array('code' => '200', 'messsage' => 'Your password has been successfully changed.');
	}
	
	/**
	 * Given a user's e-mail, will send request to CIS to initiate password reset. If the email is valid 
	 * CIS will send user the 'Reset Password' e-mail with a link to click to send them to the reset password
	 * page on your site (defined in plugin admin).
	 * 
	 * @param string $email - User's e-mail (SSO logon)
	 * @return bool - If CIS recognizes e-mail as a valid SSO logon, will return TRUE, otherwise FALSE
	 * @see $_reset_pwd_page - this is set to the reset password page. Set this value in plugin admin.
	 * @access public
	 */
	
	public function reset_password($email) {
		
		$xml = $this->set_query('sid', $this->_sid)
					->set_query('ts', $this->timestamp)
					->set_query('sig', $this->digital_signature)
					->set_query('logon', urlencode($email))
					->set_query('url', urlencode($this->_reset_pwd_page))
					->set_action(__METHOD__)
					->set_method('POST')
					->execute();
					
		if(empty($xml)) {
			
			return array('code' => '200', 'message' => 'An e-mail has been sent to the address you provided. Please follow the instructions in the e-mail to reset your password.');
			
		} else {
			
				try {
					
					$user = $this->handle_response($xml);
					
				} catch (Exception $e) {
					
					return array('code' => '500', 'message' => 'There was an issue processing your request. Please try again.');
				}
					
					require_once SHCSSO_CONFIG_DIR . 'errors.php';
					
					return array('code' => $user->code, 'message' => $sso_errors[$user->code]);
		}
					
	}
	
	/**
	 * Used to send new password along with auth token to CIS to reset password.
	 * Will be used on $_reset_pwd_page that Reset Password e-mail sends user to.
	 * 
	 * @param string $new_pwd - new password
	 * @param string $auth_token - auth token sent from link in CIS reset password e-mail
	 * 
	 * @return array - An array of return code & message (keys: code, message)
	 * @access public
	 */
	public function authorize_reset($new_pwd, $auth_token) {
		
		$xml = $this->set_query('sid', $this->_sid)
					->set_query('ts', $this->timestamp)
					->set_query('sig', $this->digital_signature)
					->set_post(array('pd' => $new_pwd, 'auth' => $auth_token))
					->set_action(__METHOD__)
					->set_method('POST')
					->execute();
					
			try {
				
				$user = $this->handle_response($xml);
				
			} catch (Exception $e) {
				
				return array('code' => '500', 'message' => 'There was an issue processing your request.');
			}
			
				//There was an issue processing (bad/expired auth token; password doesn't meet min. requirement)
				if(isset($user->code)) {
					
					//include error file
					require_once SHCSSO_CONFIG_DIR . 'errors.php';
					
					return array('code' => (string) $user->code, 'message' => $sso_errors[(string) $user->code]);
				}
				
					return array('code' => '200', 'message' => 'Your password has been successfully changed.');
	} 
	
	/**
	 * Probably won't be used.
	 * @param unknown_type $email
	 */
	public function search($email) {
		
		$xml = $this->set_query('sid', $this->_sid)
					->set_query('ts', $this->timestamp)
					->set_query('sig', $this->digital_signature)
					->set_query('email', $email)
					->set_action(__METHOD__)
					->set_method('GET')
					->execute();
					
			/*echo $xml;
			exit;*/
					
			try {
				
				$user = $this->handle_response($xml);
				
				return $user;
				
			} catch(Exception $e) {
				
				return array('code' => '', 'message' => $e->message);
			}
	}
	
	/**
	 * Use to update SSO User's profile data.
	 * 
	 * @param int $sso_guid - SSO GUID
	 * @param array $user - An array of profile data to update a user's profile.
	 * Keys: first_name, last_name, middle_name, screen_name, zipcode, birthdate, associate_number, e-mail
	 * 
	 * @uses to_xml()
	 * @return bool - true on success, false on failure.
	 * @access public
	 */
	public function update($sso_guid, $user) {
		
		$n = "\n\n";
		$postdata = '<?xml version=\'1.0\' encoding=\'UTF-8\'?'.'>'.$n.'<user></user>';
		
		 $xml = new SimpleXMLElement($postdata);
		 
		 $data = array(
            'id'        => (int) $sso_guid,
            'status'    => (isset($user['status'])) ? $user['status'] : 'active',
            'name'      => array(
                'first'     => (isset($user['first_name'])) ? $user['first_name'] : NULL,
                'last'      => (isset($user['last_name'])) ? $user['last_name'] : NULL,
                'middle'    => (isset($user['middle_name'])) ? $user['middle_name'] : NULL,
            ),
            'screen-names'  => array(
                array(
                    'screen-name'   => array(
					                        'name'      => (isset($user['screen_name'])) ? $user['screen_name'] : NULL,
					                        'site-id'   => 'global',
                   							 ),
                ),
            ),
            'zipcode'   => (isset($user['zipcode'])) ? $user['zipcode'] : NULL,
            'birthdate' => (isset($user['birthdate'])) ? $user['birthdate'] : NULL,
            'associate-number'   => (isset($user['associate-number'])) ? $user['associate-number'] : NULL,
            'emails'    => array(
                array(
                    'email' => (isset($user['email'])) ? $user['email'] : NULL,
                ),
            )
        );
        
        //Convert array to XML
        $this->to_xml($data, $xml);
		 
        //Set post as XML string
       // $this->set_post($xml->asXML());
        
        $ouput = $this->set_query('sid', $this->_sid)
					->set_query('ts', $this->timestamp)
					->set_query('sig', $this->digital_signature)
					->set_post($xml->asXML())
					->set_action(__METHOD__)
					->set_method('POST')
					->execute();
					
		try {
			
			$this->handle_response($output);
			
			return true;
			
		} catch (Exception $e) {
			
			return false;
			
		}
		
	}
	
	public function create($user) {
		
		$n = "\n\n";
		$postdata = '<?xml version=\'1.0\' encoding=\'UTF-8\'?'.'>'.$n.'<user></user>';
		
		 $xml = new SimpleXMLElement($postdata);
		 
		 $data = array(
            'id'        => '',
            'status'    => (isset($user['status'])) ? $user['status'] : 'active',
            'name'      => array(
                'first'     => (isset($user['first_name'])) ? $user['first_name'] : NULL,
                'last'      => (isset($user['last_name'])) ? $user['last_name'] : NULL,
                'middle'    => (isset($user['middle_name'])) ? $user['middle_name'] : NULL,
            ),
            'zipcode'   => (isset($user['zipcode'])) ? $user['zipcode'] : NULL,
            'birthdate' => (isset($user['birthdate'])) ? $user['birthdate'] : NULL,
            'associate-number'   => (isset($user['associate-number'])) ? $user['associate-number'] : NULL,
            'emails'    => array(
                array(
                    'email' => (isset($user['email'])) ? $user['email'] : NULL,
                ),
            ),
            'openid-info' => array('openid-info-detail'	=> array('provider-name' => isset($user['openid_provider']) ? $user['openid_provider'] : null,
            														'identifier'	=> isset($user['openid_id']) ? $user['openid_id'] : null
            														)
            						)
        );
        
	        //Convert array to XML
	        $this->to_xml($data, $xml);
        
	       //return $xml->asXML();
	       $output = $this->set_query('sid', $this->_sid)
						->set_query('ts', $this->timestamp)
						->set_query('sig', $this->digital_signature)
						->set_query('openid', 'YES')
						->set_post($xml->asXML())
						->set_action(__METHOD__)
						->set_method('PUT')
						->execute();
						
			$guid = $this->handle_response($output);
		
		
		return $guid;
	}
	
	/**
	 * Given an array of user data, adds data to SimpleXMLElement object
	 * 
	 * @param array $data - array of user profile data
	 * @param object $xml - SimpleXMLElement object.
	 * @see update()
	 * @return void
	 * @access private
	 */
	private function to_xml(array $data, $xml) {
		
		foreach ($data as $key => $value) {
			
	            if (is_array($value)) 
	            {
	                if ( ! is_numeric($key)) {
	                	
	                    $sub = $xml->addChild($key);
	                  	$this->to_xml($value, $sub);
	                   
	                } else {
	                	
	                    $this->to_xml($value, $xml);
	                }
	            }
	            else
	            {
	                $xml->addChild($key, $value);
	            }
	        }
	}
	
	
	/**
	 * Sets the $_action property based on the method from where it is called. Uses the $_actions 
	 * array to determine the value to use.
	 * 
	 * @param string $method - method where this method is called from
	 * @uses $_actions
	 * @see $_action
	 * @return void
	 * @access private
	 */
	private function set_action($method) {
		
		$method = ltrim($method, __CLASS__ . '::');
		$this->_action = $this->_actions[$method];
		
		return $this;
	}
	
	/**
	 * Sets $_post property which is the data to POST
	 * 
	 * @param mixed $data - could be an array, a XML string, or NULL (default)
	 * @return object - instance of object
	 * @access private
	 */
	private function set_post($data) {
		
		$this->_post = $data;
		
		return $this;
	}
	
	/**
	 * Sets $_query (array) property.
	 * 
	 * @param string $key
	 * @param string $value
	 * @return object - instance of object
	 * @see $_query
	 * @access private
	 */
	private function set_query($key, $value) {
		
		$this->_query[$key] = $value;
		
		return $this;
	}
	
	/**
	 * Returns the $_query property.
	 * 
	 * @param void
	 * @return array - the $_query property
	 * @see $_query
	 * @access private
	 */
	private function get_query() {
		
		return $this->_query;
	}
	
	/**
	 * Sets $_method property - POST or GET
	 * 
	 * @param string $verb
	 * @return object - instance of object
	 * @see $_method
	 * @access private
	 */
	private function set_method($verb) {
		
		$this->_method = $verb;
		
		return $this;
	}
	 
	/**
	 * Makes HTTP request to CIS endpoint using cURL.
	 * 
	 * @param void
	 * @returns string - XML response from CIS
	 * @throws Exception
	 * @access private
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
           // CURLOPT_FOLLOWLOCATION	=> 0
        );
        
        //If this is a POST set post data 
        if($this->_method == 'POST') {
        	
        	
        		//If POST data is NOT an array, assume it is XML and set Content-type to application/xml
        		if(! is_array($this->_post)) {
        			
        			$options[CURLOPT_POSTFIELDS] = $this->_post;
        			$options[CURLOPT_HTTPHEADER] = array('Content-Type: application/xml');
        			
        		} else {
        			
        			$options[CURLOPT_POSTFIELDS] = http_build_query($this->_post);
        			$options[CURLOPT_HTTPHEADER] = array('Content-type: application/x-www-form-urlencoded');
        		}
        		
        } elseif($this->_method == 'PUT') {//Use for create
        	
        	$options[CURLOPT_POSTFIELDS] = $this->_post;
        	$options[CURLOPT_HTTPHEADER] = array('Content-Type: application/xml');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
	        
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ( ! $response)
        {
            throw new Exception('Encountered an error trying to send request. cURL Error: ' . $code);
            
        } else {
        	
	       return $response;
	       
        }
		
	}
	
	/**
	 * Converts XML string to SimpleXMLElement object.
	 * 
	 * @param string $xml - XML string
	 * @return object - SimpleXMLElement object
	 * @access private
	 */
	private function handle_response($xml) {
		
		/*if($this->_method == 'PUT') {
			var_dump($xml);
			exit;
		}*/
		
		 $xml = new SimpleXmlElement($xml);
         $user = $xml->children();
         
		 /*echo '<pre>';
         var_dump($user);
         exit;*/
         
         //Check if an error exists
        /* if($error) {
         	
         	throw new Exception('There was an error');
         }*/
         //return data as array
         
         return $user;
		
	}
	
	/**
	 * Retrieves $_query property and converts to querystring string.
	 * 
	 * @param void
	 * @return string - Querystring string to use for request.
	 * @uses get_query()
	 * @see $_query
	 * @access private
	 */
	private function create_querystring() {
		
		$qs = '';
		
		foreach ($this->get_query() as $key => $value) {
			
			$qs .= $key . '=' . $value . '&';
		}
		
		return rtrim($qs, '&');
	}
	
	/**
	 * Constructs URL to use for request
	 * 
	 * @param void
	 * @return string - URL (CIS server)
	 * @uses $_endpoint
	 * @uses $_action
	 * @uses create_querystring()
	 * @access private
	 */
	private function create_url() {
		
		return $this->_endpoint . $this->_action . '?' . $this->create_querystring();
	}
	
	/**
	 * Creates digital signature and sets $digital_signature property.
	 * 
	 * @param void
	 * @uses ds_encrypt()
	 * @return void
	 * @access private
	 */
	private function create_digital_signature() {
		
		$ds = 'sid=' . $this->_sid . 'ts=' . $this->timestamp;
		$this->digital_signature = $this->ds_encrypt($ds);
	} 

	/**
	 * 
	 * Encrypts string used in digital signature.
	 * 
	 * @param string $data - string to encrypt
	 * @uses $_key
	 * @return string - Encrypted hash
	 * @access private
	 */
	private function ds_encrypt($data) {
		
		$key = $this->_key;
		
		$blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	
	    return urlencode(base64_encode($hmac)) . '%0D%0A';
	}
	
	/**
	 * Sets $timestamp property
	 * 
	 * @param void
	 * @return void
	 * @access private
	 */
	private function set_timestamp() {
		
		$this->timestamp = date("Y-m-d\TH:i:s\Z");
	}
	
	
}