

jQuery(document).ready( function(){
	
	//Logout
	if(document.getElementById('logout')) {
		
		jQuery('#logout').bind('click', function(event){
			
			event.preventDefault();
    		var url = window.sso_base + window.sso_plugin_path + 'login.php?sso_action=_logout';
    		
			jQuery('<iframe src="' + url +'" frameborder="0" scrolling="no" id="sso-auth" style="display:none;"></iframe>').appendTo(document.body);
		});
	}
	
	//For Login and reg page - non-modal
	if(document.getElementById("login") || document.getElementById('registration')) {
		
		login_reg_page();
		
	}
	
	//SSO cookie check
	sso_check_init();

});


function sso_error(message, type) {
	
	var template = (type == 'login') ? 'page-login' : 'page-register';
	
	//remove iframe
	jQuery('#sso-auth').remove();
	
	//Update modal, if it is present -- login/register
	if(document.getElementById('moodle_window')) {
		
		shcJSL.get(window).moodle({width:480, method:'ajax', target:ajaxdata.ajaxurl, type:'POST', data:{action: 'get_template_ajax', template: template}})
	}
	
	//send error (message)
	setTimeout( function() {
		
		if(document.getElementById('sso-error')) {
			
			document.getElementById('sso-error').innerHTML = message;
		}
	}, 1000 );
	
}

function login_reg_page() {
	
	var form = document.getElementById("login") || document.getElementById('registration');
	
	function sso_iframe() {
		
		jQuery('<iframe frameborder="0" scrolling="no" id="sso-auth" name="sso-auth" style="display:none;"></iframe>').appendTo(document.body);
	}
	
	if (form) {
		
		$(form).on("valid", function(event, submit) {
			
			submit.preventDefault();
			
			var sso_action = 'login.php?sso_action=' + ((document.getElementById('login')) ? '_login' : '_register');
			
			sso_iframe();
        	url = window.sso_base + window.sso_plugin_path + sso_action;
        	
        	form.action = url;
        	form.target = 'sso-auth';
        	form.method = 'POST';
        	form.submit();
    		
		});
		
	}
	
}

function sso_check_init() {
	
	var cookie_name = 'sso-checked';
	
	if(shcJSL.cookies(cookie_name).serve() != 'yes') {
		
		var url = window.sso_base + window.sso_plugin_path + 'login.php?sso_action=_session_check';
		jQuery('<iframe frameborder="0" scrolling="no" id="sso-auth" name="sso-auth" src="' + url + '" style="display:none;"></iframe>').appendTo(document.body);
		shcJSL.cookies(cookie_name).bake({value: 'yes'});
	}
	
}


/**
 * Reloads page after SSO login/register is complete
 */
function sso_complete() {
	
	window.location.reload();
}

function sso_iframe_close() {
	
	jQuery('#sso-auth').remove(); 
}

