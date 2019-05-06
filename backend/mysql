<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
$connection = new mysqli('localhost','root','********');
if ($connection->connect_errno) exit('Could not connect to the server!');
$user = 'fiddle_';
$password = 'Aa0';
for ($i = 0; $i<20; $i++) $user.=chr(rand(65,90));
for ($i = 0; $i<27; $i++) $password.=chr(rand(65,90));
$connection->query("create database $user");
$connection->query("create user $user identified by '$password'");
$connection->query("grant all privileges on $user.* to $user");
$connection->query("grant select on performance_schema.* to $user");
$connection->close();

$connection = new mysqli('localhost',$user,$password,$user);
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
            $result->head[$i] = $column->name;
            $result->align[$i] = in_array($column->type,[1,2,3,4,5,8,9,246])?STR_PAD_LEFT:STR_PAD_RIGHT;
            $i++;
          }
          $i = 0;
          while ($row = $output->fetch_row()) {
            foreach($row as $j=>$field){
              $result->data[$j][$i] = $field;
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

$connection = new mysqli('localhost','root','********');
if ($connection->connect_errno) exit('Could not connect to the server!');
$connection->query("drop user $user");
$connection->query("drop database $user");
$connection->close();
echo json_encode($return);
?>
