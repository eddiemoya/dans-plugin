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
	 * Indicates if the user's data has been saved to database.
	 * @var bool
	 */
	public $is_saved = false;
	
	
	public function __construct($guid = false) {
		
		if($guid !== false) {
			
			$this->get($guid);
		}
		
	}
	
	public static function factory($guid = false) {
		
		return new SSO_User($guid);
	}
	
	protected function get($guid) {
		
		global $wpdb;
		
		if($data = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}sso_users WHERE guid = {$guid}", ARRAY_A)) {
			
			$this->set($data[0]);
			$this->set('guid', (string) $guid);
			$this->updated = false;
			
		} else {
			
			$this->is_new = true;
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
	
	
}