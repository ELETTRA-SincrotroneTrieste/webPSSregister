<?php

// ----------------------------------------------------------------
//
// pss.php
//
// acs log viewer tool
//
// 14/12/2007 - LZ - First release
//
// ----------------------------------------------------------------

  // phpinfo();  exit();   echo "<pre>\ngd: "; var_dump(gd_info()); echo "</pre><p>\n";

  // in order to ease debugging comment or uncomment the following line: $debug = 1;
  // $debug = 1;
  if (isset($debug)) {echo "<pre>\nREQUEST: "; var_dump($_REQUEST); echo "</pre><p>\n";}

  // echo "<pre>\n_COOKIE: "; var_dump($_COOKIE); echo "</pre><p>\n";
  $script = $_SERVER["SCRIPT_NAME"];

  // require_once('./pss_conf.php');
  // define("HOST", "srv-db-srf");
  define("HOST", "srv-db-srf-02");
  define("USERNAME", "facro");
  define("PASSWORD", null);
  define("DB", "fermiaccesscontrol");

  $filter_select = '';
  $db = mysql_connect(HOST, USERNAME, PASSWORD);
  mysql_select_db(DB, $db);

	$time_buffer = array('L'=>'','H'=>'');
	$line_style = 0;

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
  // Quote variable to make safe
  function quote_smart($value)
  {
     // Stripslashes
     if (get_magic_quotes_gpc()) {
         $value = stripslashes($value);
     }
     strtr($value, 'ï¿½ï¿½`', '""'."'");
     // Quote if not integer
     if (!is_numeric($value)) {
         $value = "'".mysql_real_escape_string($value)."'";
     }
     return $value;
  }

  // ----------------------------------------------------------------
  // check access as administrator
  function check_admin_access()
  {
    global $debug, $script;
    $admin_ip = array(
      "140.105.5.32" => array("140.105.5.32", "0.0.0.0"),
      "140.105.4.3" => array("140.105.5.32", "140.105.5.214", "192.168.205.55"),
      "140.105.2.5" => array("140.105.5.32", "140.105.5.214", "192.168.205.55"),
      "140.105.8.30" => array("140.105.5.32", "140.105.5.214", "192.168.205.55"),
      "140.105.8.31" => array("140.105.5.32", "140.105.4.214", "192.168.205.55")
    );
    $remote = $_SERVER['REMOTE_ADDR'];
    $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
    // echo "remote ip: $remote, forwarded for: $forwarded<br />\n";
    /*
    if ($remote == "127.0.0.1") {
      return;
    }
    */
    foreach ($admin_ip as $ip => $f) {
      if ($forwarded) {
        foreach ($f as $forw) {
          if (($forwarded == $forw) and ($remote == $ip)) {
            return true;
          }
        }
      }
      else if ($remote == $ip) {
        return true;
      }
    }
    return false;
  }

	// ----------------------------------------------------------------
	// parse and detect time periods
	function parse_time($time) {
		if (strpos($time, 'last ')!== false) {
			$last = explode(' ', $time);
			$i = $n = 1;
			if (count($last) == 3) {
				$i = 2;
				$n = $last[1];
			}
			if (strpos($last[$i], "second")!==false) {
				$time_factor = 1;
			}
			else if (strpos($last[$i], "minute")!==false) {
				$time_factor = 60;
			}
			else if (strpos($last[$i], "hour")!==false) {
				$time_factor = 3600;
			}
			else if (strpos($last[$i], "day")!==false) {
				$time_factor = 86400;
			}
			else if (strpos($last[$i], "week")!==false) {
				$time_factor = 604800;
			}
			else if (strpos($last[$i], "month")!==false) {
				$time_factor = 2592000; // 30days
			}
			else if (strpos($last[$i], "year")!==false) {
				$time_factor = 31536000; // 365days
			}
			$t = time();
			return date('Y-m-d H:i:s', $t - $n*$time_factor - ($t % $time_factor));
		}
		return $time;
	}

	// ----------------------------------------------------------------
	// check presence of people
	function emit_presence() {
		global $debug, $script, $statquery, $admin;
		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:i:s", time()-600);
		$stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND plc_time<=UNIX_TIMESTAMP('".parse_time($_REQUEST["stopdate"])."')": "";
		// $db = mysql_connect(HOST, "eacro", "");
		// mysql_select_db("elettraaccesscontrol", $db);
		$yy = substr($startdate, 0, 4);
		$query = "SELECT CONCAT(FROM_UNIXTIME(plc_time-MOD(plc_time,1)),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, CONCAT('L ',position) AS position, present, name, time_id FROM linac_db50_$yy, enabled_user, linac_time_$yy WHERE plc_time>UNIX_TIMESTAMP('$startdate')$stopdate AND enabled_user.id=enabled_user_id AND time_id=linac_time_$yy.id"
			." UNION ". 
			"SELECT CONCAT(FROM_UNIXTIME(plc_time-MOD(plc_time,1)),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, CONCAT('U ',position) AS position, present, name, time_id FROM undulator_db50_$yy, enabled_user, undulator_time_$yy WHERE plc_time>UNIX_TIMESTAMP('$startdate')$stopdate AND enabled_user.id=enabled_user_id AND time_id=undulator_time_$yy.id ORDER BY time_id";
		$res = mysql_query($query);
		if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
		echo "<br><br>\n<h3>Presence</h3>\n<table class='table table-hover table-striped'> \n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>position</th><th>present</th><th>name</th></tr>\n";
		while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if (isset($_REQUEST['debug'])) {echo "<pre>\n";print_r($d);echo "</pre>\n";}
			unset($d['time_id']);
			echo "<tr>";
			foreach ($d as $arr) {
				echo "<td>$arr &nbsp; </td>";
			}
			echo "</tr>\n";
		}
		echo "</table><br><br><br><br><br>\n";
	}

  // ----------------------------------------------------------------
  // check access as administrator
  function emit_stat()
  {
    global $debug, $script, $statquery, $admin, $filter_select;
    // debug($_REQUEST);
    $id = 0;
    $ids = array();
    $time = 'plc_time'; // 'db_time'; //
    $csv_separator = ",";
    $startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:00:00", time()-3600);
    $stopdate = isset($_REQUEST["stopdate"])? parse_time($_REQUEST["stopdate"]): "";
    $signal = isset($_REQUEST["signal"])? $_REQUEST["signal"]: "";
    $offset = isset($_REQUEST["offset"])? $_REQUEST["offset"]: 0;
    $byte = (isset($_REQUEST["byte"])? $_REQUEST["byte"]: 1);
    $bit = (isset($_REQUEST["bit"])? $_REQUEST["bit"]: "");
    $year = substr($startdate, 0, 4);
    if (isset($_REQUEST["var"])) {
      $offset = 12;
      $byte = 312;
      $_REQUEST["oldval"] = true;
    }
    $leftbuffer = "<input name=\"search\" value=\"Search\" type=\"submit\" onClick=\"captureDialog()\"> &nbsp;<input value=\"Normal\" type=\"button\" onClick=\"implode()\"><br><br>Offset (first byte): <input name=\"offset\" type=\"text\" size=\"5\" value=\"$offset\"><br><br>Number of bytes: <input name=\"byte\" type=\"text\" size=\"5\" value=\"$byte\"><br><br>Bit: <input name=\"bit\" type=\"text\" size=\"5\" value=\"$bit\"><br><br>On variation: <input name=\"oldval\" type=\"checkbox\" ".(isset($_REQUEST["oldval"])? "CHECKED": "CHECKED")."> <br><br>";
    $leftshort = "<input name=\"var\" value=\"Search\" type=\"submit\" onClick=\"captureDialog()\"> &nbsp;
                  <input name=\"csv\" value=\"Export (csv)\" type=\"submit\" onClick=\"captureDialog()\"> &nbsp;
                  <!--input name=\"alarm_description\" value=\"Alarm Description\" type=\"submit\"> &nbsp;<br-->";
    $leftstart = $leftshort; // (isset($_REQUEST["var"]) or !isset($_REQUEST["startdate"]))? $leftshort: $leftbuffer;
    $rightstart = ""; // (isset($_REQUEST["var"]) or !isset($_REQUEST["startdate"]))? "": $names;
    // $stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND plc_time<=UNIX_TIMESTAMP('{$_REQUEST["stopdate"]}')": "";
    $stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND $time<=UNIX_TIMESTAMP('{$_REQUEST["stopdate"]}')": "";

    $filter_select = "<select name='filter_name'>\n<option value=''> </option>\n"; 
    $res = mysql_query("SELECT byte_number, bit_number, name, comment FROM linac_db51_descr ORDER BY name");
    // $res = mysql_query("SELECT byte_number, bit_number, name, comment FROM linac_db51_descr ORDER BY byte_number, bit_number");
    while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
      $linac_names[$r["byte_number"]][$r["bit_number"]] = $r["name"];
      $linac_comments[$r["byte_number"]][$r["bit_number"]] = $r["comment"];
      $filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - L{$r["byte_number"]}.{$r["bit_number"]}</option>\n";
    }
    // debug info                                                         <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<================================================
    // $linac_names[308][3] = "debug bit";
    // $linac_names[309][3] = "debug bit";
    // $linac_names[323][0] = "BST_L1_OPENCMD (debug)";
    // $res = mysql_query("SELECT byte_number, bit_number, name, comment FROM undulator_db51_descr ORDER BY byte_number, bit_number");
    $res = mysql_query("SELECT byte_number, bit_number, name, comment FROM undulator_db51_descr ORDER BY name");
    while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
      $undulator_names[$r["byte_number"]][$r["bit_number"]] = $r["name"];
      $undulator_comments[$r["byte_number"]][$r["bit_number"]] = $r["comment"];
      $filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - U{$r["byte_number"]}.{$r["bit_number"]}</option>\n";
    }
    $filter_select .= "</select>\n";
    if (!isset($_REQUEST["startdate"])) return;
    $linac_db51_old = $undulator_db51_old = array();
    //$res = mysql_query("SELECT 'linac' AS plc, CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, plc_time, byte_number, value FROM linac_db51_$year, linac_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')");
    // $linac_query = "SELECT 'linac' AS plc, FROM_UNIXTIME(plc_time) AS plc_time, plc_time AS t, FROM_UNIXTIME(db_time) AS db_time, byte_number, value FROM linac_db51_$year, linac_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate";
    // $undulator_query = "SELECT 'undulator' AS plc, FROM_UNIXTIME(plc_time) AS plc_time, plc_time AS t, FROM_UNIXTIME(db_time) AS db_time, byte_number, value FROM undulator_db51_$year, undulator_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate";
    $linac_query = "SELECT 'linac' AS plc, CONCAT(FROM_UNIXTIME(plc_time-MOD(plc_time,1)),SUBSTR(MOD(plc_time,1),2)) AS plc_time, plc_time AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, byte_number, value FROM linac_db51_$year, linac_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate";
    $undulator_query = "SELECT 'undulator' AS plc, CONCAT(FROM_UNIXTIME(plc_time-MOD(plc_time,1)),SUBSTR(MOD(plc_time,1),2)) AS plc_time, plc_time AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, byte_number, value FROM undulator_db51_$year, undulator_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate";
    if (isset($_REQUEST['debug'])) echo "<br><br><br>$undulator_query ORDER BY t, byte_number;<br>";
    if ($_REQUEST['plc']=='all') $res = mysql_query("$linac_query\nUNION\n$undulator_query\nORDER BY t, byte_number");
    if ($_REQUEST['plc']=='L') $res = mysql_query("$linac_query ORDER BY t, byte_number");
    if ($_REQUEST['plc']=='H') $res = mysql_query("$undulator_query ORDER BY t, byte_number");
    
		$big_data = array();
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			// debug($r); // continue;
			if ($r['plc']=='linac') {
				if (!isset($linac_db51_old[$r["byte_number"]])) {
					$old_res = mysql_query("SELECT value FROM linac_db51_$year, linac_time_$year WHERE time_id=id AND plc_time<{$r['t']} AND byte_number={$r["byte_number"]} ORDER BY plc_time DESC LIMIT 1");
					$old_r = mysql_fetch_array($old_res, MYSQL_ASSOC);
					$linac_db51_old[$r["byte_number"]] = isset($old_r["value"])? $old_r["value"]: 0;
				}
				for ($i=0; $i<8; $i++) {
					if ((pow(2,$i) & $r["value"])==(pow(2,$i) & $linac_db51_old[$r["byte_number"]])) continue;
					$stat = pow(2,$i) & $r["value"]? 1: 0;
					$big_data[] = array(
						'plc'=>'L',
						't'=>$r['t'],
						'plc_time'=>$r['plc_time'],
						'db_time'=>$r['db_time'],
						'val'=>$stat,
						'address'=>$r["byte_number"].'.'.$i,
						'name'=>isset($linac_names[$r["byte_number"]][$i])? $linac_names[$r["byte_number"]][$i]: '',
						'description'=>isset($linac_comments[$r["byte_number"]][$i])? $linac_comments[$r["byte_number"]][$i]: ''
					);
				} 
				$linac_db51_old[$r["byte_number"]] = $r["value"]; 
			}
			else if ($r['plc']=='undulator') {
				if (isset($_REQUEST['debug'])) debug($r);
				if (in_array($r["byte_number"], array(33,35,37,39))) continue;
				if (!isset($undulator_db51_old[$r["byte_number"]])) {
					$old_res = mysql_query("SELECT value FROM undulator_db51_$year, undulator_time_$year WHERE time_id=id AND plc_time<{$r['t']} AND byte_number={$r["byte_number"]} ORDER BY plc_time DESC LIMIT 1");
					$old_r = mysql_fetch_array($old_res, MYSQL_ASSOC);
					// echo "{$old_r["value"]}<br>";
					$undulator_db51_old[$r["byte_number"]] = isset($old_r["value"])? $old_r["value"]: 0;
				}
				// $diff = abs($r["value"] - $undulator_db51_old[$r["byte_number"]]); //???????????????????????????????????????????????????????????????????
				for ($i=0; $i<8; $i++) {
					// if (pow(2,$i) & $diff) { //???????????????????????????????????????????????????????????????????
					if ((pow(2,$i) & $r["value"])==(pow(2,$i) & $undulator_db51_old[$r["byte_number"]])) continue;
					$stat = pow(2,$i) & $r["value"]? 1: 0;
					$big_data[] = array(
						'plc'=>'U',
						't'=>$r['t'],
						'plc_time'=>$r['plc_time'],
						'db_time'=>$r['db_time'],
						'val'=>$stat,
						'address'=>$r["byte_number"].'.'.$i,
						'name'=>isset($undulator_names[$r["byte_number"]][$i])? $undulator_names[$r["byte_number"]][$i]: '',
						'description'=>isset($undulator_comments[$r["byte_number"]][$i])? $undulator_comments[$r["byte_number"]][$i]: ''
					);
					// } //???????????????????????????????????????????????????????????????????
				} 
				$undulator_db51_old[$r["byte_number"]] = $r["value"]; 
			}
		}
		return $big_data;
	}

	// ----------------------------------------------------------------
	// display line in table
	function emit_line($line) {
		global $script, $time_buffer, $line_style;
		$t = $line['plc_time'];
		$new_line = "<td>{$line['plc']}</td><td>{$line['address']}</td><td>".($line['val']?'':'NOT')."</td><td>{$line['name']}</td><td>{$line['description']}</td></tr>\n";
		if (!empty($_REQUEST['filter']) and (stripos($new_line, $_REQUEST['filter'])===false)) return;
		if ($time_buffer[$line['plc']]==$t) $t = '&nbsp;'; else {$time_buffer[$line['plc']]=$t;$line_style = 1 - $line_style;}
		echo "<tr class='".($line_style? 'info':'warning')."'><td>$t</td><td>{$line['db_time']}</td>$new_line";
	}


	// ----------------------------------------------------------------
	// export data in CSV
	function emit_csv($data1) {
		$csv = "PLC time,DB time,PLC,address,val,name,description\n";
		foreach ($data1 as $data1_line) {
			$new_line = "{$data1_line['plc_time']},{$data1_line['db_time']},{$data1_line['plc']},{$data1_line['address']},".($data1_line['val']?'':'NOT').",{$data1_line['name']},{$data1_line['description']}\n";
			$csv .= (empty($_REQUEST['filter']) or (stripos($new_line, $_REQUEST['filter'])!==false))? $new_line: '';
		}
		header("Content-Disposition: attachment; filename=pss.csv");
		header("Content-Type: application/x-csv");
		header("Content-Length: ".strlen($csv));
		echo $csv;
		exit();
	}

	// ----------------------------------------------------------------
	// display data in HTML
	function emit_data($data1) {
		global $debug, $script, $filter_select;
		// if (isset($_REQUEST['debug'])) {debug($data1); exit();}
		if (!empty($_REQUEST['filter_name'])) {$_REQUEST['filter'] = $_REQUEST['filter_name'];}
		if (isset($_REQUEST['export']) and ($_REQUEST['export']=='csv')) {emit_csv($data1);}
		$template = file_get_contents('./header_fermi.html');
		$replace = array("<!--startdate-->"=>$_REQUEST["startdate"],"<!--stopdate-->"=>$_REQUEST["stopdate"]);
		$replace['<!--L-->'] = $_REQUEST['plc']=='L'? ' checked': '';
		$replace['<!--H-->'] = $_REQUEST['plc']=='H'? ' checked': '';
		$replace['<!--all-->'] = $_REQUEST['plc']=='all'? ' checked': '';
		$replace['<!--filter-->'] = $_REQUEST['filter'];
		$replace['<!--filter_select-->'] = $filter_select;
		echo strtr($template, $replace);
		echo "<table class='table table-hover'>\n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>PLC</th><th>address</th><th>val</th><th>name</th><th>description</th></tr>\n";
		// header("Content-Type: application/json");
		// echo json_encode(array_merge($data1,$data2));
		if (!empty($data1)) foreach ($data1 as $data1_line) {
			emit_line($data1_line);
		}
		echo "</table>\n";
		if (isset($_REQUEST['debug'])) echo "emit_presence()<br>\n";
		emit_presence();
		echo "</div>\n</div>\n";
		readfile('./footer.html');
	}
  
  

	// ----------------------------------------------------------------
	// MAIN
	// ----------------------------------------------------------------
	if (!isset($_REQUEST['plc'])) $_REQUEST['plc']='all';
	emit_data(emit_stat());

?>
