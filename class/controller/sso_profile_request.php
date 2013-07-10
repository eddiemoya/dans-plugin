<?php

class SSO_Profile_Request extends SSO_Base_Request {
	
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
	 * An array mapping various methods of this class to URI values to use
	 * for different requests to CIS server. Used by _set_action().
	 * 
	 * @see set_action()
	 * @var array
	 */
	private $_actions = array('update'					=> 'user/',
							  'get'						=> 'user/',
				 			  'search' 					=> 'user/search/',
							  'reset_password' 			=> 'user/reset/',
							  'authorize_reset' 		=> 'user/auth/',
							  'change_password' 		=> 'user/changepass/',
							  'create'					=> 'user/',
							  'validate_screen_name'	=> 'user/screenName/validate/');
	
	/**
	 * The digital signature to use in the request to CIS.
	 * The digital signature is a hash of 'sid=<sid>ts=<timestamp>'. 
	 * It is only valid for 30 seconds from the time timestamp is created.
	 * 
	 * @var string
	 */
	
	public $digital_signature;
	
	/**
	 * SSO/Profile siteID parameter. Set by Plugin options.
	 * 
	 * @var int
	 */
	private $_sid = 41;
	
	/**
	 * The key provided by CIS to use in creation of the digital signature.
	 * Set by Plugin option.
	 * 
	 * @var string
	 */
	private $_key = 'YWCHJTDwxjHlZXro3NggLiWKu_I';
	
	/**
	 * Current timestamp. Set by set_timestamp()
	 * 
	 * @see set_timestamp()
	 * @var string date/time
	 */
	public $timestamp;
	
	/**
	 * Contains URL of page to use in Reset Password e-mail that CIS will send to user
	 * for Forgot password process.
	 * 
	 * @see reset_password()
	 * @var string - URL to reset password page.
	 */
	protected $_reset_pwd_page;
	
	/**
	 * Contains permalink to default login page.
	 *  @var string
	 */
	protected $_login_page;
	
	/**
	 * Contains permalink to default register page.
	 * @var unknown_type
	 */
	protected $_register_page;
	
	
	
	public function __construct() {
		
		parent::__construct();
		
		//Set option properties
		$options = SSO_Utils::options();
		
		$this->_reset_pwd_page = $options['profile_pwd_reset_page'];
		$this->_login_page = $options['sso_login_page_uri'];
		$this->_register_page = $options['sso_reg_page_uri'];
		$this->_sid = $options['profile_site_id'];
		$this->_key = $options['profile_key'];
		
		$this->_environment = $options['environment'];
		$this->_endpoint();
		$this->_set_timestamp();
		$this->_create_digital_signature();
		
	}
	
	public static function factory() {
		
		return new SSO_Profile_Request();
	}	
	/**
	 * Retrieves SSO Profile User data given a SSO GUID. Returns an array of user attributes, or if invalid
	 * guid, will return with a single element (error) containing error message.
	 * @param int|string $sso_guid
	 * @return array (contains: id, status, name (first & last), screenname, dob, gender, email)
	 * @access public
	 */
	
	public function get($sso_guid) {
		
		try {
			
			$user = $this->_query('sid', $this->_sid)
						->_query('ts', $this->timestamp)
						->_query('sig', $this->digital_signature)
						->_query('id', $sso_guid)
						->_set_action(__METHOD__)
						->_url(false)
						->_method('GET')
						->_execute(true, 'xml', false);
						
			
			//Check for error from CIS server
			
			if(isset($user->code)) {
				
				return array('error' => $user->message);
			}
					
				
			} catch (Exception $e) {
				
				
				return array('error' => 'An issue occured trying to retrieve data from CIS.');
			}
			
			//Package user attributes into array and return it.
			$profile = array('id'		=> (string) $user->id,
							'status'	=> (string) $user->status,
							'name'		=> isset($user->name->first) ? (string) $user->name->first . ' ' . (string) $user->name->last : null,
							'screenname'	=> isset($user->{'screen-names'}->{'screen-name'}->name) ? (string) $user->{'screen-names'}->{'screen-name'}->name : null,
							'dob'		=> isset($user->birthdate) ? (string) $user->birthdate : null,
							'zipcode'	=> (string) $user->zipcode,
							'gender'	=> isset($user->gender) ? (string) $user->gender : null,
							'email'		=> isset($user->emails->email) ? (string) $user->emails->email : null);
			
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
		
		$user = $this->_query('sid', $this->_sid)
					->_query('ts', $this->timestamp)
					->_query('sig', $this->digital_signature)
					->_post(array('pd' => $old_pwd, 'npd' => $new_pwd, 'id' => $sso_guid))
					->_set_action(__METHOD__)
					->_url(false)
					->_method('POST')
					->_execute(true, 'xml', false);
					
		
		if(isset($user->code)) {
			
			return array('code' => $user->code, 'message' => SSO_Utils::config('errors', $user->code));
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
		
		$xml = $this->_query('sid', $this->_sid)
					->_query('ts', $this->timestamp)
					->_query('sig', $this->digital_signature)
					->_query('logon', urlencode($email))
					->_query('url', urlencode($this->_reset_pwd_page))
					->_set_action(__METHOD__)
					->_url(false)
					->_method('POST')
					->_execute();
					
		if(empty($xml)) {
			
			return array('code' => '200', 'message' => 'An e-mail has been sent to the address you provided. Please follow the instructions in the e-mail to reset your password.');
			
		} else {
			
				try {
					
					$user = $this->_xml_to_object($xml, false);
					
				} catch (Exception $e) {
					
					return array('code' => '500', 'message' => 'There was an issue processing your request. Please try again.');
				}
					
					return array('code' => $user->code, 'message' => SSO_Utils::config('errors', $user->code));
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
		
		try {
			
				$user = $this->_query('sid', $this->_sid)
							->_query('ts', $this->timestamp)
							->_query('sig', $this->digital_signature)
							->_post(array('pd' => $new_pwd, 'auth' => $auth_token))
							->_action(__METHOD__)
							->_url(false)
							->_method('POST')
							->_execute(true, 'xml', false);
					
			} catch (Exception $e) {
				
				return array('code' => '500', 'message' => 'There was an issue processing your request.');
			}
			
				//There was an issue processing (bad/expired auth token; password doesn't meet min. requirement)
				if(isset($user->code)) {
					
					$code = (string) $user->code;
					
					return array('code' => $code, 'message' => SSO_Utils::config('errors', $code));
				}
				
					return array('code' => '200', 'message' => 'Your password has been successfully changed.');
	} 
	
	/**
	 * Given an e-mail searches for user's profile data.
	 * 
	 * @param string $email
	 * @return array - An array containing user profile data, or containing an 'error' element
	 */
	public function search($email) {
		
		try {
			
			$user = $this->_query('sid', $this->_sid)
						->_query('ts', $this->timestamp)
						->_query('sig', $this->digital_signature)
						->_query('email', $email)
						->_set_action(__METHOD__)
						->_url(false)
						->_method('GET')
						->_execute(true, 'xml', false);
						
				
				if(isset($user->code)) {
					
					return array('code' => $user->code, 'error' => $user->message);
				}
				
			} catch(Exception $e) {
				
				return array('code' => '', 'message' => 'There was an issue trying to retrieve data from CIS.');
			}
			
			//Package user attributes into array and return it.
			$profile = array('id'			=> $user->id,
							'status'		=> $user->status,
							'name'			=> isset($user->name->first) ? $user->name->first . ' ' . $user->name->last : null,
							'screenname'	=> isset($user->{'screen-names'}->{'screen-name'}->name) ? $user->{'screen-names'}->{'screen-name'}->name : null,
							'dob'			=> isset($user->birthdate) ? $user->birthdate : null,
							'zipcode'		=> $user->zipcode,
							'gender'		=> isset($user->gender) ? $user->gender : null,
							'email'			=> isset($user->emails->email) ? $user->emails->email : null);
			
			return $profile;
	}
	
	/**
	 * Accepts a screen name and validates it.
	 * 
	 * @param string $screen_name
	 * @return array - contains a code and message
	 */
	public function validate_screen_name($screen_name) {
		
		$validate = $this->_query('sid', $this->_sid)
						->_query('ts', $this->timestamp)
						->_query('sig', $this->digital_signature)
						->_query('screenName', $screen_name)
						->_set_action(__METHOD__)
						->_url(false)
						->_method('GET')
						->_execute(true, 'xml', false);
					
		
			if($validate) {
					
				return array('code' => $validate->code, 'message' => SSO_Utils::config('errors', $validate->code));
				
			} else {
				
				return array('code' => '200', 'message' => 'Screen name is valid and available.');
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
        $this->_to_xml($data, $xml);
		 
        
        $output = $this->_query('sid', $this->_sid)
						->_query('ts', $this->timestamp)
						->_query('sig', $this->digital_signature)
						->_post($xml->asXML())
						->_set_action(__METHOD__)
						->_url(false)
						->_method('POST')
						->_execute(true, 'xml', false);
		
		if(isset($output->code)) {
	
			$code = (string) $output->code;
			
			return array('code' => $code, 'message' => SSO_Utils::config('errors', $code));
			
		} else {
			
			return array('guid' => $output->id);
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
       $this->_to_xml($data, $xml);
       
       $output = $this->_query('sid', $this->_sid)
						->_query('ts', $this->timestamp)
						->_query('sig', $this->digital_signature)
						->_query('openid', 'YES')
						->_post($xml->asXML())
						->_set_action(__METHOD__)
						->_url(false)
						->_method('PUT')
						->_execute(true, 'xml', false);
						
						
		return $output;
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
	private function _set_action($method) {
		
 		$method = str_replace(__CLASS__ . '::', '', $method);
		$this->_action = $this->_actions[$method];

		return $this;
	}
	
	/**
	 * Sets _endpoint property (in parent).
	 * 
	 * @param void
	 * @return void
	 */
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
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
	private function _ds_encrypt($data) {
		
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
	
	private function _create_digital_signature() {
		
		$ds = 'sid=' . $this->_sid . 'ts=' . $this->timestamp;
		$this->digital_signature = $this->_ds_encrypt($ds);
	}

	private function _set_timestamp() {
		
		$this->timestamp = date("Y-m-d\TH:i:s\Z");
	}
	
}