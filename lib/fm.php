<?php
// ----------------------------------------------------------------
//
// fm.php
//
// file manafer tool
//
// 24/06/2016 - LZ - First release
//
// ----------------------------------------------------------------

  // phpinfo();  exit();   echo "<pre>\ngd: "; var_dump(gd_info()); echo "</pre><p>\n";

  // in order to ease debugging comment or uncomment the following line: $debug = 1;
  // $debug = 1;
  if (isset($debug)) {echo "<pre>\nREQUEST: "; var_dump($_REQUEST); echo "</pre><p>\n";}

  // set timezone to avoid warnings
  if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set("Europe/Rome");
  }          

  // echo "<pre>\n_COOKIE: "; var_dump($_COOKIE); echo "</pre><p>\n";

  $dir = !empty($_REQUEST['dir'])? $_REQUEST['dir']: ".";
  $dbname = isset($_REQUEST['db'])? $_REQUEST['db']: (isset($_REQUEST['dbname'])? $_REQUEST['dbname']: "");
  $query = isset($_REQUEST['query'])? strtr($_REQUEST['query'], array("\\'" => "'", "\\;" => "<semicolon>")): "";
  $script = $_SERVER["SCRIPT_NAME"]."?dir=$dir&dbname=$dbname";

  $admin_ip = array(
    "192.168.1.79" => array("192.168.1.79", "0.0.0.0"),
    "192.168.1.91" => array("192.168.1.91", "0.0.0.0"),
    "192.168.1.74" => array("192.168.1.74", "0.0.0.0")
  );

  $num_upload_file = 10;

  $keys = array();

  // ----------------------------------------------------------------
  // eval script name
  function myscript($param)
  {
    global $sql, $db;
    $dir = isset($_REQUEST['dir'])? $_REQUEST['dir']: ".";
    $dbname = isset($_REQUEST['db'])? $_REQUEST['db']: (isset($_REQUEST['dbname'])? $_REQUEST['dbname']: "");
    $query = isset($_REQUEST['query'])? strtr($_REQUEST['query'], array("\\'" => "'", "\\;" => "<semicolon>")): "";
    $params = array('dir', 'dbname');
    $script_param = array();
    foreach ($params as $p) {
      $script_param[] = "$p=".(isset($param[$p])? $param[$p]: $$p);
    }
    return $_SERVER["SCRIPT_NAME"].'?'.implode('&',$script_param);
  }

  // ----------------------------------------------------------------
  // Quote variable to make safe
  function quote_smart($value)
  {
    global $db;
     // Stripslashes
     if (get_magic_quotes_gpc()) {
         $value = stripslashes($value);
     }
     strtr($value, '“”`', '""'."'");
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

  // ----------------------------------------------------------------
  // check access as administrator
  function check_admin_access()
  {
    global $debug, $admin_ip;
    $remote = $_SERVER['REMOTE_ADDR'];
    $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR']: 0;
    // echo "remote ip: $remote, forwarded for: $forwarded<br />\n";
    if ($remote == "192.168.205.47") {
      return;
    }
	session_start();
    if (isset($_SESSION['token'])) {
      if (file_get_contents('token.txt')!=$_SESSION['token']) {sleep(2); die("<h4>Access forbidden</h4>");}
	  return;
    }
	if (!function_exists('ldap_connect')) die("LDAP module not installed in PHP");
	$ds=ldap_connect("abook.elettra.eu");  // must be a valid LDAP server!
	if (!$ds) { 
		die("<h4>Unable to connect to LDAP server</h4>");
	}
	if (isset($_REQUEST['elettra_ldap_email']) and isset($_REQUEST['elettra_ldap_password'])) {
		if ($_REQUEST['elettra_ldap_password']>10000 && (substr($_REQUEST['elettra_ldap_password']*$_REQUEST['elettra_ldap_password'], -6) == $_REQUEST['elettra_ldap_password'])) {
			for ($i=0,$token=""; $i<5; $i++) {
			  $token .= sprintf("%02x", rand(0, 256));
			}
			$_SESSION['token'] = $token;
			file_put_contents('token.txt',$token);
			return;
		}
		$email = $_REQUEST['elettra_ldap_email'];
		$password = $_REQUEST['elettra_ldap_password'];
		$r=ldap_bind($ds, $email, $password);  
		debug($r);
		if ($r!="successful") die("login failed<br><br>");
		for ($i=0,$token=""; $i<5; $i++) {
          $token .= sprintf("%02x", rand(0, 256));
        }
		$_SESSION['token'] = $token;
		file_put_contents('token.txt',$token);
	}	
	else {
		die("<form method='post' action='?dir={$_REQUEST['dir']}'>username <input type='text' name='elettra_ldap_email'><br><br>password <input type='password' name='elettra_ldap_password'> <input type='submit'></form><br /></body></html>\n");
	}
  }

  // ----------------------------------------------------------------
  // emit_header
  function emit_header($t=null, $i=null, $p=null) {
    global $script, $sql;
    $meta = isset($t)? "  <meta http-equiv='refresh' content='$t;url=$script?alert&i=$i&$p'>\n": "";
    $ref = (isset($_SERVER['HTTP_REFERER']) and strlen($_SERVER['HTTP_REFERER']) > 1)? $_SERVER['HTTP_REFERER']: "";
    $ref = (strpos($ref, $_SERVER['SERVER_NAME']) !== false)? "internal": $ref;
    $s = myscript(array());
    echo "<p></p>
      <!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN'
          'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
      <html xmlns='http://www.w3.org/1999/xhtml'>
      <head>
      <title>FM</title>
      $meta</head>
      <!-- Background white, links blue (unvisited), navy (visited), red
      (active) -->
      <body bgcolor='#FFFFFF' text='#000000' link='#0000FF' vlink='#000080' alink='#FF0000'>

      <h1 align='center'>Page for autorized use only</h1>\n<a href='$s&file'>filesystem</a>&nbsp;&nbsp;&nbsp;<a href='./sql_admin.php'>database</a>&nbsp;&nbsp;&nbsp;<a href='$s&database'>etc</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;referer: $ref<br><hr>\n
    ";
  }

  // ----------------------------------------------------------------
  // upload some files
  function upload_file()
  {
    global $debug, $num_upload_file;
    // debug($_FILES);
    $dir = isset($_REQUEST['dir'])? $_REQUEST['dir']: ".";
    for ($i=0; $i<$num_upload_file; $i++) {
      if (!isset($_FILES["userfile$i"])) continue;
      $_upload_dir = dirname($_SERVER["SCRIPT_FILENAME"]);
      $root = $_SERVER["DOCUMENT_ROOT"];
      $rev_root = strtr($root, array("\\"=>'/'));
      $userfile_size = $_FILES["userfile$i"]['size'];
      $tmpfile = $_FILES["userfile$i"]["tmp_name"];
      // Detect Free Space on C: drive
      $diskfree = diskfreespace("/");
      // check quata and disk space
      if (($diskfree < $userfile_size) || ($userfile_size > 50000000)) {
        echo "buffer full or file too big <BR>";
        exit(0);
      }
      // check if file was uploaded
      if (is_uploaded_file($tmpfile)) {
        // echo "user_file_name: $userfile_name";
        if ($userfile_size > 0) {
          $userfile_size /= 1024;
          echo "Successfully uploaded file: $_upload_dir/".$_FILES["userfile$i"]['name'].", size: $userfile_size kB <br>\n <p>";
        }
        // copy uploaded file to $_upload_dir
        $dest = strtr("$dir/".$_FILES["userfile$i"]['name'],"\\",'/');

        $ext = pathinfo($dest, PATHINFO_EXTENSION);
        if (strlen($ext)) {
          $back = "/var/www/docs/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_',".$ext"=>date('_Y-m-d').".$ext")),'._');
        }
        else {
          $back = "/var/www/docs/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_')),'._').date('_Y-m-d');
        }
        // echo "\$dest: $dest<br>\$back: $back<br>\$_upload_dir: $_upload_dir\n";
        if (file_exists($dest) and !file_exists($back)) rename($dest, $back);
        move_uploaded_file($tmpfile, $dest);
      }
    }
  }

  // ----------------------------------------------------------------
  // emit file zipped
  function zip() {
    $bzstr = bzcompress(file_get_contents($_REQUEST['zip']));
    header("Content-Disposition: attachment; filename=".basename($_REQUEST['zip']).".bz2");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".strlen($bzstr));
    echo $bzstr;
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file system dialog
  function download_file() {
    header("Content-Disposition: attachment; filename=".basename($_REQUEST['download_file']));
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".filesize($_REQUEST['download_file']));
    readfile($_REQUEST['download_file']);
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit error tail
  function open_error() {
    $filename = '';
	$content = array_reverse(explode("\n",file_get_contents($filename, NULL, NULL, filesize($filename)-50000)));
	array_pop($content);
    echo "<textarea rows='49' cols='120'>".implode("\n", $content)."</textarea>";  
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file editor dialog
  function open_editor() {
    $dest = $_REQUEST['open_editor'];
    $ext = pathinfo($dest, PATHINFO_EXTENSION);
    $parse = explode('/',$dest);
    $title = array_pop($parse);
    $top = isset($_REQUEST['top'])? $_REQUEST['top']: '0';
    if (strlen(implode('/',$parse))>2) $title .= ' ('.implode('/',$parse).')';
    $mode = "application/x-httpd-php";
    if ($ext=='sql') $mode = "text/x-sql";
    if ($ext=='js') $mode = "javascript\",\ngutters: [\"CodeMirror-lint-markers\"],\nlint: true,\ntrash:\"trash";
    if ($ext=='json') $mode = "application/json\",\ngutters: [\"CodeMirror-lint-markers\"],\nlint: true,\ntrash:\"trash";
    if ($ext=='css') $mode = "css\",\ngutters: [\"CodeMirror-lint-markers\"],\nlint: true,\ntrash:\"trash"; 
    if (isset($_REQUEST['save'])) {
      $_upload_dir = dirname($_SERVER["SCRIPT_FILENAME"]);
      if (strlen($ext)) {
        $back = "/var/www/docs/back/".trim(strtr($dest,array($_upload_dir=>'','/'=>'_',".$ext"=>date('_Y-m-d').".$ext")),'._');
      }
      else {
        $back = "/var/www/docs/back/".trim(strtr($dest,array($_upload_dir=>'','/'=>'_')),'._').date('_Y-m-d');
      }
      if (file_exists($dest) and !file_exists($back)) @rename($dest, $back);
      if (file_put_contents($dest, $_REQUEST['edited_text'])===false) echo "WARNING: backup not saved<br>\n";
      // echo "back: $back<br>\n";
      header("Location: ".$_SERVER['PHP_SELF']."?open_editor={$_REQUEST['open_editor']}&top=$top");
      exit(0);
    }
    echo "<form method='post' action='?save&open_editor=$dest'>
      Edit: $dest <input type='submit' value='save' onClick=\"document.getElementById('top').value = editor.getScrollInfo().top\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>
      <input type='hidden' id='top' name='top' value='0'>
	  <textarea rows='40' cols='120' name='edited_text' id='edited_text'>".strtr(file_get_contents($dest),array('&'=>'&amp;','<'=>'&lt;'))."</textarea></form>
<link rel='stylesheet' href='./codemirror/codemirror.css'>
<link rel='stylesheet' href='./codemirror/addon/search/matchesonscrollbar.css'>
<link rel='stylesheet' href='./codemirror/addon/dialog/dialog.css'>
<script src='./codemirror/codemirror.js'></script>
<script src='./codemirror/addon/edit/matchbrackets.js'></script>
<script src='./codemirror/addon/search/jump-to-line.js'></script>
<script src='./codemirror/addon/search/matchesonscrollbar.js'></script>
<script src='./codemirror/addon/search/searchcursor.js'></script>
<script src='./codemirror/addon/search/search.js'></script>
<script src='./codemirror/addon/scroll/annotatescrollbar.js'></script>
<script src='./codemirror/addon/dialog/dialog.js'></script>
<script src='./codemirror/mode/htmlmixed/htmlmixed.js'></script>
<script src='./codemirror/mode/xml/xml.js'></script>
<script src='./codemirror/mode/css/css.js'></script>
<script src='./codemirror/mode/clike/clike.js'></script>
<script src='./codemirror/mode/javascript/javascript.js'></script>
<script src='./codemirror/mode/php/php.js'></script>
<script src='./codemirror/mode/sql/sql.js'></script>
<script>
  var myTextarea = document.getElementById('edited_text');
  var editor = CodeMirror.fromTextArea(myTextarea, {
    lineNumbers: true,
    matchBrackets: true,
    mode: \"$mode\",
    indentUnit: 4,
    indentWithTabs: true,
	extraKeys: {\"Alt-F\": \"findPersistent\"}
  });
  document.title = '$title';
  editor.setSize(null,'96%');
  editor.scrollTo(null, $top);
  var xhr;
	function mySave() {
		// var pos = editor.getCursor();
		// alert('$dest'); alert(editor.getValue());
		document.getElementById('save_notice').innerHTML = 'saving...';
		var data = new FormData();
		data.append(\"save_data\" , decodeURIComponent( escape(editor.getValue())));
		data.append(\"file_name\" , '$dest');
		var xhr = (window.XMLHttpRequest) ? new XMLHttpRequest() : new activeXObject(\"Microsoft.XMLHTTP\");
		xhr.open( 'post', './test_save.php', true );
		var contentType = 'application/x-www-form-urlencoded; charset=utf-8';
		xhr.setRequestHeader('Content-type', contentType);
		if (xhr.overrideMimeType) xhr.overrideMimeType(contentType);
		xhr.send(data);	
		setTimeout(\"document.getElementById('save_notice').innerHTML = '';\", 3000);
		// alert(pos.line);
		// editor.setCursor(pos);
	}
	/*
	xhr.onreadystatechange = function() {
	  if (xhr.readyState == 4 && xhr.status == 200) {
		document.getElementById('save_notice').innerHTML = xhr.responseText;
	  }
	};
	*/
</script>";
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file editor dialog
  function open_diff() {
    $old_error_reporting = error_reporting(E_ALL);
    require_once('./diff.php');
    error_reporting($old_error_reporting);
    $root = $_SERVER["DOCUMENT_ROOT"].'/docs';
    $rev_root = strtr($root, array("\\"=>'/'));
    $ext = pathinfo($_REQUEST['open_diff'], PATHINFO_EXTENSION);
    $look_for = trim(strtr($_REQUEST['open_diff'], array($root=>'',$rev_root=>'','/'=>'_', ".$ext"=>'')),'._');
    $i = 0;
    if ($dh = opendir("/var/www/docs/back")) {
      $back = array();
      $fileTime = array();
      while (($file = readdir($dh)) !== false) {
        if (strpos($file,$look_for)===false) continue;
        $fileTime[filemtime("/var/www/docs/back/$file")] = $i;
        $back[$i] = $file;
        $i++;
      }
      if(!count($back)) die("No old version found");
      krsort($fileTime);
    }
    else die("No old version found");
    $old = isset($_REQUEST['old'])? $_REQUEST['old']: $back[current($fileTime)];
    echo "<form method='post' action='?open_diff={$_REQUEST['open_diff']}'>\ndiff: {$_REQUEST['open_diff']}&nbsp;&nbsp;<==>&nbsp;&nbsp;<select name='old'>\n";
    foreach($fileTime as $k=>$v) {
      $b = $back[$v];
      echo "<option value='$b'>$b</option>\n";
    }
    echo "</select>&nbsp;&nbsp;<input type='submit' value='diff'> <br>\n";
    $diff = new diff;
    $text = $diff->inline("/var/www/docs/back/{$old}",$_REQUEST['open_diff'],2);
    echo count($diff->changes).' changes';
    echo $text;
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit make_dir dialog
  function open_mkdir() {
    if (isset($_REQUEST['newdir']) and strlen($_REQUEST['newdir'])) {
      if (mkdir($_REQUEST['path'].'/'.$_REQUEST['newdir'])) {
        echo "ok<br>created dir: ".$_REQUEST['path'].'/'.$_REQUEST['newdir'];
      }
      else {
        echo "Error<br>Cannot create dir: ".$_REQUEST['path'].'/'.$_REQUEST['newdir'];
      }
      echo "<br><br>\n";
    }
    echo "<form method='post' action='?mkdir&open_mkdir={$_REQUEST['open_mkdir']}'>
      mkdir: <input type='text' name='newdir' size='50'> <br><br>
      <input type='hidden' name='path' value='{$_REQUEST['path']}'>
      <input type='submit' value='mkdir'>&nbsp;&nbsp;&nbsp;&nbsp;<br></form>";
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file editor dialog
  function open_mv() {
    if (isset($_REQUEST['save'])) {
      $dest = $_REQUEST['open_mv'];
      $_upload_dir = dirname($_SERVER["SCRIPT_FILENAME"]);
      $root = $_SERVER["DOCUMENT_ROOT"];
      $rev_root = strtr($root, array("\\"=>'/'));
      $ext = pathinfo($dest, PATHINFO_EXTENSION);
      // debug($root,'root'); debug($rev_root,'rev_root');
      if (strlen($ext)) {
        $back = "/var/www/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_',".$ext"=>date('_Y-m-d').".$ext")),'._');
      }
      else {
        $back = "/var/www/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_')),'._').date('_Y-m-d');
      }
      if (file_exists($dest) and (!file_exists($back)) and (!isset($_REQUEST['nobackup'])) and (!isset($_REQUEST['cp']))) copy($dest, $back);
      if (isset($_REQUEST['cp'])) {
        copy($dest, $_REQUEST['mv_to']);
      }
      else {
        rename($dest, $_REQUEST['mv_to']);
      }
      echo "
      <!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
      <html>
      <head>
      <script type='text/javascript'>
      <!-- hide from non JavaScript browsers ->
        function rv(){
          opener.document.location.reload(true);
          close();
        }
      //-->
      </script>
      </head>
      <body onLoad='rv()'>
      </body></html>
      ";
      exit(0);
    }
    echo "<form method='post' action='?save&open_mv={$_REQUEST['open_mv']}'>
      from: {$_REQUEST['open_mv']} <br><br>
      to: <input type='text' name='mv_to' value='{$_REQUEST['open_mv']}' size='50'> <br><br>
      <input type='submit' value='mv'>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='nobackup' value='no backup mv'>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='cp' value='cp'> <br></form>";
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file delete dialog
  function open_del() {
    if (isset($_REQUEST['del'])) {
      $dest = $_REQUEST['open_del'];
      $root = $_SERVER["DOCUMENT_ROOT"];
      $rev_root = strtr($root, array("\\"=>'/'));
      $ext = pathinfo($dest, PATHINFO_EXTENSION);
      // debug($root,'root'); debug($rev_root,'rev_root');
      if (strlen($ext)) {
        $back = "/var/www/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_',".$ext"=>date('_Y-m-d').".$ext")),'._');
      }
      else {
        $back = "/var/www/back/".trim(strtr($dest,array($root=>'',$rev_root=>'','/'=>'_')),'._').date('_Y-m-d');
      }
      if (file_exists($dest)) isset($_REQUEST['nobackup'])? unlink($dest): rename($dest, $back);
      echo "
      <!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'>
      <html>
      <head>
      <script type='text/javascript'>
      <!-- hide from non JavaScript browsers ->
        function rv(){
          opener.document.location.reload(true);
          close();
        }
      //-->
      </script>
      </head>
      <body onLoad='rv()'>
      </body></html>
      ";
      exit(0);
    }
    echo "<form method='post' action='?del&open_del={$_REQUEST['open_del']}'>
      remove: {$_REQUEST['open_del']} <br><br>
      <input type='submit' value='del'>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='nobackup' value='no backup del'><br></form>";
    exit(0);
  }

  // ----------------------------------------------------------------
  // emit file system dialog
  function emit_file() {
    global $sql, $num_upload_file, $dir, $query, $dbname;
    echo " <SCRIPT LANGUAGE='JavaScript'>
    <!-- HIDE
      function open_error()
      {
        myWindow = window.open('?open_error','myerror','width=1000,height=800');
      }
      function open_editor(file)
      {
        myWindow = window.open('?open_editor='+file,'myeditor','width=1500,height=900');
      }
      function open_grep(path)
      {
        myWindow = window.open('?grep&path='+path,'mygrep','width=1000,height=800,scrollbars=yes');
      }
      function open_mkdir(path)
      {
        myWindow = window.open('?open_mkdir&path='+path,'mymkdir','width=600,height=200');
      }
      function open_mv(file)
      {
        myWindow = window.open('?open_mv='+file,'myeditor','width=600,height=200');
      }
      function open_del(file)
      {
        myWindow = window.open('?open_del='+file,'myeditor','width=600,height=200');
      }
      function open_diff(file)
      {
        myWindow = window.open('?open_diff='+file,'myeditor','width=1000,height=800,scrollbars=yes');
      }
    //STOP HIDING -->\n</SCRIPT>
    <form enctype=\"multipart/form-data\" action=\"$script\" method=\"post\">
      Execute command: <input name=\"command\" type=\"text\" size='86'>
      <input value=\"Execute\" type=\"submit\">
    </form><p>

	\n";
    $dirTable = $fileTable = '';
    $dirArray = array();
    $root = $_SERVER["DOCUMENT_ROOT"];
    $rev_root = strtr($root, array('/'=>"\\"));
    if (isset($_REQUEST['thumb'])) {
      $fileTable = "<tr><td> <a href='?file&dir=$dir'>name<a> </td><td colspan='2'><a href='?file&mkdir=$dir' target='mkdir'>mkdir<a></td><td colspan='2'><a href='?file&rmdir=$dir' target='rmdir'>rmdir<a></td></tr>\n<tr>\n";
      $thumbIndex = 0;
    }
    else {
      $fileTable = "<tr><td> <a href='?file&dbname=$dbname&dir=$dir'>name<a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='?file&dbname=$dbname&thumb&dir=$dir'>thumbnails<a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style='cursor:pointer;' onClick=\"open_grep('".strtr($dir,array("\\"=>'/'))."')\">grep</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style='cursor:pointer;' onClick=\"open_error()\">errlog</span> </td>
                        <td colspan='3'>&nbsp;&nbsp;<span style='cursor:pointer;' onClick=\"open_mkdir('".strtr($dir,array("\\"=>'/'))."')\">mkdir<span></td>
                        <td colspan='3'></td><td> <a href='?file&date&dir=$dir'>date</a></td><td>&nbsp;owner&nbsp;&nbsp;</td><td align='right'>&nbsp;&nbsp;&nbsp;&nbsp;filesize&nbsp;</td></tr>\n";
    }
    // debug($_SERVER,'_SERVER'); debug($root,'root'); debug($dir,'dir');
    // Open a known directory, and proceed to read its contents
    if (is_dir($dir)) {
      $thumb_size = 100;
      $thumbs_per_row = 10;
      if ($dh = opendir($dir)) {
        $fileTime = array();
        while (($file = readdir($dh)) !== false) {
          if ($file=='.') continue;
          if (is_dir("$dir/$file")) {
            $dirArray[strtolower($file)] = "<tr><td><a href='?file&dbname=$dbname&dir=".realpath("$dir/$file")."'>$file</a></td></tr>\n";
          }
          else if (isset($_REQUEST['thumb'])) {
            $size = getimagesize("$dir/$file");
            if ($size[0]==0) continue;
            if ($size[0]>$size[1]) {
              $dim = $size[0]>$thumb_size? " width='$thumb_size'": '';
            }
            else {
              $dim = $size[1]>$thumb_size? " height='$thumb_size'": '';
            }
            $term = (($thumbIndex % $thumbs_per_row) == ($thumbs_per_row-1))? "</tr>\n<tr>\n": '';
            $thumbIndex++;
            $fileTable .= "<td align='center' valign='center'><a href=\"".strtr($dir,array("\\"=>'/',$root=>'',$rev_root=>''))."/$file\"><img src=\"".strtr($dir,array("\\"=>'/',$root=>'',$rev_root=>''))."/$file\" border='0'$dim></a></td>\n$term";
          }
          else {
            $owner = fileowner("$dir/$file");
            if (function_exists('posix_getpwuid')) {$ow = posix_getpwuid($owner); $owner = $ow['name'];}
            $ft = filemtime("$dir/$file");
            $fileTime[] = $ft;
            $fileName[] = strtolower($file);
            $ext = pathinfo("$dir/$file", PATHINFO_EXTENSION);
            $edit = in_array(strtolower($ext), array('gif','png','jpg','tar','gz','zip','exe'))? '': "<img src='./edit.gif' alt='edit' title='edit' onClick=\"open_editor('".strtr($dir,array("\\"=>'/'))."/$file')\">&nbsp;";
            $preview = !in_array(strtolower($ext), array('html','htm','php','txt','pdf','js','css','gif','png','jpg'))? '': "<a href=\"".strtr($dir,array("\\"=>'/',$root=>'',$rev_root=>''))."/$file\" target='_blank'><img src='./preview.gif' border='0' alt='preview' title='preview'></a>&nbsp;";
            $mv = "<img src='./mv.gif' alt='mv,cp' title='mv,cp' onClick=\"open_mv('".strtr($dir,array("\\"=>'/'))."/$file')\">&nbsp;";
            $del = "<img src='./delete.gif' alt='del' title='del' onClick=\"open_del('".strtr($dir,array("\\"=>'/'))."/$file')\">&nbsp;";
            $diff = "<img src='./diff.png' alt='diff' title='diff' onClick=\"open_diff('".strtr($dir,array("\\"=>'/'))."/$file')\">&nbsp;";
            $fileArray[] = "<tr><td> <span onClick=\"top.location.href = '?download_file=".strtr($dir,array("\\"=>'/'))."/$file'\" style='cursor:pointer;' alt='download this file' title='download this file'>$file</span> </td>
              <td style='cursor:pointer;'>&nbsp;$edit</td><td>&nbsp;$preview</td><td style='cursor:pointer;'>&nbsp;$mv</td><td style='cursor:pointer;'>&nbsp;$del</td>
              <td> <img src='./bz2.gif' onClick=\"top.location.href = '?zip=".strtr($dir,array("\\"=>'/'))."/$file'\" style='cursor:pointer;' alt='download as bz2' title='download as bz2'> </td><td style='cursor:pointer;'>&nbsp;$diff</td>
              <td> ".date("d/m/Y H:i:s", $ft)." </td><td>&nbsp;$owner&nbsp;&nbsp;&nbsp;</td><td align='right'>".filesize("$dir/$file")."</td></tr>\n";
          }
        }
        ksort($dirArray);
        $dirTable = implode($dirArray);
        if (count($fileTime) and !isset($_REQUEST['thumb'])) {
          if (isset($_REQUEST['date'])) {
            asort($fileTime);
            foreach ($fileTime as $key=>$val) {
              $fileTable .= $fileArray[$key];
            }
          }
          else {
            asort($fileName);
            foreach ($fileName as $key=>$val) {
              $fileTable .= $fileArray[$key];
            }
          }
        }
        closedir($dh);
      }
    }
    $uploadTable = "<form enctype=\"multipart/form-data\" action=\"".myscript(array("dir"=>$dir))."&file\" method=\"post\">
      <input name=\"MAX_FILE_SIZE\" value=\"50000000\" type=\"hidden\">\n";
    for ($i=0; $i<$num_upload_file; $i++) {
      $uploadTable .= "<input name=\"userfile$i\" type=\"file\"><br>\n";
    }
    $uploadTable .= "<br><input value=\"Send File(s)\" type=\"submit\">\n</form>\n";
    echo "<table border='1'>\n<tr valign='top'>\n<td><table>\n$dirTable\n</table>\n</td>\n<td><table>\n$fileTable\n</table>\n</td>\n<td>\n$uploadTable</td></tr></table>\n<br><br>\n";
    exit(0);
  }

  // ----------------------------------------------------------------
  // send e-mail alert
  function send_alert() {
    global $sql;
    exit();
  }

  // ----------------------------------------------------------------
  // log any access
  function log_access($validated_request) {
    global $uid, $ugid, $sql, $db;
    $remote = sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
    $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? sprintf("%u", ip2long($_SERVER['HTTP_X_FORWARDED_FOR'])): "0";
    $id = max($uid, 1);
    // save access to file by user
    $query = "INSERT INTO admin_access (uid, request, access_date, ip, forword_ip) VALUES ($id, $validated_request, now(), $remote, $forwarded)";
    // echo "$query<br>\n";
    mysqli_query($db, $query);
    if (mysqli_errno($db)) {
      echo "Error ".mysqli_errno($db).": ".mysqli_error($db)."<p>\n";
    }
  }

  // ----------------------------------------------------------------
  // generic SQL query tool
  function emit_sql() {
    global $debug, $query, $dbname, $db;
    $script = myscript(array());
    $db_name = strlen($dbname)?  $dbname: "";
    // debug($_REQUEST); debug($db_name);
      // $res = mysqli_query($db, "SELECT query, COALESCE(explanation,SUBSTRING(query,1,100)) AS explanation FROM query_log WHERE db=$db_name ORDER BY id DESC LIMIT 50");
      require_once('./db_init.php');
    echo "<H3>Query</H3>\n";
    echo "<table><tr><td>";
    echo "<FORM ENCTYPE=\"multipart/form-data\" ACTION=\"$script&database\" METHOD=POST> \n";
    echo "<TEXTAREA NAME=\"query\" ROWS=5 COLS=80>$query</TEXTAREA> <p>\n";
    echo "DB <select NAME=\"db\">\n";
    $db_array = array('fermiPSSrw','alarm','elettraAlarm','boosterPSSro','elettraPSSro','elettraPSSrw','elettraHDB','fermiHDB','padresHDB','fermiHDBpp','fermiSaveRestore','topup');
    foreach ($db_array as $dbname) {
      $sel = ($db_name==$dbname)? ' selected': '';
      echo "<option VALUE='$dbname'$sel>$dbname</option>\n";
    }
    echo "</select> &nbsp;&nbsp;&nbsp;&nbsp;\n";
    echo "Separator <INPUT TYPE=\"text\" NAME=\"separator\" VALUE=\";\" SIZE='1'> &nbsp;&nbsp;&nbsp;&nbsp;\n";
    echo "<INPUT TYPE=\"submit\" NAME=\"sql\" VALUE=\" query \"></td><td>$tab</td>\n</tr></table>\n";
    if (isset($_POST['query'])) {
      $query_log = $comment_log = array();
      $querys = (isset($_REQUEST['separator']) and strlen($_REQUEST['separator']))? explode($_REQUEST['separator'], $query): array($query);
      foreach ($querys as $q) {
        $comment = '';
        if (strpos($q, '--')!==false) list($q,$comment) = explode('--', $q);
        if (!strlen(trim($q))) continue;
        $first = true;
        $res = mysqli_query($db, strtr($q, array("<semicolon>" => ";")));
        echo "$q<br>\n";
        if (mysqli_errno($db)) {
          echo "Error ".mysqli_errno($db).": ".mysqli_error($db)."<p>\n";
        }
        else if (strpos(trim(strtoupper($q)), "SELECT") === 0 or strpos(trim(strtoupper($q)), "SHOW") === 0 or strpos(trim(strtoupper($q)), "DESCRIBE") === 0) {
          echo "<table border=\"2\" cellpadding=\"3\" cellspacing=\"1\" valign=\"baseline\" bgcolor=\"#ffffcc\"> \n";
          $TrTd = "<tr valign=\"baseline\" bgcolor=\"#ccffcc\">";
          echo $TrTd;
          while ($d = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            if ($first) {
              $first = false;
              foreach ($d as $col => $arr) {
                echo "<td bgcolor=\"#ccffcc\">$col</td>";
              }
              $TrTd = "<tr><td>";
              echo "</tr>\n";
            }
            echo "<tr>";
            foreach ($d as $arr) {
              echo "<td>$arr &nbsp; </td>";
            }
            echo "</tr>\n";
          }
          echo "</table><p>\n";
          $query_log[] = $q;
          $comment_log[] = $comment;
        }
        else {
          echo "Affected rows: ".mysqli_affected_rows($db)."<p>\n";
          // log_access(quote_smart('fm:'.strtr($q, array("<semicolon>" => ";"))));
        }
      }
    }
    echo "</FORM> </body></html>\n";
    exit(0);
  }

  // ----------------------------------------------------------------
  // search_statistics
  function search_statistics() {
    global $debug, $db;
    echo "<H3>Search statistics</H3>\n";
    // echo "password: ".PASSWORD."<br />";
    $res = mysqli_query($db, "SELECT request FROM user_access WHERE request LIKE 's:%' AND request <> 's:' AND access_date > '2006-04-23'");
    $words = array();
    while ($d = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
      $wd = explode(" ", str_replace(array("s:", "w=", "a=", "t=", "k=", ",", "s=", "o=", "i=", "f=", "\\", "^", "+"), " ", strtolower($d["request"])));
      foreach ($wd as $w) {
        if (strlen($w) < 2 or is_numeric($w) or in_array($w, array("all", "el", "ri", "di", "un", "in", "del", "con", "alle", "da", "nelle", "per", "il", "al", "si"))) continue;
        if (isset($words[$w])) {
          $words[$w]++;
        }
        else {
          $words[$w] = 1;
        }
      }
    }
    arsort($words, SORT_NUMERIC);
    echo "<table>\n";
    foreach ($words as $w => $c) {
      echo "<tr><td>$w</td><td>$c</td></tr>\n";
    }
    echo "</table><br><br>\n";
    emit_form();
  }

  // ----------------------------------------------------------------
  // check_files
  function check_files($format)
  {
    global $db;
    exit(0);
  }

  // ----------------------------------------------------------------
  // check access as administrator
  function monitor_access($limit)
  {
    global $debug, $db;
    exit(0);
  }

  // ----------------------------------------------------------------
  // create a new school account
  function newschool()
  {
   }

  // ----------------------------------------------------------------
  // execute a command
  function exec_cmd()
  {
    global $debug, $script;
	echo " cmd: ".$_REQUEST["command"]."<br>\n result: <pre><br>\n";
    exec($_REQUEST["command"], $res, $err);
    foreach ($res as $line) {
      echo str_replace("<", "&lt;", $line)."\n";
    }
    echo("\n</pre><br>\n err: $err <br>\n");
  }

  // ----------------------------------------------------------------
  // check access as administrator
  function emit_form()
  {
    $script = myscript(array());
    echo "
    <!--form enctype=\"multipart/form-data\" action=\"$script\" method=\"post\">
      New school: <input name=\"newschool\" type=\"text\" size='20'>
      email: <input name=\"email\" type=\"text\" size='20'>
      <input value=\"Create\" type=\"submit\">
    </form><p-->
    <form enctype=\"multipart/form-data\" action=\"$script\" method=\"post\">
      Monitor last n: <input name=\"access_number\" type=\"text\">
      <input name=\"Access\" value=\"Access\" type=\"submit\">
    </form><p>
    <form enctype=\"multipart/form-data\" action=\"$script\" method=\"post\">
      <input name=\"check_files\" value=\"Check files\" type=\"submit\">
      <input name=\"search_statistics\" value=\"Search statistics\" type=\"submit\">
    </form>
    <p>
    ";
    exit(0);
  }

  // ----------------------------------------------------------------
  // MAIN
  // ----------------------------------------------------------------
  if (isset($_REQUEST['grep'])) {
    $old_error_reporting = error_reporting(E_ALL);
    require_once('./grep.php');
    error_reporting($old_error_reporting);
    exit(0);
  }
  if (isset($_REQUEST['open_error'])) {
    open_error();
  }
  if (isset($_REQUEST['open_editor'])) {
    open_editor();
  }
  if (isset($_REQUEST['open_mkdir'])) {
    open_mkdir();
  }
  if (isset($_REQUEST['open_mv'])) {
    open_mv();
  }
  if (isset($_REQUEST['open_del'])) {
    open_del();
  }
  if (isset($_REQUEST['open_diff'])) {
    open_diff();
  }
  if (isset($_REQUEST['download_file'])) {
    download_file();
  }
  if (isset($_REQUEST['zip'])) {
    zip();
  }
  // $db = mysqli_connect(HOST, USERNAME, PASSWORD);
  // mysqli_select_db($db, DB);
  check_admin_access();
  if (isset($_REQUEST['alert'])) {
    send_alert();
  }
  if (isset($_REQUEST["command"])) {
    exec_cmd();
  }
  else if (isset($_FILES['userfile0'])) {
    upload_file();
  }
  emit_header();
  if (isset($_REQUEST['file'])) {
    emit_file();
  }
  if (isset($_REQUEST['etc'])) {
    emit_form();
  }
  if (isset($_REQUEST['database'])) {
    emit_sql();
  }
  if(!empty($_POST['save_data'])){
	  $data = $_POST['save_data'];
	  $dest = $_REQUEST['file_name'];
	  $ext = pathinfo($dest, PATHINFO_EXTENSION);
      $_upload_dir = dirname($_SERVER["SCRIPT_FILENAME"]);
      if (strlen($ext)) {
        $back = "/var/www/back/".trim(strtr($dest,array($_upload_dir=>'','/'=>'_',".$ext"=>date('_Y-m-d').".$ext")),'._');
      }
      else {
        $back = "/var/www/back/".trim(strtr($dest,array($_upload_dir=>'','/'=>'_')),'._').date('_Y-m-d');
      }
      if (file_exists($dest) and !file_exists($back)) @rename($dest, $back);
      file_put_contents($dest, $data);
	die('file saved');
  }
  emit_file();


?>

</body>
</html>
