<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
$o = [];
exec('/usr/local/bin/fiddledb_create'.(isset($_GET['sample'])?' sample':''),$o);
if(count($o)!==2) exit('fiddledb_create did not return 2 lines');
$db = 'db_'.$o[0];
$user = 'u_'.$o[0];
$password = 'Password'.$o[1];

$connection = new mysqli('localhost',$user,$password,$db);
if ($connection->connect_errno) exit('Could not connect to the server as $user!');
$queries = json_decode(file_get_contents('php://input'), true);
$return = [];
foreach($queries as $query){
  $all_results = (object)['result'=>[],'error'=>''];
  if($connection->multi_query($query)){
    do{
      $result = (object)['head'=>[],'align'=>[],'data'=>[]];
      if($output = $connection->store_result()){
        if($output!==true){
          $i = 0;
          while ($column = $output->fetch_field()){
            $is_binary[$i] = $column->flags & 16;
            $result->head[$i] = $column->name;
            $result->align[$i] = in_array($column->type,[1,2,3,4,5,8,9,246])?STR_PAD_LEFT:STR_PAD_RIGHT;
            $i++;
          }
          $i = 0;
          while ($row = $output->fetch_row()) {
            foreach($row as $j=>$field){
              $result->data[$j][$i] = $is_binary[$j] ? bin2hex($field) : $field;
            }
            $i++;
          }
        $output->free();
        }
      }
      array_push($all_results->result,$result);
    } while ($connection->next_result());
  }else{
    $all_results->error = $connection->error;
  }
  //mssql_free_result($output);
  array_push($return,$all_results);
}
$connection->close();

exec('/usr/local/bin/fiddledb_drop '.$o[0]);

echo json_encode($return);
?>
