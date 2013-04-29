jQuery(document).ready( function(){
	
	if(document.getElementById('logout')) {
		
		jQuery('#logout').bind('click', function(event){
			
			event.preventDefault();
			//var base = url_parts[0] + '//' + url_parts[2] + '/community/'; //Production
    		var base = window.location.protocol + '//' + window.location.host + '/'; //Local Dev
    		var plugin_path = 'wp-content/plugins/shc-sso-profile/public/';
    		var url = base + plugin_path + 'login.php?sso_action=_logout';
    		
			jQuery('<iframe src="' + url +'" frameborder="0" scrolling="no" id="sso-auth" style="display:hidden;"></iframe>').appendTo(document.body);
		});
	}

});


function sso_error(message, type='login') {
	
	var template = (type == 'login') ? 'page-login' : 'page-register';
	
	//remove iframe
	jQuery('#sso-auth').remove();
	
	//Update modal -- login/register
	shcJSL.get(window).moodle({width:480, method:'ajax', target:ajaxdata.ajaxurl, type:'POST', data:{action: 'get_template_ajax', template: template}})
	
	//send error (message)
	setTimeout( function() {
		
		if(document.getElementById('sso-error')) {
			
			document.getElementById('sso-error').innerHTML = message;
		}
	}, 1000 );
	
}

/**
 * Reloads page after SSO login/register is complete
 */
function sso_complete() {
	
	window.location.reload();
}

