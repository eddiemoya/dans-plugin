<?php

class SSO_Epsilon_Request extends SSO_Base_Request {
	
	/**
	 * Array of Epsilon endpoints based on environment.
	 * 
	 * @var array 
	 * @access protected
	 */
	protected $_endpoints = array('production' 		=> 'https://spc.gc.epsilon.com/prefcenter/external/updSubscriptions.aspx',
									'integration' 	=> 'https://spcuat.gc.epsilon.com/prefcenter/external/updSubscriptions.aspx',
									'qa'			=> 'https://spcuat.gc.epsilon.com/prefcenter/external/updSubscriptions.aspx');
	
	/**
	 * Array of optcodes based on which site is user is opting into. To have requests
	 * sent for multiple optcodes, add new element to appropriate stores array.
	 * 
	 * @var array 
	 * @access protected
	 */
	protected $_optcodes = array('sears' 	=> array('MS'),
								 'kmart'	=> array('MK'));
	
	/**
	 * Array of optcodes to send in request
	 * 
	 * @var array 
	 * @access protected
	 */
	protected $_optcode;
	
	
	/**
	 * Constructor
	 * 
	 * @param void
	 */
	public function __construct() {
		
		parent::__construct();
		$this->_endpoint();
		$this->_optcode();
	}
	
	/**
	 * Factory
	 * 
	 * @param void
	 * @return object - instance of this class
	 */
	public static function factory() {
		
		return new SSO_Epsilon_Request;
	}
	
	/**
	 * Sets which endpoint to use in request based on plugin's environment setting.
	 * 
	 * @param void
	 * @access protected
	 * @return void
	 */
	protected function _endpoint() {
		
		$this->_endpoint = $this->_endpoints[$this->_environment];
	}
	
	/**
	 * Sets $optcode based on 'brand' theme option [sears | kmart]
	 * 
	 * @param void 
	 * @access protected
	 * @return void
	 */
	protected function _optcode() {
		
		$store = theme_option('brand');
		$this->_optcode = isset($this->_optcodes[$store]) ? $this->_optcodes[$store] : array();
	}
	
	/**
	 * Sends request to Epsilon endpoint.
	 * 
	 * @param string $email
	 * @access public
	 * @return void
	 */
	public function opt_in($email) {
		
		//Send request for each optcode defined in $_optcode
		foreach($this->_optcode as $optcode) {
			
			$this->_query('emailaddress', $email)
				->_query('optcode', $optcode)
				->_query('optvalue', 'Y')	
				->_url(false)
				->_execute();
		}
	}
}