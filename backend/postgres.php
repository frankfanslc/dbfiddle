<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
$connection = pg_connect('dbname=postgres user=postgres');
if(!$connection) trigger_error('cant connect',E_USER_ERROR);
$db = '';
$password = '';
for ($i = 0; $i<20; $i++) $db.=chr(rand(97,122));
for ($i = 0; $i<30; $i++) $password.=chr(rand(97,122));
shell_exec("cp -a /mnt/template /mnt/image/$db");
$uuid = trim(shell_exec("uuid"));
shell_exec("xfs_admin -U $uuid /mnt/image/$db");
$lo = trim(shell_exec("udisksctl loop-setup -f /mnt/image/$db | grep -oP '(?<=loop)([0-9]+)(?=.$)'"));
$mt = trim(shell_exec("udisksctl mount -b /dev/loop$lo | grep -oP '(?<=/)([-0-9a-f]+)(?=.$)'"));
pg_send_query($connection,"create user u_$db password '$password'");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);
pg_send_query($connection,"create tablespace ts_$db owner u_$db location '/media/postgres/$mt'");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);
pg_send_query($connection,"create database db_$db template postgres tablespace ts_$db");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);

$connection = pg_connect("dbname=db_$db user=u_$db password=$password");

pg_send_query($connection,'select 1;');
$res = pg_get_result($connection);
pg_field_type($res,0);
pg_free_result($res);

$queries = json_decode(file_get_contents('php://input'), true);
$return = [];
foreach($queries as $query){
  $wrapper = (object)['result'=>[],'error'=>''];
  pg_send_query($connection,$query);
  while(($res = pg_get_result($connection))!==FALSE){
    $result = (object)['head'=>[],'align'=>[],'data'=>[],'message'=>''];
    if(pg_result_error($res)){
      $wrapper->error = pg_result_error($res);
    }else{
      if(pg_num_fields($res)>0){
        $rows = [];
        while($row = pg_fetch_row($res)){
          array_push($rows,$row);
        }
        $output = [];
        foreach ($rows as $k1=>$a) {
            foreach ($a as $k2=>$v) {
                $output[$k2][$k1] = $v;
            }
        }

        for($i = 0; $i<pg_num_fields($res); $i++){
          $result->head[$i] = pg_field_name($res,$i);
          $result->align[$i] = in_array(pg_field_type($res,$i),['int2','int4','int8','numeric','money'])?STR_PAD_LEFT:STR_PAD_RIGHT;
          foreach($output[$i] as $j=>$field){
            $result->data[$i][$j] = $field;
          }
        }
      }else{
        if(pg_affected_rows($res)>0) $result->message = pg_affected_rows($res).' rows affected';
      }
    }
    array_push($wrapper->result,$result);
    pg_free_result($res);
  }
  array_push($return,$wrapper);
}
pg_close($connection);

$connection = pg_connect('dbname=postgres user=postgres');
pg_send_query($connection,"drop database db_$db");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);
pg_send_query($connection,"drop tablespace ts_$db");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);
pg_send_query($connection,"drop user u_$db");
$res = pg_get_result($connection);
if(pg_result_error($res)) trigger_error(htmlentities(pg_result_error_field($res,PGSQL_DIAG_SQLSTATE).pg_result_error($res), ENT_QUOTES), E_USER_ERROR);
pg_close($connection);

shell_exec("(sleep 5; udisksctl unmount -b /dev/loop$lo; udisksctl loop-delete -b /dev/loop$lo; rm /mnt/image/$db;) 2>/dev/null >/dev/null &");

echo json_encode($return);
?>
