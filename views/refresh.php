<script type="text/javascript">
<?php if(isset($close_OID) && $close_OID === true):?>
	
	window.opener.location.reload(false);
	window.close();
	
<?php else:?>

	parent.sso_complete();
	
<?php endif;?>
</script>
