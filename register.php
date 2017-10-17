<?php

	$dbtype = "pg";
	// $dbtype = "mysqli";
	if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') $dbtype = "mysqli";

	if (isset($_REQUEST['debug'])) debug($_SERVER);

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

	$remote_ip = $_SERVER['REMOTE_ADDR'];

	$multi_number = 12;

	$elog = $machine=='fermi'? 'felog': 'elog';

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

	$jsSearch = "	<script>\n	var entered = [".detect_entered()."];\n	var badge = {".detect_badge()."};\n	var dosimeter = {\n".detect_dosimeter()."};\n	var dosimeteredUser = [\n'".implode("',\n'", array_keys($user))."'\n];\nvar userList = [\n";
	$f = file('./users.csv');	
	foreach ($f as $l) {$jsSearch .= "'".trim($l)."',\n";}
	$jsSearch .= "''";
	$jsSearch .= "];\n$( function() {\n $( \".username\" ).autocomplete({source: userList});\n} );\n	</script>\n";

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
		$data = $sql->sql_select("*", "access_$machine", "id=".quote_smart($_REQUEST[id]));
		if (!$data) {sql_debug("*", "access_$machine", "id=".quote_smart($_REQUEST[id])); echo ";<br>\n".$sql->sql_error(); exit();}
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
		// $replace["<input type='submit' name='insert' value='salva'"] = "<input type='submit' name='update_save' value='modifica' onClick=\"validate_backoffice('$machine');return false;\">&nbsp;<input type='submit' name='delete' value='elimina'>&nbsp;<input type='submit' name='exit' value='esci'><input type='hidden' name='id' value='{$d['id']}'";
		$replace["<input type='submit' name='insert' value='salva'"] = "<input type='submit' name='update_save' value='modifica' onClick=\"validate_backoffice('$machine');\">&nbsp;<input type='submit' name='delete' value='elimina'>&nbsp;<input type='submit' name='exit' value='esci'><input type='hidden' name='id' value='{$d['id']}'";
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
		header("location: ./register.php?machine=$machine&backoffice");
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
			$form = "<form class='form-inline' enctype='multipart/form-data' action='?backoffice&load_csv' method='post'><table><tr><td>dati storici&nbsp;&nbsp;</td><td><input id='f' type='file' name='userfile' length='40'></td><td><input type='submit' name='download' value='Carica file CSV'></td></tr></table></form><br>";
			$err_msg = show_anomalies(date('z')+1);
			$template = explode('<!--item-->', file_get_contents("register_backoffice_$machine.html"));
			$data = $sql->sql_select("*", "access_$machine", "1=1 ORDER BY enter_time DESC LIMIT 500");
			if (!$data) {sql_debug("*", "access_$machine", "1=1 ORDER BY enter_time DESC LIMIT 500"); echo ";<br>\n".$sql->sql_error(); exit();}
			$items = '';
			foreach ($data as $row) {
				foreach ($row as $k=>$v) $replace["<!--$k-->"] = $v;
				$replace['<!--start-->'] = substr($replace['<!--enter_time-->'], 0, 16);
				$replace['<!--stop-->'] = substr($replace['<!--exit_time-->'], 0, 16);
				$replace['<!--place_psa-->'] = strtr($replace['<!--place-->'], array('psa'=>'PSA','booster'=>'Booster','sa'=>'Service Area','ring'=>'Storage Ring','linac'=>'Linac','uh'=>'Sala Ondulatori','linacuh'=>'Linac + Sala Ond.','kgzc'=>"KG zone controllate"));
				$replace['<!--badge-->'] = strtr($replace['<!--badge_type-->'], array('personal'=>'Personale','host'=>'Ospite '.$replace['<!--badge_number-->'],'search'=>'Ronda '.$replace['<!--badge_number-->']));
				$replace['<!--dosimeter-->'] = strtr($replace['<!--dosimeter_type-->'], array('personal'=>'Personale','host'=>'Ospite '.$replace['<!--dosimeter_number-->'],'personal_host'=>'Personale + Ospite '.$replace['<!--dosimeter_number-->']));
				$replace['<!--dosimeter_value-->'] = $replace['<!--dosimeter_value-->']=='0'? '': 'IN: '.$replace['<!--dosimeter_value-->'].($replace['<!--dosimeter_exitvalue-->']=='0'? '': ', OUT: '.$replace['<!--dosimeter_exitvalue-->']);
				$replace['<!--token-->'] = $replace['<!--token-->']=='-1'? 'cancellato': ''; // $replace['<!--token-->'];
				$items .= strtr($template[1], $replace);
			}
			$replace = array("<h1>FERMI</h1>"=>"<h1>FERMI</h1>$form$err_msg","<h1>ELETTRA</h1>"=>"<h1>ELETTRA</h1>$form$err_msg", '<!--badge_personal-->'=>'', '<!--badge_host-->'=>'', '<!--badge_search-->'=>'', '<!--badge_id-->'=>'', '<!--dosimeter_personal-->'=>'', '<!--dosimeter_host-->'=>'', '<!--dosimeter_personal_host-->'=>'','<!--dosimeter_id-->'=>'','<!--dosimeter_value-->'=>'','<!--dosimeter_exitvalue-->'=>'');
			foreach ($place as $p) {
				$replace["<!--place_$p-->"] = '';
			}
			echo strtr($template[0], $replace)."$items{$template[2]}$jsSearch<br><br><br><br><br><h1>Ultime operazioni</h1>";
			$data = $sql->sql_select("*", "access_{$machine}_log", "1=1 ORDER BY action_time DESC LIMIT 500");
			debug($data);
			exit();
		}
	}

	if (!in_array($remote_ip, $master_ip+$controlroom_ip)) die("Accesso non autorizzato da questa workstation");

	$file_headers = @get_headers($loginService);
	if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
		die("ATTENZIONE<br>\nservizio non disponibile<br>\ne' OBBLIGATORIO usare il registro cartaceo");
	}


	// ----------------------------------------------------------------
	// show_anomalies
	function show_anomalies($days=30) {
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
		$err_msg = '';
		$nl = '%0D%0A';
		$cond = ($dbtype=="pg")? "exit_time IS NULL AND EXTRACT(EPOCH FROM current_timestamp-enter_time)>86400": "ISNULL(exit_time) AND TIME_TO_SEC(TIMEDIFF(NOW(),enter_time))>86400";
		$data = $sql->sql_select("*", "access_$machine", "token>0 AND $cond");
		$yy = date("Y");
		if (isset($_REQUEST['debug3'])) debug($data, "data");
		$err_msg = '';
		if (!empty($data)) foreach ($data as $row) {
			$startdate = $row['enter_time'];
			echo "<!--\n";
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
				$doordata = $sql2->sql_select("FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id", "sr_db49_$yy, enabled_user, sr_time_$yy", 
											  "position<64 AND plc_time>=UNIX_TIMESTAMP('$startdate')-4000 AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id AND name=\"{$user[$row['name']]}\" ORDER BY time_id DESC LIMIT 1", 1);
				if (isset($_REQUEST['debug'])) debug($doordata, 'doordata');
			}
			echo "-->\n";
			$msg_body = "Attenzione!{$nl}Risulta incompleta la registrazione nel registro elettronico dell'accesso dalle {$row['enter_time']} alle {$doordata[0]['db_time']}.{$nl}Si prega di recarsi in sala controllo e completare la registrazione indicando un orario che comprenda quello segnalato con qualche minuto di margine.{$nl}{$nl}Saluti.{$nl}Servizio Radioprotezione";
			$err_msg .= (isset($_REQUEST['backoffice']))? "<a href=\"mailto:{$row['name']}@elettra.eu?subject=Anomalia registrazione accesso&body=$msg_body\">{$row['name']}</a>": $row['name'];
			$err_msg .= " in macchina da oltre 24 ore, entrata dichiarata alle {$row['enter_time']}, uscita rilevata alle {$doordata[0]['db_time']}\n";
		}
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
			$startdate = time()-86400*$days;
			if ($machine=='fermi') {
				$doordata = $sql->sql_select("CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'L' AS machine, position, present, name, time_id", "linac_db50_$yy, enabled_user, linac_time_$yy", "position<16 AND plc_time>$startdate AND enabled_user.id=enabled_user_id AND time_id=linac_time_$yy.id"
					." UNION ". 
					"SELECT CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'U' AS machine, position, present, name, time_id FROM undulator_db50_$yy, enabled_user, undulator_time_$yy 
					 WHERE position<16 AND plc_time>$startdate AND enabled_user.id=enabled_user_id AND time_id=undulator_time_$yy.id ORDER BY time_id");
			}
			else {
				$doordata = $sql2->sql_select("FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id", "sr_db49_$yy, enabled_user, sr_time_$yy", "position<64 AND plc_time>$startdate AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id ORDER BY time_id");
				if (isset($_REQUEST['debug'])) debug($doordata, 'data');
			}
			$present = array();
			if (!empty($doordata)) foreach ($doordata as $row) {
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
					if (isset($_REQUEST['debug2'])) debug($query, 'query');
					$res2 = $sql->sql_query($query);
					$row2 = $sql->sql_fetch_array($res2);
					if (isset($_REQUEST['debug2'])) debug($row2);
					$color = (($color=='red') or empty($row2))? 'red': 'green';
					if ($machine=='elettra') {
						$doordata2 = $sql2->sql_select("FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id", "sr_db50_$yy, enabled_user, sr_time_$yy", 
													  "position<64 AND plc_time>=UNIX_TIMESTAMP('$startdate')-4000 AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id AND name=\"{$row['name']}\" ORDER BY time_id DESC LIMIT 1");
						if (isset($_REQUEST['debug3'])) debug($doordata2, 'doordata2');
						if (isset($doordata2[0])) $in_machine = "in storage ring";
						$doordata3 = $sql3->sql_select("FROM_UNIXTIME(plc_time) AS t, FROM_UNIXTIME(db_time) AS db_time, CONCAT(position) AS position, present, name, time_id", "booster_db50_$yy, enabled_user, booster_time_$yy", 
													  "position<64 AND plc_time>=UNIX_TIMESTAMP('$startdate')-4000 AND enabled_user.id=enabled_user_id AND time_id=booster_time_$yy.id AND name=\"{$row['name']}\" ORDER BY time_id DESC LIMIT 1");
						if (isset($_REQUEST['debug3'])) debug($doordata3, 'doordata3');
						if (isset($doordata3[0])) $in_machine = "in booster";
					}
					else {
						$in_machine = $row['machine']=='L'? "in linac": "in sala ondulatori";
					}
					if (empty($row2)) {
						$present[$row['name']] = true;
						$msg_body = "Attenzione!{$nl}Risulta mancante la registrazione nel registro elettronico dell'accesso $in_machine dalle {$row['db_time']} alle <!--{$row['name']}-->.{$nl}Si prega di recarsi in sala controllo ed effettuare la registrazione indicando un orario che comprenda quello segnalato con qualche minuto di margine.{$nl}{$nl}Saluti.{$nl}Servizio Radioprotezione";
						$err_msg = (isset($_REQUEST['backoffice'])? "<a href=\"mailto:{$name}@elettra.eu?subject=Anomalia registrazione accesso&body=$msg_body\">{$row['name']}</a>": $row['name'])." $in_machine dalle {$row['db_time']} alle <!--{$row['name']}--> ma risulta assente dal registro. \n".$err_msg;
					}
				}
				else if (isset($present[$row['name']]) and $present[$row['name']]) {
					$present[$row['name']] = false; 
					$err_msg = strtr($err_msg, array("<!--{$row['name']}-->" => $row['db_time']));
				}
			}
		}
		if (strlen($err_msg)) $err_msg = "<b>Lista anomalie</b>".(isset($_REQUEST['backoffice'])?"\n<pre>$err_msg</pre>\n": "<br>\n<textarea rows='3' cols='140'>$err_msg</textarea>\n");
		$t = time() - $t0;
		echo "\n<!--\n show_anomalies() t: $t\n-->\n";
		return strtr($err_msg, array("alle  ma risulta"=>"; ma risulta"));
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
		if ($search == "*") file_put_contents('./users.csv', implode("\n", array_keys($data)));
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
				printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());
			}
			$id = $sql->last_insert_id();
			$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
			$res = $sql->sql_query($logquery);
			if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
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
			printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();
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
		if ($_REQUEST['badge']!='personal') {$param[] = 'badge_number='.quote_smart($_REQUEST['badge_id']);}
		if ($_REQUEST['dosimeter']!='personal') {$param[] = 'dosimeter_number='.quote_smart($_REQUEST['dosimeter_id']);$param[] = 'dosimeter_value='.quote_smart($_REQUEST['dosimeter_value']);}
		if (strlen($_REQUEST['note'])) {$param[] = 'note='.quote_smart($_REQUEST['note']);}
		$query = "UPDATE access_$machine SET ".implode(',', $param)." WHERE token=".quote_smart($_REQUEST['token']);
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
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
		$query = "INSERT INTO access_$machine (".implode(',', array_keys($param)).") VALUES (".implode(',', $param).")";
		$res = $sql->sql_query($query);
		if (!$res) {printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$id = $sql->last_insert_id();
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ($id,NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		// if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') $res = file("http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['start'])."&name={$_REQUEST['name']}&place=".trim($param['place'],"'")."&note=".urlencode($_REQUEST['note'])."&exit=false");
		else die("http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['start'])."&name={$_REQUEST['name']}&place=".trim($param['place'],"'")."&exit=false");
		header("location: ./register.php?machine=$machine");
		exit();
	}
	if (isset($_REQUEST['exit'])) {
		validate_request();
		$exitvalue = isset($_REQUEST["dosimeter_exitvalue"])? quote_smart($_REQUEST["dosimeter_exitvalue"]): 'NULL';
		$query = "UPDATE access_$machine SET exit_time=".($dbtype=="pg"? 'TIMESTAMP ':'')."'".trim(quote_smart($_REQUEST['stop']),"'").":00',dosimeter_exitvalue=$exitvalue WHERE token=".quote_smart($_REQUEST['token']);
		$sql->sql_query($query);
		if ($sql->sql_error()) {
			debug($_REQUEST);
			debug(quote_smart($_REQUEST["stop"].':00'));
			printf("$query;<br>\nErrormessage: %s\n", $sql->sql_error()); exit();
		}
		$data = $sql->sql_select("id", "access_{$machine}", "token=".quote_smart($_REQUEST['token']));
		$logquery = "INSERT INTO access_{$machine}_log (access_{$machine}_id,action_time,from_ip, query) VALUES ({$data[0]['id']},NOW(),'{$_SERVER["REMOTE_ADDR"]}',".quote_smart(strtr($query,array("'"=>'&#39;','"'=>'&quot;'))).")";
		$res = $sql->sql_query($logquery);
		// if (!$res) {printf("$logquery;<br>\nErrormessage: %s\n", $sql->sql_error());exit();}
		$data = $sql->sql_select("name,place", "access_$machine", "token=".quote_smart($_REQUEST['token']));
		$row = $data[0];
		if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') $res = file("http://{$elog}/InsertTextAccessi.php?date=".urlencode($_REQUEST['stop'])."&name={$row['name']}&place={$row['place']}&exit=true");
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
	$items_outdoor = strtr($template[0], array('<!--start-->'=>'','<!--name-->'=>'','<!--note-->'=>'','<!--anomalies-->'=>show_anomalies(),
											'<!--place_psa-->'=>'','<!--place_bo-->'=>'','<!--place_sa-->'=>'','<!--place_ring-->'=>'','<!--place_linac-->'=>'','<!--place_uh-->'=>'','<!--place_linacuh-->'=>'','<!--place_kgzc-->'=>'',
											'<!--badge_personal-->'=>'','<!--badge_host-->'=>'','<!--badge_search-->'=>'','<!--badge_id-->'=>'',
											'<!--dosimeter_personal-->'=>'','<!--dosimeter_host-->'=>'','<!--dosimeter_personal_host-->'=>'','<!--dosimeter_id-->'=>'','<!--dosimeter_value-->'=>''
											));
	$items_indoor = '';
	$is_null_exit_time = ($dbtype=="pg")? "exit_time IS NULL": "ISNULL(exit_time)";
	$data = $sql->sql_select("*", "access_$machine", "token>=0 AND $is_null_exit_time ORDER BY enter_time DESC");
	if (!empty($data)) foreach ($data as $row) {
		$replace['<!--start-->'] = substr($row['enter_time'],0,16);
		$replace['<!--name-->'] = $row['name'];
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
	$keys = array('<!--start-->', '<!--name-->', '<!--place-->', '<!--note-->', '<!--badge-->', '<!--badge_id-->', '<!--dosimeter-->', '<!--dosimeter_id-->', '<!--dosimeter_value-->', '<!--stop-->');
	$items = '';
	$data = $sql->sql_select("*", "access_$machine", "token>=0 AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT 500");
	// $stmt = $sql->sql_prepare("SELECT * FROM access_$machine WHERE token>=? AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT 500");
	// $params = array($stmt, "i", 0);
	// $stmt = $sql->sql_prepare("SELECT * FROM access_$machine WHERE token>=$1 AND NOT $is_null_exit_time ORDER BY enter_time DESC LIMIT 500", "q1");
	// $params = array(0);
	// $data = $sql->sql_execute($params, "q1");
	if (!empty($data)) foreach ($data as $row) {
		$replace['<!--start-->'] = substr($row['enter_time'],0,16);
		$replace['<!--stop-->'] = substr($row['exit_time'],0,16);
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
