<?php
// mysql -h srv-db-srf -u facrw -pFaCRWxField-2013 fermiaccesscontrol
// mysql -h log.ecs.elettra.trieste.it -u eacrw -pAccessControlRW elettraaccesscontrol

	$loginService = 'http://www.elettra.eu/service/pss/pss_login.php';
	$operator_url = 'http://www.elettra.eu/service/export/lista_operatori.php?b2kZue6jgilGir0UDfbb1rDS';

	$master_ip = array('LZ'=>'192.168.205.47');
	$backoffice_ip = array('AV'=>'172.19.110.238','KC'=>'172.19.110.79','GR'=>'172.19.110.92');
	$controlroom_ip = array('AV'=>'172.19.110.238','ca-fermi'=>'192.168.231.20','do'=>'192.168.231.21','re'=>'192.168.231.22','mi'=>'192.168.231.23','fa'=>'192.168.231.24','sol'=>'192.168.231.25','la'=>'192.168.231.26','si'=>'192.168.231.27',
							'wilma'=>'192.168.204.13','dino'=>'192.168.204.15','bambam'=>'192.168.204.17',
							'fred'=>'192.168.204.12','pebbles'=>'192.168.204.18','barney'=>'192.168.204.16','ante'=>'192.168.204.19');

    $remote_ip = $_SERVER['REMOTE_ADDR'];

	$multi_number = 12;
	$user = array(
		'maurizio.bossi'=>'maurizio.bossi',
		'antonio.conte'=>'antonio.conte',
		'andrea.allemandi'=>'a.allemandi',
		'vincenzo.ingravallo'=>'v.ingravallo',
		'katia.casarin'=>'k.casarin',
		'roberto.godnig'=>'r.godnig',
		'andrea.milocco'=>'a.milocco',
		'giuliano.medessi'=>'g.medessi',
		'gerardo.dauria'=>'g.d_auria',
		'massimo.milloch'=>'m.milloch',
		'andrea.pozzer'=>'a.pozzer',
		'cristina.pasotti'=>'c.pasotti',
		'stefano.krecic'=>'s.krecic',
		'alessandro.coronica'=>'a.coronica',
		'riccardo.gobessi'=>'r.gobessi',
		'massimo.vento'=>'m.vento',
		'salvatore.sbarra'=>'s.sbarra',
		'pietro.zupancich'=>'p.zupancich',
		'mauro.bocciai'=>'m.bocciai',
		'paolo.delgiusto'=>'p.del_giusto',
		'massimiliano.pelko'=>'m.pelko',
		'piergiorgio.tosolini'=>'p.tosolini',
		'andrea.apollonio'=>'a.apollonio',
		'piergiorgio.borsi'=>'p.borsi',
		'luca.veljak'=>'l.veljak',
		'nicola.sodomaco'=>'n.sodomaco',
		'fabrizio.lauro'=>'f.lauro',
		'diego.vittor'=>'d.vittor',
		'roberto.visintini'=>'r.visintini',
		'maurizio.zaccaria'=>'m.zaccaria',
		'mauro.rinaldi'=>'m.rinaldi',
		'marco.veronese'=>'m.veronese',
		'luca.bortolossi'=>'l.bortolossi',
		'federico.pribaz'=>'f.pribaz',
		'emanuel.karantzoulis'=>'e.karantzoulis',
		'walter.cortivo'=>'w.cortivo',
		'andrea.vescovo'=>'a.vescovo',
		'raoul.giovanoni'=>'r.giovanoni',
		'luca.vidotto'=>'l.vidotto',
		'luca.rumiz'=>'l.rumiz',
		'silvano.bassanese'=>'s.bassanese',
		'diego.lonza'=>'d.lonza',
		'furio.zudini'=>'f.zudini',
		'rocco.umer'=>'r.umer',
		'federico.gelmetti'=>'f.gelmetti',
		'raffaele.demonte'=>'r.de_monte',
		'giuliana.tromba'=>'g.tromba',
		'michele.pasqualetto'=>'m.pasqualetto',
		'enzo.benfatto'=>'e.benfatto',
		'marco.cautero'=>'m.cautero',
		'claudio.serpico'=>'c.serpico',
		'marco.folla'=>'m.folla',
		'alessandro.carniel'=>'a.carniel',
		'roberto.bracco'=>'r.bracco',
		'daniele.millo'=>'d.millo',
		'alessandro.vascotto'=>'a.vascotto'
	);
	$machine = isset($_REQUEST['machine'])? $_REQUEST['machine']: (strpos($_SERVER["HTTP_HOST"],'fcsproxy')!==false? 'fermi': 'elettra');
	if ($machine == 'elettra') {
		$db = mysqli_connect('log.ecs.elettra.trieste.it', 'eacrw', 'AccessControlRW');
		mysqli_select_db($db, 'elettraaccesscontrol');
	}
	else if ($machine == 'fermi') {
		$db = mysqli_connect('srv-db-srf', 'facrw', 'FaCRWxField-2013');
		// $db = mysqli_connect('srv-db-srf', 'facro', '');
		mysqli_select_db($db, 'fermiaccesscontrol');
	}
	$elog = $machine=='fermi'? 'felog': 'elog';

	if (in_array($remote_ip, $master_ip)) {
		if (isset($_REQUEST['debuglog'])) {
			$query = "SELECT * FROM access_{$machine}_log";
			$res = mysqli_query($db, $query);
			echo "\n$machine\n<pre>\n";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				print_r($row);
			}
			echo "\n</pre>\n";
		}
		if (isset($_REQUEST['query'])) {
			$res = mysqli_query($db, $_REQUEST['query']);
			echo "\n$machine\n<pre>\n";
			while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
				print_r($row);
			}
			echo "\n</pre>\n";
		}
	}

	// if (!in_array($remote_ip, $master_ip+$controlroom_ip)) die("Accesso non autorizzato");

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
