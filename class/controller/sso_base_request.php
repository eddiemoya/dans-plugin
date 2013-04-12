<?php

class SSO_Base_Request {
	
	protected $_endpoint;
	
	protected $_environment = 'production';
	
	protected $_action;
	
	protected $_method = 'GET';
	
	protected $_query = array();
	
	protected $_post = array();
	
	public $url;
	

	public function __construct() {
		
		$this->_environment = SSO_Utils::options('environment');
	}
	
	protected function _execute($object=false) {
		
		$options = array(CURLOPT_URL            => $this->url,
			            CURLOPT_RETURNTRANSFER  => TRUE,
			            CURLOPT_HEADER          => FALSE,
			            CURLOPT_SSL_VERIFYHOST  => 0,
			            CURLOPT_SSL_VERIFYPEER  => 0,
			            CURLOPT_USERAGENT       => $_SERVER['HTTP_USER_AGENT'],
	        			CURLOPT_CUSTOMREQUEST	=> $this->_method);
			            
		 switch(strtoupper($this->_method)) {
		 	
		 	case 'POST':
		 		$post = (is_array($this->_post)) ? trim(http_build_query($this->_post)) : $this->_post;
	        	$options[CURLOPT_HTTPHEADER] = array((is_array($this->_post)) ? 'Content-type: application/x-www-form-urlencoded' : 'Content-Type: application/xml' ,
	        											'Content-length: ' . $strlen($post));
	        	
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
        
     
        if ( ! $response) {
            $error = curl_error($ch);
        }

        curl_close($ch);
        
         if (isset($error)) {
         	
            throw new Exception('Error fetching remote '.$url.' [ status '.$code.' ] '.$error);
        }

        return ($object) ? $this->_xml_to_object($response)  : $response;
		
	}
	
	protected function _query($name, $value) {	
		
		$this->_query[$key] = $value;
		
		return $this;
	}
	
	
	protected function _url() {
		
		$this->url = $this->_endpoint . $this->_action . ((count($this->_query)) ? '?' . http_build_query($this->_query) : '');
		
		return $this;
	}
	
	protected function _xml_to_object($xml) {
		
		$xml = "<?xml version='1.0'?>\n" . trim($xml);
	    $xml = preg_replace('~\s*(<([^>]*)>[^<\s]*</\2>|<[^>]*>)\s*~', '$1', $xml);
	    $xml = preg_replace_callback('~<([A-Za-z\s]*)>~', create_function('$matches', 'return "<".strtolower($matches[1]).">";'), $xml);
	    $xml = preg_replace_callback('~</([A-Za-z\s]*)>~', create_function('$matches', 'return "</".strtolower($matches[1]).">";'), $xml);
	    $xml = new \SimpleXmlElement($xml);
	    
	    return $xml->children('http://www.yale.edu/tp/cas');
	}
}