<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--request') {
  $input=json_decode($argv[2],true);
  session_save_path(__DIR__);
  session_start();
  $_SESSION['barangay_user']=['id'=>(int)$input['id'],'role'=>$input['role'],'auth_version'=>2,'name'=>'Test account'];
  $_SESSION['last_activity']=time(); $_SESSION['locations_csrf']='test-token'; $_SESSION['document_request_csrf']='test-token';
  $_SERVER['REQUEST_METHOD']=$input['method'] ?? 'POST'; $_POST=$input['post'];
  ob_start();
  register_shutdown_function(static function() { $body=ob_get_clean(); session_destroy(); echo json_encode(['status'=>http_response_code() ?: 200,'body'=>$body]); });
  require __DIR__.'/../resident-service-lookup.php'; exit;
}
$testName='luna_endpoint_test_'.bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName`"); putenv('BARANGAY_DB_NAME='.$testName);
function endpoint_check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function endpoint_request(int $id,string $role,array $post,string $method='POST'): array {
  $process=proc_open([PHP_BINARY,__FILE__,'--request',json_encode(compact('id','role','post','method'))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
  if ($exit || $errors) throw new RuntimeException($errors ?: $output);
  $result=json_decode($output,true,512,JSON_THROW_ON_ERROR); $result['json']=json_decode($result['body'],true,512,JSON_THROW_ON_ERROR); return $result;
}
try {
  $server->exec("USE `$testName`"); $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  $insert=$server->prepare("INSERT INTO users (user_code,role,full_name,username,password_hash,status,birthdate,qr_status,qr_code) VALUES (?,?,'Alex Test',?,'test-only','Active','2000-01-01','Active',?)");
  $ids=[];
  foreach (['admin','staff','resident'] as $role) { $insert->execute([strtoupper($role),$role,$role,$role==='resident'?'LUNA|RESIDENT|current':null]); $ids[$role]=(int)$server->lastInsertId(); }
  foreach (['admin','staff'] as $role) {
    $result=endpoint_request($ids[$role],$role,['kind'=>'issue','csrf'=>'test-token','code'=>'LUNA|RESIDENT|current']);
    endpoint_check($result['status']===200 && $result['json']['resident']['user_code']==='RESIDENT',"$role can look up a resident for issuance");
    $result=endpoint_request($ids[$role],$role,['kind'=>'resident-search','csrf'=>'test-token','search'=>'Alex']);
    endpoint_check($result['status']===200 && count($result['json']['residents'])===1 && $result['json']['residents'][0]['user_code']==='RESIDENT',"$role can search names without including staff/admin accounts");
    $result=endpoint_request($ids[$role],$role,['kind'=>'duplicates','csrf'=>'test-token','first_name'=>'Alex','last_name'=>'Test','birthdate'=>'2000-01-01']);
    endpoint_check($result['status']===200 && count($result['json']['matches'])===1 && strlen($result['json']['token'])===64,"$role receives a signed duplicate review, excluding staff/admin matches");
  }
  $result=endpoint_request($ids['resident'],'resident',['kind'=>'issue','csrf'=>'test-token','code'=>'RESIDENT']);
  endpoint_check($result['status']===403 && !isset($result['json']['resident']),'Residents cannot access lookup data');
  $result=endpoint_request($ids['staff'],'staff',['kind'=>'issue','csrf'=>'wrong','code'=>'RESIDENT']);
  endpoint_check($result['status']===403,'Invalid CSRF token rejected');
  $result=endpoint_request($ids['staff'],'staff',['kind'=>'issue','csrf'=>'test-token','code'=>'RESIDENT'],'GET');
  endpoint_check($result['status']===405,'Lookup rejects GET requests');
  $result=endpoint_request($ids['staff'],'staff',['kind'=>'issue','csrf'=>'test-token','code'=>'UNKNOWN']);
  endpoint_check($result['status']===422 && !$result['json']['ok'],'Unknown code returns a usable error');
} finally { $server->exec("DROP DATABASE `$testName`"); }
echo "Isolated endpoint database removed. No real accounts or SMS changed.\n";
