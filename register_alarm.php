<?php

	require('./register_conf.php');
	$db = mysqli_connect(HOST2, USERNAME2, PASSWORD2, DB);

	// ----------------------------------------------------------------
	// Quote variable to make safe
	function quote_smart($value)
	{
		global $db;
		// Stripslashes
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		strtr($value, '&#147;&#148;`', '""'."'");
		// Quote if not integer
		if (!is_numeric($value)) {
			$value = "'".mysqli_real_escape_string($db, $value)."'";
		}
		return $value;
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

	$color = 'green';
	$err_msg = '';
	$query = "SELECT * FROM access_$machine WHERE ISNULL(exit_time) AND token>0 AND TIME_TO_SEC(TIMEDIFF(NOW(),enter_time))>86400";
	$res = mysqli_query($db, $query);
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
		$color = (($color=='red') or (!empty($row)))? 'red': 'green';
		if (!empty($row)) $err_msg .= "{$row['name']} in macchina da oltre 24 ore. ";
		if (isset($_REQUEST['debug'])) debug($row);
	}

    $startdate = time()-86400*3;
    $yy = date("Y");
    $query = 
    "SELECT CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'L' AS machine, position, present, name, time_id FROM linac_db50_$yy, enabled_user, linac_time_$yy WHERE position<16 AND plc_time>$startdate AND enabled_user.id=enabled_user_id AND time_id=linac_time_$yy.id"
    ." UNION ". 
    "SELECT CONCAT(FROM_UNIXTIME(plc_time),SUBSTR(MOD(plc_time,1),2)) AS t, CONCAT(FROM_UNIXTIME(db_time),SUBSTR(MOD(db_time,1),2)) AS db_time, 'U' AS machine, position, present, name, time_id FROM undulator_db50_$yy, enabled_user, undulator_time_$yy WHERE position<16 AND plc_time>$startdate AND enabled_user.id=enabled_user_id AND time_id=undulator_time_$yy.id ORDER BY time_id";
	if (isset($_REQUEST['debug'])) debug($query);
	$res = mysqli_query($db, $query);
	while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
		if ($row['present']=='Y') {
			if (isset($_REQUEST['debug'])) debug($row);
			$name = array_search(trim($row['name']), $user);
			if ($name) {
				$query = "SELECT * FROM access_$machine WHERE name='$name' AND enter_time<'{$row['db_time']}' AND (ISNULL(exit_time) OR exit_time>'{$row['db_time']}')";
			}
			else {
				$query = "SELECT * FROM access_$machine WHERE STRING_CONCAT(dosimeter_type,'$machine',dosimeter_number)='{$row['name']}' AND enter_time<'{$row['db_time']}' AND (ISNULL(exit_time) OR exit_time>'{$row['db_time']}')";
			}
			$res2 = mysqli_query($db, $query);
			$row2 = mysqli_fetch_array($res2, MYSQLI_ASSOC);
			if (isset($_REQUEST['debug'])) debug($row2);
			$color = (($color=='red') or empty($row2))? 'red': 'green';
			if (empty($row2)) $err_msg .= "{$row['name']} entrato in macchina ma assente dal registro. ";
		}
	}
	echo "<img src='./img/led_$color.png' alt='$err_msg' title='$err_msg'>";

?>
