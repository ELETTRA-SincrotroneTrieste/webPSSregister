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
$debug = isset($_REQUEST['debug']);
// if (isset($debug)) {echo "<pre>\nREQUEST: "; var_dump($_REQUEST); echo "</pre><p>\n";}

// echo "<pre>\n_COOKIE: "; var_dump($_COOKIE); echo "</pre><p>\n";
$script = $_SERVER["SCRIPT_NAME"];

require_once('./pss_conf.php');


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
	// check access as administrator
	function emit_stat_bo()
	{
		global $debug, $script, $statquery, $admin;
		if (isset($_REQUEST['debug'])) echo "<br><br>emit_stat_bo()<br><br>\n";

		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:i:s", time()-600);
		$stopdate = isset($_REQUEST["stopdate"])? parse_time($_REQUEST["stopdate"]): "";

		$db = mysql_connect(HOST, "eacro", "");
		mysql_select_db("elettraaccesscontrol", $db);
		$year = substr($startdate, 0, 4);
		$cmd = array();
		$query = "SELECT FROM_UNIXTIME(db_time) AS t, db_time, cmd FROM sr_cmd_$year WHERE cmd LIKE '%booster%' AND db_time>=UNIX_TIMESTAMP('$startdate')".(strlen($stopdate)? " AND db_time<UNIX_TIMESTAMP('$stopdate') ORDER BY db_time": '');
		if (isset($_REQUEST['debug'])) echo "<br><br>$query;<br><br>\n";
		$res = mysql_query($query);
		while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$cmd[] = array($d['db_time'],$d['t'],$d['cmd']);	
		}
		$cmd_index=0;
		if ($debug) {debug($d); debug($query); debug($cmd); /*exit(0);*/}



		$db = mysql_connect(HOST, "BACro", "Bacolo");
		mysql_select_db("BoostAccessCtrl", $db);
		if (isset($_REQUEST["query"])) {
			debug($_REQUEST["query"]);
			$res = mysql_query($_REQUEST["query"]);
			while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
				debug($r);
			}
			exit();
		}

		$specialChars = array("?"=>"");
		for ($i=0; $i<32; $i++) {
			$specialChars[chr($i)] = "";
		}
		$access_desc_string = array();
		$res = mysql_query("SELECT byte, name FROM access_desc_string ORDER BY byte");
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$access_desc_string[$r['byte'] / 16 -1] = $r['name'];
		}
		// debug($access_desc_string);
		$id = 0;
		$ids = array();

		$filter = "";
		$offset = isset($_REQUEST["offset"])? $_REQUEST["offset"]: 0;
		$byte = (isset($_REQUEST["byte"])? $_REQUEST["byte"]: 1);
		$bit = (isset($_REQUEST["bit"])? $_REQUEST["bit"]: "");
		$stroffset = 18;
		if (isset($_REQUEST["var"])) {
			$offset = 12;
			$byte = 332;
			$_REQUEST["oldval"] = true;
		}
		if (strlen($filter)) {
			if (isset($_REQUEST["var"])) {
				unset($_REQUEST["var"]);
			}
			$res = mysql_query("SELECT * FROM state_desc_bit WHERE name LIKE '%$filter%' OR descr LIKE '%$filter%'");
			while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
				$offsetArray[] = $r["byte"];
				$bitArray[] = $r["bit"];
				$ids[] = $r["id"];
			}
			if (is_array($offsetArray) and count($offsetArray)) {
				$offset = implode(",", $offsetArray);
				$bit = implode(",", $bitArray);
				$id = $_REQUEST["name"];
				$_REQUEST["names"] = implode(",", $ids);
			}
		}
		else if (isset($_REQUEST["names"]) and strlen($_REQUEST["names"]) > 0) {
			$res = mysql_query("SELECT * FROM state_desc_bit WHERE id IN ({$_REQUEST["names"]})");
			while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
				$offsetArray[] = $r["byte"];
				$bitArray[] = $r["bit"];
			}
			$offset = implode(",", $offsetArray);
			$bit = implode(",", $bitArray);
			$id = $_REQUEST["name"];
			$ids = explode(",", $_REQUEST["names"]);
		}
		$names = "Filters:<br><select name=\"name\" size=\"20\" onClick=\"clearfilter()\" multiple>";
		$res = mysql_query("SELECT id, name FROM state_desc_bit ORDER BY name");
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$sel = in_array($r["id"], $ids)? " selected": "";
			$names .= "<option value=\"{$r["id"]}\"$sel>{$r["name"]}</option>\n";
		}
		$names .= "</select>";
		$leftbuffer = "<input name=\"search\" value=\"Show\" type=\"submit\" onClick=\"captureDialog()\"> &nbsp;<input value=\"Normal\" type=\"button\" onClick=\"implode()\"><br><br>Offset (first byte): <input name=\"offset\" type=\"text\" size=\"5\" value=\"$offset\"><br><br>Number of bytes: <input name=\"byte\" type=\"text\" size=\"5\" value=\"$byte\"><br><br>Bit: <input name=\"bit\" type=\"text\" size=\"5\" value=\"$bit\"><br><br>On variation: <input name=\"oldval\" type=\"checkbox\" ".(isset($_REQUEST["oldval"])? "CHECKED": "CHECKED")."> <br><br>";
		$leftshort = "<input name=\"var\" value=\"Show\" type=\"submit\" onClick=\"captureDialog()\"> &nbsp;<input value=\"Advanced\" type=\"button\" onClick=\"expand()\"><br>";
		$leftstart = (isset($_REQUEST["var"]) or !isset($_REQUEST["startdate"]))? $leftshort: $leftbuffer;
		$rightstart = (isset($_REQUEST["var"]) or !isset($_REQUEST["startdate"]))? "": $names;
		if (!isset($_REQUEST["startdate"])) return;
		// if (strpos($offset, ",") !== false) {
		$offsetArray = explode(",", $offset);
		$bitArray = explode(",", $bit);
		$byteb = 2 * $byte;
		// $stopdateb = strlen($stopdate)>5? " AND FROM_UNIXTIME(timestamp) <= '$stopdate'": "";
		$stopdateb = strlen($stopdate)>5? " AND timestamp <= ".strtotime($stopdate): "";
		if (strlen($bit)) {
			foreach ($offsetArray as $i => $o) {
				$offsetb = 1 + 2 * $o + ($bitArray[$i]>3? 0: 1);
				$bit = 4 - ($bitArray[$i] % 4);
				$valArray[] = "SUBSTR(LPAD(CONV(SUBSTR(HEX(value) FROM $offsetb FOR 1),16,2), 4, '0') FROM $bit FOR 1)";
			}
		}
		else {
			foreach ($offsetArray as $i => $o) {
				$offsetb = 1 + 2 * $o;
				$valArray[] = "SUBSTR(HEX(value) FROM $offsetb FOR $byteb) ";
			}
		}
		$val = "CONCAT_WS(',',".implode(",", $valArray).")";
		$plcdate = "CONCAT('20',SUBSTR(HEX(value) FROM 11 FOR 2),'-',SUBSTR(HEX(value) FROM 9 FOR 2),'-', SUBSTR(HEX(value) FROM 15 FOR 2),' ',SUBSTR(HEX(value) FROM 13 FOR 2),':',SUBSTR(HEX(value) FROM 19 FOR 2),':',SUBSTR(HEX(value) FROM 17 FOR 2),'.',SUBSTR(HEX(value) FROM 23 FOR 2),SUBSTR(HEX(value) FROM 21 FOR 1)) AS PLC_Date";
		// $plcdate = "DB_Date AS PLC_Date";
		// $statquery = "SELECT CONCAT(FROM_UNIXTIME(timestamp), SUBSTR(timestamp MOD 1 FROM 2)) AS t, $plcdate, $val AS val FROM statemsg WHERE FROM_UNIXTIME(timestamp)>'$startdate'$stopdateb";
		$statquery = "SELECT 0 AS str, timestamp, FROM_UNIXTIME(timestamp) AS t, $plcdate, $val AS val FROM statemsg WHERE timestamp>".strtotime($startdate)."$stopdateb";
		// $statquery .= "UNION SELECT 1 AS str, FROM_UNIXTIME(timestamp) AS t, $plcdate, SUBSTR(value FROM $stroffset FOR 292) AS val FROM accessmsg WHERE FROM_UNIXTIME(timestamp)>'$startdate'$stopdateb ORDER BY PLC_Date";
		$statquery .= " UNION SELECT 1 AS str, timestamp, FROM_UNIXTIME(timestamp) AS t, $plcdate, SUBSTR(value FROM $stroffset FOR 292) AS val FROM accessmsg WHERE timestamp>".strtotime($startdate)."$stopdateb ORDER BY PLC_Date";
		if (isset($_REQUEST['debug'])) echo "<br><br>$statquery;<br><br>\n";
		$res = mysql_query($statquery);
		// echo "$statquery<br>";
		if (mysql_errno()) {
			echo "Error ".mysql_errno().": ".mysql_error()."<p>\n";
		}
		$oldval = "";
		$first = true;
		while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			// debug($d);
			if ($d["str"] == 1) {
				if (isset($_REQUEST["oldval"]) and $d["val"] == $oldvalStr) {
					continue;
				}
				// todo: treat case of search in DB accessstr, now skipped
				if (!isset($_REQUEST["var"])) {
					continue;
				}
				if (!$first) {
					$oldStr = str_split($oldvalStr, 16);
					$newStr = str_split($d["val"], 16);
					// debug($newStr);
					$df = $ds = "";
					foreach ($newStr as $i => $name) {   
						$nam = strtr($name, $specialChars);
						if (strlen($nam)) {
							$ds .= "{$access_desc_string[$i]}<br>\n";
							$df .= "$nam<br>\n";
							// debug(count_chars($name, 1));
						}
					}
					if (strlen($ds) == 0) {
						$ds = "[no badge in presence list]";
					}
				}
				$oldvalStr = $d["val"];
				continue;
			}
			if (isset($_REQUEST["oldval"]) and $d["val"] == $oldval) {
				continue;
			}
			$values = array();
			$diff = array();
			if (isset($_REQUEST["var"])) {
				for ($i=0; $i<strlen($oldval); $i++) {
				// for ($i=strlen($oldval)-1; $i>-0; $i--) {
					if ($d["val"][$i] !== $oldval[$i]) {
						$diff[$i+$offsetb-1] = array($oldval[$i], $d["val"][$i]);
					}
				}
				// debug($diff);
				$addr_buffer = 1000000;
				foreach ($diff as $i => $val) {
					if (floor($i/2) > $addr_buffer) {
						// debug($values_buffer);
						$values = array_merge($values, $values_buffer);
						$addr_buffer = 1000000;
						$values_buffer = array();
					}
					if ($i/2 > 327) {
						if ($i & 1 == 1) continue;
						list($newVal) = sscanf($val[1].$diff[$i+1][1], "%d");
						$byteq = mysql_query("SELECT name FROM state_desc_byte WHERE byte=".floor($i/2));
						$byte = mysql_fetch_array($byteq, MYSQL_ASSOC);
						$byteqdesc = mysql_query("SELECT message AS descr FROM msg_display WHERE code=$newVal");
						$bytedesc = mysql_fetch_array($byteqdesc, MYSQL_ASSOC);
						$address = "byte: ".floor($i/2).", bit: ".($k+4-($i%2)*4);
						$label = (isset($byte["name"]) and strlen($byte["name"]))? "<span title='$address' alt='$address'>{$byte["name"]}</span>": $address;
						$desc = "";
						if (isset($bytedesc["descr"]) and strlen($bytedesc["descr"])) {
							// $label = "<span title='{$byte["descr"]}' alt='{$byte["descr"]}'>$label</span>";
							$desc = $bytedesc["descr"];
						}
						$values[] = array('val'=>0,'address'=>floor($i/2).'.'.($k+4-($i%2)*4),'name'=>$byte["name"],'description'=>$desc);
						// $df[] = " $label<br>\n"; $ds[] = " $desc<br>\n";
						continue;
					}
					list($oldVal) = sscanf($val[0], "%x");
					list($newVal) = sscanf($val[1], "%x");
					// for ($j=8,$k=3; $k>=0; $j/=2, $k--) {
					for ($j=1,$k=0; $k<=3; $j*=2, $k++) {
						if (($oldVal & $j) !== ($newVal & $j)) {
							$byteq = mysql_query("SELECT name, descr FROM state_desc_bit WHERE byte=".floor($i/2)." AND bit=".($k+4-($i%2)*4));
							$byte = mysql_fetch_array($byteq, MYSQL_ASSOC);
							$address = "byte: ".floor($i/2).", bit: ".($k+4-($i%2)*4);
							$label = (isset($byte["name"]) and strlen($byte["name"]))? "<span title='$address' alt='$address'>{$byte["name"]}</span>": $address;
							// $label = (isset($byte["name"]) and strlen($byte["name"]))? $byte["name"]: "byte: ".floor($i/2).", bit=".($k+4-($i%2)*4);
							$desc = "";
							if (isset($byte["descr"]) and strlen($byte["descr"])) {
								// $label = "<span title='{$byte["descr"]}' alt='{$byte["descr"]}'>$label</span>";
								$desc = $byte["descr"];
							}
							if ($i%2 == 0) {
								$values_buffer[] = array('val'=>(($newVal & $j)? 1: 0),'address'=>floor($i/2).'.'.($k+4-($i%2)*4),'name'=>$byte["name"],'description'=>$desc);
								$addr_buffer = floor($i/2);
							}
							else {
								$values[] = array('val'=>(($newVal & $j)? 1: 0),'address'=>floor($i/2).'.'.($k+4-($i%2)*4),'name'=>$byte["name"],'description'=>$desc);
							}
							// $df[] = (($newVal & $j)? 1: 0)." $label<br>\n"; $ds[] = " $desc<br>\n";
							//echo floor($i/2).", $i, j: $j, k: $k, ".($k+4-($i%2)*4).", ".(($b & $j)? 1: 0)." {$byte["name"]}<br>\n";
						}
					}
				}
				// debug($values_buffer, 'values_buffer');
				$values = array_merge($values, $values_buffer);
				$addr_buffer = 1000000;
				$values_buffer = array();
			}
			$oldval = $d["val"];
			if ($first) {
				// Database date
				if (isset($_REQUEST["names"]) and strlen($_REQUEST["names"])) {
					$r = mysql_query("SELECT name, descr FROM state_desc_bit WHERE id IN ({$_REQUEST["names"]})");
					while ($c = mysql_fetch_array($r, MYSQL_ASSOC)) {
						$label = $c["name"];
						if (isset($c["descr"]) and strlen($c["descr"])) {
							$label = "<span title=\"{$c["descr"]}\" alt=\"{$c["descr"]}\">$label</span>";
						}
					}
				}
			}
			$first = false;
			foreach ($values as $f) {
				if (isset($cmd[$cmd_index]) and ($d['timestamp']>$cmd[$cmd_index][0])) {
					$big_data[] = array('plc'=>'bo','t'=>$cmd[$cmd_index][0],'plc_time'=>$cmd[$cmd_index][1],'db_time'=>$cmd[$cmd_index][1],'val'=>1,'address'=>'','name'=>$cmd[$cmd_index][2],'description'=>'');
					$cmd_index++;
				}
				$big_data[] = array_merge(array('plc'=>'bo','t'=>$d['timestamp'],'plc_time'=>$d['PLC_Date'],'db_time'=>$d['t']),$f);
			}	
			if (isset($cmd[$cmd_index]) and ($d['timestamp']>$cmd[$cmd_index][0])) {
				$big_data[] = array('plc'=>'bo','t'=>$cmd[$cmd_index][0],'plc_time'=>$cmd[$cmd_index][1],'db_time'=>$cmd[$cmd_index][1],'val'=>1,'address'=>'','name'=>$cmd[$cmd_index][2],'description'=>'');
				$cmd_index++;
			}
		}
		if (isset($cmd[$cmd_index])) {
			$big_data[] = array('plc'=>'bo','t'=>$cmd[$cmd_index][0],'plc_time'=>$cmd[$cmd_index][1],'db_time'=>$cmd[$cmd_index][1],'val'=>1,'address'=>'','name'=>$cmd[$cmd_index][2],'description'=>'');
		}
		return $big_data;
	}


	// ----------------------------------------------------------------
	// check presence of people in booster
	function emit_presence_bo() {
		global $debug, $script, $statquery, $admin;
		$db = mysql_connect(HOST, "BACro", "Bacolo");
		mysql_select_db("BoostAccessCtrl", $db);
		$trusted_time = 'db_time'; // should be plc_time, but substituted by db_time when not satisfactory
		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:i:s", time()-600);
		$stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND $trusted_time<=UNIX_TIMESTAMP('".parse_time($_REQUEST["stopdate"])."')": "";
		$yy = substr($startdate, 0, 4);
		$query = "SELECT FROM_UNIXTIME(plc_time) AS plc_t, FROM_UNIXTIME(db_time) AS db_t, CONCAT(position) AS position, present, name, time_id FROM booster_db50_$yy, enabled_user, booster_time_$yy WHERE $trusted_time>UNIX_TIMESTAMP('$startdate')$stopdate AND enabled_user.id=enabled_user_id AND time_id=booster_time_$yy.id ORDER BY time_id";
		if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
		$res = mysql_query($query);
		if (!$res) echo mysql_error();
		echo "<br><br>\n<h3>Booster presence</h3>\n<table class='table table-hover table-striped'> \n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>door</th><th>present</th><th>name</th></tr>\n";
		if (!empty($res)) while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if (isset($_REQUEST['debug'])) debug($d);
			unset($d['time_id']);
			if ($d['position']>=16) continue;
			echo "<tr>";
			foreach ($d as $k=>$arr) {
				$v = $arr;
				if ($k=='position') $v = $arr<8? 'Booster A1': 'Preiniettore A0';
				echo "<td>$v &nbsp; </td>";
			}
			echo "</tr>\n";
		}
		echo "</table><br><br><br><br><br>\n";
	}

	// ----------------------------------------------------------------
	// check access to booster
	function emit_stat_bo_new() {
		global $debug, $script, $statquery, $admin;
		$db = mysql_connect(HOST, "BACro", "Bacolo");
		mysql_select_db("BoostAccessCtrl", $db);

		if (isset($_REQUEST["startdate"]) and ($_REQUEST["startdate"]=='lastday')) {
			$_REQUEST["startdate"] = date("Y-m-d H:00:00", time()-86400);
		}
		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:00:00", time());
		$stopdate = isset($_REQUEST["stopdate"])? parse_time($_REQUEST["stopdate"]): "";
		$filter = isset($_REQUEST["filter"])? $_REQUEST["filter"]: "";
		$offset = isset($_REQUEST["offset"])? $_REQUEST["offset"]: 0;
		$byte = (isset($_REQUEST["byte"])? $_REQUEST["byte"]: 1);
		$bit = (isset($_REQUEST["bit"])? $_REQUEST["bit"]: "");
		$year = substr($startdate, 0, 4);
		if (isset($_REQUEST["var"])) {
			$offset = 12;
			$byte = 312;
			$_REQUEST["oldval"] = true;
		}
		if (!isset($_REQUEST["startdate"])) return;
		$stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND plc_time<=UNIX_TIMESTAMP('{$_REQUEST["stopdate"]}')": "";
		$query = "SELECT byte_number, bit_number, name, comment FROM booster_db51_descr ORDER BY byte_number, bit_number";
		$res = mysql_query($query);
		if ($debug) {echo "<!--\n"; debug($query); debug($res); echo "\n-->\n";}
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$var_names[$r["byte_number"]][$r["bit_number"]] = $r["name"];
			$var_desc[$r["byte_number"]][$r["bit_number"]] = $r["comment"];
			$filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - L{$r["byte_number"]}.{$r["bit_number"]}</option>\n";
		}
		// $var_names[308][3] = "debug bit";
		// $var_names[309][3] = "debug bit";
		// $var_names[323][0] = "BST_L1_OPENCMD (debug)";
		$order_by = isset($_REQUEST['order_by'])? quote_smart($_REQUEST['order_by'], ''): 'plc_time, db_time';
		$c = explode(',', $order_by);
		$order_cond = $c[0];
		// $query = $year>2015? "SELECT 'bo' AS plc, FROM_UNIXTIME(db_time) AS plc_time, db_time AS t, FROM_UNIXTIME(db_time) AS db_time, -1 AS byte_number, cmd AS value FROM bo_cmd_$year WHERE cmd LIKE '%anello%' AND db_time>UNIX_TIMESTAMP('$startdate')".strtr($stopdate,array('plc_time'=>'db_time'))." UNION " : '';
		$query = "SELECT 'bo' AS plc, FROM_UNIXTIME(plc_time) AS plc_time, plc_time AS t, FROM_UNIXTIME(db_time) AS db_time, byte_number, value FROM booster_db51_$year, booster_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate ORDER BY $order_by, byte_number";
		if (isset($_REQUEST['debug'])) echo "<br><br>$query;<br><br>\n";
		$res = mysql_query($query);
		if ($debug) {echo "<!--\n"; debug($query); echo "\n-->\n";}
		$big_data = array();
		$linac_db51_old = array();
		$time = 'plc_time';
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if ($debug) {echo "<!--\n"; debug($r); echo "\n-->\n";}
			if ($r["byte_number"]==-1) {
				$big_data[] = array(
					'plc'=>'bo',
					't'=>$r['t'],
					'plc_time'=>$r['plc_time'],
					'db_time'=>$r['db_time'],
					'val'=>1,
					'address'=>'',
					'name'=> $r["value"],
					'description'=>''
				);
				continue;
			}
			if (!isset($linac_db51_old[$r["byte_number"]])) {
				$query = "SELECT value, $order_by FROM booster_db51_$year, booster_time_$year WHERE time_id=id AND $order_cond<UNIX_TIMESTAMP('{$r[$order_cond]}') AND byte_number={$r["byte_number"]} ORDER BY plc_time DESC LIMIT 1";
				$old_res = mysql_query($query);
				if ($debug) {echo "<!--\n"; debug($query); echo "\n-->\n";}
				$old_r = mysql_fetch_array($old_res, MYSQL_ASSOC);
				if ($debug) {echo "<!--\n $order_by"; debug($r); echo "\n-->\n";}
				$linac_db51_old[$r["byte_number"]] = isset($old_r["value"])? $old_r["value"]: 0;
			}
			for ($i=0; $i<8; $i++) {
				if ((pow(2,$i) & $r["value"])==(pow(2,$i) & $linac_db51_old[$r["byte_number"]])) continue;
				$stat = pow(2,$i) & $r["value"]? 1: 0;
				$big_data[] = array(
					'plc'=>'bo',
					't'=>$r['t'],
					'plc_time'=>$r['plc_time'],
					'db_time'=>$r['db_time'],
					'val'=>$stat,
					'address'=>$r["byte_number"].'.'.$i,
					'name'=>isset($var_names[$r["byte_number"]][$i])? $var_names[$r["byte_number"]][$i]: '',
					'description'=>$var_desc[$r["byte_number"]][$i]
				);
			} 
			$linac_db51_old[$r["byte_number"]] = $r["value"]; 
		}
		if ($debug) {echo "<!--\n"; debug($big_data, 'big_data'); echo "\n-->\n";}
		return $big_data;
	}

	// ----------------------------------------------------------------
	// check presence of people
	function emit_presence_sr() {
		global $debug, $script, $statquery, $admin;
		$db = mysql_connect(HOST, "eacro", "");
		mysql_select_db("elettraaccesscontrol", $db);

		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:i:s", time()-600);
		$stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND plc_time<=UNIX_TIMESTAMP('".parse_time($_REQUEST["stopdate"])."')": "";
		$yy = substr($startdate, 0, 4);
		
		// BRIDGE
		$query = "SELECT FROM_UNIXTIME(plc_time) AS plc_t, FROM_UNIXTIME(db_time) AS db_t, CONCAT(position) AS position, present, name, time_id FROM sr_db49_$yy, enabled_user, sr_time_$yy WHERE plc_time>UNIX_TIMESTAMP('$startdate')$stopdate AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id ORDER BY time_id";
		if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
		$res = mysql_query($query);
		if (!$res) echo mysql_error();
		echo "<br><br>\n<h3>Bridge presence</h3>\n<table class='table table-hover table-striped'> \n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>position</th><th>present</th><th>name</th></tr>\n";
		if (!empty($res)) while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if (isset($_REQUEST['debug'])) debug($d);
			unset($d['time_id']);
			if (($d['position']>=64) && !isset($_REQUEST['debug'])) continue;
			echo "<tr>";
			foreach ($d as $k=>$arr) {
				// if ($k=='position') continue;
				echo "<td>$arr &nbsp; </td>";
			}
			echo "</tr>\n";
		}
		echo "</table><br><br><br><br><br>\n";

		// SR
		$query = "SELECT FROM_UNIXTIME(plc_time) AS plc_t, FROM_UNIXTIME(db_time) AS db_t, CONCAT(position) AS position, present, name, time_id FROM sr_db50_$yy, enabled_user, sr_time_$yy WHERE plc_time>UNIX_TIMESTAMP('$startdate')$stopdate AND enabled_user.id=enabled_user_id AND time_id=sr_time_$yy.id ORDER BY time_id";
		if (isset($_REQUEST['debug'])) echo "$query;<br>\n";
		$res = mysql_query($query);
		if (!$res) echo mysql_error();
		echo "<br><br>\n<h3>SR presence</h3>\n<table class='table table-hover table-striped'> \n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>door</th><th>present</th><th>name</th></tr>\n";
		if (!empty($res)) while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if (isset($_REQUEST['debug_sr'])) debug($d);
			unset($d['time_id']);
			if ($d['position']>=16) continue;
			echo "<tr>";
			foreach ($d as $k=>$arr) {
				$v = $arr;
				if ($k=='position') $v = $arr<8? 'A2': 'A3';
				echo "<td>$v &nbsp; </td>";
			}
			echo "</tr>\n";
		}
		echo "</table><br><br><br><br><br>\n";	
	}


	// ----------------------------------------------------------------
	// check access to SR
	function emit_stat_sr()
	{
		global $debug, $script, $statquery, $admin;

		$db = mysql_connect(HOST, "eacro", "");
		mysql_select_db("elettraaccesscontrol", $db);

		if (isset($_REQUEST["startdate"]) and ($_REQUEST["startdate"]=='lastday')) {
			$_REQUEST["startdate"] = date("Y-m-d H:00:00", time()-86400);
		}
		$startdate = isset($_REQUEST["startdate"])? parse_time($_REQUEST["startdate"]): date("Y-m-d H:00:00", time()-3600);
		$stopdate = isset($_REQUEST["stopdate"])? parse_time($_REQUEST["stopdate"]): "";
		$filter = isset($_REQUEST["filter"])? $_REQUEST["filter"]: "";
		$offset = isset($_REQUEST["offset"])? $_REQUEST["offset"]: 0;
		$byte = (isset($_REQUEST["byte"])? $_REQUEST["byte"]: 1);
		$bit = (isset($_REQUEST["bit"])? $_REQUEST["bit"]: "");
		$year = substr($startdate, 0, 4);
		if (isset($_REQUEST["var"])) {
			$offset = 12;
			$byte = 312;
			$_REQUEST["oldval"] = true;
		}
		if (!isset($_REQUEST["startdate"])) return;
		$stopdate = (isset($_REQUEST["stopdate"]) and strlen($_REQUEST["stopdate"]))? " AND plc_time<=UNIX_TIMESTAMP('{$_REQUEST["stopdate"]}')": "";
		$res = mysql_query("SELECT byte_number, bit_number, name, comment FROM sr_db51_descr ORDER BY byte_number, bit_number");
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$var_names[$r["byte_number"]][$r["bit_number"]] = $r["name"];
			$var_desc[$r["byte_number"]][$r["bit_number"]] = $r["comment"];
			$filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - L{$r["byte_number"]}.{$r["bit_number"]}</option>\n";
		}
		// $var_names[308][3] = "debug bit";
		// $var_names[309][3] = "debug bit";
		// $var_names[323][0] = "BST_L1_OPENCMD (debug)";
		$order_by = isset($_REQUEST['order_by'])? quote_smart($_REQUEST['order_by'], ''): 'plc_time, db_time';
		$c = explode(',', $order_by);
		$order_cond = $c[0];
		$query = $year>2015? "SELECT 'sr' AS plc, FROM_UNIXTIME(db_time) AS plc_time, db_time AS t, FROM_UNIXTIME(db_time) AS db_time, -1 AS byte_number, cmd AS value FROM sr_cmd_$year WHERE cmd LIKE '%anello%' AND db_time>UNIX_TIMESTAMP('$startdate')".strtr($stopdate,array('plc_time'=>'db_time'))." UNION " : '';
		$query .= "SELECT 'sr' AS plc, FROM_UNIXTIME(plc_time) AS plc_time, plc_time AS t, FROM_UNIXTIME(db_time) AS db_time, byte_number, value FROM sr_db51_$year, sr_time_$year WHERE time_id=id AND plc_time>UNIX_TIMESTAMP('$startdate')$stopdate ORDER BY $order_by, byte_number";
		$res = mysql_query($query);
		if ($debug) {echo "<!--\n"; debug($query); echo "\n-->\n";}
		$big_data = array();
		$linac_db51_old = array();
		$time = 'plc_time';
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			if ($debug) {echo "<!--\n"; debug($r); echo "\n-->\n";}
			if ($r["byte_number"]==-1) {
				$big_data[] = array(
					'plc'=>'sr',
					't'=>$r['t'],
					'plc_time'=>$r['plc_time'],
					'db_time'=>$r['db_time'],
					'val'=>1,
					'address'=>'',
					'name'=> $r["value"],
					'description'=>''
				);
				continue;
			}
			if (!isset($linac_db51_old[$r["byte_number"]])) {
				$query = "SELECT value, $order_by FROM sr_db51_$year, sr_time_$year WHERE time_id=id AND $order_cond<UNIX_TIMESTAMP('{$r[$order_cond]}') AND byte_number={$r["byte_number"]} ORDER BY plc_time DESC LIMIT 1";
				$old_res = mysql_query($query);
				if ($debug) {echo "<!--\n"; debug($query); echo "\n-->\n";}
				$old_r = mysql_fetch_array($old_res, MYSQL_ASSOC);
				if ($debug) {echo "<!--\n $order_by"; debug($r); echo "\n-->\n";}
				$linac_db51_old[$r["byte_number"]] = isset($old_r["value"])? $old_r["value"]: 0;
			}
			for ($i=0; $i<8; $i++) {
				if ((pow(2,$i) & $r["value"])==(pow(2,$i) & $linac_db51_old[$r["byte_number"]])) continue;
				$stat = pow(2,$i) & $r["value"]? 1: 0;
				$big_data[] = array(
					'plc'=>'sr',
					't'=>$r['t'],
					'plc_time'=>$r['plc_time'],
					'db_time'=>$r['db_time'],
					'val'=>$stat,
					'address'=>$r["byte_number"].'.'.$i,
					'name'=>isset($var_names[$r["byte_number"]][$i])? $var_names[$r["byte_number"]][$i]: '',
					'description'=>$var_desc[$r["byte_number"]][$i]
				);
			} 
			$linac_db51_old[$r["byte_number"]] = $r["value"]; 
		}
		if ($debug) {echo "<!--\n"; debug($big_data); echo "\n-->\n";}
		return $big_data;
	}


	$time_buffer = array('bo'=>'','sr'=>'');
	$line_style = 0;


	// ----------------------------------------------------------------
	// eval filter select
	function eval_filter() {
		$filter_select = "<select name='filter_name'>\n<option value=''> </option>\n"; 
		$db = mysql_connect(HOST, "eacro", "");
		mysql_select_db("elettraaccesscontrol", $db);
		// $res = mysql_query("SELECT byte_number, bit_number, name, comment FROM sr_db51_descr ORDER BY byte_number, bit_number");
		$res = mysql_query("SELECT byte_number, bit_number, name, comment FROM sr_db51_descr ORDER BY name");
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - SR {$r["byte_number"]}.{$r["bit_number"]}</option>\n";
		}
		$db = mysql_connect(HOST, "BACro", "Bacolo");
		mysql_select_db("BoostAccessCtrl", $db);
		// $res = mysql_query("SELECT byte, name FROM access_desc_string ORDER BY byte");
		$res = mysql_query("SELECT * FROM state_desc_bit ORDER BY name");
		while ($r = mysql_fetch_array($res, MYSQL_ASSOC)) {
			$access_desc_string[$r['byte'] / 16 -1] = $r['name'];
			$filter_select .= "<option value='{$r["name"]}'>{$r["name"]} - BO {$r["byte"]}.{$r["bit"]}</option>\n";
		}
		return "$filter_select</select>\n";
	}

	// ----------------------------------------------------------------
	// display line in table
	function emit_line($line) {
		global $debug, $script, $time_buffer, $line_style;
		$t = $line['plc_time'];
		$new_line = "<td>{$line['db_time']}</td><td>{$line['plc']}</td><td>{$line['address']}</td><td>".($line['val']?'':'NOT')."</td><td>{$line['name']}</td><td>{$line['description']}</td></tr>\n";
		if (!empty($_REQUEST['filter']) and (stripos($new_line, $_REQUEST['filter'])===false)) return;
		if ($time_buffer[$line['plc']]==$t) $t = '&nbsp;'; else {$time_buffer[$line['plc']]=$t;$line_style = 1 - $line_style;}
		echo "<tr class='".($line_style? 'info':'warning')."'><td>$t</td>$new_line";
	}

	// ----------------------------------------------------------------
	// export data in CSV
	function emit_csv($data1,$data2) {
		$csv = "PLC time,DB time,PLC,address,val,name,description\n";
		$data2_counter = 0;
		$data2_max = count($data2);
		foreach ($data1 as $data1_line) {
			while (($data1_line['t']>$data2[$data2_counter]['t']) and ($data2_counter<$data2_max)) {
				$data2_line = $data2[$data2_counter];
				$new_line = "{$data2_line['plc_time']},{$data2_line['db_time']},{$data2_line['plc']},{$data2_line['address']},".($data2_line['val']?'':'NOT').",{$data2_line['name']},{$data2_line['description']}\n";
				$csv .= (empty($_REQUEST['filter']) or (stripos($new_line, $_REQUEST['filter'])!==false))? $new_line: '';
				$data2_counter++;
			}
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
	function emit_data($data1,$data2) {
		global $debug, $script;
		if (!empty($_REQUEST['filter_name'])) {$_REQUEST['filter'] = $_REQUEST['filter_name'];}
		if (isset($_REQUEST['export']) and ($_REQUEST['export']=='csv')) {emit_csv($data1,$data2);}
		$template = file_get_contents('./header_elettra.html');
		$replace = array("<!--startdate-->"=>$_REQUEST["startdate"],"<!--stopdate-->"=>$_REQUEST["stopdate"]);
		$replace['<!--sr-->'] = $_REQUEST['plc']=='sr'? ' checked': '';
		$replace['<!--bo-->'] = $_REQUEST['plc']=='bo'? ' checked': '';
		$replace['<!--all-->'] = $_REQUEST['plc']=='all'? ' checked': '';
		$replace['<!--filter-->'] = $_REQUEST['filter'];
		$replace['<!--filter_select-->'] = eval_filter();
		echo strtr($template, $replace);
		echo "\n<table class='table table-hover'>\n";
		echo "\n<tr><th>PLC time</th><th>DB time</th><th>PLC</th><th>address</th><th>val</th><th>name</th><th>description</th></tr>\n";
		// header("Content-Type: application/json");
		// echo json_encode(array_merge($data1,$data2));
		$data2_counter = 0;
		$data2_max = count($data2);
		if (!empty($data1)) foreach ($data1 as $data1_line) {
			while (($data1_line['t']>$data2[$data2_counter]['t']) and ($data2_counter<$data2_max)) {
				emit_line($data2[$data2_counter]);
				$data2_counter++;
			}
			emit_line($data1_line);
		}
		echo "</table>\n";
		emit_presence_sr();
		emit_presence_bo();
		echo "</div>\n</div>\n";
		readfile('./footer.html');
	}



// ----------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------
$data = array('bo'=>array(), 'sr'=>array());
if (!isset($_REQUEST['plc'])) $_REQUEST['plc']='all';
if (($_REQUEST['plc']=='all') or ($_REQUEST['plc']=='bo')) $data['bo'] = !isset($_REQUEST['oldbooster'])? emit_stat_bo_new(): emit_stat_bo();
if (($_REQUEST['plc']=='all') or ($_REQUEST['plc']=='sr')) {
	$data['sr'] = emit_stat_sr();
	emit_data($data['sr'],$data['bo']);
} 
else
	emit_data($data['bo'],$data['sr']);

// debug($_REQUEST);


?>
