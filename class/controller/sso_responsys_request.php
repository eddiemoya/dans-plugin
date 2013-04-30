<?php
class SSO_Responsys_Request extends SSO_Base_Request {
	
	protected $_email;
	
	protected $_user_id;
	
	protected $_username;

		
	public function __construct($email, $user_id) {
		
		$this->_email = $email;
		$this->_user_id = $user_id;
		$this->_username();
		$this->_endpoint();
		$this->_url(false);
	}
	
	public static function factory($email, $user_id) {
		
		return new SSO_Responsys_Request($email, $user_id);
	}
	
	protected function _username() {
		
		$email_parts = explode('@', $this->_email);
		$this->_username = $email_parts[0];
	}
	
	protected function _endpoint() {
		
		$site = (stripos(get_bloginfo('name'), 'sears') !== false) ? 'MS' : 'MK';
		
		if($site == 'MS') { //Sears
			
			$this->_endpoint = "https://sears.rsys4.net/servlet/campaignrespondent?_ID_=g34ri4.872&EMAIL_ADDRESS={$this->_email}&SCREEN_NAME={$this->_username}&SID_CODE=ITx20120921TriggeredSRSMCWelcome&LAUNCH_DATE=2012-09-21&OPT_TYPE_CODE=MS&USERID={$this->_user_id}";
			
		} else { //Kmart
			
			$this->_endpoint = "https://kmart.rsys4.net/servlet/campaignrespondent?_ID_=kmart.3611&EMAIL_ADDRESS={$this->_email}&SCREEN_NAME={$this->_username}&SID_CODE=ITx20120921TriggeredMKwelcome&LAUNCH_DATE=2012-09-21&OPT_TYPE_CODE=MK&USERID={$this->_user_id}";
		}
	}
	
	public function send() {
		
		$this->_execute(false);
	}
}