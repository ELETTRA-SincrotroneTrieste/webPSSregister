<?php

/*
   +--------------------------------------------------------------+
   |                         PHP-GREP.php                         |
   +--------------------------------------------------------------+
   |  By Jan Zumwalt - Net-Wrench.com - 2012-11-17                |
   |  Function concept by cafewebmaster.com                       |
   |  1) added input form                                         |
   |  2) file line numbers                                        |
   |  3) matched line output                                      |
   |  4) Stats count                                              |
   |                                                              |
   |  notes: I have not found an easy way to highlite the match   |
   |         text in the result. If someone finds a simple way,   |
   |         please send code from the message form located at    |
   |         net-wrench.com/email      Thank you - Jan Zumwalt    |
   +--------------------------------------------------------------+   
*/


  // echo "err: ".error_reporting(-1)."<br>\n";  // turn off error reporting
  error_reporting(0);  // turn off error reporting
  define("SLASH", stristr($_SERVER['SERVER_SOFTWARE'], "win") ? "\\" : "/"); // slash for win or unix
  
  $path  = ($_REQUEST['path']) ? $_REQUEST['path'] : dirname(__FILE__) ;
  $search    = $_POST['search'];
  $title = "Php-Grep.php";
  $ver   = "2012-11-17";
  $total        = 0;  // stat count (statistics)
  $occurance    = 0;  // stat count
  $filesearched = 0;  // stat count
  $dirsearched  = 0;  // stat count  
  
  function php_grep($search, $path){            // entry point for recursive search
    global $total;                              // make stat vars available outside function
    global $occurance;
    global $filesearched;
    global $dirsearched;   
    
    if (!isset($ret)) $ret = array();
    $fp = opendir($path);
    while($f = readdir($fp)){
      if( preg_match("#^\.+$#", $f) ) continue; // ignore symbolic links
      $file_full_path = $path.SLASH.$f;         // insert win/unix slash in proper direction
if (strpos($f, '.m4v')!==false) continue;
if (strpos($f, '.swish-e')!==false) continue;
// echo "$file_full_path<br>\n";
      $filesearched++;                          // assume path is a file for stat count
      
      if(is_dir($file_full_path)) {             // if path is directory, search recursively
        $ret = array_merge($ret, php_grep($search, $file_full_path));
        $filesearched--;                        // path is directory, so subtract from file count
        $dirsearched++;                         // inc directory count
        
      } else if( stristr(file_get_contents($file_full_path), $search) ) { // quick check for match in entire file 
        // match found in file so process each line of file
        $fh = fopen("$file_full_path", "r");
        $linect = 0;
       
        while (!feof($fh)) {                    // search each line of file
          $line = trim(fgets($fh));
          $pattern = "/$search/";
          
          if(preg_match($pattern,$line)) {      // if match found in this line show line number and text
            $ret[] = "<a style='color:#44b;' href='?open_editor=$file_full_path'>$file_full_path</a> <span style='color:#c44;'>[$linect]</span> <i><xmp>$line</xmp></i>"; 
            $occurance++;
          }
          $linect++;
        }
        fclose($file_handle);
        $total++;
      }
    }
         
    return $ret;
  }
  
  if($search){  // if search is set, we process a search request 
    $res = php_grep($search, $path);
    sort($res);
    $results = implode("\n",$res);
  }


    // if ($search == '') { $search = 'enter text to search for'; } 

  echo "
    <html>
    <head>
      <title>grep</title>
      <style>
      body  { margin: 100px; background-color: #fff; color: #123; font-family: arial;}
      h1    { text-align: center; color: #ea7; display: inline;}
      h3    { text-align: center; color: #ea7; display: inline;}
      xmp   { display: inline;}
      </style>
    </head>
    <body>
    <form method=post>
    <table>
      <!--tr align=center><td>
        <h1>$title</h1>
        <br>
        <h3>ver $ver</h2>
        <br>
        </td></tr-->
        <tr><td>
        <input name=path size=100 value=\"$path\" /><br>
        Path<br><br>
        <input name=search size=100 value=\"$search\" /><br>
        Search<br>
        <center><input type=submit></center><br>
      </td></tr>
      <tr><td
        <span style='color: #2c2;'>
          Dirs  searched = $dirsearched<br> 
          Files searched = $filesearched<br>   
          Files matched  = $total<br>
          Occurances     = $occurance<br>
        </span>
      </td></tr>  
    </table>
    </form> ";

    echo "<pre>$results</pre>";
    echo "<span style='color: #eee;'>By Jan Zumwalt - net-wrench.com </span>"

?>
