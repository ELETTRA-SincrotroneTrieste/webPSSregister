<?php

  define("HOST", "192.168.204.100");
  define("USERNAME", "BACuser");
  define("PASSWORD", "CABuser");
  define("DB", "BoostAccessCtrl");
  define("BGCOLOR", "#fff0f0");

  // echo "<pre>\n_COOKIE: "; var_dump($_COOKIE); echo "</pre><p>\n";
  $script = $_SERVER["SCRIPT_NAME"];
  $varArray = array('eff','i0st','iacc','iboo','ibsr','iinj','ilostlt','ilostst','isr');
  $times = array();
  $data = array();

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
     strtr($value, '“”`', '""'."'");
     // Quote if not integer
     if (!is_numeric($value)) {
         $value = "'".mysql_real_escape_string($value)."'";
     }
     return $value;
  }

  $db = mysql_connect(HOST, USERNAME, PASSWORD);
  mysql_select_db(DB, $db);


  // ----------------------------------------------------------------
  // get_data
  function get_data() {
    global $script, $varArray, $data, $times;
    $startdate = isset($_REQUEST["startdate"])? $_REQUEST["startdate"]: date("Y-m-d H:i:s", time()-600);
    $stopdate = isset($_REQUEST["stopdate"])? $_REQUEST["stopdate"]: "";
    $stopcond = strlen($stopdate)>5? " AND timestamp <= UNIX_TIMESTAMP('$stopdate')": '';

    $query = "SELECT id, FROM_UNIXTIME(timestamp) AS t, shotnumber FROM topupmsg WHERE timestamp >= UNIX_TIMESTAMP('$startdate')$stopcond ORDER BY id";
    $res = mysql_query($query);
    while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
      $times[$d['id']] = $d;
    }
    end($times); $last_id = key($times);
    reset($times); $first_id = key($times);
    
    // echo "last_id: $last_id, first_id: $first_id<br>\n";
    foreach ($varArray as $var) {
      $query = "SELECT topupmsg_id, value AS value FROM topup_$var WHERE topupmsg_id BETWEEN $first_id AND $last_id\n";
      $res = mysql_query($query);
      $data[$var] = array();
      while ($d = mysql_fetch_array($res, MYSQL_ASSOC)) {
        $data[$var][$d['topupmsg_id']] = $d['value'];
      }
    }
  }


  // ----------------------------------------------------------------
  // display data in HTML
  function emit_csv() {
    global $script, $varArray, $data, $times;
    $csv = "time,shot #";
    $oldval = "";
    $first = true;
    foreach ($varArray as $val) {
      $csv .= ",$val";
    }
    $csv .= "\n";
    foreach ($times as $k=>$t) {
      $csv .= "{$t['t']},{$t['shotnumber']}";
      foreach ($varArray as $val) {
        $csv .= ",{$data[$val][$k]}";
      }
      $csv .= "\n";
    }
    header("Content-Disposition: attachment; filename=pss.csv");
    header("Content-Type: application/x-csv");
    header("Content-Length: ".strlen($csv));
    echo $csv;
    exit();
  }

  // ----------------------------------------------------------------
  // display data in HTML
  function emit_data() {
    global $script, $varArray, $data, $times;
    if (isset($_REQUEST['export']) and ($_REQUEST['export']=='csv')) {emit_csv();}
    $template = file_get_contents('./header_topup.html');
    $replace = array("<!--startdate-->"=>$_REQUEST["startdate"],"<!--stopdate-->"=>$_REQUEST["stopdate"],'<!--filter-->' =>$_REQUEST['filter']);
    echo strtr($template, $replace);
    echo "\n<table class='table table-hover'><th align='right'> time </th><th align='right'> shot # </th>\n";
    $oldval = "";
    $first = true;
    foreach ($varArray as $val) {
      echo "<th align='right'> $val </th>";
    }
    echo "</tr>\n";
    foreach ($times as $k=>$t) {
      echo "<tr><td>{$t['t']}</td><td>{$t['shotnumber']}</td>\n";
      foreach ($varArray as $val) {
        echo "<td align='right'>&nbsp;{$data[$val][$k]}&nbsp;</td>";
      }
      echo "</tr>\n";
    }
    echo "</table>\n</div>\n</div>\n";
    readfile('./footer.html');
  }

	if (isset($_REQUEST['startdate'])) get_data();
	emit_data();
	

?>
