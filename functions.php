<?php


function strip_qs($url) {
	
	$url_parts = parse_url($url);
	
	if(count($url_parts)) {
	
		$url_out = $url_parts['scheme'] . '://' . $url_parts['host'];
		
			//Is there a port number?
			if(isset($url_parts['port'])) {
				
				$url_out .= ':' . $url_parts['port'];
			}
			
			//Is there a uri?
			if(isset($url_parts['path'])) {
				
				$url_out .= $url_parts['path'];
			} 
			
			
				return $url_out;
				
		} else { //$url was not a URL, return it
			
			return $url;
		}
}

function sso_logout_link($text) {
	
	$current_url = (! empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
	
	echo '<a href="?ssologout&origin=' . urlencode(strip_qs($current_url)) . '" title="Logout" class="bold">' . $text . '</a>';
}

function can_access_admin() {
    return ((current_user_can("activate_plugins") && current_user_can("create_users")) || current_user_can("access_dashboard"));
}
function disallow_dashboard() {
    if (!can_access_admin()) {
        if (is_admin()) {
            wp_redirect(site_url());
        }
    }
}

add_action('admin_init', "disallow_dashboard");

function hide_adminbar() {
    if (!can_access_admin()) {
        add_filter('show_admin_bar','__return_false');
    }
}

add_action('setup_theme', "hide_adminbar");