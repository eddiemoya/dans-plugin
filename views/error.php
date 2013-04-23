<script type="text/javascript">
//JS here to hide waiting div and print error to modal, will pass $msg variable
//Also, can pass close_OID var to also have this optionally close OID login window


<?php if(isset($close_OID) && $close_OID === true):?>

	window.opener.location.reload(false);
	window.close();

<?php endif;?>
</script>
