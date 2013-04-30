<?php $mode = (isset($_REQUEST['sso-registration'])) ? 'register' : 'login';?>

<script type="text/javascript">
//JS here to hide waiting div and print error to modal, will pass $msg variable
//Also, can pass close_OID var to also have this optionally close OID login window
<?php if(isset($close_OID) && $close_OID === true):?>

	if(window.opener.document.getElementById('sso-error')) 
			window.opener.document.getElementById('sso-error').innerHTML = '<?php echo $msg;?>';

	window.close();

<?php else:?>

	parent.sso_error('<?php echo $msg;?>', '<?php echo $mode;?>');

<?php endif;?>
</script>
