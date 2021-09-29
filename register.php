<?php
	if (isset($_REQUEST['login'])) {
		die("<html><body><form method='post' action='?checklog'>username <input type='text' name='elettra_ldap_email'><br><br>password <input type='password' name='elettra_ldap_password'> <input type='submit'></form><br /></body></html>\n");
	}

	$dbtype = "pg";
	// $dbtype = "mysqli";
	if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') $dbtype = "mysqli";

	if (isset($_REQUEST['debug'])) debug($_SERVER);
	define('STATISTICS_FIRST_YEAR', 2017);

	$old_error_reporting = error_reporting(E_ALL);
	require_once("./lib/sql_interface.php");
	error_reporting($old_error_reporting);
	require_once("./register_conf.php");
	$place = array('psa', 'bo', 'sa', 'ring', 'linac', 'uh', 'linacuh', 'kgzc');


	if (strpos($_SERVER['HTTP_HOST'], 'trieste.it')!==false) {
		header('Location: http://'.strtr($_SERVER['HTTP_HOST'], array('.trieste.it'=>'.eu')).$_SERVER['REQUEST_URI']);
		// echo ('Location: '.strtr($_SERVER['HTTP_HOST'], array('.trieste.it'=>'.eu')).$_SERVER['REQUEST_URI']);
	}

	// ----------------------------------------------------------------
	// Quote variable to make safe
	function quote_smart($value, $quote="'", $null='NULL') {
		global $sql, $dbtype;
		// skip null strings
		if (strlen($value)==0) {
			return $null;
		}
		// Stripslashes
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		strtr($value, '“”`', '""'."'");
		if ($dbtype=="pg") $quote="";
		// Quote if not integer
		// if (!is_numeric($value)) {
		if (count(array_diff(array_keys(count_chars($value,1)),array_keys(count_chars("0123456789 <>=.,",1))))) {
			$value = $quote.$sql->real_escape_string($value).$quote;
		}
		return $value;
	}
/*
	if (isset($_REQUEST['testvuo'])) {
		$f = file("https://vuo.elettra.eu/pls/vuo/amm_people.show_tbadge_list_ecs");
		debug($f);
		exit(0);
	}
*/

	// ----------------------------------------------------------------
	// debug a select query
	function sql_debug($select, $from, $where) {
		echo "<p>SELECT $select FROM $from WHERE $where;</p>";
	}

	// ----------------------------------------------------------------
	// debug a variable
	function debug($var, $name='')
	{
		if ($name !== '') {
			echo "\$$name: ";
		}
		if (is_array($var)) {
			echo "<pre>"; print_r($var); echo "</pre><p>\n";
		}
		else {
			echo ($var===0? "0": $var)."<p>\n";
		}
	}

	// ----------------------------------------------------------------
	// open_db
	function open_db() {
		global $dbtype;
		// instance SQL interface class
		$sql = new SqlInterface($dbtype);
		// connect to database
		$db = $sql->sql_connect(HOST, USERNAME, PASSWORD, DB);
		// if connection has failed emit a warnig and auto-retry
		if ($db === FALSE) {
			echo "<html>\n <head>";
			echo "	<meta http-equiv='refresh' content='5;url=#'>\n";
			echo " </head>\n <body>\n	<H1>Please wait</H1>Waiting for database connection<br />\n";
			exit();
		}
		// select db
		if ($dbtype!="pg") {$sql->sql_select_db(DB, $db);}
		return $sql;
	}

	// open database
	$sql = open_db();

	$remote_ip = $_SERVER['REMOTE_ADDR']; // echo "$remote_ip<br>\n";
	// if (isset($_REQUEST['debug'])) {echo "<pre>"; print_r($_SERVER); echo "</pre>";}

	$multi_number = 12;

	$elog = $machine=='fermi'? 'felog': 'elog';

	// Prepare list of users
	$jsSearch = "	<script>\n	var entered = [".detect_entered()."];\n	var badge = {".detect_badge()."};\n	var dosimeter = {\n".detect_dosimeter()."};\n	var dosimeteredUser = [\n'".implode("',\n'", array_keys($user))."'\n];\nvar userList = [\n";
	if (time() - filemtime('./users.csv') > (7 * 86400)) {
		// echo "<!--\nn: ".time()."<br>\nf: ".filemtime('./users.csv')."<br>\n-->\n";
		$f = array_keys(findUsers());
	}
	else {
		$f = file('./users.csv');
	}
	foreach ($f as $l) {$jsSearch .= "'".trim($l)."',\n";}
	// Prepare list of hosts
	$hostFile = $_SERVER['REMOTE_ADDR'] != '127.0.0.1'? "https://vuo.elettra.eu/pls/vuo/amm_people.show_tbadge_list_ecs": './from_vuo.csv';
	$f = file($hostFile);
	$host = '';
	foreach ($f as $i=>$l) {
		$tok = explode(',', $l); 
		if (($i==0) || (strpos($tok[9],'smarrito')!==false)) continue; 
		$name = strtr(trim($tok[5], " '\""), array('"'=> "'"));
		$jsSearch .= "{$tok[2]},\n\"$name\",\n";
		$host .= "{$tok[2]}: \"$name\",\n\"$name\": \"$name\",\n";
	}
	foreach ($castellani as $i=>$name) {
		$jsSearch .= "\"$i\",\n\"$name\",\n";
		$host .= "\"$i\": \"$name\",\n\"$name\": \"$name\",\n";
	}	
	$jsSearch .= "''";
	$jsSearch .= "];\n$( function() {\n $( \".username\" ).autocomplete({source: userList, select: function( event, ui ) {changeUser('', ui.item.value);}});})\nhostList = {{$host}};	</script>\n";

	// ----------------------------------------------------------------
	// upload db data from csv file
	function upload_file($name, $_upload_dir = ".") {
		global $script;
		// $_upload_dir = dirname($_SERVER["SCRIPT_FILENAME"])."/upload/";
		$userfile_size = $_FILES["userfile"]["size"];
		$tmpfile = $_FILES["userfile"]["tmp_name"];
		// Detect Free Space on disk
		$diskfree = diskfreespace("/");
		// check quata and disk space
		if (($userfile_size > $diskfree) || ($userfile_size > 20971520)) {
			echo "Errore: disco pieno o file troppo grande (> 20 MB) <BR>";
			emit_exit();
		}
		// check if file was uploaded
		if (is_uploaded_file($tmpfile)) {
			if ($userfile_size > 0) {
				$userfile_size = $userfile_size>2048? sprintf("%5.1f kB", $userfile_size / 1024): "$userfile_size Bite";
				// echo "Caricato il file: {$_FILES["userfile"]['name']}, dimensione: $userfile_size <br>\n <p>";
			}
			// copy uploaded file to $_upload_dir
			move_uploaded_file($tmpfile, "$_upload_dir/$name");
			@chmod("$_upload_dir/$name", 0777);
			// echo "Salvato il file in: $_upload_dir/$name <p>";
		}
		else {
			echo "Non posso caricare il file<p>\n";
			switch ($filearray["error"]) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_INI_SIZE:
					echo("The uploaded file exceeds the upload_max_filesize directive (".ini_get("upload_max_filesize").") in php.ini.");
					break;
				case UPLOAD_ERR_FORM_SIZE:
					echo("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.");
					break;
				case UPLOAD_ERR_PARTIAL:
					echo("The uploaded file was only partially uploaded.");
					break;
				case UPLOAD_ERR_NO_FILE:
					echo("No file was uploaded.");
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					echo("Missing a temporary folder.");
					break;
				case UPLOAD_ERR_CANT_WRITE:
					echo("Failed to write file to disk");
					break;
				default:
					echo("Unknown File Error");
			}
			emit_exit();
		}
	}

	// ----------------------------------------------------------------
	// detect entered people
	function detect_entered() {
		global $sql, $machine;
		$entered = array();
		$data = $sql->sql_select("name", "access_$machine", "exit_time IS NULL AND token>0");
		if (!empty($data)) foreach ($data as $row) {
			$entered[] = '"'.$row['name'].'"';
		}
		return implode(',', $entered);
	}

	// ----------------------------------------------------------------
	// detect badge
	function detect_badge() {
		global $sql, $machine;
		$badge = array();
		$data = $sql->sql_select("badge_number", "access_$machine", "exit_time IS NULL AND token>0 AND badge_number>0 ORDER BY badge_number, enter_time DESC");
		if (!empty($data)) foreach ($data as $row) {
			$badge[] = "{$row['badge_number']}:1";
		}
		return implode(",\n", $badge);
	}

	// ----------------------------------------------------------------
	// detect dosimeter values
	function detect_dosimeter() {
		global $sql, $machine;
		$dosimeter = array();
		$data = $sql->sql_select("dosimeter_number, dosimeter_value, dosimeter_exitvalue, COALESCE(dosimeter_exitvalue, -1) AS myexit", "access_$machine", "token>0 AND dosimeter_number>0 ORDER BY dosimeter_number, enter_time DESC");
		$dosimeter_number = 0;
		if (!empty($data)) foreach ($data as $row) {
			if ($dosimeter_number == $row['dosimeter_number']) continue;
			$exit = $row['dosimeter_exitvalue']>0? $row['dosimeter_exitvalue']: $row['dosimeter_value'];
			$dosimeter[] = "{$row['dosimeter_number']}: [{$row['dosimeter_value']}, $exit, {$row['myexit']}]";
			$dosimeter_number = $row['dosimeter_number'];
		}
		return implode(",\n", $dosimeter);
	}

	// ----------------------------------------------------------------
	// put date in DB format
	function db_date($v) {
		$d = explode('/', $v);
		if (!isset($d[1])) return "";
		if (!isset($d[2])) $d[2] = date('Y');
		if ($d[2]<100) $d[2] += 2000;
		return sprintf("%d-%02d-%02d", $d[2], $d[1], $d[0]);
	}

	// ----------------------------------------------------------------
	// check Dosimeter
	function checkDosimeter() {
		global $sql, $machine, $dbtype;
		$year = $_REQUEST['statistics']-0;
		if (($year<STATISTICS_FIRST_YEAR) or ($year>date('Y'))) die("ERROR: Invalid year: $year");
		echo "<h2>Controllo dosimetri</h2><a href='?backoffice&statistics=$year'>esci</a><br><br>\n";
		$registerdata = $sql->sql_select("*", "access_$machine", "token>=0 AND dosimeter_number>0 AND YEAR(enter_time)=$year ORDER BY dosimeter_number, enter_time", isset($_REQUEST['debugDose'])? 1: 0);
		// dosimeter_number, dosimeter_exitvalue, dosimeter_value
		$old_dosimeter_value = $old_dosimeter = 0;
		foreach ($registerdata as $data) {
			if ($data['dosimeter_exitvalue'] < $data['dosimeter_value']) {echo " {$data['enter_time']} <a href='?backoffice&year=$year&dosimeter={$data['dosimeter_number']}'>dosimetro</a>: {$data['dosimeter_number']}, valore: {$data['dosimeter_exitvalue']} minore di: {$data['dosimeter_value']}<br>\n";}
			if ($old_dosimeter==$data['dosimeter_number'] && $old_dosimeter_value>0 && $old_dosimeter_value > $data['dosimeter_value']) {echo "{$data['enter_time']} <a href='?backoffice&year=$year&dosimeter={$data['dosimeter_number']}'>dosimetro</a>: {$data['dosimeter_number']}, valore: {$data['dosimeter_value']} minore di: $old_dosimeter_value<br>\n";}
			$old_dosimeter_value = $data['dosimeter_value'];
			$old_dosimeter = $data['dosimeter_number'];
		}
		exit();
	}

	// ----------------------------------------------------------------
	// compare function used by uasort()
	function cmp($a, $b) {
		global $sortPlace, $sortParam, $sortDirection;
		if ($a[$sortPlace][$sortParam] == $b[$sortPlace][$sortParam]) {
			return 0;
		}
		return ($a[$sortPlace][$sortParam] < $b[$sortPlace][$sortParam]) ? -1*$sortDirection : $sortDirection;
	}

	// ----------------------------------------------------------------
	// show_statistics
	function show_statistics() {
		global $sql, $machine, $dbtype, $user, $sortPlace, $sortParam, $sortDirection;
		if (!empty($_REQUEST['user']) && strpos($_REQUEST['user'],'.')===false && strpos($_REQUEST['user'],' ')!==false) {
			list($a, $b) = explode(' ', $_REQUEST['user']);
			$_REQUEST['user'] = "$b.$a";
		}
		if (!isset($_REQUEST['export'])) echo '<!DOCTYPE html>
<html lang="it">
  <head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="size=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<meta name="description" content="">
	<meta name="author" content="">
	<link rel="icon" href="http://www.elettra.eu/favicon.png"> 

	<title>Statistiche del registro di sala controllo di '.strtoupper($machine).'</title>

	<!-- jquery -->
	<link rel="stylesheet" href="./lib/jquery/jquery-ui.min.css">
	<script src="./lib/jquery/jquery.min.js" type="text/javascript"></script>
	<script src="./lib/jquery/jquery-ui.min.js" type="text/javascript"></script>

	<!-- Bootstrap CSS -->
	<link href="./lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">  
	<link href="data:text/css;charset=utf-8," data-href="./lib/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet" id="bs-theme-stylesheet">  
	<link href="./lib/bootstrap/css/docs.min.css" rel="stylesheet"> 

	<!-- Custom styles for this template -->
	<link href="./lib/bootstrap/css/theme.css" rel="stylesheet">
		';
		$year = $_REQUEST['statistics']-0;
		if (($year<STATISTICS_FIRST_YEAR) or ($year>date('Y'))) die("ERROR: Invalid year: $year");
		$prevyy = $year > STATISTICS_FIRST_YEAR? "<a href='./register.php?backoffice&statistics=".($year-1)."'>&lt;</a> ": '';
		$nextyy = $year < date('Y')? " <a href='./register.php?backoffice&statistics=".($year+1)."'>&gt;</a>": '';
		/*
		$ddata = file("https://pwma-dev.elettra.eu/misc/dosimetered.php?year=$year");
		// if (isset($_REQUEST['dosimetered'])) {echo "https://pwma-dev.elettra.eu/misc/dosimetered.php?year=$year<br>"; debug($ddata);}
		$dosimetered = array();
		foreach ($ddata as $d) {
			list($name,$category) = explode(';', trim($d));
			$dosimetered[$name] = $category;
		}
		if (isset($_REQUEST['dosimetered'])) {debug($dosimetered); exit();}
		*/
		$err_cache = '';
		$registerdata_ids = array();
		$file = file("./{$machine}_banned.csv");
		foreach ($file as $f) {
			$v = explode(',', $f);
			$banned[$v[0]] = $f;
		}
		if (isset($_REQUEST['removePlc'])) {
			$banned[$_REQUEST['removePlc']] = $_REQUEST['removePlc'].date(',Y-m-d H:i:s,').$_SERVER['REMOTE_ADDR']."\n";
			file_put_contents("./{$machine}_banned.csv", implode("",$banned));
		}
		if (isset($_REQUEST['restorePlc'])) {
			unset($banned[$_REQUEST['restorePlc']]);
			file_put_contents("./{$machine}_banned.csv", implode("",$banned));
		}
		if ($machine=='elettra') {
			foreach ($user as $k=>$u) {
				$userEntry = $k;
				if (strpos($k, '.') !== false) {
					list($i,$s) = explode('.', $k);
					$userEntry = "$s $i";
				}
				$sortedUser[$userEntry] = array('sr'=>array('t'=>0,'dose'=>0,'count'=>0),'booster'=>array('t'=>0,'dose'=>0,'count'=>0),'sa'=>array('t'=>0,'dose'=>0,'count'=>0));
			}
			$sql2 = new SqlInterface($dbtype);
			$db2 = $sql2->sql_connect(HOST2, USERNAME2, PASSWORD2, DB);
			if ($dbtype!="pg") {$sql2->sql_select_db(DB, $db2);}
			$sqlBooster = new SqlInterface($dbtype);
			$db3 = $sqlBooster->sql_connect(HOST3, USERNAME3, PASSWORD3, DB3);
			if ($dbtype!="pg") {$sqlBooster->sql_select_db(DB3, $db3);}
			$places = array('sr', 'booster', 'sa');
			$nameCondition = '';
			if (!empty($_REQUEST['user'])) {
				$n = explode('.', $_REQUEST['user']); 
				$fullnameSearched = trim($n[1].' '.$n[0]);
				$uname = strtr($_REQUEST['user'], array(' '=>'.'));
				if (strpos($uname, '.')!==false) {
					list($trash, $uname) = explode('.',$uname,2);
				}
				// $nameCondition = "(name LIKE ".quote_smart("%$uname%")." OR name LIKE '%elettra%') AND";
				$nameCondition = "(name LIKE ".quote_smart("%$uname%").") AND";
				if (strpos($uname, '{sconosciuto}') !== false) {
					$nameCondition = "(name LIKE 'osp.%' OR name LIKE 'osp\\_%' OR name LIKE 'ospite%' OR name LIKE 'ronda%') AND";
				}
				echo "<table class='table table-hover'>\n<tr><td colspan='20'><h4>{$_REQUEST['user']}</h4></td></tr>\n<tr><td colspan='6'>&nbsp;</td><td colspan='3' align='center'>&nbsp;secondi totali</td></tr>
					<tr><td>zona </td><td> DB </td><td> entrata </td><td> uscita </td><td align='right'> secondi /numeroZone </td><td align='right'> dose/numeroZone </td><td align='right'>SR</td><td align='right'>Booster</td><td align='right'>Service Area + PSA</td></tr>\n";
			}
			// check PLC
			foreach ($places as $place) {
				if ($place === 'booster') {
					$data = $sqlBooster->sql_select("db_time,name,present,position,FROM_UNIXTIME(db_time) AS t, CONCAT('{$year}_',position,'_',time_id) AS db50_key", "booster_time_$year, booster_db50_$year, enabled_user", "$nameCondition position<16 AND time_id=booster_time_$year.id AND enabled_user.id=enabled_user_id ORDER BY enabled_user_id, db_time", isset($_REQUEST['debug_plc_query'])? 1: 0);
				}
				else if ($place === 'sr') {
					$data = $sql2->sql_select("db_time,name,present,position,FROM_UNIXTIME(db_time) AS t, CONCAT('{$year}_',position,'_',time_id) AS db50_key", "{$place}_time_$year, {$place}_db50_$year, enabled_user", "$nameCondition position<16 AND time_id={$place}_time_$year.id AND enabled_user.id=enabled_user_id ORDER BY enabled_user_id,db_time", isset($_REQUEST['debug_plc_query'])? 1: 0);				
				}
				else  {
					$data = $sql2->sql_select("db_time,name,present,position,FROM_UNIXTIME(db_time) AS t, CONCAT('{$year}_',position,'_',time_id) AS db50_key", "sr_time_$year, sr_db49_$year, enabled_user", "$nameCondition position<64 AND time_id=sr_time_$year.id AND enabled_user.id=enabled_user_id ORDER BY enabled_user_id,db_time", isset($_REQUEST['debugDose'])? 1: 0);				
				}
				// if (!empty($_REQUEST['user'])) {echo "$place, ".count($data)."<br>\n"; debug($data);}
				$oldLine = array();
				foreach ($data as $line) {
					// if (!empty($_REQUEST['user'])) {echo "$place {$line['name']} $uname - ".strpos($line['name'],$uname)."<br>\n"; debug($line);}
					$oldDose = false;
					if ($line['present']=='Y') {
						if ($oldLine['present']=='Y') {$err_cache .= "ERRORE! USCITA MANCANTE {{$oldLine['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']})<br>\n"; }
						$oldLine = $line; 
						continue;
					}
					if ($line['name'] !== $oldLine['name']) {$err_cache .= "ERRORE! RIGA NON VALIDA {$line['name']} - {$oldLine['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n"; $oldLine = $line;continue;}
					if (($username = array_search($line['name'], $user)) !== false) {
						$userCond = "name=\"$username\"";
					}
					else {
						// if (strpos($line['name'], $machine)===false) {echo "ERRORE! RIGA NON VALIDA {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n"; $oldLine = $line;continue;}
						list($badge_type, $badge_number) = explode('_', strtr($line['name'],array('osp_'=>'osp.')));
						if (strpos($badge_type, 'osp')!==false) $badge_type = 'host';
						else if (strpos($badge_type, 'ronda')!==false) $badge_type = 'search';
						// else  {echo "ERRORE! RIGA NON VALIDA {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n"; $oldLine = $line; continue;}
						$userCond = "badge_type='$badge_type' AND badge_number=$badge_number";
					}
					$registerdata = $sql->sql_select("id, name, place, dosimeter_exitvalue-dosimeter_value AS dosimeter", "access_$machine", "token>=0 AND $userCond AND UNIX_TIMESTAMP(enter_time)<{$oldLine['db_time']} AND UNIX_TIMESTAMP(exit_time)>{$line['db_time']}", isset($_REQUEST['debugDose'])? 1: 0);
					if (isset($_REQUEST['debugDose'])) echo "name: ".$line['name']."<br>\n";
					if ($registerdata === false) {
						$err_cache .= "ERRORE! ACCESSO non trovato nel registro {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})\n";
						if ($key = array_search($line['name'],$user)) {
							list($i,$s) = explode('.', $key);
							$fullname = trim("$s $i");
						}
						else {
							$fullname = '{sconosciuto}';
						}
					}
					else {
						$oldDose = isset($registerdata_ids[$registerdata[0]['id']]); // $oldDose is useed for multiple access included in a single register row
						$registerdata_ids[$registerdata[0]['id']] = true;
						list($i,$s) = explode('.', $registerdata[0]['name']);
						$fullname = trim("$s $i");
					}
					if (!isset($sortedUser[$fullname])) $sortedUser[$fullname] = array('sr'=>array('t'=>0,'dose'=>0,'count'=>0),'booster'=>array('t'=>0,'dose'=>0,'count'=>0),'sa'=>array('t'=>0,'dose'=>0,'count'=>0));
					$placeArray = array_flip(explode(',',strtr($registerdata[0]['place'],array('bo'=>'booster','psa'=>'sa','ring'=>'sr'))));
					if (!isset($placeArray[$place])) {$err_cache .= "ERRORE! ACCESSO in $place non trovato nel registro, dichiarato: {$registerdata[0]['place']}, {$line['name']} - Entrata ".date('Y-m-d H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})\n"; /*$oldLine = $line; continue;*/}
					$place_count = count($placeArray);
					// $place_count = 1;
					$t = $line['db_time']-$oldLine['db_time'];
					$dose = ($registerdata !== false)? $registerdata[0]['dosimeter']: 0;
					$color = '';
					if (!isset($banned[$line['db50_key']])) {
						$sortedUser[$fullname][$place]['t'] += $t;
						if (!$oldDose) $sortedUser[$fullname][$place]['dose'] += $dose/$place_count;
						$sortedUser[$fullname][$place]['count']++;
						$butt = "<button onClick=\"window.location = './register.php?backoffice&user={$_REQUEST['user']}&statistics=$year&removePlc={$line['db50_key']}'\">Non considerare</button>";
					}
					else {
						$color = ' style="background-color: plum"';
						$butt = "<button onClick=\"window.location = './register.php?backoffice&user={$_REQUEST['user']}&statistics=$year&restorePlc={$line['db50_key']}'\">Ripristina</button>";
					}
					$plc = $fullname=='{sconosciuto}'? $line['name']: 'PLC';
					if (!empty($_REQUEST['user']) && $fullnameSearched==$fullname) {echo "<tr$color><td><a href='http://ecsproxy.elettra.eu/docs/pss/elettra.php?var=Show&startdate=".date('Y-m-d+H:i:s', $oldLine['db_time']-3701)."&stopdate=".date('Y-m-d H:i:s', $line['db_time']+1)."&plc=all'>$place</a> </td><td> $plc </td><td> ".date('Y-m-d H:i:s', $oldLine['db_time'])." </td><td> ".date('Y-m-d H:i:s', $line['db_time']).
						" </td><td align='right'> ".round($t)." /1 </td><td align='right'> $dose/$place_count </td><td align='right'>".round($sortedUser[$fullname]['sr']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['booster']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['sa']['t'])."</td><td>$butt</td></tr>\n";}
					if (isset($_REQUEST['debugSort']) && $fullname == '{sconosciuto}') {debug($line);debug($t);debug($dose);}
					if (isset($_REQUEST['check_user']) && $fullname==$_REQUEST['check_user']) echo "t: $t (".date('d/m/Y H:i:s',$line['db_time'])."-{$oldLine['db_time']}), dose: $dose, place: $place ({$registerdata[0]['place']}), place_count: $place_count<br>\n";
					if (isset($_REQUEST['debug'])) echo "{$registerdata[0]['name']} - Luogo: $place - Entrata: ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita: ".date('d/m/Y H:i:s', $line['db_time'])." - Time ".($line['db_time']-$oldLine['db_time'])." [s] - Dose: {$registerdata[0]['dosimeter']}<br>\n";
					$oldLine = $line;
				}
			}
			$not_in_plc = empty($registerdata_ids)? '': "AND id NOT IN (".implode(',', array_keys($registerdata_ids)).")";
			$rdata = $sql->sql_select("id, name, place, dosimeter_exitvalue-dosimeter_value AS dose, enter_time, exit_time, UNIX_TIMESTAMP(exit_time)-UNIX_TIMESTAMP(enter_time) AS t", "access_$machine", "token>=0 $not_in_plc AND enter_time>='$year-01-01' AND enter_time<='$year-12-31 23:59:59'", isset($_REQUEST['debug_register_query'])? 1: 0);
			$goto_page = urlencode("&user=$fullnameSearched&statistics=$year");
			// if (!empty($_REQUEST['user'])) {echo "SELECT id, name, place, dosimeter_exitvalue-dosimeter_value AS dose, enter_time, exit_time, UNIX_TIMESTAMP(exit_time)-UNIX_TIMESTAMP(enter_time) AS t FROM access_$machine WHERE token>=0 AND id NOT IN (".implode(',', array_keys($registerdata_ids)).") AND enter_time>='$year-01-01' AND enter_time<='$year-12-31 23:59:59'"; debug($rdata);}
			foreach ($rdata as $d) {
				if (empty($d['exit_time'])) {$err_cache .= "ERRORE! USCITA MANCANTE nel registro, {$d['enter_time']} {$d['name']} {$d['place']}\n"; continue;}
				$fullname = $d['name'];
				if (strpos($fullname, '.') !== false) {
					list($i,$s) = explode('.', $fullname);
					$fullname = trim("$s $i");
				}
				$placeArray = array_flip(explode(',',strtr($d['place'],array('bo'=>'booster','psa'=>'sa','ring'=>'sr'))));
				if (isset($placeArray['sr']) || isset($placeArray['booster'])) {$err_cache .= "ERRORE! ACCESSO dichiarato nel registro ma non trovato nel PLC, {$d['enter_time']} {$d['name']} {$d['place']}\n";}
				$place_count = count($placeArray);
				if (!isset($sortedUser[$fullname])) $sortedUser[$fullname] = array('sr'=>array('t'=>0,'dose'=>0,'count'=>0),'booster'=>array('t'=>0,'dose'=>0,'count'=>0),'sa'=>array('t'=>0,'dose'=>0,'count'=>0));
				foreach ($placeArray as $place=>$trash) {
					$sortedUser[$fullname][$place]['count']++;
					$sortedUser[$fullname][$place]['t'] += $d['t']/$place_count;
					$sortedUser[$fullname][$place]['dose'] += $d['dose']/$place_count;
					if (!empty($_REQUEST['user']) && $fullnameSearched==$fullname) {echo "<tr><td>$place </td><td> registro </td><td> {$d['enter_time']} </td><td> {$d['exit_time']} </td><td align='right'> ".round($d['t'])." /$place_count </td><td align='right'> {$d['dose']}/$place_count </td><td align='right'>".
						round($sortedUser[$fullname]['sr']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['booster']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['sa']['t'])."</td><td><button onClick=\"document.location='?backoffice&update&id={$d['id']}&goto_page=$goto_page'\">modifica</button></td></tr>\n";}
				}
				if (isset($_REQUEST['debug_id'])) {debug($d);debug($placeArray); debug($sortedUser[$fullname]);}
			}
			$buffer = isset($_REQUEST['export'])? '': "</head>\n<body style='padding: 10px;'>\n<h2>Elettra</h2>\n<b>$prevyy$year$nextyy</b><br>\n<a href='?backoffice&statistics=$year&checkDosimeter'>controllo dosimetri</a>&nbsp;&nbsp;&nbsp;<a href='?backoffice&statistics=$year&export=CSV'>esporta CSV</a>&nbsp;&nbsp;&nbsp;<a href='?backoffice'>Esci</a><br><textarea style='width:100%;heigth:15%'>$err_cache</textarea><br>";
			if (isset($_REQUEST['sortKey'])) {
				list($sortPlace, $sortParam) = explode('_',$_REQUEST['sortKey']);
				$sortDirection = isset($_REQUEST['sortDirection'])? ($_REQUEST['sortDirection']=='desc'? -1: 1): 1;
				uasort($sortedUser, "cmp");
			}
			else {
				(isset($_REQUEST['sortDirection']) && $_REQUEST['sortDirection']=='desc')? krsort($sortedUser) :ksort($sortedUser);
			}
			if (isset($_REQUEST['export'])) {
				$buffer .= "Cognome Nome;;;SR;;;Booster;;;Service Area + PSA\n";
				$buffer .= ";;n.accessi;t;dose;n.accessi;t;dose;n.accessi;t;dose\n";
			}
			else {
				$buffer .= "<table class='table table-hover'><tr><td colspan='1'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year\"' src='./img/blue_up.png'>&nbsp;Cognome Nome&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td colspan='3' align='center'>SR</td><td colspan='3' align='center' style='background-color: #fff0f0;'>Booster</td><td colspan='3' align='center'>Service Area + PSA</td></tr>\n";
				$buffer .= "<tr><td colspan='1'>&nbsp;</td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sr_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=booster_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=sa_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					</tr>\n";
			}
			$sum = array('sa'=>array('t'=>0,'dose'=>0,'count'=>0),'sr'=>array('t'=>0,'dose'=>0,'count'=>0),'booster'=>array('t'=>0,'dose'=>0,'count'=>0));
			$params = array('t','dose','count');
			if (isset($_REQUEST['debug_register_query'])) echo "fullnameSearched: -$fullnameSearched-<br>\n";
			foreach ($sortedUser as $fullname=>$user) {
				$oname = $fullname;
				if (strpos($fullname, ' ')!==false) {
					$n = explode(' ', $fullname);
					$oname = $n[1].'.'.$n[0];
				}
				if (isset($_REQUEST['debug_register_query'])) echo "fullname: -$fullname-<br>\n";
				if (!empty($_REQUEST['user']) && $fullnameSearched!=$fullname) continue;
				foreach ($places as $place) {
					foreach ($params as $param) {
						$sum[$place][$param] += $user[$place][$param];
					}
				}
				if (isset($_REQUEST['export'])) {
					// $buffer .= "$fullname;{$dosimetered[$oname]};"; // removed asked by GS 9/9/21
					$buffer .= "$fullname;";
					$buffer .= "{$user['sr']['count']};".displayTime($user['sr']['t']).";".($user['sr']['dose']).";";
					$buffer .= "{$user['booster']['count']};".displayTime($user['booster']['t']).";".($user['booster']['dose']).";";
					$buffer .= "{$user['sa']['count']};".displayTime($user['sa']['t']).";".($user['sa']['dose'])."\n";
				}
				else {
					// $buffer .= "<tr><td><a href='?backoffice&user=$oname&".(empty($_REQUEST['user'])? 'statistics': 'year')."=$year'>$fullname</a> </td><td> {$dosimetered[$oname]}</td>
					$buffer .= "<tr><td><a href='?backoffice&user=$oname&".(empty($_REQUEST['user'])? 'statistics': 'year')."=$year'>$fullname</a> </td>
						<td align='right'>{$user['sr']['count']}</td><td align='right'>".displayTime($user['sr']['t'])."</td><td align='right'>".($user['sr']['dose'])."</td>
						<td align='right' style='background-color: #fff0f0;'>{$user['booster']['count']}</td><td align='right' style='background-color: #fff0f0;'>".displayTime($user['booster']['t'])."</td><td align='right' style='background-color: #fff0f0;'>".($user['booster']['dose'])."</td>
						<td align='right'>{$user['sa']['count']}</td><td align='right'>".displayTime($user['sa']['t'])."</td><td align='right'>{$user['sa']['dose']}</td></tr>\n";
				}
			}
			if (empty($_REQUEST['user'])) {
				if (isset($_REQUEST['export'])) {
					$buffer .= "TOTALE;;";
					$buffer .= "{$sum['sr']['count']};".displayTime($sum['sr']['t']).";".($sum['sr']['dose']).";";
					$buffer .= "{$sum['booster']['count']};".displayTime($sum['booster']['t']).";".($sum['booster']['dose']).";";
					$buffer .= "{$sum['sa']['count']};".displayTime($sum['sa']['t']).";".($sum['sa']['dose'])."\n";
				}
				else {
					$buffer .= "<tr><td colspan='1'>TOTALE</td>
						<td align='right'>{$sum['sr']['count']}</td><td align='right'>".displayTime($sum['sr']['t'])."</td><td align='right'>".($sum['sr']['dose'])."</td>
						<td align='right'>{$sum['booster']['count']}</td><td align='right'>".displayTime($sum['booster']['t'])."</td><td align='right'>".($sum['booster']['dose'])."</td>
						<td align='right'>{$sum['sa']['count']}</td><td align='right'>".displayTime($sum['sa']['t'])."</td><td align='right'>{$sum['sa']['dose']}</td></tr>\n</table>\n";
				}
			}
		}
		if ($machine=='fermi') {
			foreach ($user as $k=>$u) {
				list($i,$s) = explode('.', $k);
				$sortedUser[trim("$s $i")] = array('linac'=>array('t'=>0,'dose'=>0,'count'=>0),'undulator'=>array('t'=>0,'dose'=>0,'count'=>0),'kgzc'=>array('t'=>0,'dose'=>0,'count'=>0));
			}
			$places = array('linac', 'undulator', 'kgzc');
			if (!empty($_REQUEST['user'])) {
				$n = explode('.', $_REQUEST['user']); 
				$fullnameSearched = trim($n[1].' '.$n[0]);
				list($trash, $uname) = explode('.',strtr($_REQUEST['user'], array(' '=>'.')),2);
				$nameCondition = "(name LIKE ".quote_smart("%$uname%")." OR name LIKE '%fermi%') AND";
				if (strpos($uname, '{sconosciuto}') !== false) {
					$nameCondition = "(name LIKE 'osp.%' OR name LIKE 'osp\\_%' OR name LIKE 'ospite%' OR name LIKE 'ronda%') AND";
				}
				echo "<table class='table table-hover'>\n<tr><td colspan='20'><h4>{$_REQUEST['user']}</h4></td></tr>\n<tr><td colspan='6'>&nbsp;</td><td colspan='3' align='center'>&nbsp;secondi totali</td></tr>
					<tr><td>zona </td><td> DB </td><td> entrata </td><td> uscita </td><td align='right'> secondi /numeroZone </td><td align='right'> dose/numeroZone </td><td align='right'>Linac</td><td align='right'>Sala Ondulatori</td><td align='right'>KGZC</td><td>&nbsp;</td></tr>\n";
			}
			// check PLC
			foreach ($places as $place) {
				if ($place == 'kgzc') continue;
				$data = $sql->sql_select("db_time,name,present,FROM_UNIXTIME(db_time) AS t, CONCAT('{$year}_',position,'_',time_id) AS db50_key", "{$place}_time_$year, {$place}_db50_$year, enabled_user", "position<16 AND time_id={$place}_time_$year.id AND enabled_user.id=enabled_user_id ORDER BY enabled_user_id,db_time", isset($_REQUEST['debug_plc_query'])? 1: 0);
				$oldLine = array();
				foreach ($data as $line) {
					$oldDose = false;
					if ($line['present']=='Y') {
						// if (isset($banned[$line['db50_key']])) echo "---------------: {$line['db50_key']}<br>\n";
						if ($oldLine['present']=='Y') {$err_cache .= "ERRORE! USCITA MANCANTE {{$oldLine['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']})<br>\n"; }
						$oldLine = $line; 
						continue;
					}
					if ($line['name'] !== $oldLine['name']) {$err_cache .= "ERRORE! RIGA NON VALIDA {$line['name']} - {$oldLine['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n"; $oldLine = $line;continue;}
					if (($username = array_search($line['name'], $user)) !== false) {
						$userCond = "name=\"$username\"";
					}
					else {
						// if (strpos($line['name'], $machine)===false) {$err_cache .= "ERRORE! RIGA NON VALIDA {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n"; }
						list($badge_type, $badge_number) = explode($machine, $line['name']);
						if (strpos($badge_type, 'osp')!==false) $badge_type = 'host';
						else if (strpos($badge_type, 'ronda')!==false) $badge_type = 'search';
						// else  {$err_cache .= "ERRORE! RIGA NON VALIDA {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})<br>\n";}
						$userCond = "badge_type='$badge_type' AND badge_number=$badge_number";
					}
					$registerdata = $sql->sql_select("id, name, place, dosimeter_exitvalue-dosimeter_value AS dosimeter", "access_$machine", "token>=0 AND $userCond AND UNIX_TIMESTAMP(enter_time)<{$oldLine['db_time']} AND UNIX_TIMESTAMP(exit_time)>{$line['db_time']};");
					if ($registerdata === false) {
						$err_cache .= "ERRORE! ACCESSO non trovato nel registro {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})\n";
						if ($key = array_search($line['name'],$user)) {
							list($i,$s) = explode('.', $key);
							$fullname = trim("$s $i");
						}
						else {
							$fullname = '{sconosciuto}';
						}
					}
					else {
						$oldDose = isset($registerdata_ids[$registerdata[0]['id']]); // $oldDose is useed for multiple access included in a single register row
						$registerdata_ids[$registerdata[0]['id']] = true;
						list($i,$s) = explode('.', $registerdata[0]['name']);
						$fullname = trim("$s $i");
					}
					if (!isset($sortedUser[$fullname])) $sortedUser[$fullname] = array('linac'=>array('t'=>0,'dose'=>0,'count'=>0),'undulator'=>array('t'=>0,'dose'=>0,'count'=>0),'kgzc'=>array('t'=>0,'dose'=>0,'count'=>0));
					$placeArray = array_flip(explode(',',$registerdata[0]['place']));
					if (!isset($placeArray[$place])) {$err_cache .= "ERRORE! ACCESSO in $place non trovato nel registro, dichiarato: {$registerdata[0]['place']}, {$line['name']} - Entrata ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita ".date('d/m/Y H:i:s', $line['db_time'])." ({$line['db_time']})\n"; /*$oldLine = $line; continue;*/}
					$place_count = count($placeArray);
					// if (isset($banned[$line['db50_key']])) echo "---------------: {$line['db50_key']}<br>\n";
					// $place_count = 1;
					$t = $line['db_time']-$oldLine['db_time'];
					$dose = ($registerdata !== false)? $registerdata[0]['dosimeter']: 0;
					$color = '';
					if (!isset($banned[$line['db50_key']])) {
						$sortedUser[$fullname][$place]['t'] += $t;
						if (!$oldDose) $sortedUser[$fullname][$place]['dose'] += $dose/$place_count;
						$sortedUser[$fullname][$place]['count']++;
						$butt = "<button onClick=\"window.location = './register.php?backoffice&user={$_REQUEST['user']}&statistics=$year&removePlc={$line['db50_key']}'\">Non considerare</button>";
					}
					else {
						$color = ' style="background-color: plum"';
						$butt = "<button onClick=\"window.location = './register.php?backoffice&user={$_REQUEST['user']}&statistics=$year&restorePlc={$line['db50_key']}'\">Ripristina</button>";
					}
					if (!empty($_REQUEST['user']) && $fullnameSearched==$fullname) {echo "<tr$color><td><a href='http://fcsproxy.elettra.eu/docs/pss/fermi.php?var=Show&startdate=".date('Y-m-d+H:i:s', $oldLine['db_time']-3701)."&stopdate=".date('Y-m-d H:i:s', $line['db_time']+1)."&plc=all'>$place</a> </td><td> PLC </td><td> ".date('Y-m-d H:i:s', $oldLine['db_time'])." </td><td> ".date('Y-m-d H:i:s', $line['db_time']).
						" </td><td align='right'> ".round($t)." /1 </td><td align='right'> $dose/$place_count </td><td align='right'>".round($sortedUser[$fullname]['linac']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['undulator']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['kgzc']['t'])."</td><td>$butt</td></tr>\n";}
					if (isset($_REQUEST['debugSort']) && $fullname == '{sconosciuto}') {debug($line);debug($t);debug($dose);}
					if (isset($_REQUEST['check_user']) && $fullname==$_REQUEST['check_user']) echo "t: $t (".date('Y-m-d H:i:s',$line['db_time'])."-{$oldLine['db_time']}), dose: $dose, place: $place ({$registerdata[0]['place']}), place_count: $place_count<br>\n";
					if (isset($_REQUEST['debug'])) echo "{$registerdata[0]['name']} - Luogo: $place - Entrata: ".date('d/m/Y H:i:s', $oldLine['db_time'])." ({$oldLine['db_time']}) - Uscita: ".date('d/m/Y H:i:s', $line['db_time'])." - Time ".($line['db_time']-$oldLine['db_time'])." [s] - Dose: {$registerdata[0]['dosimeter']}<br>\n";
					$oldLine = $line;
				}
			}
			$not_in_plc = empty($registerdata_ids)? '': "AND id NOT IN (".implode(',', array_keys($registerdata_ids)).")";
			$rdata = $sql->sql_select("id, name, place, dosimeter_exitvalue-dosimeter_value AS dose, enter_time, exit_time, UNIX_TIMESTAMP(exit_time)-UNIX_TIMESTAMP(enter_time) AS t", "access_$machine", "token>=0 $not_in_plc AND enter_time>='$year-01-01' AND enter_time<='$year-12-31 23:59:59'");
			$goto_page = urlencode("&user=$fullnameSearched&statistics=$year");
			foreach ($rdata as $d) {
				if (empty($d['exit_time'])) {$err_cache .= "ERRORE! USCITA MANCANTE nel registro, {$d['enter_time']} {$d['name']} {$d['place']}\n"; continue;}
				list($i,$s) = explode('.', $d['name']);
				$fullname = trim("$s $i");
				$placeArray = array_flip(explode(',',strtr($d['place'],array('uh'=>'undulator'))));
				if (isset($placeArray['linac']) || isset($placeArray['undulator'])) {$err_cache .= "ERRORE! ACCESSO dichiarato nel registro ma non trovato nel PLC, {$d['enter_time']} {$d['name']} {$d['place']}\n";}
				$place_count = count($placeArray);
				if (!isset($sortedUser[$fullname])) $sortedUser[$fullname] = array('linac'=>array('t'=>0,'dose'=>0,'count'=>0),'undulator'=>array('t'=>0,'dose'=>0,'count'=>0),'kgzc'=>array('t'=>0,'dose'=>0,'count'=>0));
				foreach ($placeArray as $place=>$trash) {
					$sortedUser[$fullname][$place]['count']++;
					$sortedUser[$fullname][$place]['t'] += $d['t']/$place_count;
					$sortedUser[$fullname][$place]['dose'] += $d['dose']/$place_count;
					if (!empty($_REQUEST['user']) && $fullnameSearched==$fullname) {echo "<tr><td>$place </td><td> registro </td><td> {$d['enter_time']} </td><td> {$d['exit_time']} </td><td align='right'> ".round($d['t'])." /$place_count </td><td align='right'> {$d['dose']}/$place_count </td><td align='right'>".
						round($sortedUser[$fullname]['linac']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['undulator']['t'])."</td><td align='right'>".round($sortedUser[$fullname]['kgzc']['t'])."</td><td><button onClick=\"document.location='?backoffice&update&id={$d['id']}&goto_page=$goto_page'\">modifica</button></td></tr>\n";}
				}
				if (isset($_REQUEST['debug_id'])) {
					debug($d);debug($place);
				}
			}
			$buffer = isset($_REQUEST['export'])? '': "</head>\n<body style='padding: 10px;'>\n<h2>FERMI</h2>\n<b>$prevyy$year$nextyy</b><br>\n<a href='?backoffice&statistics=$year&checkDosimeter'>controllo dosimetri</a>&nbsp;&nbsp;&nbsp;<a href='?backoffice&statistics=$year&export=CSV'>esporta CSV</a>&nbsp;&nbsp;&nbsp;<a href='?backoffice'>Esci</a><br><textarea style='width:100%;heigth:15%'>$err_cache</textarea><br>";
			// debug($sortedUser); echo "<hr>\n";
			if (isset($_REQUEST['sortKey'])) {
				list($sortPlace, $sortParam) = explode('_',$_REQUEST['sortKey']);
				$sortDirection = isset($_REQUEST['sortDirection'])? ($_REQUEST['sortDirection']=='desc'? -1: 1): 1;
				uasort($sortedUser, "cmp");
			}
			else {
				(isset($_REQUEST['sortDirection']) && $_REQUEST['sortDirection']=='desc')? krsort($sortedUser) :ksort($sortedUser);
			}
			if (isset($_REQUEST['export'])) {
				$buffer .= "Cognome Nome;;;Linac;;;Sala Ondulatori;;;Sala Klystron\n";
				$buffer .= ";;n.accessi;t;dose;n.accessi;t;dose;n.accessi;t;dose\n";
			}
			else {
				$buffer .= "<table class='table table-hover'><tr><td colspan='1'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year\"' src='./img/blue_up.png'>&nbsp;Cognome Nome&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortDirection=desc\"' src='./img/blue_down.png'></td>
				<td colspan='3' align='center'>Linac</td><td colspan='3' align='center' style='background-color: #fff0f0;'>Sala Ondulatori</td><td colspan='3' align='center'>KGZC</td></tr>\n";
				$buffer .= "<tr><td colspan='1'>&nbsp;</td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=linac_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right' style='background-color: #fff0f0;'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=undulator_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_count\"' src='./img/blue_up.png'>&nbsp;n.accessi&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_count&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_t\"' src='./img/blue_up.png'>&nbsp;&nbsp;t&nbsp;&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_t&sortDirection=desc\"' src='./img/blue_down.png'></td>
					<td align='right'><input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_dose\"' src='./img/blue_up.png'>&nbsp;dose&nbsp;<input type='image' onClick='window.location=\"./register.php?backoffice&statistics=$year&sortKey=kgzc_dose&sortDirection=desc\"' src='./img/blue_down.png'></td>
					</tr>\n";
			}
			$sum = array('linac'=>array('t'=>0,'dose'=>0,'count'=>0),'undulator'=>array('t'=>0,'dose'=>0,'count'=>0),'kgsp'=>array('t'=>0,'dose'=>0,'count'=>0));
			$params = array('t','dose','count');
			foreach ($sortedUser as $fullname=>$user) {
				$oname = $fullname;
				if (strpos($fullname, ' ')!==false) {
					$n = explode(' ', $fullname);
					$oname = $n[1].'.'.$n[0];
				}
				if (!empty($_REQUEST['user']) && $fullnameSearched!=$fullname) continue;
				foreach ($places as $place) {
					foreach ($params as $param) {
						$sum[$place][$param] += $user[$place][$param];
					}
				}
				if (isset($_REQUEST['export'])) {
					// $buffer .= "$fullname;{$dosimetered[$oname]};";
					$buffer .= "$fullname;";
					$buffer .= "{$user['linac']['count']};".displayTime($user['linac']['t']).";".($user['linac']['dose']).";";
					$buffer .= "{$user['undulator']['count']};".displayTime($user['undulator']['t']).";".($user['undulator']['dose']).";";
					$buffer .= "{$user['kgzc']['count']};".displayTime($user['kgzc']['t']).";".($user['kgzc']['dose'])."\n";
				}
				else {
					// $buffer .= "<tr><td><a href='?backoffice&user=$oname&".(empty($_REQUEST['user'])? 'statistics': 'year')."=$year'>$fullname</a> </td><td> {$dosimetered[$oname]}</td>
					$buffer .= "<tr><td><a href='?backoffice&user=$oname&".(empty($_REQUEST['user'])? 'statistics': 'year')."=$year'>$fullname</a> </td>
						<td align='right'>{$user['linac']['count']}</td><td align='right'>".displayTime($user['linac']['t'])."</td><td align='right'>".($user['linac']['dose'])."</td>
						<td align='right' style='background-color: #fff0f0;'>{$user['undulator']['count']}</td><td align='right' style='background-color: #fff0f0;'>".displayTime($user['undulator']['t'])."</td><td align='right' style='background-color: #fff0f0;'>".($user['undulator']['dose'])."</td>
						<td align='right'>{$user['kgzc']['count']}</td><td align='right'>".displayTime($user['kgzc']['t'])."</td><td align='right'>{$user['kgzc']['dose']}</td></tr>\n";
				}
			}
			if (empty($_REQUEST['user'])) {
				if (isset($_REQUEST['export'])) {
					$buffer .= "TOTALE;;";
					$buffer .= "{$sum['linac']['count']};".displayTime($sum['linac']['t']).";".($sum['linac']['dose']).";";
					$buffer .= "{$sum['undulator']['count']};".displayTime($sum['undulator']['t']).";".($sum['undulator']['dose']).";";
					$buffer .= "{$sum['kgzc']['count']};".displayTime($sum['kgzc']['t']).";".($sum['kgzc']['dose'])."\n";
				}
				else {
					$buffer .= "<tr><td colspan='1'>TOTALE</td>
						<td align='right'>{$sum['linac']['count']}</td><td align='right'>".displayTime($sum['linac']['t'])."</td><td align='right'>".($sum['linac']['dose'])."</td>
						<td align='right'>{$sum['undulator']['count']}</td><td align='right'>".displayTime($sum['undulator']['t'])."</td><td align='right'>".($sum['undulator']['dose'])."</td>
						<td align='right'>{$sum['kgzc']['count']}</td><td align='right'>".displayTime($sum['kgzc']['t'])."</td><td align='right'>{$sum['kgzc']['dose']}</td></tr>\n</table>\n";
				}
			}
		}
		if (isset($_REQUEST['export'])) {
			header("Content-Disposition: attachment; filename=pss.csv");
			header("Content-Type: application/x-csv");
			header("Content-Length: ".strlen($buffer));
		}
		echo $buffer;
		exit(0);
	}

	// ----------------------------------------------------------------
	// displayTime
	function displayTime($t) {
		return floor($t/3600).':'.sprintf('%02d',($t/60)%60).':'.sprintf('%02d',($t+0.5)%60);
	}

	// ----------------------------------------------------------------
	// load_csv
	function load_csv() {
		global $sql, $machine, $dbtype, $user_fromfile;
		$filename = 'tmp.txt';
		upload_file($filename);
		$f = file($filename);
		$keys = $data = $name_array = array();
		foreach($f as $n=>$line) {
			$l = explode(';', $line);
			foreach($l as $i=>$col) {
				if ($n==0) {
					$keys[$i] = $col;
				}
				else {
					$data[$keys[$i]] = $col;
				}
			}
			if ($n>0) {
				$date = db_date($data['Data_ingresso']);
				$enter_time = "$date ".strtr($data['Ora_entrata'],array('.'=>':')).":00";
				$exit_time = "$date ".strtr($data['Ora_uscita'],array('.'=>':')).":00";
				// if (!isset($name[$data['Nominativo']])) $name[$data['Nominativo']] = findUsers("*".$data['Nominativo']);
				$name = isset($user_fromfile[$data['Nominativo']])? $user_fromfile[$data['Nominativo']]: $data['Nominativo'];
				$placeFermi = ($data['Linac']>0)? (($data['Ondulatori']>0)? "Linac + Sala Ond.": "Linac"): (($data['Ondulatori']>0)? "Sala Ondulatori": "Klystron");
				$badge_type = $data['Badge_personale']>0? "Personale": "Ospite";
				$badge_number = $data['Badge_ospite']>0? strtr($data['Badge_ospite'], array('?'=>'')): 'NULL';
				$dosimeter_type = $data['Dosimetro_personale']>0? "Personale": "Ospite";
				$dosimeter_number = $data['Dosimetro_ospite']>0? $data['Dosimetro_ospite']: 'NULL';
				$dosimeter_value = $data['Lettura_dosimetro_in_entrata']>0? $data['Lettura_dosimetro_in_entrata']: 'NULL';
				$dosimeter_exitvalue = $data['Lettura_dosimetro_in_uscita']>0? $data['Lettura_dosimetro_in_uscita']: 'NULL';
				$note = strlen($data['Azienda'])>0? "'azienda: ".strtr($data['Azienda'],array("'"=>'','"'=>''))."'": 'NULL';
				echo "INSERT INTO access_{$machine} 
					(token,enter_time,exit_time,name,signed_by,place,badge_type,badge_number,dosimeter_type,dosimeter_number,dosimeter_value,dosimeter_exitvalue, note) VALUES 
					(0,'$enter_time','$exit_time',\"$name\",'caricato da file','$placeFermi',$badge_type,$badge_number,$dosimeter_type,$dosimeter_number,$dosimeter_value,$dosimeter_exitvalue);<br>\n";
			}
		}
		/*
		foreach ($name as $k=>$v) {
			$val = implode(',', array_keys($v));
			echo "'$k'=>'$val',";
		}
		*/
		exit();
	}

	// ----------------------------------------------------------------
	// delete using backoffice
	function delete_backoffice() {
		global $sql, $machine, $dbtype;
		$query = "UPDATE access_$machine SET token=-1, exitnote=CONCAT(exitnote,' delete_backoffice()') WHERE id=".quote_smart($_REQUEST['id']);
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES (".quote_smart($_REQUEST['id']).",NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		header("location: ./register.php?machine=$machine&backoffice");
		exit();
	}

	// ----------------------------------------------------------------
	// insert using backoffice
	function insert_backoffice() {
		global $sql, $machine, $dbtype, $place;
		if (strpos($_REQUEST['name'],'@')!==false) {list($_REQUEST['name'],$trash)=explode('@',$_REQUEST['name'],2);}
		$placeArray = array();
		foreach ($place as $p) {
			if (isset($_REQUEST["place_$p"])) $placeArray[] = $p;
		}
		$param = array(
			'token' => rand(0, 1000000000),
			'enter_time' => ($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['start']),"'").":00'",
			'exit_time' => ($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['stop']),"'").":00'",
			'name' => quote_smart(strtolower($_REQUEST['name'])),
			'place' => quote_smart(implode(',', $placeArray)),
			'badge_type' => quote_smart($_REQUEST['badge']),
			'dosimeter_type' => quote_smart($_REQUEST['dosimeter']),
			'exitnote' => quote_smart($_REQUEST['exitnote'].' insert_backoffice()')
		);
		if ($_REQUEST['badge']!='personal') {$param['badge_number'] = quote_smart($_REQUEST['badge_id']);}
		if ($_REQUEST['dosimeter']!='personal') {$param['dosimeter_number'] = quote_smart($_REQUEST['dosimeter_id']);$param['dosimeter_value'] = quote_smart($_REQUEST['dosimeter_value']);$param['dosimeter_exitvalue'] = quote_smart($_REQUEST['dosimeter_exitvalue']);}
		if (strlen($_REQUEST['note'])) {$param['note'] = quote_smart($_REQUEST['note']);}
		$query = "INSERT INTO access_$machine (".implode(',', array_keys($param)).") VALUES (".implode(',', $param).")";
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$id = $sql->last_insert_id();
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		header("location: ./register.php?machine=$machine&backoffice");
		exit();
	}

	// ----------------------------------------------------------------
	// update using backoffice
	function update_backoffice() {
		global $sql, $machine, $dbtype, $place;
		$template = explode('<!--item-->', file_get_contents("register_backoffice_$machine.html"));
		$data = $sql->sql_select("*", "access_$machine", "id=".quote_smart($_REQUEST['id']));
		if (!$data) {sql_debug("*", "access_$machine", "id=".quote_smart($_REQUEST['id'])); echo ";<br>\n".$sql->sql_error(); exit();}
		$d = $data[0];
		$replace["name='start'"] = "name='start' value='".substr($d['enter_time'], 0, 16)."'";
		$replace["name='stop'"] = "name='stop' value='".substr($d['exit_time'], 0, 16)."'";
		$replace["name='name'"] = "name='name' value=\"{$d['name']}\"";
		$replace["name='exitnote'"] = "name='exitnote' value=\"{$d['exitnote']}\"";
		$placeArray = explode(',', strtr($d['place'],array('linacuh'=>'linac,uh')));
		foreach ($place as $p) {
			$selected = in_array($p, $placeArray)? ' checked': ''; 
			$replace["<!--place_{$p}-->"] = $selected;
		}
		$replace['<!--badge_personal-->'] = $d['badge_type']=='personal'? ' selected': '';
		$replace['<!--badge_host-->'] = $d['badge_type']=='host'? ' selected': '';
		$replace['<!--badge_search-->'] = $d['badge_type']=='search'? ' selected': '';
		$replace['<!--badge_id-->'] = $d['badge_number']>0? $d['badge_number']: '';
		if ($d['badge_number']>0) {
			$replace["<span id='badge_number' style='display:none;'>"] = "<span id='badge_number'>";
		}
		$replace['<!--dosimeter_personal-->'] = $d['dosimeter_type']=='personal'? ' selected': '';
		$replace['<!--dosimeter_host-->'] = $d['dosimeter_type']=='host'? ' selected': '';
		$replace['<!--dosimeter_personal_host-->'] = $d['dosimeter_type']=='personal_host'? ' selected': '';
		$replace['<!--dosimeter_id-->'] = $d['dosimeter_number']>0? $d['dosimeter_number']: '';
		if ($d['dosimeter_number']>0) {
			$replace["<span id='dosimeter_number' style='display:none;'>"] = "<span id='dosimeter_number'>";
			$replace["<span id='dosimeter_value' style='display:none;'>"] = "<span id='dosimeter_value'>";
		}
		$replace['<!--dosimeter_value-->'] = $d['dosimeter_value']>0? $d['dosimeter_value']: '';
		$replace['<!--dosimeter_exitvalue-->'] = $d['dosimeter_exitvalue']>0? $d['dosimeter_exitvalue']: '';
		$replace['<!--token-->'] = $d['token']=='-1'? 'cancellato': '';
		$replace['changeElement'] = 'console.log';
		// $replace["<input type='submit' name='insert' value='salva'"] = "<input type='submit' name='update_save' value='modifica' onClick=\"validate_backoffice('$machine');return false;\">&nbsp;<input type='submit' name='delete' value='elimina'>&nbsp;<input type='submit' name='exit' value='esci'><input type='hidden' name='id' value='{$d['id']}'";
		$replace["<input type='submit' name='insert' value='salva'"] = "<input type='hidden' name='goto_page' value='{$_REQUEST['goto_page']}'><input type='submit' name='update_save' value='modifica' onClick=\"validate_backoffice('$machine');\">&nbsp;<input type='submit' name='delete' value='elimina'>&nbsp;<input type='submit' name='exit' value='esci'><input type='hidden' name='id' value='{$d['id']}'";
		echo strtr($template[0], $replace)."{$template[2]}<br><br><br><br><br>";
		exit();
	}

	// ----------------------------------------------------------------
	// save updates using backoffice
	function update_save_backoffice() {
		global $sql, $machine, $dbtype, $place;
		if (strpos($_REQUEST['name'],'@')!==false) {list($_REQUEST['name'],$trash)=explode('@',$_REQUEST['name'],2);}
		$placeArray = array();
		foreach ($place as $p) {
			if (isset($_REQUEST["place_$p"])) $placeArray[] = $p;
		}
		$param = array(
			'enter_time='.($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['start']),"'").":00'",
			'name='.quote_smart(strtolower($_REQUEST['name'])),
			'place='.quote_smart(implode(',', $placeArray)),
			'badge_type='.quote_smart($_REQUEST['badge']),
			'dosimeter_type='.quote_smart($_REQUEST['dosimeter'])
		);
		if (strlen($_REQUEST['stop'])) {$param[] = 'exit_time='.($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['stop']),"'").":00'";}
		if ($_REQUEST['badge']!='personal') {$param[] = 'badge_number='.quote_smart($_REQUEST['badge_id']);}
		if ($_REQUEST['dosimeter']!='personal') {$param[] = 'dosimeter_number='.quote_smart($_REQUEST['dosimeter_id']);$param[] = 'dosimeter_value='.quote_smart($_REQUEST['dosimeter_value']);$param[] = 'dosimeter_exitvalue='.quote_smart($_REQUEST['dosimeter_exitvalue']);}
		if (strlen($_REQUEST['note'])) {$param[] = 'note='.quote_smart($_REQUEST['note']);}
		$query = "UPDATE access_$machine SET ".implode(',', $param)." WHERE id=".quote_smart($_REQUEST['id']);
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES (".quote_smart($_REQUEST['id']).",NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		header("location: ./register.php?machine=$machine&backoffice".$_REQUEST['goto_page']);
		exit();
	}

	if (in_array($remote_ip, $master_ip)) {
		if (isset($_REQUEST['debuglog'])) {
			$data = $sql->sql_select("*", "access_{$machine}_log", "1");
			echo "\n$machine\n<pre>\n";
			print_r($data);
			echo "\n</pre>\n";
		}
	}
	if (in_array($remote_ip, $master_ip+$backoffice_ip)) {
		if (isset($_REQUEST['backoffice'])) {
			$goto_page = '';
			if (isset($_REQUEST['checkDosimeter'])) {
				checkDosimeter();
			}
			if (isset($_REQUEST['statistics'])) {
				show_statistics();
			}
			if (isset($_REQUEST['load_csv'])) {
				load_csv();
			}
			if (isset($_REQUEST['delete'])) {
				delete_backoffice();
			}
			if (isset($_REQUEST['insert'])) {
				insert_backoffice();
			}
			if (isset($_REQUEST['update'])) {
				update_backoffice();
			}
			if (isset($_REQUEST['update_save'])) {
				update_save_backoffice();
			}
			$year = isset($_REQUEST['year'])? $_REQUEST['year']-0: date('Y');
			$form = "<form class='form-inline' enctype='multipart/form-data' action='?backoffice&load_csv' method='post'><table><tr><td>dati storici&nbsp;&nbsp;</td><td><input id='f' type='file' name='userfile' length='40'></td>
			<td><input type='submit' name='download' value='Carica file CSV'></td><td>&nbsp;&nbsp;&nbsp;
			<a href='?backoffice&statistics=$year'>statistiche</a>&nbsp;&nbsp;&nbsp;
			<a href='?backoffice&anomalies&year=$year'>anomalie</a>&nbsp;&nbsp;&nbsp;
			<a href='?backoffice&database&year=$year'>storico operazioni</a></td></tr></table></form><br>";
			$err_msg = (isset($_REQUEST['show_details']) || (isset($_REQUEST['anomalies']) && !isset($_REQUEST['user']) && !isset($_REQUEST['dosimeter'])))? show_anomalies(date('z')+1): '';
			$template = explode('<!--item-->', file_get_contents("register_backoffice_$machine.html"));
			$cond = '1=1';
			$prevyy = $year > 2012? "<a href='./register.php?backoffice&year=".($year-1)."'>&lt;</a> ": '';
			$nextyy = $year < date('Y')? " <a href='./register.php?backoffice&year=".($year+1)."'>&gt;</a>": '';
			$form = "<b>$prevyy$year$nextyy</b>$form";
			if (isset($_REQUEST['user'])) {$usr = explode('.', quote_smart($_REQUEST['user'], '')); $cond = "(name LIKE '%".quote_smart($_REQUEST['user'], '')."%' OR name LIKE '%{$usr[1]}_{$usr[0]}%')"; $goto_page .= '&user='.$_REQUEST['user'];}
			/*if (isset($_REQUEST['year']))*/ {$cond .= " AND (YEAR(enter_time) = $year OR YEAR(exit_time) = $year)"; $goto_page .= '&year='.$year;}
			if (isset($_REQUEST['dosimeter'])) {$cond .= " AND dosimeter_number = ".quote_smart($_REQUEST['dosimeter']); $goto_page .= '&dosimeter='.$_REQUEST['dosimeter'];}
			$data = $sql->sql_select("*", "access_$machine", "$cond ORDER BY enter_time DESC", isset($_REQUEST['mainquery_debug'])? 1: 0);
			if (!$data) {sql_debug("*", "access_$machine", "$cond ORDER BY enter_time DESC"); echo ";<br>\n".$sql->sql_error(); exit();}
			$items = '';
			foreach ($data as $row) {
				foreach ($row as $k=>$v) $replace["<!--$k-->"] = $v;
				$replace['<!--start-->'] = substr($replace['<!--enter_time-->'], 0, 16);
				$replace['<!--stop-->'] = substr($replace['<!--exit_time-->'], 0, 16).(strlen($row['exitsigned_by'])? "<br>".$row['exitsigned_by']: '');
				$replace['<!--place_psa-->'] = strtr($replace['<!--place-->'], array('psa'=>'PSA','booster'=>'Booster','sa'=>'Service Area','ring'=>'Storage Ring','linac'=>'Linac','uh'=>'Sala Ondulatori','linacuh'=>'Linac + Sala Ond.','kgzc'=>"KG zone controllate"));
				$replace['<!--badge-->'] = strtr($replace['<!--badge_type-->'], array('personal'=>'Personale','host'=>'Ospite '.$replace['<!--badge_number-->'],'search'=>'Ronda '.$replace['<!--badge_number-->']));
				$replace['<!--dosimeter-->'] = strtr($replace['<!--dosimeter_type-->'], array('personal'=>'Personale','host'=>'Ospite '.$replace['<!--dosimeter_number-->'],'personal_host'=>'Personale + Ospite '.$replace['<!--dosimeter_number-->']));
				$replace['<!--dosimeter_value-->'] = $replace['<!--dosimeter_value-->']=='0'? '': 'IN: '.$replace['<!--dosimeter_value-->'].($replace['<!--dosimeter_exitvalue-->']=='0'? '': ', OUT: '.$replace['<!--dosimeter_exitvalue-->']);
				$replace['<!--token-->'] = ($replace['<!--token-->']=='-1'? 'cancellato': '')."<input type='hidden' name='goto_page' value='$goto_page'>"; // $replace['<!--token-->'];
				$items .= strtr($template[1], $replace);
			}
			if (isset($_REQUEST['anomalies'])) {
				echo "$err_msg";
			}
			if (!isset($_REQUEST['database']) && !isset($_REQUEST['anomalies'])) {
				$replace = array("<h1>FERMI</h1>"=>"<h1>FERMI</h1>$form$err_msg","<h1>ELETTRA</h1>"=>"<h1>ELETTRA</h1>$form$err_msg", '<!--badge_personal-->'=>'', '<!--badge_host-->'=>'', '<!--badge_search-->'=>'', '<!--badge_id-->'=>'', '<!--dosimeter_personal-->'=>'', '<!--dosimeter_host-->'=>'', '<!--dosimeter_personal_host-->'=>'','<!--dosimeter_id-->'=>'','<!--dosimeter_value-->'=>'','<!--dosimeter_exitvalue-->'=>'');
				foreach ($place as $p) {
					$replace["<!--place_$p-->"] = '';
				}
				echo strtr($template[0], $replace)."$items{$template[2]}";
			}
			if (isset($_REQUEST['database'])) {
				echo "<h1>Operazioni sul database nell'anno $year</h1>";
				$data = $sql->sql_select("*", "access_{$machine}_log", "action_time >= '$year-01-01' AND action_time < '".($year+1)."-01-01' ORDER BY action_time DESC");
				debug($data);
			}
			exit();
		}
	}

	if (!in_array($remote_ip, $master_ip+$controlroom_ip)) {
		session_start();
		if (isset($_SESSION['token'])) {
			if (file_get_contents('token.txt')!=$_SESSION['token']) {sleep(2); die("<h4>Access forbidden</h4>");}
		}
		else if (isset($_REQUEST['elettra_ldap_email']) and isset($_REQUEST['elettra_ldap_password'])) {
			if (!function_exists('ldap_connect')) die("LDAP module not installed in PHP");
			$ds=ldap_connect("abook.elettra.eu");  // must be a valid LDAP server!
			if (!$ds) { 
				die("<h4>Unable to connect to LDAP server</h4>");
			}
			$email = $_REQUEST['elettra_ldap_email'];
			if ($email!=='lucio.zambon') {sleep(2); die("<h4>Access forbidden</h4>");}
			$password = $_REQUEST['elettra_ldap_password'];
			$r=ldap_bind($ds, $email, $password);  
			if ($r!="successful") die("login failed<br><br>");
			for ($i=0,$token=""; $i<5; $i++) {
				$token .= sprintf("%02x", rand(0, 256));
			}
			$_SESSION['token'] = $token;
			file_put_contents('token.txt',$token);
		}	
		else die("Accesso non autorizzato da questa workstation $remote_ip<a href='?login'>.</a>");
	}
	$file_headers = @get_headers($loginService);
	if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
		die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo");
	}


	// ----------------------------------------------------------------
	// search for name
	function detect_name($name) {
		global $user;
		$n = array_search(trim($nome), $user);
		if ($n) {
			return $n;
		}
		else {
			return strtr($name,array('ronda.'=>'search','osp.'=>'host','osp'=>'host','ospite'=>'host','_'=>''));
		}
	}

	// ----------------------------------------------------------------
	// show_anomalies
	function show_anomalies($days=180) {
		global $sql, $machine, $user, $dbtype;
		$t0 = time();
		if ($machine=='elettra') {
			$sql2 = new SqlInterface($dbtype);
			$db2 = $sql2->sql_connect(HOST2, USERNAME2, PASSWORD2, DB);
			if ($dbtype!="pg") {$sql2->sql_select_db(DB, $db2);}
			$sql3 = new SqlInterface($dbtype);
			$db3 = $sql3->sql_connect(HOST3, USERNAME3, PASSWORD3, DB3);
			if ($dbtype!="pg") {$sql3->sql_select_db(DB3, $db3);}
		}
		if (isset($_REQUEST['debug3'])) debug("show_anomalies($days), machine: $machine");
		$err_msg = array();
		$nl = '%0D%0A';
		$cond = ($dbtype=="pg")? "exit_time IS NULL AND EXTRACT(EPOCH FROM current_timestamp-enter_time)>86400": "ISNULL(exit_time) AND TIME_TO_SEC(TIMEDIFF(NOW(),enter_time))>86400";
		$data = $sql->sql_select("*", "access_$machine", "token>0 AND $cond", isset($_REQUEST['debug2'])? 1: 0);
		$yy = date("Y");
		$firstdate = $dbfirstdate = time()-86400*$days;
		if (isset($_REQUEST['year'])) {
			$yy = $_REQUEST['year']-0;
			$firstdate = strtotime(($_REQUEST['year']-0)."-01-01 00:00:00")." AND plc_time<".strtotime(($_REQUEST['year']+1)."-01-01 00:00:00");
			$dbfirstdate = strtotime(($_REQUEST['year']-0)."-01-01 00:00:00")." AND db_time<".strtotime(($_REQUEST['year']+1)."-01-01 00:00:00");
		}
		if (isset($_REQUEST['debug3'])) debug($data, "data");
		if (isset($_REQUEST['debug2'])) debug(count($data), "count(data)");
		$orderby = isset($_REQUEST['orderby'])? $_REQUEST['orderby']: 'date';
		$reverse = isset($_REQUEST['reverse'])? $_REQUEST['reverse']=='true': $orderby=='date';
		if (!empty($data)) foreach ($data as $row) {
			$startdate = $row['enter_time'];
			// echo "<!--\n";
			if ($machine=='fermi') {
				$doordata = $sql->sql_select(
					"CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'L' AS machine, position, present, name, time_id", 
					"linac_db50_$yy, enabled_user, linac_time_$yy", "position<16 AND plc_time>=UNIX_TIMESTAMP('$startdate') AND enabled_user.id=enabled_user_id AND time_id=linac_time_$yy.id"
					." UNION ". 
					"SELECT CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'U' AS machine, position, present, name, time_id FROM undulator_db50_$yy,".
					"enabled_user, undulator_time_$yy WHERE position<16 AND plc_time>=UNIX_TIMESTAMP('$startdate') AND enabled_user.id=enabled_user_id AND time_id=undulator_time_$yy.id AND name=\"{$user[$row['name']]}\" ORDER BY time_id DESC LIMIT 1");
			}
			else { // $machine=='elettra'
				$sql2 = new SqlInterface($dbtype);
				$db2 = $sql2->sql_connect(HOST2, USERNAME2, PASSWORD2, DB);
				if ($dbtype!="pg") {$sql2->sql_select_db(DB, $db2);}
				if (isset($_REQUEST['debug2'])) echo "FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id FROM sr_db49_$yy, enabled_user, sr_time_$yy WHERE position<64 AND plc_time>=UNIX_TIMESTAMP('$startdate')-4000 AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id AND name=\"{$user[$row['name']]}\" ORDER BY time_id DESC LIMIT 1;<br>";
				$doordata = $sql2->sql_select("FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id", "sr_db49_$yy, enabled_user, sr_time_$yy", 
					"position<64 AND plc_time>=UNIX_TIMESTAMP('$startdate')-4000 AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id AND name=\"{$user[$row['name']]}\" ORDER BY time_id DESC LIMIT 1", isset($_REQUEST['debug2'])? 1: 0);
			}
			// echo "-->\n";
			$name = array_search(trim($row['name']), $user);
			$msg_body = "Attenzione!{$nl}Risulta incompleta la registrazione nel registro elettronico dell'accesso dalle {$row['enter_time']} alle {$doordata[0]['db_time']}.{$nl}Si prega di recarsi in sala controllo e completare la registrazione indicando un orario che comprenda quello segnalato con qualche minuto di margine.{$nl}{$nl}Saluti.{$nl}Servizio Radioprotezione";
			$key = $orderby=='date'? $row['enter_time'].$row['name']: (strpos($row['name'],'.')!==false? substr($row['name'],2): 'zz'.$row['name']).$row['enter_time'];
			$err_msg[$key] = (isset($_REQUEST['backoffice'])? "<a href=\"mailto:{$name}@elettra.eu?subject=Anomalia registrazione accesso&body=$msg_body\">{$row['name']}</a>": $row['name'])." in macchina da oltre 24 ore, entrata dichiarata alle {$row['enter_time']}, uscita rilevata alle {$doordata[0]['db_time']}\n";
		}
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
			if ($machine=='fermi') {
				$doordata = $sql->sql_select("CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'L' AS machine, position, present, name, time_id", "linac_db50_$yy, enabled_user, linac_time_$yy", "position<16 AND plc_time>$firstdate AND enabled_user.id=enabled_user_id AND time_id=linac_time_$yy.id"
					." UNION ". 
					"SELECT CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'U' AS machine, position, present, name, time_id FROM undulator_db50_$yy, enabled_user, undulator_time_$yy 
					 WHERE position<16 AND plc_time>$firstdate AND enabled_user.id=enabled_user_id AND time_id=undulator_time_$yy.id ORDER BY time_id", isset($_REQUEST['debug2'])? 1: 0);
				if (!empty($doordata)) foreach ($doordata as $row) {
					$startdate = $row['db_time'];
					if ($row['present']=='Y') {
						$in_machine = "in service area";
						if (isset($_REQUEST['debug2'])) debug($row, 'row');
						$name = array_search(trim($row['name']), $user);
						if ($name) {
							$query = "SELECT * FROM access_$machine WHERE name='$name' AND enter_time<'{$row['db_time']}' AND (ISNULL(exit_time) OR exit_time>'{$row['db_time']}')";
						}
						else {
							$name = strtr($row['name'],array('ronda.'=>'search','osp.'=>'host','osp'=>'host','ospite'=>'host','_'=>''));
							$query = "SELECT * FROM access_$machine WHERE CONCAT(badge_type,'$machine',badge_number)='$name' AND token>0 AND enter_time<'{$row['db_time']}' AND (ISNULL(exit_time) OR exit_time>'{$row['db_time']}')";
						}
						if (isset($_REQUEST['debug4'])) debug($query, 'query');
						$res2 = $sql->sql_query($query);
						$row2 = $sql->sql_fetch_array($res2);
						if (isset($_REQUEST['debug4'])) debug($row2, 'row2');
						$color = (($color=='red') or empty($row2))? 'red': 'green';
						$in_machine = $row['machine']=='L'? "in linac": "in sala ondulatori";
						if (empty($row2)) {
							$present[$row['name']] = true;
							$msg_body = "Attenzione!{$nl}Risulta mancante la registrazione nel registro elettronico dell'accesso $in_machine dalle {$row['db_time']} alle <!--{$row['name']}-->.{$nl}Si prega di recarsi in sala controllo ed effettuare la registrazione indicando un orario che comprenda quello segnalato con qualche minuto di margine.{$nl}{$nl}Saluti.{$nl}Servizio Radioprotezione";
							$key = $orderby=='date'? $row['db_time'].$name: (strpos($name,'.')!==false? substr($row['name'],strpos($name,'.')+1): 'zz'.$name).$row['db_time'];
							$err_msg[$key] = (isset($_REQUEST['backoffice'])? "<a href=\"mailto:{$name}@elettra.eu?subject=Anomalia Registrazione accesso&body=$msg_body\">{$row['name']}</a>": $row['name'])." $in_machine dalle {$row['db_time']} alle <!--{$row['name']}--> ma risulta assente dal registro. \n";
						}
					}
					else if (isset($present[$row['name']]) and $present[$row['name']]) {
						$present[$row['name']] = false;
						foreach ($err_msg as $k => $v) {
							$err_msg[$k] = strtr($v, array("<!--{$row['name']}-->" => $row['db_time']));
						}
					}
				}
			}
			if ($machine!='fermi') {
				$brg = array();
				$doordata_brg = $sql2->sql_select("FROM_UNIXTIME(db_time) AS t, db_time, CONCAT(position) AS position, present, name, time_id", "sr_db49_$yy, enabled_user, sr_time_$yy", "position<64 AND plc_time>$firstdate AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id ORDER BY time_id", isset($_REQUEST['debug2'])? 1: 0);
				if ($doordata_brg) foreach ($doordata_brg as $d) {
					$name = $d['name']; // detect_name($d['name']);
					if (!isset($brg[$name])) $brg[$name] = array();
					if ($d['present']=='Y') {
						$brg[$name][] = array('enter'=>$d['db_time'],'enter_t'=>$d['t']);
					}
					else {
						$brg[$name][count($brg[$name])-1]['exit'] = $d['db_time'];
						$brg[$name][count($brg[$name])-1]['exit_t'] = $d['t'];
					}
				}
				if (isset($_REQUEST['debug4'])) debug(count($doordata), 'data');
				$doordata_sr = $sql2->sql_select("*", "sr_db50_$yy, enabled_user, sr_time_$yy", "position<16 AND db_time>=$dbfirstdate AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id ORDER BY time_id", isset($_REQUEST['debug5'])? 1: 0);
				$sr = array();
				if ($doordata_sr) foreach ($doordata_sr as $d) {
					$name = $d['name']; // detect_name($d['name']);
					if (!isset($sr[$name])) $sr[$name] = array();
					if ($d['present']=='Y') {
						$sr[$name][] = array('enter'=>$d['db_time']);
					}
					else {
						$sr[$name][count($sr[$name])-1]['exit'] = $d['db_time'];
					}
				}
				if (isset($_REQUEST['debug7'])) debug($sr, 'sr');
				$doordata_bo = $sql3->sql_select("*", "booster_db50_$yy, enabled_user, booster_time_$yy", "position<16 AND db_time>=$dbfirstdate AND enabled_user.id=enabled_user_id AND time_id=booster_time_$yy.id ORDER BY time_id", isset($_REQUEST['debug5'])? 1: 0);
				$bo = array();
				if ($doordata_bo) foreach ($doordata_bo as $d) {
					$name = $d['name']; // detect_name($d['name']);
					if (!isset($bo[$name])) $bo[$name] = array();
					if ($d['present']=='Y') {
						$bo[$name][] = array('enter'=>$d['db_time']);
					}
					else {
						$bo[$name][count($bo[$name])-1]['exit'] = $d['db_time'];
					}
				}
				if (isset($_REQUEST['debug6'])) debug($bo, 'bo');
			}
			$present = array();
			if (isset($_REQUEST['debug9'])) debug($brg);
			if (!empty($brg)) {
				krsort($brg);
				foreach ($brg as $name=>$b) {
					foreach ($b as $bridge) {
						$n = array_search(trim($name), $user);
						if ($n) {
							$query = "SELECT * FROM access_$machine WHERE name='$n' AND enter_time<ADDTIME('{$bridge['enter_t']}','5:0.0') AND (ISNULL(exit_time) OR exit_time>SUBTIME('{$bridge['exit_t']}','0:5:0'))";
						}
						else {
							$n = strtr($name,array('ronda_'=>'search','ronda.'=>'search','osp.'=>'host','osp'=>'host','ospite'=>'host','_'=>''));
							$query = "SELECT * FROM access_$machine WHERE CONCAT(badge_type,'$machine',badge_number)='$n' AND token>0 AND enter_time<ADDTIME('{$bridge['enter_t']}','5:0.0') AND (ISNULL(exit_time) OR exit_time>SUBTIME('{$bridge['exit_t']}','0:5:0'))";
						}
						if (isset($_REQUEST['debug8'])) echo "$query;<br>\n";
						$res2 = $sql->sql_query($query);
						$row2 = $sql->sql_fetch_array($res2);
						if (empty($row2)) {
							$in_machine = "in service area";
							foreach ($sr[$name] as $s) {
								if (($bridge['enter'] < $s['enter']) && ($s['enter'] < $bridge['exit'])) {
									$in_machine = "in storage ring";
									break;
								}
							}
							foreach ($bo[$name] as $booster) {
								if (($bridge['enter'] < $booster['enter']) && ($booster['enter'] < $bridge['exit'])) {
									$in_machine = ($in_machine=="in storage ring")? "in storage ring e in booster": "in booster";
									break;
								}
							}
							$present[$name] = true;
							$msg_body = "Attenzione!{$nl}Risulta mancante la registrazione nel registro elettronico dell'accesso $in_machine dalle {$bridge['enter_t']} alle {$bridge['exit_t']}.{$nl}Si prega di recarsi in sala controllo ed effettuare la registrazione indicando un orario che comprenda quello segnalato con qualche minuto di margine.{$nl}{$nl}Saluti.{$nl}Servizio Radioprotezione";
							$key = $orderby=='date'? $bridge['enter_t'].$name: (strpos($name,'.')===1? substr($name,2): 'zz'.$name).$bridge['enter_t'];
							$err_msg[$key] = (isset($_REQUEST['backoffice'])? "<a href=\"mailto:{$n}@elettra.eu?subject=Anomalia Registrazione Accesso&body=$msg_body\">{$name}</a>": $name)." $in_machine dalle {$bridge['enter_t']} alle {$bridge['exit_t']} ma risulta assente dal registro. \n";
						}
					}
				}
			}
		}
		$msg = '';
		if ($reverse) krsort($err_msg); else ksort($err_msg);
		if (count($err_msg)) $msg = "<b>Lista anomalie</b>".(isset($_REQUEST['anomalies'])? "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='?backoffice&anomalies&year=$yy&orderby=name&reverse=true' title='ordina per cognome decrescente'>&lt;</a> nome <a href='?backoffice&anomalies&year=$yy&orderby=name&reverse=false' title='ordina per cognome crescente'>&gt;</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='?backoffice&anomalies&year=$yy&orderby=date&reverse=true' title='ordina per data decrescente'>&lt;</a> data <a href='?backoffice&anomalies&year=$yy&orderby=date&reverse=false' title='ordina per data crescente'>&gt;</a>\n<pre>".implode('', $err_msg)."</pre>\n": "<br>\n<textarea rows='3' cols='140'>".implode('', $err_msg)."</textarea>\n");
		$t = time() - $t0;
		echo "\n<!--\n show_anomalies() t: $t\n-->\n";
		return strtr($msg, array("alle  ma risulta"=>"; ma risulta"));
	}

	// ----------------------------------------------------------------
	// retrive operator list
	function findOperators() {
		global $operator_url;
		$data = file($operator_url);
		$backup = file('./operators.csv');
		$list = '';
		if (count($data)<2) {
			foreach ($backup as $line) {
				$l = trim($line);
				$list .= "<option value=\"$l\">$l</option>\n";
			}
			return $list;
		}
		foreach ($data as $line) {
			if (strpos($line, '@elettra.eu')!==false) {
				$op = strtr($line, array('@elettra.eu'=>''));
				$operator[] = $op;
				$l = trim($op);
				$list .= "<option value=\"$l\">$l</option>\n";
			}
		}
		if (count(array_diff($backup, $operator)) + count(array_diff($operator, $backup))) {
			file_put_contents('./operators.csv', implode("", $operator));
			if (isset($_REQUEST['debug'])) {echo "<hr><pre>";print_r(array_diff($backup, $operator));print_r(array_diff($operator, $backup));echo "</pre>";}
		}
		return $list;
	}

	// ----------------------------------------------------------------
	// validate request against JavaScript errors
	function validate_request() {
		if ($_REQUEST['confirm'] !== 'ok') {
			die('<!DOCTYPE html>
	<html lang="it"><head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="size=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<meta name="description" content="">
	<meta name="author" content="">
	<link rel="icon" href="http://www.elettra.eu/favicon.png"> 
	<title>PSS register</title>
	<!-- Bootstrap CSS -->
	<link href="./lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">	
	<link href="data:text/css;charset=utf-8," data-href="./lib/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet" id="bs-theme-stylesheet">	
	<link href="./lib/bootstrap/css/docs.min.css" rel="stylesheet"> 
	<!-- Custom styles for this template -->
	<link href="./lib/bootstrap/css/theme.css" rel="stylesheet">
	</head><body>&nbsp;<h1>Attenzione</h1>&nbspSi &egrave; verificato un errore, se dovesse permanere &egrave; obbligatorio usare il registro cartaceo<br><br>&nbsp<a href="./register.php">continua</a><br><br><br><br>');
		}
	}

	// ----------------------------------------------------------------
	// retrive user list from LDAP
	function findUsers($search = "*") {
		if (!function_exists('ldap_connect')) {
			return array_flip(file('./users.csv', FILE_IGNORE_NEW_LINES));
			return false;
		}
		$ds=ldap_connect("abook.elettra.eu");	// must be a valid LDAP server!
		if (!$ds) { 
			return false;
		}
		$r=ldap_bind($ds);		 // this is an "anonymous" bind, typically
		$field = "mail";
		$sr=ldap_search($ds, "cn=elettra.eu", "$field=$search");
		$info = ldap_get_entries($ds, $sr);
		if (isset($_REQUEST['debug'])) {echo "<hr><pre>";print_r($info);echo "</pre>";exit();}
		$data = array();
		for ($i=0; $i<$info["count"]; $i++) {
			if (strpos($info[$i]['uid'][0], '0')!==false) continue;
			if (strpos($info[$i]['uid'][0], '1')!==false) continue;
			if (strpos($info[$i]['uid'][0], '2')!==false) continue;
			if (strpos($info[$i]['uid'][0], '3')!==false) continue;
			if (strpos($info[$i]['uid'][0], '4')!==false) continue;
			if (strpos($info[$i]['uid'][0], '5')!==false) continue;
			if (strpos($info[$i]['uid'][0], '6')!==false) continue;
			if (strpos($info[$i]['uid'][0], '7')!==false) continue;
			if (strpos($info[$i]['uid'][0], '8')!==false) continue;
			if (strpos($info[$i]['uid'][0], '9')!==false) continue;
			if (substr_count($info[$i]['uid'][0], '.')==1) {$data[$info[$i]['uid'][0]]=true;}
		}
		if ($search == "*") {
			if (count($data)>100) {
				file_put_contents('./users.csv', implode("\n", array_keys($data)));
			}
			else {
				touch('./users.csv');
			}
		}
		return($data);
	}

	// save multiple access
	if (isset($_REQUEST['name_0'])) {
		$err_msg = '';
		$list = findUsers();
		// debug($list); exit();
		for ($i=0; $i<$multi_number; $i++) {
			if (empty($_REQUEST["name_$i"])) continue;
			if ((!empty($list)) and (!isset($list[$_REQUEST["name_$i"]]))) {
				$err_msg .= "ERRORE: Username non valido: ".quote_smart($_REQUEST["name_$i"])."<br>\n";
				continue;
			}
			if (empty($_REQUEST["badge_$i"]) or (($_REQUEST["badge_$i"]!=='personal') and (empty($_REQUEST["badge_id_$i"]))) or empty($_REQUEST["dosimeter_$i"]) or (($_REQUEST["dosimeter_$i"]!=='personal') and (empty($_REQUEST["dosimeter_id_$i"]) or empty($_REQUEST["dosimeter_value_$i"])))) {
				$err_msg .= "ERRORE: dati incompleti relativi a ".quote_smart($_REQUEST["name_$i"])."<br>\n";
				continue;
			}
			if (strpos($_REQUEST["name_$i"],'@')!==false) {list($_REQUEST["name_$i"],$trash)=explode('@',$_REQUEST["name_$i"],2);}
			$placeArray = array();
			foreach ($place as $p) {
				if (isset($_REQUEST["place_$p"])) $placeArray[] = $p;
			}
			$param = array(
				'token' => rand(0, 1000000000),
				'enter_time' => ($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['start']),"'").":00'",
				'name' => quote_smart($_REQUEST["name_$i"]),
				'signed_by' => quote_smart($_REQUEST["name"]),
				'place' => quote_smart(implode(',', $placeArray)),
				'badge_type' => quote_smart($_REQUEST["badge_$i"]),
				'dosimeter_type' => quote_smart($_REQUEST["dosimeter_$i"])
			);
			if ($_REQUEST["badge_$i"]!='personal') {$param['badge_number'] = quote_smart($_REQUEST["badge_id_$i"]);}
			if ($_REQUEST["dosimeter_$i"]!='personal') {$param['dosimeter_number'] = quote_smart($_REQUEST["dosimeter_id_$i"]);$param['dosimeter_value'] = quote_smart($_REQUEST["dosimeter_value_$i"]);}
			if (strlen($_REQUEST['note'])) {$param['note'] = quote_smart($_REQUEST['note']);}
			$query = "INSERT INTO access_$machine (".implode(',', array_keys($param)).") VALUES (".implode(',', $param).")";
			$res = $sql->sql_query($query);
			if (!$res) {
				die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>$query;<br>\nError message: ". $sql->sql_error());
			}
			$id = $sql->last_insert_id();
			$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
			$res = $sql->sql_query($logquery);
			if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
			if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
				$txt = "http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['start'])."&name={$_REQUEST["name_$i"]}&place=".trim($param['place'],"'")."&note=".urlencode($_REQUEST['note'])."&exit=false";
				$res = file($txt);
				file_put_contents('./enter.log', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
			}
		}
		header("location: ./register.php?machine=$machine".(strlen($err_msg)? "&msg=$err_msg": ''));
		exit();
	}

	if (isset($_REQUEST['list'])) {
		if (!function_exists('ldap_connect')) die("LDAP module not installed in PHP");
		$ds=ldap_connect("abook.elettra.eu") or die("Could not connect to $ldaphost");
		if (!$ds) { die("<h4>Unable to connect to LDAP server</h4>");}
		$r=ldap_bind($ds);
		$search = "*";
		$field = "mail";
		$sr=ldap_search($ds, "cn=elettra.eu", "$field=$search");
		$info = ldap_get_entries($ds, $sr);
		$data = array();
		for ($i=0; $i<$info["count"]; $i++) {
			if (strpos($info[$i]['uid'][0], '0')!==false) continue;
			if (strpos($info[$i]['uid'][0], '1')!==false) continue;
			if (strpos($info[$i]['uid'][0], '2')!==false) continue;
			if (strpos($info[$i]['uid'][0], '3')!==false) continue;
			if (strpos($info[$i]['uid'][0], '4')!==false) continue;
			if (strpos($info[$i]['uid'][0], '5')!==false) continue;
			if (strpos($info[$i]['uid'][0], '6')!==false) continue;
			if (strpos($info[$i]['uid'][0], '7')!==false) continue;
			if (strpos($info[$i]['uid'][0], '8')!==false) continue;
			if (strpos($info[$i]['uid'][0], '9')!==false) continue;
			if (substr_count($info[$i]['uid'][0], '.')==1) {list($firstname,$lastname) = explode('.',$info[$i]['uid'][0]); $data["$lastname $firstname"]=$info[$i]['uid'][0];}
		}
		ksort($data);
		echo "username: <select name='username'>\n";
		foreach ($data as $k=>$d) echo "<option value='$d'>$k</option>\n";
		echo "</select>\n";
		exit();
	}
	if (isset($_REQUEST['edit'])) {
		$template = explode('<!--item-->', file_get_contents("register_$machine.html"));
		$data = $sql->sql_select("*", "access_$machine", "token=".quote_smart($_REQUEST['edit']));
		$row = $data[0];
		if (!empty($row['signed_by'])) {
			$replace["<span id='operatorList' style='display:none;'"] = "<span id='operatorList'";
			$replace['<!--operators-->'] = findOperators();
		}
		$replace['<!--start-->'] = substr($row['enter_time'],0,16);
		$replace["<!--name-->'"] = "{$row['name']}' readonly";
		$replace['<!--note-->'] = $row['note'];
		$placeArray = explode(',', strtr($row['place'],array('linacuh'=>'linac,uh')));
		foreach ($place as $p) {
			$selected = in_array($p, $placeArray)? ' checked': ''; 
			$replace["<!--place_{$p}-->"] = $selected;
		}
		$replace['<!--badge_personal-->'] = $row['badge_type']=='personal'? ' selected': '';
		$replace['<!--badge_host-->'] = $row['badge_type']=='host'? ' selected': '';
		$replace['<!--badge_search-->'] = $row['badge_type']=='search'? ' selected': '';
		$replace['<!--badge_id-->'] = $row['badge_number']>0? $row['badge_number']: '';
		$replace['<!--dosimeter_personal-->'] = $row['dosimeter_type']=='personal'? ' selected': '';
		$replace['<!--dosimeter_host-->'] = $row['dosimeter_type']=='host'? ' selected': '';
		$replace['<!--dosimeter_personal_host-->'] = $row['dosimeter_type']=='personal_host'? ' selected': '';
		$replace['<!--dosimeter_id-->'] = $row['dosimeter_number']>0? $row['dosimeter_number']: '';
		$replace['<!--dosimeter_value-->'] = $row['dosimeter_value']>0? $row['dosimeter_value']: '';
		$replace['<!--token-->'] = $row['token'];
		$replace['<div class="bs-callout bs-callout-danger">'] = '<!--';
		$replace["<button id='multi'"] = '<!--';
		$replace["multiplo</button>"] = ' -->';
		$replace['<h1>INGRESSO</h1>'] = '<h1>Modifica</h1>';
		$replace['<h1>FERMI</h1>'] = $replace['<h1>ELETTRA</h1>'] = $replace['<br><br><br>'] = $replace['<h1>&nbsp;</h1>'] = '';
		$replace["salva</button>"] = "salva</button><input type='hidden' name='save'><input type='hidden' name='token' value='{$_REQUEST['edit']}'>";
		$replace["</form>"] = "</form><br><table><tr><td><form action='./register.php' method='POST' id='form_del'><input type='hidden' id='confirm_del' name='confirm' value='NO'><button id='del' name='del' class='btn btn-lg btn-danger' type='submit' onClick=\"validate_del('{$row['name']}'); return false;\">elimina</button><input type='hidden' name='del'><input type='hidden' name='token' value='{$_REQUEST['edit']}'></form>
			</td><td>&nbsp;&nbsp;&nbsp;<button id='cancel' name='del' class='btn btn-lg btn-danger' type='submit' onClick=\"window.location='?'\">torna al registro</button></td></tr></table>";
		$items_indoor = strtr($template[0], $replace);
		die("$items_indoor");
	}

	if (isset($_REQUEST['del'])) {
		validate_request();
		$data = $sql->sql_select("id", "access_$machine", "token=".quote_smart($_REQUEST['token']));
		$id = $data[0]['id'];
		$query = "UPDATE access_$machine SET token=-1 WHERE id=$id";
		$sql->sql_query($query);
		if ($sql->sql_error()) {
			die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>$query;<br>\nError message: ". $sql->sql_error());
		}
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		header('Location: ./register.php');
		// echo "<script>\n	document.domain = 'elettra.eu';\n	window.location='./register.php';\n</script>";
		exit();
	}
	if (isset($_REQUEST['save'])) {
		validate_request();
		$placeArray = array();
		foreach ($place as $p) {
			if (isset($_REQUEST["place_$p"])) $placeArray[] = $p;
		}
		$param = array(
			'enter_time='.($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['start']),"'").":00'",
			// 'name='.quote_smart($_REQUEST['name']),
			'place='.quote_smart(implode(',', $placeArray)),
			'badge_type='.quote_smart($_REQUEST['badge']),
			'dosimeter_type='.quote_smart($_REQUEST['dosimeter'])
		);
		if (strlen($_REQUEST['operator'])) {$param[] = "signed_by=".quote_smart($_REQUEST["operator"]);}
		if ($_REQUEST['badge']!='personal') {$param[] = 'badge_number='.quote_smart($_REQUEST['badge_id']);}
		if ($_REQUEST['dosimeter']!='personal') {$param[] = 'dosimeter_number='.quote_smart($_REQUEST['dosimeter_id']);$param[] = 'dosimeter_value='.quote_smart($_REQUEST['dosimeter_value']);}
		if (strlen($_REQUEST['note'])) {$param[] = 'note='.quote_smart($_REQUEST['note']);}
		$query = "UPDATE access_$machine SET ".implode(',', $param)." WHERE token=".quote_smart($_REQUEST['token']);
		$res = $sql->sql_query($query);
		if (!$res) {
			die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>$query;<br>\nError message: ". $sql->sql_error());
		}
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ((SELECT id FROM access_$machine WHERE token=".quote_smart($_REQUEST['token'])."),NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		// if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		header('Location: ./register.php');
		// echo "<script>\n	document.domain = 'elettra.eu';\n	window.location='./register.php';\n	close();\n</script>";
		exit();
	}
	if (isset($_REQUEST['enter'])) {
		validate_request();
		if (strpos($_REQUEST['name'],'@')!==false) {list($_REQUEST['name'],$trash)=explode('@',$_REQUEST['name'],2);}
		$placeArray = array();
		foreach ($place as $p) {
			if (isset($_REQUEST["place_$p"])) $placeArray[] = $p;
		}
		$param = array(
			'token' => rand(0, 1000000000),
			'enter_time' => ($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['start']),"'").":00'",
			'name' => quote_smart(strtolower($_REQUEST['name'])),
			'place' => quote_smart(implode(',', $placeArray)),
			'badge_type' => quote_smart($_REQUEST['badge']),
			'dosimeter_type' => quote_smart($_REQUEST['dosimeter'])
		);
		if ($_REQUEST['badge']!='personal') {$param['badge_number'] = quote_smart($_REQUEST['badge_id']);}
		if ($_REQUEST['dosimeter']!='personal') {$param['dosimeter_number'] = quote_smart($_REQUEST['dosimeter_id']);$param['dosimeter_value'] = quote_smart($_REQUEST['dosimeter_value']);}
		if (strlen($_REQUEST['note'])) {$param['note'] = quote_smart($_REQUEST['note']);}
		if (strlen($_REQUEST['operator'])) {$param['signed_by'] = quote_smart($_REQUEST["operator"]);}
		$query = "INSERT INTO access_$machine (".implode(',', array_keys($param)).") VALUES (".implode(',', $param).")";
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$id = $sql->last_insert_id();
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		// if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$replaceArray = array('delnegro'=>'Del+Negro');
		if (strpos($_REQUEST['name'],'.')===false) $replaceArray[' '] = '.';
		$uname = strtr($_REQUEST['name'], $replaceArray);
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
			$txt = "http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['start'])."&name=$uname&place=".trim($param['place'],"'")."&note=".urlencode($_REQUEST['note'])."&exit=false";
			$res = @file($txt);
			file_put_contents('./enter.log', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		}
		else die("http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['start'])."&name={$_REQUEST['name']}&place=".trim($param['place'],"'")."&exit=false");
		header("location: ./register.php?machine=$machine");
		exit();
	}
	if (isset($_REQUEST['exit'])) {
		validate_request();
		$exitvalue = isset($_REQUEST["dosimeter_exitvalue"])? ',dosimeter_exitvalue='.quote_smart($_REQUEST["dosimeter_exitvalue"]): '';
		if (strlen($_REQUEST['operator'])) {$exitvalue .= ", exitsigned_by=".quote_smart($_REQUEST["operator"]);}
		$query = "UPDATE access_$machine SET exit_time=".($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['stop']),"'").":00'$exitvalue WHERE token=".quote_smart($_REQUEST['token']);
		$sql->sql_query($query);
		if ($sql->sql_error()) {
			echo "<!--\n"; debug($_REQUEST); debug(quote_smart($_REQUEST["stop"].':00'));echo "\n-->\n"; 
			die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>$query;<br>\nError message: ". $sql->sql_error());
		}
		$data = $sql->sql_select("id", "access_{$machine}", "token=".quote_smart($_REQUEST['token']));
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ({$data[0]['id']},NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		// if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$data = $sql->sql_select("name,place", "access_$machine", "token=".quote_smart($_REQUEST['token']));
		$row = $data[0];
		$replaceArray = array('delnegro'=>'Del+Negro');
		if (strpos($row['name'],'.')===false) $replaceArray[' '] = '.';
		$uname = strtr($row['name'], $replaceArray);
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
			$txt = "http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['stop'])."&name=$uname&place={$row['place']}&exit=true";
			$res = file($txt);
			file_put_contents('./enter.log', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		}
		// else die("http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['stop'])."&name={$row['name']}&place={$row['place']}&exit=true");
		header("location: ./register.php?machine=$machine");
		exit();
	}

	$keys = array('<!--start-->', '<!--name-->', '<!--place-->', '<!--note-->', '<!--badge-->', '<!--badge_id-->', '<!--dosimeter-->', '<!--dosimeter_id-->', '<!--dosimeter_value-->');

	if (isset($_REQUEST['multi_input'])) {
		findUsers();
		$template = explode('<!--item-->', file_get_contents("register_{$machine}_multi.html"));
		echo $template[0].$jsSearch;
		for ($i=0; $i<$multi_number; $i++) {
			echo strtr($template[1], array('<!--id-->'=>$i,'<!--name-->'=>'','<!--badge_personal-->'=>'','<!--badge_host-->'=>'','<!--badge_search-->'=>'','<!--badge_id-->'=>'','<!--dosimeter_personal-->'=>'','<!--dosimeter_host-->'=>'','<!--dosimeter_personal_host-->'=>'','<!--dosimeter_id-->'=>'','<!--dosimeter_value-->'=>''));
		}
		die(strtr($template[2], array('<!--start-->'=>'','<!--note-->'=>'','<!--place_psa-->'=>'','<!--place_bo-->'=>'','<!--place_sa-->'=>'','<!--place_ring-->'=>'','<!--place_linac-->'=>'','<!--place_uh-->'=>'','<!--place_linacuh-->'=>'','<!--place_kgzc-->'=>'','<!--operators-->'=>findOperators())));
	}

	if (isset($_REQUEST['msg'])) echo "<pre>{$_REQUEST['msg']}</pre>\n";

	$template = explode('<!--item-->', file_get_contents("register_$machine.html"));
	$anomalies = (isset($_REQUEST['show_details']) || isset($_REQUEST['backoffice']))? show_anomalies(): "<a href='?show_details'>maggiori dettagli</a> (anomalie e storico)<br>";
	$items_outdoor = strtr($template[0], array('<!--start-->'=>'','<!--name-->'=>'','<!--note-->'=>'','<!--anomalies-->'=>$anomalies,'<!--operators-->'=>findOperators(),
											'<!--place_psa-->'=>'','<!--place_bo-->'=>'','<!--place_sa-->'=>'','<!--place_ring-->'=>'','<!--place_linac-->'=>'','<!--place_uh-->'=>'','<!--place_linacuh-->'=>'','<!--place_kgzc-->'=>'',
											'<!--badge_personal-->'=>'','<!--badge_host-->'=>'','<!--badge_search-->'=>'','<!--badge_id-->'=>'',
											'<!--dosimeter_personal-->'=>'','<!--dosimeter_host-->'=>'','<!--dosimeter_personal_host-->'=>'','<!--dosimeter_id-->'=>'','<!--dosimeter_value-->'=>''
											));
	$items_indoor = '';
	$is_null_exit_time = ($dbtype=="pg")? "exit_time IS NULL": "ISNULL(exit_time)";
	$data = $sql->sql_select("*", "access_$machine", "token>=0 AND $is_null_exit_time ORDER BY enter_time DESC");
	if (!empty($data)) {
		$operatorList = findOperators();
		foreach ($data as $row) {
			if ($row['enter_time']=='2020-10-06 09:00:00') continue;
			$replace['<!--start-->'] = substr($row['enter_time'],0,16);
			$replace['<!--name-->'] = strpos($row['name'], '.')!==false? $row['name']: $row['name']."<br>operatore: <select name='operator' id='operator_{$row['token']}'>$operatorList</select>";
			$replace['<!--agent-->'] = strpos($row['name'], '.')!==false? $row['name']: "";
			$replace['<!--note-->'] = $row['note'];
			$replace['<!--place-->'] = strtr($row['place'], array('psa'=>'PSA','bo'=>'Booster','sa'=>'Service Area','ring'=>'Storage Ring','linac'=>'Linac','uh'=>'Sala Ondulatori','linacuh'=>'Linac + Sala Ond.','kgzc'=>"KG zone controllate"));
			$replace['<!--badge-->'] = strtr($row['badge_type'], array('personal'=>'Personale','host'=>'Ospite','search'=>'Ronda'));
			$replace['<!--badge_id-->'] = $row['badge_number']>0? $row['badge_number']: '';
			$replace['<!--dosimeter-->'] = strtr($row['dosimeter_type'], array('personal'=>'Personale','host'=>'Ospite','personal_host'=>'Personale + Ospite'));
			$replace['<!--dosimeter_id-->'] = $row['dosimeter_number']>0? $row['dosimeter_number']: '';
			$replace['<!--dosimeter_value-->'] = $row['dosimeter_value']>0? "IN: {$row['dosimeter_value']} - OUT: <input type='hidden' name='dosimeter_id' id='dosimeter_id_{$row['token']}' value='{$row['dosimeter_number']}'><input type='text' name='dosimeter_exitvalue' id='dosimeter_exitvalue_{$row['token']}' size='20' required onChange=\"changeElement('dosimeter_exitvalue_{$row['token']}', '#save_{$row['token']}')\">": '';
			$replace['<!--token-->'] = $row['token'];
			$items_indoor .= strtr($template[1], $replace);
		}
	}
	$keys = array('<!--start-->', '<!--name-->', '<!--place-->', '<!--note-->', '<!--badge-->', '<!--badge_id-->', '<!--dosimeter-->', '<!--dosimeter_id-->', '<!--dosimeter_value-->', '<!--stop-->');
	$items = '';
	$data = $sql->sql_select("*", "access_$machine", "token>=0 AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT ".((isset($_REQUEST['show_details']) || isset($_REQUEST['backoffice']))? 1500: 30));
	// $stmt = $sql->sql_prepare("SELECT * FROM access_$machine WHERE token>=? AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT 500");
	// $params = array($stmt, "i", 0);
	// $stmt = $sql->sql_prepare("SELECT * FROM access_$machine WHERE token>=$1 AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT 500", "q1");
	// $params = array(0);
	// $data = $sql->sql_execute($params, "q1");
	if (!empty($data)) foreach ($data as $row) {
		$replace['<!--start-->'] = substr($row['enter_time'],0,16);
		if (strlen($row['signed_by'])>0) $replace['<!--start-->'] .= "<br>{$row['signed_by']}";
		$replace['<!--stop-->'] = substr($row['exit_time'],0,16);
		if (strlen($row['exitsigned_by'])>0) $replace['<!--stop-->'] .= "<br>{$row['exitsigned_by']}";
		$replace['<!--name-->'] = $row['name'];
		$replace['<!--note-->'] = $row['note'];
		$replace['<!--place-->'] = strtr($row['place'], array('psa'=>'PSA','bo'=>'Booster','sa'=>'Service Area','ring'=>'Storage Ring','linac'=>'Linac','uh'=>'Sala Ondulatori','linacuh'=>'Linac + Sala Ond.','kgzc'=>"KG zone controllate"));
		$replace['<!--badge-->'] = strtr($row['badge_type'], array('personal'=>'Personale','host'=>'Ospite','search'=>'Ronda'));
		$replace['<!--badge_id-->'] = $row['badge_number']>0? $row['badge_number']: '';
		$replace['<!--dosimeter-->'] = strtr($row['dosimeter_type'], array('personal'=>'Personale','host'=>'Ospite','personal_host'=>'Personale + Ospite'));
		$replace['<!--dosimeter_id-->'] = $row['dosimeter_number']>0? $row['dosimeter_number']: '';
		$replace['<!--dosimeter_value-->'] = $row['dosimeter_value']>0? "IN: {$row['dosimeter_value']} - OUT: {$row['dosimeter_exitvalue']}": '';
		$replace['<!--token-->'] = $row['token'];
		$items .= strtr($template[3], $replace);
	}
	die("{$items_outdoor}{$jsSearch}$items_indoor{$template[2]}$items{$template[4]}");

?>
