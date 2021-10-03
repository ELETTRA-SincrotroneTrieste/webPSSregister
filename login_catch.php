<?php
  if (strpos($_REQUEST['pss_token'], 'mytest')!==false) {
	  echo "<script>var x = history.length;alert(document.origin); for (i in window.history) alert(window.history[i]);</script><pre>\n"; print_r($_REQUEST); print_r($_SERVER); print_r($_SESSION); print_r($_ENV); echo "\n</pre>\n";exit();
  }
  if (strpos($_REQUEST['pss_token'], 'app:')!==false) {
	$user = array(
	);
	  // echo "<pre>\n"; print_r($_SERVER); echo "</pre>\n"; exit();
	  // if ($_SERVER['REMOTE_ADDR'] !== '140.105.206.82') {sleep(1); die("  <script>\n  window.top.postMessage({'result': 'ERROR: server not authorized'}, '*');\n</script>");}
	  if ($_REQUEST['result']!=='successful') {sleep(1); die("  <script>\n  window.top.postMessage({'result': '".strtr($_REQUEST['result'],array('failed'=>'Command not authorized, invalid username or password'))."'}, '*');\n</script>");}
	  if (!isset($user[$_REQUEST['pss_username']])) {sleep(1); die("  <script>\n  window.top.postMessage({'result': 'ERROR: user not authorized'}, '*');\n</script>");}
	  require_once('../tangoproxy/frontend.php');
	  require_once('../tangoproxy/fermi/tangoproxy_conf.php');
	  $param = NULL;
	  $token = substr($_REQUEST['pss_token'], 4);
	  if (strpos($_REQUEST['pss_token'], ',')!==false) {
		  list($var, $param) = explode(',', $token, 2);
	  }
	  else {
	    $var = $token;
	  }
	  $val = write_var($var, $param);
	  $line = date('Y-m-d H:i:s').';'.$_REQUEST['pss_username'].';'.$token.';'.PHP_EOL;
	  file_put_contents('logs.txt', $line, FILE_APPEND | LOCK_EX);
	  /*
		$db = mysqli_connect('localhost', 'hdbbrowser', 'hdbbrowser', 'hdb');
		$query = "SELECT ID FROM cdt WHERE full_name=\"$var\"";
		$res = mysqli_query($db, $query);
		$data = mysqli_fetch_assoc($res);
		if (isset($data['ID'])) {
			$id = $data['ID'];
		}
		else {
			$query = "INSERT INTO cdt (time, full_name) VALUES (NOW(), \"$var\")";
			$res = mysqli_query($db, $query);
			$id = mysqli_insert_id($db);
		}
		$query = "INSERT INTO cmd$id (timestamp, value, error) VALUES (NOW(), \"$param\", \"{$_REQUEST['pss_username']}\")";
		$res = mysqli_query($db, $query);
	  */
	  die("  <script>\n  window.top.postMessage({'result': \"$val\"}, '*');\n</script>");
  }
  if ($_REQUEST['result']==='aborted') {
    die("<html><head></head><body><script LANGUAGE='JavaScript'>close(); </script></body></html>");
  }
  if ($_REQUEST['result']!=='successful') {
    if ($_REQUEST['pss_token']<-3) die("ERROR! Login failed");
    die("Spiacente, o lo username o la password sono errati<br>Usa lo username del tuo account di posta elettronica<br>\nSe la verifica della password non dovesse funzionare o<br>\nnon si dispone di un account di posta aziendale<br>\nsi deve obbligatoriamente usare il registro cartaceo<br><br><button onClick='window.close();'>esci</script>");
  }
  // echo "token: ".$_REQUEST['pss_token']."<br>"; 
  // echo "esito: ".$_REQUEST['result']."<br>";
  // echo "utente: ".$_REQUEST['pss_username']."<br>";
  if ($_REQUEST['pss_token']==-1) $_REQUEST['pss_token'] = 'input';
  if ($_REQUEST['pss_token']==-2) $_REQUEST['pss_token'] = 'del';
  if ($_REQUEST['pss_token']==-3) $_REQUEST['pss_token'] = 'edit';
  echo " <html><head></head><body><script LANGUAGE='JavaScript'>
      var target = window.opener.postMessage ? window.opener : (window.opener.document.postMessage ? window.opener.document : undefined);
	  if(typeof target != 'undefined') target.postMessage('form_{$_REQUEST['pss_token']}', '*');
      // window.document.domain = 'elettra.eu';
      // window.opener.document.getElementById('form_{$_REQUEST['pss_token']}').submit(); 
      close();
  </script></body></html>";
?>
