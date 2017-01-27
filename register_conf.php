<?php
	$loginService = 'https://your_authentication_service';
	$operator_url = 'http://your_operator_list_service';

	$master_ip = array('localhost'=>'127.0.0.1');
	$backoffice_ip = array();
	$controlroom_ip = array();

	$machine = isset($_REQUEST['machine'])? $_REQUEST['machine']: 'your_machine';
	define("USERNAME", "root");
	define("PASSWORD", "");
	define("DB", "db");

	$user = array(
	);

	$user_fromfile = array(
	);
	
?>
