<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);

$o = [];
exec('/usr/local/bin/fiddledb_create',$o);
$db = 'db_'.$o[0];

$connection = ibase_connect("localhost:/mnt/$db/fiddle.fdb", 'fiddle', 'fiddle') or exit('Could not connect to the server!');

$queries = json_decode(file_get_contents('php://input'), true);
$return = [];
foreach($queries as $query){
  $all_results = (object)['result'=>[],'error'=>''];
  $response = ibase_query($connection, $query);
  $result = (object)['head'=>[],'align'=>[],'data'=>[], 'message'=>[]];
  if (gettype($response) === "resource") {
    $i = 0;
    set_error_handler(function($errno,$errmsg,$errfile) use($result) { $result->message = substr($errmsg,strpos($errmsg,':')+2); });
    while ($row = ibase_fetch_row($response,IBASE_TEXT)) {
      foreach($row as $j=>$field){
        if(mb_check_encoding($field,'UTF-8')){
          $result->data[$j][$i] = $field;
          $result->data[$j][$i] = ' ';
        }else{
          $result->message = 'field invalid for utf8 encoding';
        }
      }
      $i++;
    }
    restore_error_handler();
    for ($i = 0; $i < ibase_num_fields($response); $i++) {
      $col_info = ibase_field_info($response, $i);
      $result->head[$i] = $col_info['alias'];
      $result->align[$i] = in_array($col_info['type'],['INTEGER','INT64','DECIMAL','FLOAT','NUMERIC','DOUBLE','SMALLINT'])?STR_PAD_LEFT:STR_PAD_RIGHT;
    }
    ibase_free_result($response);
  } elseif (gettype($response) === "integer") {
    $result->message = $response.' rows affected';
  } elseif ($response === false) {
    $result->message = ibase_errmsg();
  }
  ibase_commit($connection);
  array_push($all_results->result,$result);
  array_push($return,$all_results);
}

ibase_close($connection);

unlink("/mnt/$db/fiddle.fdb");
rmdir("/mnt/$db");

echo json_encode($return) . PHP_EOL;
?>
