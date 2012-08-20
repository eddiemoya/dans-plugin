<?php

class SSO {
	
	/**
	 * An array of environment settings to endpoints (SSO CAS Servers)
	 * @var array
	 */
	private $_endpoints = array('production'	=> 'https://sso.shld.net/shccas/',
								'integration'	=> 'http://toad.ecom.sears.com:8080/shccas/',
								'qa'			=> 'https://phoenix.ecom.sears.com:1443/shccas/');
	
	/**
	 * Selected environment for request. Plugin option used to set thielseifs - 
	 * defaults to production.
	 * 
	 * @var string
	 */
	private $_environment = 'production';
	
	/**
	 * The WP role to use when creating new WP user from SSO User.
	 * Set from plugin option. Defaults to subscriber.
	 * 
	 * @var string
	 */
	private $_default_role = 'subscriber';
	
	/**
	 * SSO siteID parameter. Set by Plugin options.
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
	 * The URL to the login page on the site. Set via plugin option.
	 * 
	 * @var string
	 */
	private $_login_page = '';
	
	/**
	 * An array containing querystring parameters used in request.
	 * Set by set_query()
	 * 
	 * @see set_query()
	 * @var array
	 */
	private $_query = array();
	
	/**
	 * An array mapping various methods of this class to URI values to use
	 * for different requests to CAS server. Used by set_action().
	 * 
	 * @see set_action()
	 * @var array
	 */
	private $_actions = array('login' 			=> 'shcLogin',
							  'login_check'		=> 'shcLogin',
							  'register' 		=> 'shcRegistration',
							  'logout'			=> 'logout',
							  'validate'		=>  'serviceValidate',
							  'login_openid'	=>	'shcOpenIdLogin');
	
	/**
	 * The endpoint (CAS server) to send request to. This is set in Constructor, based on
	 * plugin's environment option.
	 * 
	 * @var string - CAS server URL
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
	 * Site's url that CAS will send reponses to - usually the page where
	 * a request is sent from.
	 * 
	 * @var string - callback URL
	 */
	private $_callback;
	 
	/** 
	 * Holds instance of OpenID_RPX object
	 * @var object
	 */
	private $_openid_rpx = null;
	
	/**
	 * Holds an instance of SSO_Profile object
	 * @var object
	 */
	private $_sso_profile = null;
	
	
	
	/**
	 * PHP4 Constructor
	 * 
	 * @param void
	 * @return void
	 * @uses __construct()
	 * @access public
	 */
	public function SSO() {
		
		$this->__construct();
	}
	
	/**
	 * Constructor
	 * @param void
	 * @return void
	 */
	public function __construct() {
		
			//Retrieve and set options
			$this->set_options();
			
			//Set endpoint based on environment (qa, integration, production)
			$this->_endpoint = $this->_endpoints[$this->_environment];
			
			//Check if user is already logged into CAS on other sites --- only check if NOT logged into wordpress and cookie NOT set
			if(! isset($_COOKIE['sso_checked']) && ! isset($_GET['ssologincheck']) && ! is_user_logged_in() && ! is_admin()) {
				 
				$this->verify_sso_login_state();
			}
			
			//Instantiate OpenID_RPX object
			$this->_openid_rpx = new OpenID_RPX;
			
			
			//Insert login & reg form json into head, if user is not logged in.
			if(! is_user_logged_in()) {
				
				add_action('wp_head', array($this, 'add_sso_forms'));
			} 
		
			
			//Determine request made, and process accordingly
			$this->process_request();
				
	}
	
	public static function init() {
		
		return new SSO;
	}
	
	/**
	 * Sets various properties based on plugin options
	 * @param void
	 * @return void
	 * @access private
	 */
	private function set_options() {
		
		$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
		
		if(is_array($options)){
			
			$this->_environment = $options['environment'];
			$this->_sid = $options['sso_site_id'];
			$this->_default_role = $options['sso_role'];
			$this->_login_page = get_permalink($options['sso_login_page_uri']);
		}
	}
	
	/**
	 * Display json of environment vars for SSO.
	 * 
	 * @param void
	 * @access public
	 * @return string - JSON string
	 */
	public function add_sso_forms() {
		
		$origin = $this->get_current_url();
		$sso_vars = '<script type="text/javascript">var OID = '. json_encode(array('token_url' => $this->_openid_rpx->token_url)) .';</script>';
		/*$sso_forms = '<script type="text/javascript">var sso = [' .json_encode(array('sso_loginform' => '<form method="post" action="?ssologin&origin=' . urlencode($origin) . '"><label for="loginId">Username</label>
						<input type="text" name="loginId" /><label for="logonPassword">Password</label>
						<input type="password" name="logonPassword" /><input type="submit" name="submit" value="Login" class="btn" /></form>',
									'sso_regform' => '<form action="?ssoregister&origin=' . urlencode($origin) . '"><label for="loginId">Username</label><input type="text" name="loginId" /><label for="logonPassword">Password</label><input type="password" name="logonPassword" /><label for="zipcode">Zip Code</label>
						<input type="text" name="zipcode" /><input type="submit" name="submit" value="Login" class="btn" />')) . '];
					
						(function() {
    if (typeof window.janrain !== "object") window.janrain = {};
    if (typeof window.janrain.settings !== "object") window.janrain.settings = {};
    
   

    janrain.settings.tokenUrl = "'. $this->_openid_rpx->token_url .'";
    janrain.settings.type = "embed";
    janrain.settings.appId = "lmnligghmdckfgnhpaih";
    janrain.settings.appUrl = "https://sears-qa.rpxnow.com";
    janrain.settings.providers = ["facebook","google","yahoo","twitter"];
    janrain.settings.providersPerPage = "4";
    janrain.settings.format = "one row";
    janrain.settings.actionText = " ";
    janrain.settings.showAttribution = true;
    janrain.settings.fontColor = "#333333";
    janrain.settings.fontFamily = "helvetica, sans-serif";
    janrain.settings.backgroundColor = "#FFFFFF";
    janrain.settings.width = "217";
    janrain.settings.borderColor = "#FFFFFF";
    janrain.settings.borderRadius = "0";    janrain.settings.buttonBorderColor = "#CCCCCC";
    janrain.settings.buttonBorderRadius = "10";
    janrain.settings.buttonBackgroundStyle = "gradient";
    janrain.settings.language = "en";
    janrain.settings.linkClass = "janrainEngage";

 

    function isReady() { janrain.ready = true; };
    if (document.addEventListener) {
      document.addEventListener("DOMContentLoaded", isReady, false);
    } else {
      window.attachEvent("onload", isReady);
    }

    var e = document.createElement("script");
    e.type = "text/javascript";
    e.id = "janrainAuthWidget";

    if (document.location.protocol === "https:") {
      e.src = "https://rpxnow.com/js/lib/sears-qa/engage.js";
    } else {
      e.src = "http://widget-cdn.rpxnow.com/js/lib/sears-qa/engage.js";
    }

    var s = document.getElementsByTagName("script")[0];
    s.parentNode.insertBefore(e, s);
})();
						</script>';*/
			
		echo $sso_vars;
	}
	
	/**
	 * Sends request to CAS to see if user has auth session from other Sears SSO sites
	 * 
	 * @param void
	 * @return void
	 * @uses login_check()
	 * @access private
	 */
	private function verify_sso_login_state() {
			
		$origin = $this->get_current_url();
		
		$this->_callback = $this->url_append_qs('ssologincheck&origin=' . urlencode($origin), $origin);
		
		$this->login_check()
			 ->redirect();
	}
	
	/**
	 * Processes SSO requests based on GET parameters passed
	 * 
	 * @param void
	 * @return void
	 * @access private
	 */
	private function process_request() {
		
		//Check if there was an error, if so redirect to default login page
		if($error = $this->get_login_error()) {
	
			$this->error_redirect($error);
		}

		
		
		//Login form post 
		if(! isset($_POST['ticket']) && isset($_GET['ssologin'])) {
			
			
			
			$this->_callback = $this->get_current_url();
			
			$this->login($_POST['loginId'], $_POST['logonPassword'])
				->redirect();	
		} 

		//Login Response ticket
		if(isset($_POST['ticket']) && ! isset($_GET['ssologincheck'])) { //Login response with ticket, validate ticket ..
			
			try {
				
				/*echo 'Got ticket';
				exit;*/
				$xml = $this->validate($_POST['ticket'])
							->execute();
							
				/*echo $xml;
				exit;*/
							
			} catch(Exception $e) {
				
				echo '<pre>';
				var_dump($e);
			}
			
					//User Login
					try {
						
						$this->user_login($xml);
						
					} catch(Exception $e) {
						
						echo '<pre>';
						var_dump($e);
						exit;
						
						$this->error_redirect('New User Creation failed.');
					}
						
				}
				
				
				//Register
				if(! isset($_POST['ticket']) && isset($_GET['ssoregister'])) {
					
					$this->_callback = $this->get_current_url();
					
					$this->register($_POST['loginId'], $_POST['logonPassword'], $_POST['zipcode'])
						 ->redirect();
				}
				
				
				//Logout
				if(isset($_GET['ssologout']) && ! isset($_GET['ssologoutreceive'])) {
					
					$this->_callback =  urldecode($_GET['origin']) . '?origin=' . urlencode($_GET['origin']) . '&ssologoutreceive';
					
					$this->logout()	
						 ->redirect();
				}
				
				//Receive logout
				if(isset($_GET['ssologoutreceive'])) {
					
					$this->receive_logout();
				}
				
				//Verify if already SSO authenticated (from other site)
				if(isset($_GET['ssologincheck'])) {
					
					if(isset($_POST['ticket'])) {
						
						try {
					
							$xml = $this->validate($_POST['ticket'])
										->execute();
										
						} catch(Exception $e) {
							
							/*echo '<pre>';
							var_dump($e);*/
							//$this->error_redirect($e->message);
						}
					
							//User Login
							try {
								
								setcookie('sso_checked', 'yes', 0);
								
								$this->user_login($xml);
								
							} catch(Exception $e) {
								
								echo '<pre>';
								var_dump($e);
								//$this->error_redirect($e->message);
							}
						
					} else {
						
						setcookie('sso_checked', 'yes', 0);
						header('Location: '. urldecode($_GET['origin']));
					}
					
				} 
				
				//OpenID Auth request
				if(isset($_GET['openid_auth']) && isset($_POST['token']) && ! isset($_POST['ticket'])) {

					/*echo $_POST['token'];
					exit;*/
					
					if($user = $this->_openid_rpx->auth_info($_POST['token'])) {
						
						/*echo '<pre>';
						var_dump($user);
						exit;*/
						
						//Do openid CAS login
						$this->_callback = $this->get_current_url();
						
						$this->login_openid(urlencode($user['email']))
							 ->openid_execute();
							
					} else {
						
						$this->error_redirect('There was an issue with the Authentication provider, please try again.SSO');
					}
					
				}
				
		
	}
	
	/**
	 *  Returns $_callback property - Callback URL sent to SSO server
	 *  
	 *  @param void
	 *  @return void
	 */
	public function get_callback() {
		
		return $this->_callback;
	}
	
	/**
	 * Sets properties for SSO auth check
	 * 
	 * @param void
	 * @return object - instance of object
	 * @access private
	 */
	private function login_check() {
		
		//Set query params and action
		$this->set_query('gateway', 'true')
			->set_query('service', $this->get_callback())
			->set_query('sourceSiteid', $this->_sid)
			->set_action(__METHOD__);
			
			return $this;
	}

	/**
	 * Sets properties for SSO login request
	 * 
	 * @param string $username - SSO user logon (e-mail)
	 * @param string $password - SSO user password
	 * @return object - instance of object
	 * @access private
	 */
	private function login($username, $password) {
		
		if((! $username || empty($username)) || (! $password) || empty($password)) {
			 
			$this->error_redirect('Please enter a username and password.');
		}
		
		//Set query params and action
		$this->set_query('loginId', $username)
			->set_query('logonPassword', $password)
			->set_query('service', $this->get_callback())
			->set_query('renew', 'true')
			->set_query('sourceSiteid', $this->_sid)
			->set_action(__METHOD__);
			
			return $this;
	}
	
	private function login_openid($username) {
		
		$this->_sso_profile = new SSO_Profile;
		
		
		$this->set_query('loginId', $username)
			 ->set_query('ts', $this->_sso_profile->timestamp)
	 		 ->set_query('sourceSiteId', $this->_sid)
	 		 ->set_query('renew', 'false')
	 		 ->set_query('gateway', 'true')
	 		 ->set_query('service', urlencode($this->get_callback()))
	 		 ->set_query('sig', urlencode($this->_sso_profile->digital_signature))
	 		 ->set_method('POST')
	 		 ->set_action(__METHOD__);
	 		 
	 		 
	 		/* echo $this->create_openid_url();
	 		 exit;*/
	 		 
	 		return $this;
		
	}
	
	/**
	 * Redirects a request to SSO(CAS) server.
	 * 
	 * @param void
	 * @return void
	 * @access private
	 */
	private function redirect() {
		
		$url = $this->create_url();
		
		/*echo $url;
		exit;*/
		
		
		header('Refferer: ' . $this->get_current_url());
		header('Location: ' . $url);
		
		exit;
	}
	
	/**
	 * Returns CAS server URL with params for a request.
	 * 
	 * @param void
	 * @return string - CAS URL with params
	 */
	public function create_url() {
		
		return $this->_endpoint . $this->_action . '?' . http_build_query($this->get_query());
	}
	
	public function create_openid_url() {
		
		return $this->_endpoint . $this->_action . '?' . $this->create_querystring();
	}
	
	/**
	 * Sets element in $_query property
	 * 
	 * @param string $key
	 * @param string $value
	 * @return object - instance of object
	 * @access private
	 */
	private function set_query($key, $value) {
		
		$this->_query[$key] = $value;
		
		return $this;
	}
	
	/**
	 * Returns $_query property
	 * 
	 * @param void
	 * @return array - $_query
	 */
	private function get_query() {
		
		return $this->_query;
	}
	
	/**
	 * Prepares SSO logout request
	 * 
	 * @param void
	 * @return object - instance of object
	 * @access private
	 */
	private function logout() {
		
		$this->set_query('service', $this->get_callback())
			 ->set_action(__METHOD__);
			 
		 return $this;
		
	}
	
	/**
	 * Handles logout response from CAS - redirects user to page
	 * where logout was initiated.
	 * 
	 * @param void
	 * @return void
	 * @access private
	 */
	function receive_logout() {
		
		wp_logout();
		
		$refferer = $this->get_current_url();

        header('Refferer: ' . $refferer);
        header('Location: ' . urldecode($_GET['origin'])); 

        exit;
	}
	
	/**
	 * Prepares SSO register request.
	 * 
	 * @param string $username
	 * @param string $password
	 * @param string $zipcode
	 * @return object - instance of object
	 * @access private
	 */
	function register($username, $password, $zipcode) {
		/*echo 'Username: ' . $username . '<br>';
		echo 'Password: ' . $password . '<br>';
		echo 'Zipcode: '  . $zipcode . '<br>';
		exit;*/
		if(empty($username) || empty($password) || empty($zipcode)) {
			
			$this->error_redirect('Please enter a username, password and zipcode.');
		}
		
			$this->set_query('sourceSiteid', $this->_sid)
				 ->set_query('service', $this->get_callback())
				 ->set_query('loginId', $username)
				 ->set_query('logonPassword', $password)
				 ->set_query('zipcode', $zipcode)
				 ->set_action(__METHOD__);
				 
				 /*echo '<pre>';
				 var_dump($this->_query);
				 exit;*/
				 
			 return $this;
	}
	
	/**
	 * Prepares SSO ticket validation request.
	 * 
	 * @param string $ticket - token received from CAS on all requests.
	 * @return object - instance of object
	 * @access private
	 */
	private function validate($ticket = null) {
		
		$this->set_query('ticket', $ticket)
			->set_query('service', $this->get_current_url())
			->set_action(__METHOD__);
			
		return $this;
		
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
	}
	
	/**
	 * Retrieves login error and returns error message based on code.
	 * 
	 * @param void
	 * @return bool|string - retruns false if no error, otherwise returns
	 * error
	 * @access private
	 */
	private function get_login_error() {
		
		include SHCSSO_CONFIG_DIR . 'errors.php';
	
		if(isset($_POST['errorCode']) && ! empty($_POST['errorCode'])) {
			
			/*echo 'Got an error: '. $_POST['errorCode'];
			exit;*/
			
			if($this->_login_page == '') die('No Login page configured.');
			
			return $sso_errors[$_POST['errorCode']];
		}
		
			return false;
		
	}

	/**
	 * Given a SSO GUID queries wp usermeta and returns wp user's ID
	 * 
	 * @param strng $guid - SSO GUID
	 * @return int  - WP user ID
	 * @uses $wpdb
	 * @access private
	 */
	private function get_user_by_guid($sso_guid) {
		
		global $wpdb;
		$usermeta = $wpdb->prefix . 'usermeta';
		
		$user_query = "SELECT user_id FROM " . $usermeta ." WHERE meta_key = 'sso_guid' AND meta_value = " . $sso_guid;
	 	$user_id = $wpdb->get_var($user_query);
	 	
	 		return $user_id;
		
	}
	 
	/**
	 * Handles SSO response (XML), converts XML to SimpleXMLElement object. If a WP user exists, logs them in. If WP
	 * user does not exist, creates new WP user and logs them in. Returns user to page where SSO login was initiated.
	 * 
	 * @param string $xml - XML response from CAS
	 * @return void
	 * @throws Exception
	 * @access private
	 */
	private function user_login($xml) {
		
		if($xml) {
								
			$xml = "<?xml version='1.0'?>\n" . trim($xml);
            $xml = preg_replace('~\s*(<([^>]*)>[^<\s]*</\2>|<[^>]*>)\s*~', '$1', $xml);
            $xml = preg_replace_callback('~<([A-Za-z\s]*)>~', create_function('$matches', 'return "<".strtolower($matches[1]).">";'), $xml);
            $xml = preg_replace_callback('~</([A-Za-z\s]*)>~', create_function('$matches', 'return "</".strtolower($matches[1]).">";'), $xml);
            $xml = new \SimpleXmlElement($xml);
            
            $user = $xml->children('http://www.yale.edu/tp/cas');
            
           /*echo '<pre>';
            var_dump($user);
            exit;*/
            
            
            $email = $user->authenticationSuccess->user;
            $sso_guid = $user->authenticationSuccess->attributes->guid;
          	$username = $email;//preg_replace('/@.*?$/', '', $email);
          	
          	/*echo 'E-mail: ' . $email . '<br>';
          	echo 'GUID: ' . $sso_guid . '<br>';
          	echo 'Username: ' . $username;
          	exit;*/
          	
          	//If user does NOT exist, create account
		 	if(! $user_id = $this->get_user_by_guid($sso_guid)) {
		 		
		 		var_dump($user_id);
		 		exit;
		 		
		 		//Create wp user
		 		$user_id = wp_insert_user(array(
	 				'user_pass'		=> $this->random(),
		 			'user_email'	=> $email,
		 			'user_login'	=> $username,
		 			'user_role'		=> $this->_default_role));
		 		
		 		/*echo '<pre>';
		 		var_dump($user_id);
		 		exit;*/
		 		
						//Check for errors	 
		 				if(is_wp_error($user_id)) {
		 					
		 					$this->error_redirect('The username you requested already exists on this site. Please try another.');
		 				}
		 				
	 				//Insert sso_guid user meta
	 				if( ! update_user_meta($user_id, 'sso_guid', (string) $sso_guid)) {
	 					
	 					throw new Exception('Failed to add user meta for ' . $username);
	 				}
		 	} 
		 	
		 		
		 		
		 		//Check if user has a screen name set, if not check CIS and set user meta if found
		 		if(! get_user_meta($user_id, 'profile_screen_name', true)) {
		 			
		 			//Check for CIS screen name, if there is one set user_nicename and user meta
			 		if($screen_name = $this->get_screen_name($sso_guid)) {
			 			
			 			update_user_meta($user_id, 'profile_screen_name', $screen_name);
			 			
			 			//Set user_nicename to profile screen name
			 			wp_insert_user(array('ID'				=> $user_id,
			 								 'user_nicename' 	=> $screen_name));
			 		}
		 		}
		 		
		 		
		 		//Login
		 		wp_set_current_user($user_id, $username);
		 		wp_set_auth_cookie($user_id);
		 		do_action('wp_login', $username);
		 	
          		//Redirect
		 		header('Refferer: ' . $this->get_current_url());
		 		header('Location: ' . urldecode($_GET['origin']));
		 		
		 		die; 	 
		 		
		} else {
			
			throw new Exception('Invalid response for SSO validate request.');
		}
		
	}
	
	/**
	 * Given an SSO GUID will return profile screen name if
	 * user has one set, else returns false.
	 * 
	 * @param int $sso_guid
	 * @return mixed - returns screen name or false
	 * @access private
	 */
	private function get_screen_name($sso_guid) {
		
		$profile = new SSO_Profile;
		
		//Returns array of user profile data
		$user_data = $profile->get($sso_guid);
		
		 if(isset($user_data['screenname']) && ! empty($user_data['screenname'])) {
		 	
		 	return (string) $user_data['screenname'];
		 }
		
		 	return false;
	}
	
	/**
	 * Creates a random string used to create password for new WP user.
	 * 
	 * @param string $type - the type of string you want to create (see switch)
	 * @param int $length - length ofng to random string to return
	 * @return string - random string
	 * @access private
	 */
	private function random($type = NULL, $length = 8) {
		
        if ($type === NULL)
        {
            // Default is to generate an alphanumeric string
            $type = 'alnum';
        }

        switch ($type)
        {
            case 'alnum':
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
            case 'alpha':
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
            case 'hexdec':
                $pool = '0123456789abcdef';
            break;
            case 'numeric':
                $pool = '0123456789';
            break;
            case 'nozero':
                $pool = '123456789';
            break;
            case 'distinct':
                $pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
            break;
        }

        // Split the pool into an array of characters
        $pool = str_split($pool, 1);

        // Largest pool key
        $max = count($pool) - 1;

        $str = '';
        for ($i = 0; $i < $length; $i++)
        {
            // Select a random character from the pool and add it to the string
            $str .= $pool[mt_rand(0, $max)];
        }

        // Make sure alnum strings contain at least one letter and one digit
        if ($type === 'alnum' AND $length > 1)
        {
            if (ctype_alpha($str))
            {
                // Add a random digit
                $str[mt_rand(0, $length - 1)] = chr(mt_rand(48, 57));
            }
            elseif (ctype_digit($str))
            {
                // Add a random letter
                $str[mt_rand(0, $length - 1)] = chr(mt_rand(65, 90));
            }
        }

        return $str;
    }
    
    /**
     * Redirects user to login page when a login error occurs. Includes the 'error' qs with error message.
     * 
     * @param string $msg - the message you want to use on login page
     * @return void
     * @access private
     */
    private function error_redirect($msg) {
    	
    	header('Location: ' . $this->url_append_qs('error=' . urlencode($msg), $this->_login_page));
		exit;
    }
    
    private function set_method($verb) {
    	
    	$this->_method = $verb;
    	
    	return $this;
    }
	
    /**
     * Makes HTTP request to CAS via cURL
     * 
     * @throws Exception
     * @param void
     * @return void
     * @access private
     */
	private function execute() {
		
		$url = $this->create_url();
		
		/*echo $url;
		exit;*/
		
		$options = array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
        );
	        
	        if($this->_method == 'POST') {
	        	
	        	$options[CURLOPT_HTTPHEADER] = array('Content-type: application/x-www-form-urlencoded',
	        											'Content-length: 0');
	        	$options[CURLOPT_CUSTOMREQUEST] = 'POST';
	        }
	        
        $ch = curl_init();

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        
       
        
        /*echo '<pre>';
        var_dump($response);
        exit;*/

        // Get the response information
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
      /*echo $code;
        exit;*/

        if ( ! $response) {
            $error = curl_error($ch);
        }

        curl_close($ch);
        
         if (isset($error)) {
         	
            throw new Exception('Error fetching remote '.$url.' [ status '.$code.' ] '.$error);
        }

        
        
        return $response;
	}

	private function openid_execute() {
		
		$url = $this->create_openid_url();
		
		/*echo $url;
		exit;*/
		
		$options = array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_CUSTOMREQUEST 	=> 'POST',
            CURLOPT_HTTPHEADER 		=> array('Content-type: application/x-www-form-urlencoded',
	        											'Content-length: 0')
	        
        );
	        
	        
	        
        $ch = curl_init();

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        
        /*echo '<pre>';
        var_dump($response);
        exit;*/

        // Get the response information
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     

	        if ( ! $response) {
	            $error = curl_error($ch);
	        }
	
	        curl_close($ch);
	        	
	         if (isset($error)) {
	         	
	            throw new Exception('Error fetching remote '.$url.' [ status '.$code.' ] '.$error);
	        }
        
        
	        //IMPORTANT: Must echo response!!!
	       echo $response;
	       exit;
        
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
}
