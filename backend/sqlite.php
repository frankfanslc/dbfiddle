<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
$connection = new SQLite3(':memory:');

$connection->busyTimeout(0);
$connection->exec('PRAGMA journal_mode = wal;');
$connection->exec('PRAGMA max_page_count = 1000;');

$queries = json_decode(file_get_contents('php://input'), true);
$return = [];
foreach($queries as $query){
  $wrapper = (object)['result'=>[],'error'=>''];
  if($res = $connection->query($query)){
    $result = (object)['head'=>[],'align'=>[],'data'=>[],'message'=>''];
    if($res->numColumns()){
      $rows = [];
      if($row = $res->fetchArray(SQLITE3_NUM)){
        array_push($rows,$row);
        for($i = 0; $i<$res->numColumns(); $i++){
          $result->align[$i] = in_array($res->columnType($i),[SQLITE3_INTEGER,SQLITE3_FLOAT])?STR_PAD_LEFT:STR_PAD_RIGHT;
        }
      }
      while($row = $res->fetchArray(SQLITE3_NUM)){
        array_push($rows,$row);
      }
      $output = [];
      foreach ($rows as $k1=>$a) {
          foreach ($a as $k2=>$v) {
              $output[$k2][$k1] = $v;
          }
      }
      for($i = 0; $i<$res->numColumns(); $i++){
        $result->head[$i] = $res->columnName($i);
        foreach($output[$i] as $j=>$field){
          $result->data[$i][$j] = $field;
        }
      }
    }
    $res->finalize();
  }else{
    $wrapper->error = $connection->lastErrorMsg();
  }
  array_push($wrapper->result,$result);
  array_push($return,$wrapper);
}
echo json_encode($return);
?>
