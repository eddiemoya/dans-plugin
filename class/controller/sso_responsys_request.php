<?php
class SSO_Responsys_Request extends SSO_Base_Request {
	
	protected $_email;
	
	protected $_user_id;
	
	protected $_username;

	protected $_side_code_date;

	protected $_launch_date

		
	public function __construct($email, $user_id) {
		
		$this->_email = $email;
		$this->_user_id = $user_id;
		$this->_username();
		$this->_endpoint();
		$this->_url(false);
		$this->set_dates();
	}
	
	public static function factory($email, $user_id) {
		
		return new SSO_Responsys_Request($email, $user_id);
	}
	
	protected function _username() {
		
		$email_parts = explode('@', $this->_email);
		$this->_username = $email_parts[0];
	}
	
	protected function set_dates(){
		$this->_side_code_date = date('Y-m-d');
		$this->_launch_date = date('Ymd');
	}
	protected function _endpoint() {
		
		$site = (stripos(get_bloginfo('name'), 'sears') !== false) ? 'MS' : 'MK';
		
		if($site == 'MS') { //Sears
			
			$this->_endpoint = "https://value.sears.com/pub/rf?_ri_=X0Gzc2X%3DWQpglLjHJlTQGo2trMyHvzcdfAizdCOgaza4CJ1PldMVwjpnpgHlpgneHmgJoXX0Gzc2X%3DWQpglLjHJlTQGhE2WmfvtPtzfCzgd2MuuWFLcuamIG&EMAIL_ADDRESS_={$this->_email}&SCREEN_NAME={$this->_username}&SID_CODE=ITx{$this->_sid_code_date}TTriggeredSRSMCWelcome&LAUNCH_DATE={$this->_launch_date}&OPT_TYPE_CODE=MS&USERID={$this->_user_id}";
			
		} else { //Kmart
			
			$this->_endpoint = "https://kmart.rsys2.net/pub/rf?_ri_= X0Gzc2X%3DWQpglLjHJlTQGrO29WN3s0tgTzb4ADPryLRJzduOVwjpnpgHlpgneHmgJoXX0Gzc2X%3DWQpglLjHJlTQGNJaaXyBOGyYoJeUczdzbyNYBkMn&EMAIL_ADDRESS={$this->_email}&SCREEN_NAME={$this->_username}&SID_CODE=ITx{$this->_sid_code_date}TriggeredMKwelcome&LAUNCH_DATE={$this->_launch_date}&OPT_TYPE_CODE=MK&USERID={$this->_user_id}";
		}
	}
	
	public function send() {
		
		$this->_execute(false);
	}

}