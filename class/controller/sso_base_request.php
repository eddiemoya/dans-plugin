<?php
/**
 * SSO_Base_Request (Controller - parent)
 * 
 * All SSO controllers inherit from this class
 * 
 * @author Dan Crimmins
 * @since 04/25/2013
 */
class SSO_Base_Request {
	
	/**
	 * Endpoint URL for request.
	 * 
	 * @var string
	 */
	protected $_endpoint;
	
	/**
	 * Environment - integration, qa, production
	 * 
	 * @var string
	 */
	protected $_environment = 'production';
	
	/**
	 * URI attached to $_endpoint
	 * 
	 * @var string
	 */
	protected $_action;
	
	/**
	 * HTTP request method
	 * 
	 * @var string
	 */
	protected $_method = 'GET';
	
	/**
	 * Array of query string parameters.
	 * 
	 * @var array
	 */
	protected $_query = array();
	
	/**
	 * Post data.
	 * 
	 * @var mixed [string | array]
	 */
	protected $_post;
	
	/**
	 * Full URL for request.
	 * 
	 * @var string
	 */
	public $url;
	
	/**
	 * Constructor
	 * 
	 * Sets $_environment
	 * 
	 * @access public
	 * @param void
	 */
	public function __construct() {
		
		$this->_environment = SSO_Utils::options('environment');
	}
	
	/**
	 * Makes cURL request and handles formatting of response.
	 * 
	 * @access protected
	 * @param bool $object - return object?
	 * @param string $format - [xml | json]
	 * @param bool $cas - IS this a call to SSO CAS server?
	 * @return mixed [object | string]
	 * @throws Exception
	 */
	protected function _execute($object=false, $format='xml', $cas=true) {
		
		$options = array(CURLOPT_URL            => $this->url,
			            CURLOPT_RETURNTRANSFER  => TRUE,
			            CURLOPT_HEADER          => FALSE,
			            CURLOPT_SSL_VERIFYHOST  => 0,
			            CURLOPT_SSL_VERIFYPEER  => 0,
			            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
	        			CURLOPT_CUSTOMREQUEST	=> $this->_method);
			            
		 switch(strtoupper($this->_method)) {
		 	
		 	case 'POST':
		 		$post = (is_array($this->_post) && count($this->_post)) ? trim(http_build_query($this->_post)) : $this->_post;
	        	$options[CURLOPT_HTTPHEADER] = array((is_array($this->_post)) ? 'Content-type: application/x-www-form-urlencoded' : 'Content-Type: application/xml' ,
	        											'Content-length: ' . strlen($post));
	        	
	        	$options[CURLOPT_POSTFIELDS] = $post;
	        	
		 		break;
		 		
		 	case 'PUT':
		 		
		 		$options[CURLOPT_POSTFIELDS] = $this->_post;
        		$options[CURLOPT_HTTPHEADER] = array('Content-Type: application/xml');
		 		
		 		break;
		 }
			 
			 
        $ch = curl_init();

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        
      
        // Get the response information
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        

        curl_close($ch);

 
		//Return response		
        if($object) { //Convert response to object
        	
        	switch($format) {
        		
        		case 'xml':
        			
        			//If we get a HTTP 404
        			if($code == '404') {
        				
        				$out = new stdClass();
        				$out->code = '404';
        				$out->error = 'Not found';
        				
        				return $out;
        			}

        			//If we get HTTP 200 with blank response --
        			//This is necessary because screen name validate returns 200 w/ 
        			//blank response if it is OK, otherwise it returns XML.
        			if($code == '200' && ! $response) {
        				
        				return null;
        			}
        			
        			return $this->_xml_to_object($response, $cas);
        				
        			break;
        				
        		case 'json':
        			
        				return json_decode($response);
        			
        			break;
        	}
        	
        } else { //Return the raw response from call (xml or json)
        	
        	return $response;
        }
	}
	
	/**
	 * Adds element to $_query array.
	 * 
	 * @param string $name
	 * @param string $value
	 * @return object - instance of this object
	 */
	protected function _query($name, $value) {	
		
		$this->_query[$name] = $value;
		
		return $this;
	}
	
	/**
	 * Adds data to $_post.
	 * 
	 * @param mixed [string | array] $data
	 * @return object - instance of this object
	 */
	protected function _post($data) {
		
		$this->_post = $data;
		
		return $this;
	}
	
	/**
	 * Builds URL for request. Sets $url.
	 * 
	 * @param bool $encode - url encode the qs params?
	 * @return object - instance of this object
	 */
	protected function _url($encode=true) {
		
		if($encode) {
			
			$this->url = $this->_endpoint . $this->_action . ((count($this->_query)) ? '?' . http_build_query($this->_query) : '');
			
		} else {
			
			$this->url = $this->_endpoint . $this->_action . ((count($this->_query)) ? '?' . $this->_create_querystring() : '');
		}
		
		return $this;
	}
	
	/**
	 * Build unencoded querystring from $_query array.
	 * 
	 * @access private
	 * @param void
	 * @return string - the unencoded querystring 
	 */
	private function _create_querystring() {
		
		$qs = '';
		
		foreach ($this->_query as $key => $value) {
			
			$qs .= $key . '=' . $value . '&';
		}
		
		return rtrim($qs, '&');
	}
	
	/**
	 * Sets $_method.
	 * 
	 * @access protected
	 * @param string $method - HTTP request method.
	 * @return object - instance of this object
	 */
	protected function _method($method) {
		
		$this->_method = $method;
		
		return $this;
	}
	
	/**
	 * Converts XML response from CAS & CIS to SimpleXMLELement object.
	 * 
	 * @access protected
	 * @param string $xml
	 * @param bool $cas - is this xml from CAS?
	 * @return object - SimpleXMLElement 
	 */
	protected function _xml_to_object($xml, $cas=true) {
		
		if($cas) { //For SSO CAS
			
			$xml = "<?xml version='1.0'?>\n" . trim($xml);
		    $xml = preg_replace('~\s*(<([^>]*)>[^<\s]*</\2>|<[^>]*>)\s*~', '$1', $xml);
		    $xml = preg_replace_callback('~<([A-Za-z\s]*)>~', create_function('$matches', 'return "<".strtolower($matches[1]).">";'), $xml);
		    $xml = preg_replace_callback('~</([A-Za-z\s]*)>~', create_function('$matches', 'return "</".strtolower($matches[1]).">";'), $xml);
		    $xml = new \SimpleXmlElement($xml);
		    
		    return $xml->children('http://www.yale.edu/tp/cas');
		    
		} else { // For Profile CIS
			
			$xml = new SimpleXmlElement($xml);
         	return $xml->children();
		}
	}
	
	/**
	 * Given an array of user data, adds data to SimpleXMLElement object
	 * 
	 * @param array $data - array of user profile data
	 * @param object $xml - SimpleXMLElement object.
	 * @return void
	 * @access protected
	 */
	protected function _to_xml(array $data, $xml) {
		
		foreach ($data as $key => $value) {
			
	            if (is_array($value)) 
	            {
	                if ( ! is_numeric($key)) {
	                	
	                    $sub = $xml->addChild($key);
	                  	$this->_to_xml($value, $sub);
	                   
	                } else {
	                	
	                    $this->_to_xml($value, $xml);
	                }
	            }
	            else
	            {
	                $xml->addChild($key, $value);
	            }
	        }
	}
}