<?php

class SSO_User {
	
	/**
	 * The PKID 
	 * @var int
	 */
	public $id;
	
	/**
	 * The WP user ID.
	 * @var int
	 */
	public $user_id;

	/**
	 * The SSO GUID.
	 * @var int
	 */
	public $guid;
	
	/**
	 * User's e-mail/ SSO username
	 * @var string
	 */
	public $email;
	/**
	 * CIS Profile screen name.
	 * @var string
	 */
	public $screen_name;
	
	/**
	 *  User's city
	 * @var string
	 */
	public $city;
	
	/**
	 * User's state
	 * @var string
	 */
	public $state;
	
	/**
	 * User's zipcode
	 * @var string
	 */
	public $zipcode;
	
	 /**
	  * Indicates if this a new user record.
	  * @var bool
	  */
	protected $is_new = false;
	
	/**
	 * Indicates if an update via set() has been made to object.
	 * @var bool
	 */
	protected $updated = false;
	
	/**
	 * The WP role to use when creating new WP user from SSO User.
	 * Set from plugin option. Defaults to subscriber.
	 * 
	 * @var string
	 */
	protected $_default_role = 'subscriber';
	
	/**
	 * Holds data from CAS get() call.
	 * @var array
	 */
	protected $_profile_data;
	
	/**
	 * Indicates if the user's data has been saved to database.
	 * @var bool
	 */
	public $is_saved = false;
	
	/**
	 * Indicates if new WP User was created successfully.
	 * @var bool
	 */
	public $created = false;
	
	
	
	
	public function __construct($guid = false) {
		
		if($guid !== false) {
			
			if(is_object($guid)) {
				
				$this->get_by_object($guid);
				
			} else {
				
				$this->get($guid);
			}
		}
		
		$this->_default_role = SSO_Utils::options('sso_role');
		
	}
	/**
	 * The WP role to use when creating new WP user from SSO User.
	 * Set from plugin option. Defaults to subscriber.
	 * 
	 * @var string
	 */
	public static function factory($guid = false) {
		
		return new SSO_User($guid);
	}
	
	protected function get($guid) {
		
		if($data = $this->_guid($guid)) {
			
			$this->set($data[0]);
			$this->set('guid', (string) $guid);
			$this->updated = false;
			
		} else { //Create new user
			
			$this->is_new = true;
			$this->_create();
		}
		
	}
	
	protected function _guid($guid) {
		
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}sso_users WHERE guid = {$guid}", ARRAY_A);
	}
	
	/**
	 * Extracts data from SimpleXMLElement Object from SSO Auth validation response.
	 * @param object $guid
	 */
	public function get_by_object(SimpleXMLElement $obj) {
		
		$this->set(array('email'	=> $obj->authenticationSuccess->user,
						'guid'		=> $obj->authenticationSuccess->attributes->guid));
		
		if($data = $this->_guid($this->guid)) {
			
			$this->set($data[0]);
			$this->updated = false;
			
		} else { //Create new user
			
			$this->is_new = true;
			$this->_create();
		}
		
	}
	
	public function get_by_id($user_id) {
		
		global $wpdb;
		
		if($data = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}sso_users WHERE user_id = {$user_id}", ARRAY_A)) {
			
			$this->set($data[0]);
		}
		
		return $this;
	}
	
	
	public function set($name, $value = null) {
		
		if(is_array($name)) {
			
			foreach($name as $prop=>$value) {
				
				if(property_exists(__CLASS__, $prop))
					$this->{$prop} = $value;
			}
			
		} else {
			
			if(property_exists(__CLASS__, $name))
				$this->{$name} = $value;
		}
		
		$this->updated = true;
		
		return $this;
	}
	
	public function save() {
		
		global $wpdb;
		
		if($this->is_new) {
			
			$wpdb->insert($wpdb->base_prefix . 'sso_users',
							  array('user_id' 		=> $this->user_id,
							  		'guid'	  		=> $this->guid,
							  		'screen_name'	=> $this->screen_name,
							  		'city'			=> $this->city,
							  		'state'			=> $this->state,
							  		'zipcode'		=> $this->zipcode));
			
		} else {
			
			if($this->id) {
				
				$wpdb->update($wpdb->base_prefix . 'sso_users',
								array('screen_name'	=> $this->screen_name,
										'city'		=> $this->city,
										'state'		=> $this->state,
										'zipcode'	=> $this->zipcode
										),
								array('id' => $this->id));
							
			}
			
		}
		
		$this->updated = false;
		$this->is_saved = true;
		
	}
	
	protected function _create() {
		
		//Create wp user
 		$user_id = wp_insert_user(array('user_pass'		=> $this->_random(),
							 			'user_email'	=> $this->email,
							 			'user_login'	=> $this->email,
							 			'user_role'		=> $this->_default_role));
 		
 		if(is_wp_error($user_id)) {
 			
 			$this->created = false;
 			return false;
 		}
 		
 		$this->user_id = $user_id;
 		
 		//Get screen name / update screen name
 		$user_data = SSO_Profile_Request::factory()->get($this->guid);
 		
 		if(! isset($user_data['error'])) {
 			
 			$this->screen_name = $user_data['screenname'];
 			$this->zipcode = $user_data['zipcode'];
 			
 			$this->_update_user_nicename($this->user_id, $this->screen_name);
 			
 			//Get Location/ update location
			$location = User_Location::factory()->get($this->zipcode)
												->response;
												
				if($location) {
					
					$this->city = $location['city'];
					$this->state = $location['state'];
				}
 		}
 		
 		$this->created = true;
 		return true;
	}
	
	public function login() {
		
		wp_set_current_user($this->user_id, $this->email);
 		wp_set_auth_cookie($this->user_id);
 		do_action('wp_login', $this->email);
	}
	
	protected function _update_user_nicename($uid, $name) {
		
		global $wpdb;
		$user = $wpdb->base_prefix . 'users';
	
		$update = $wpdb->update($user, 
								array('user_nicename' => $name),
								array('ID' => $uid));
						
		return ($update) ? true : false;
	}
	
	public function update_screen_name() {
		
		
	}
	
	public function update_location() {
		
	}
	
	/**
	 * Creates a random string used to create password for new WP user.
	 * 
	 * @param string $type - the type of string you want to create (see switch)
	 * @param int $length - length ofng to random string to return
	 * @return string - random string
	 * @access private
	 */
	private function _random($type = NULL, $length = 8) {
		
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
	
	
}