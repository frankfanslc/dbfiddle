<?
header('X-Powered-By: ');
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
$o = [];
exec('/usr/local/bin/fiddledb_create',$o);
if(count($o)!==2) exit('fiddledb_create did not return 2 lines');
$db = 'db_'.$o[0];
$user = 'u_'.$o[0];
$password = 'Password'.$o[1];

$connection = pg_connect("dbname=$db user=$user password=$password");
pg_query($connection,'set search_path to public,x_tablefunc,x_pg_trgm,x_intarray,x_hstore,x_tsm_system_rows,x_unaccent,x_ltree;');

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

exec('/usr/local/bin/fiddledb_drop '.$o[0].' 2>/dev/null >/dev/null &');

echo json_encode($return);
?>
