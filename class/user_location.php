<?php
/**
 * User_Locations: Makes call to Ziptastic service with zipcode and returns
 * city, state, and country (array)
 * 
 * @author Dan Crimmins
 *
 */
class User_Location {
	
	/**
	 * Ziptatsic URL
	 * @var string
	 */
	private $_endpoint = 'http://zip.elevenbasetwo.com/v2/US/';
	
	/**
	 * Zipcode
	 * @var string
	 */
	private $_zipcode;
	
	/**
	 * Assoc array of state abbr to state name
	 * @var array
	 */
	private $_state_abbr = array('AL'=>"Alabama",
				                'AK'=>"Alaska", 
				                'AZ'=>"Arizona", 
				                'AR'=>"Arkansas", 
				                'CA'=>"California", 
				                'CO'=>"Colorado", 
				                'CT'=>"Connecticut", 
				                'DE'=>"Delaware", 
				                'DC'=>"District Of Columbia", 
				                'FL'=>"Florida", 
				                'GA'=>"Georgia", 
				                'HI'=>"Hawaii", 
				                'ID'=>"Idaho", 
				                'IL'=>"Illinois", 
				                'IN'=>"Indiana", 
				                'IA'=>"Iowa", 
				                'KS'=>"Kansas", 
				                'KY'=>"Kentucky", 
				                'LA'=>"Louisiana", 
				                'ME'=>"Maine", 
				                'MD'=>"Maryland", 
				                'MA'=>"Massachusetts", 
				                'MI'=>"Michigan", 
				                'MN'=>"Minnesota", 
				                'MS'=>"Mississippi", 
				                'MO'=>"Missouri", 
				                'MT'=>"Montana",
				                'NE'=>"Nebraska",
				                'NV'=>"Nevada",
				                'NH'=>"New Hampshire",
				                'NJ'=>"New Jersey",
				                'NM'=>"New Mexico",
				                'NY'=>"New York",
				                'NC'=>"North Carolina",
				                'ND'=>"North Dakota",
				                'OH'=>"Ohio", 
				                'OK'=>"Oklahoma", 
				                'OR'=>"Oregon", 
				                'PA'=>"Pennsylvania", 
				                'RI'=>"Rhode Island", 
				                'SC'=>"South Carolina", 
				                'SD'=>"South Dakota",
				                'TN'=>"Tennessee", 
				                'TX'=>"Texas", 
				                'UT'=>"Utah", 
				                'VT'=>"Vermont", 
				                'VA'=>"Virginia", 
				                'WA'=>"Washington", 
				                'WV'=>"West Virginia", 
				                'WI'=>"Wisconsin", 
				                'WY'=>"Wyoming");
	
	/**
	 * Holds response from service. Array of data (city, state, country) or false.
	 * 
	 * @var mixed (array | bool)
	 */
	public $response = false;
	
	
	
	public function __construct() {
		
	}
	
	/**
	 * Given a zipcode will make request to service. Sets _zipcode property.
	 * 
	 * @access public
	 * @param string $zipcode
	 * @return object
	 */
	public function get($zipcode) {
		 
		$this->_zipcode = $zipcode;
		
		$this->execute_request();
		
		$this->convert_state_name_to_abbr();
		
		return $this;
		
	}
	
	/**
	 * Makes cURL request to service. Sets response prodperty.
	 * 
	 * @access private
	 * @param void
	 * @return void
	 */
	
	private function execute_request() {
		
		$url = $this->_endpoint . $this->_zipcode;
		
		$options = array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_HEADER          => FALSE,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_CUSTOMREQUEST 	=> 'GET');
            
        $ch = curl_init();

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        
        // Get the response information
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     

	        if ( ! $response || $code != '200') {
	        	
	            $error = $code;
	        }
	
	        curl_close($ch);
	        
	       if(! isset($error)) {
	       	
	       		$this->set_response($response);
	       }
	        
	        
	}
	
	/**
	 * Sets response property.
	 * 
	 * @access private
	 * @param string $json
	 * @return void
	 */
	private function set_response($json) {
		
		$location = json_decode($json, true);
		
		$this->response = ($location) ? $location : false;
	}
	
	
	private function convert_state_name_to_abbr() {
		
		if($this->response) {
			
			$abbr = array_search(ucfirst(strtolower($this->response['state'])), $this->_state_abbr);
			
			if($abbr !== false) {
				
				$this->response['state'] = $abbr;
			}
			
		}
		
	}
	
}