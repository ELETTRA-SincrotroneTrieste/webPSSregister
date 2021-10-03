<?php
define('REDIRECT_URL', 'https://fcs-proxy-01.elettra.eu/pss/login_catch.php');

if (empty($_REQUEST['pss_username']) or empty($_REQUEST['pss_token'])) {
	header("Location: ".REDIRECT_URL."?result=invalid_parameters");
	die();
}
if (isset($_REQUEST['pss_password'])) {
	$ds=ldap_connect("abook.elettra.eu");
	if (!$ds) { 
		header("Location: ".REDIRECT_URL."?result=not_available");
		die();
	}
	$r=@ldap_bind($ds, $_REQUEST['pss_username'], $_REQUEST['pss_password']);
	ldap_close($ds);
	readfile(REDIRECT_URL."?pss_username={$_REQUEST['pss_username']}&pss_token={$_REQUEST['pss_token']}&result=".($r? "successful": "failed"));
	die();
}
die(strtr(file_get_contents('./pss_login.html'), array('<!--pss_username-->'=>$_REQUEST['pss_username'], '<!--pss_token-->'=>$_REQUEST['pss_token'])));
?>
