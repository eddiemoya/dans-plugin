<?php
/**
 * SSO_User (Model) 
 * 
 * Gets, sets and updates sso user data in the database. 
 * Creates 
 * 
 * @author Dan Crimmins
 *
 */
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
	public $is_new = false;
	
	/**
	 * Indicates if an update via set() has been made to object.
	 * @var bool
	 */
	public $updated = false;
	
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
	
	
	
	/**
	 * Constructor - $guid can be the guid or the SimpleXMLElement
	 * from SSO CAS validation method. 
	 *  
	 * @access public
	 * @param mixed [strng | object SimpleXMLElement] $guid
	 * @return void
	 */
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
	 * Factory
	 * 
	 * @param string $guid
	 * @return object - instance of this object
	 */
	public static function factory($guid = false) {
		
		return new SSO_User($guid);
	}
	
	/**
	 * Gets user data by either guid or object (response from
	 * @param unknown_type $guid
	 */
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
	
	/**
	 * Retrieves user data from DB.
	 * 
	 * @param string $guid
	 * @return mixed [array | bool]
	 */
	protected function _guid($guid) {
		
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}sso_users WHERE guid = {$guid}", ARRAY_A);
	}
	
	/**
	 * Extracts data from SimpleXMLElement Object from SSO Auth validation response. Sets
	 * properties and creates new user (if needed).
	 * 
	 * @access public
	 * @param object SimpleXMLElement $guid
	 * @return void
	 */
	public function get_by_object(SimpleXMLElement $obj) {
		
		$this->set(array('email'	=> (string) $obj->authenticationSuccess->user,
						'guid'		=> (string) $obj->authenticationSuccess->attributes->guid));
		
		if($data = $this->_guid($this->guid)) {
			
			$this->set($data[0]);
			$this->updated = false;
			
		} else { //Create new user
			
			$this->is_new = true;
			$this->_create();
		}
		
	}
	
	/**
	 * Retrieves user data by WP user_id and sets properties.
	 * 
	 * @access public
	 * @param int $user_id
	 * @return object - an instance of this object
	 */
	public function get_by_id($user_id) {
		
		global $wpdb;
		
		if($data = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}sso_users WHERE user_id = {$user_id}", ARRAY_A)) {
			
			$this->set($data[0]);
		}
		
		return $this;
	}
	
	/**
	 * Sets objects properties. $name can be a string or an array. Sets $updated to true.
	 *
	 * @access public
	 * @param mixed [string | array] $name
	 * @param string $value
	 * @return object - instance of this object
	 */
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
	
	/**
	 * Inserts or updates user's DB record.
	 * 
	 * @access public
	 * @param void
	 * @return void
	 */
	public function save() {
		
		global $wpdb;
		
		if($this->is_new) { //New User - insert into db
			
			$wpdb->insert($wpdb->base_prefix . 'sso_users',
							  array('user_id' 		=> $this->user_id,
							  		'guid'	  		=> $this->guid,
							  		'screen_name'	=> $this->screen_name,
							  		'city'			=> $this->city,
							  		'state'			=> $this->state,
							  		'zipcode'		=> $this->zipcode));
			
		} else {
			
			if($this->id) { //Existing User - update db record
				
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
	
	/**
	 * Creates new WP user. Sets $user_id, $screen_name, $city, $state properties.
	 * 
	 * @access public 
	 * @param void
	 * @return bool - true if user created successfully.
	 */
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
 		
 		echo '<pre>';
 		var_dump($user_data);
 		exit;
 		
 		
 		if(! isset($user_data['error'])) {
 			
 			$this->screen_name = $user_data['screenname'];
 			$this->zipcode = SSO_Utils::truncate_zipcode($user_data['zipcode']);
 			
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
	
	/**
	 * Performs WP login.
	 * 
	 * @access public
	 * @param void
	 * @return void
	 */
	public function login() {
		
		wp_set_current_user($this->user_id, $this->email);
 		wp_set_auth_cookie($this->user_id);
 		do_action('wp_login', $this->email);
	}
	
	/**
	 * Updates user's nicename field in wp_users table.
	 * 
	 * @access protected
	 * @param int $uid - WP user_id
	 * @param string $name - nicename to set
	 * @return bool - true on success, false on fail.
	 */
	protected function _update_user_nicename($uid, $name) {
		
		global $wpdb;
		$user = $wpdb->base_prefix . 'users';
	
		$update = $wpdb->update($user, 
								array('user_nicename' => $name),
								array('ID' => $uid));
						
		return ($update) ? true : false;
	}
	
	/**
	 * Checks if user's CIS profile screen name matches what we have locally. If not,
	 * updates it locally.
	 * 
	 * @access public
	 * @param void
	 * @return void
	 */
	public function update_screen_name() {
		
		if(! $this->_profile_data || isset($this->_profile_data['error'])) 
			$this->_profile_data = SSO_Profile_Request::factory()->get($this->guid);
		
		if(! isset($this->_profile_data['error']) && ($this->_profile_data['screenname'] != $this->screen_name)) {
			$this->screen_name = $this->_profile_data['screenname'];
			$this->_update_user_nicename($this->user_id, $this->screen_name);
		}
	}
	
	/**
	 * Checks if user's CIS zipcode matches what we store locally, if not updates it locally.
	 * Also, updates city and state.
	 * 
	 * @access public
	 * @param void
	 * @return void
	 */
	public function update_location() {
		
		if(! $this->_profile_data || isset($this->_profile_data['error']))
			$this->_profile_data = SSO_Profile_Request::factory()->get($this->guid);
			
		if(! isset($this->_profile_data['error']) && ($this->zipcode != SSO_Utils::truncate_zipcode($this->_profile_data['zipcode']))) {
			
			$this->zipcode = SSO_Utils::truncate_zipcode($this->_profile_data['zipcode']);
			
			//Get new city, state
			$location = User_Location::factory()->get($this->zipcode)
												->response;
			
			if($location) {
				
				$this->city = $location['city'];
				$this->state = $location['state'];
			}
		}
			
	}
	
	/**
	 * Creates a random string used to create password for new WP user.
	 * 
	 * @param string $type - the type of string you want to create (see switch)
	 * @param int $length - length of random string to return
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