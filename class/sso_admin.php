<?php

class SSO_Admin {
	
	public static $options = array('environment'		=> 'production',
									'profile_key'		=> 'YWCHJTDwxjHlZXro3NggLiWKu_I',
									'profile_site_id'	=> 41,
									'sso_site_id' 		=> 41,
									'sso_role' 			=> 'Subscriber',
									'sso_login_page_uri'=> '');
									
	public static $environments = array('production' 	=> 'production',
								 'integration'			=> 'integration',
								 'qa'					=> 'qa');
	
	public static function install() {
		
		update_option(SHCSSO_OPTION_PREFIX . 'version', SHCSSO_VERSION);
		update_option(SHCSSO_OPTION_PREFIX . 'settings', self::$options);
	}
	
	public static function uninstall() {
		
		delete_option(SHCSSO_OPTION_PREFIX . 'version');
		delete_option(SHCSSO_OPTION_PREFIX . 'settings');
	}
	
	public static function init() {
		
		add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
	}
	
	public static function menu()
    {
        add_options_page('Shc SSO and Profile Settings', 'SSO Settings', 'manage_options', 'shcsso-settings', array(__CLASS__, 'settings_page'));
    }
    
    public static function settings_page() {
    	
    	//ob_start();
    ?>
    	<div>
		<h2>SHC SSO-Profile Plugin Settings</h2>
		
		<form action="options.php" method="post">
		<?php settings_fields(SHCSSO_OPTION_PREFIX . 'settings'); ?>
		<?php do_settings_sections('shcsso-settings'); ?>
		 
		<input class="button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</form></div>
	<?php 
		//$output = ob_get_clean();
		//echo $output;
    }

    public static function register_settings()
    {
        register_setting(SHCSSO_OPTION_PREFIX . 'settings', SHCSSO_OPTION_PREFIX . 'settings');
        add_settings_section(SHCSSO_OPTION_PREFIX . 'main_section', __('Main Settings'), array(__CLASS__, 'main_section'), 'shcsso-settings');
        add_settings_field('environment', __('Environment'), array(__CLASS__, 'environment'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'main_section');
        add_settings_section(SHCSSO_OPTION_PREFIX . 'sso_settings', __('SSO Settings'), array(__CLASS__, 'sso_section'), 'shcsso-settings');
        add_settings_field('sso_site_id', __('Source Site ID'), array(__CLASS__, 'sso_site_id'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'sso_settings');
        add_settings_field('sso_role', __('User Role'), array(__CLASS__, 'sso_role'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'sso_settings');
        add_settings_field('sso_login_page_uri', __('Login Page'), array(__CLASS__, 'sso_login_page_uri'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'sso_settings');
        add_settings_section(SHCSSO_OPTION_PREFIX . 'profile_settings', __('Profile Settings'), array(__CLASS__, 'profile_section'), 'shcsso-settings');
        add_settings_field('profile_site_id', __('Profile Site ID'), array(__CLASS__, 'profile_site_id'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'profile_settings');
        add_settings_field('profile_key', __('Profile Key'), array(__CLASS__, 'profile_key'), 'shcsso-settings', SHCSSO_OPTION_PREFIX . 'profile_settings');
    }
	
 	public static function main_section(){
 		
        echo '<p>' . __('Settings specifically for this plugin.') . '</p>';
    }
    
    public static function environment() {
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$environment = $options['environment'];
    	?>
    		<select name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[environment]', ENT_QUOTES);?>" id="environment">
    		<?php foreach(self::$environments as $display => $value):?>
    			<option value="<?php echo $value;?>"<?php echo ($environment == $value) ? ' selected="selected"' : '';?>><?php echo $display;?></option>
    		<?php endforeach;?>
    		</select>   	
    	<?php 
    }
    
 	public static function sso_section() {
 		
        echo '<p>' . __('Settings specifically for SSO.') . '</p>';
    }
    
    public static function sso_site_id() {
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$site_id = $options['sso_site_id'];
    	?>
    		<input type="text" name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[sso_site_id]', ENT_QUOTES);?>" id="sso_site_id" value="<?php echo $site_id;?>" />
    	<?php 
    }
    
    public static function sso_role() {
    	
    	global $wp_roles;
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$curr_role = $options['sso_role'];
    	$roles = (array) $wp_roles->get_names();
    	?>
    		<select name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[sso_role]', ENT_QUOTES);?>" id="sso_role">
    			<?php foreach($roles as $role):?>
    				<option value="<?php echo $role;?>"<?php echo ($role == $curr_role) ? ' selected="selected"' : '';?>><?php echo $role?></option>
    			<?php endforeach;?>
    		</select>
    	<?php 
    }
    
    public static function sso_login_page_uri() {
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$uri = $options['sso_login_page_uri'];
    	
    	$pages = get_pages();
    	?>
    		<select name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[sso_login_page_uri]', ENT_QUOTES);?>" id="sso_login_page_uri">
    			<option value="" <?php if($uri == '') echo ' selected="selected"';?>>Select one...</option>
    			<?php foreach($pages as $page):?>
    				<option value="<?php echo $page->ID;?>"<?php echo ($page->ID == $uri) ? ' selected="selected"' : '';?>><?php echo get_permalink($page->ID);?></option>
    			<?php endforeach;?>
    		</select>
    	<?php 
    }
    
	public static function profile_section() {
        echo '<p>' . __('Settings specifically for Profile.') . '</p>';
    }
    
    public static function profile_site_id() {
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$site_id = $options['profile_site_id'];
    	?>
    		<input type="text" name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[profile_site_id]', ENT_QUOTES);?>" id="profile_site_id" value="<?php echo $site_id;?>" />
    	<?php 
    }
    
    public static function profile_key() {
    	
    	$options = get_option(SHCSSO_OPTION_PREFIX . 'settings');
    	$key = $options['profile_key'];
    	?>
    		<input type="text" name="<?php echo htmlspecialchars(SHCSSO_OPTION_PREFIX . 'settings[profile_key]', ENT_QUOTES);?>" id="profile_key" value="<?php echo $key;?>" />
    	<?php 
    }   	
}