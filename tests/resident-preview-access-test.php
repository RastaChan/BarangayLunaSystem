<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--worker') {
  putenv('BARANGAY_DB_NAME='.$argv[2]);
  require_once __DIR__.'/../includes/auth.php';
  session_save_path(sys_get_temp_dir()); barangay_start_session();
  $mode=$argv[3];
  $role=$mode==='staff'?'staff':'resident';
  $row=barangay_db()->query("SELECT * FROM users WHERE role = '$role' LIMIT 1")->fetch();
  if ($mode!=='anonymous') $_SESSION['barangay_user']=barangay_format_user($row)+['auth_version'=>2];
  $_SESSION['last_activity']=time(); $_SESSION['resident_preview_csrf']='good-token';
  $token=str_repeat('a',48);
  $_SESSION['resident_document_previews'][$token]=['user'=>$mode==='other'?(int)$row['id']+999:(int)$row['id'],'path'=>$argv[4],'expires'=>$mode==='expired'?time()-1:time()+600];
  $_SERVER['REQUEST_METHOD']=$mode==='csrf'?'POST':'GET'; $_GET=['token'=>$mode==='unknown'?str_repeat('b',48):$token]; $_POST=['preview_csrf'=>'bad-token'];
  register_shutdown_function(static function(){
    echo "\nSTATUS=".(http_response_code() ?: 200);
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    else { $sessionFile=sys_get_temp_dir().'/sess_'.session_id(); if (is_file($sessionFile)) unlink($sessionFile); }
  });
  require __DIR__.'/../resident-document-preview.php'; exit;
}
$name='luna_resident_preview_'.bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$path='storage/document-previews/preview-access-test-'.bin2hex(random_bytes(6)).'.pdf';
try {
  $server->exec("CREATE DATABASE `$name`");$server->exec("USE `$name`");
  $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  $server->exec("INSERT INTO users (user_code,role,full_name,username,password_hash,status) VALUES ('RES-TEST','resident','Preview Resident','preview@example.test','unused','Active'),('STF-TEST','staff','Preview Staff','staff@example.test','unused','Active')");
  file_put_contents(__DIR__.'/../'.$path,'%PDF-1.4 TEST ONLY');
  foreach (['own'=>200,'other'=>404,'expired'=>404,'unknown'=>404,'csrf'=>403,'staff'=>403,'anonymous'=>403] as $mode=>$status) {
    $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$name,$mode,$path],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    if ($code || $error || !str_contains($output,'STATUS='.$status)) throw new RuntimeException($mode.' failed: '.$output.' '.$error);
    if ($mode==='own' && !str_contains($output,'%PDF-1.4 TEST ONLY')) throw new RuntimeException('Own preview missing');
  }
  if ((int)$server->query('SELECT COUNT(*) FROM document_requests')->fetchColumn() || (int)$server->query('SELECT COUNT(*) FROM issued_documents')->fetchColumn()) throw new RuntimeException('Preview must never submit or issue documents');
  echo "PASS: private resident previews, expired and unknown tokens, owner isolation, role and CSRF checks; no requests or issued documents created.\n";
} finally {
  if (is_file(__DIR__.'/../'.$path)) unlink(__DIR__.'/../'.$path);
  $server->exec("DROP DATABASE IF EXISTS `$name`");
}
