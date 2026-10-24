<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
if (($argv[1]??'')==='--feed') {
  putenv('BARANGAY_DB_NAME='.$argv[2]);
  require_once __DIR__.'/../includes/auth.php';
  session_save_path(sys_get_temp_dir());barangay_start_session();
  $stmt=barangay_db()->prepare('SELECT * FROM users WHERE role=? LIMIT 1');$stmt->execute([$argv[3]]);$row=$stmt->fetch();
  $_SESSION['barangay_user']=barangay_format_user($row)+['auth_version'=>2];$_SESSION['last_activity']=time();
  $_SERVER['REQUEST_METHOD']='GET';
  require __DIR__.'/../document-request-feed.php';exit;
}
$name='luna_request_feed_test_'.bin2hex(random_bytes(6));
$db=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("CREATE DATABASE `$name`");
function feed_read(string $dbName,string $role): array {
  $pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'--feed',$dbName,$role],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
  if($code||$err)throw new RuntimeException($out.$err);
  return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
function feed_check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
try {
  $db->exec("USE `$name`");$schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));$db->exec(str_replace('USE barangay_luna_system;','',$schema));
  $db->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('ADM-TEST','admin','Test Admin','test-admin','unused','Active'),('STF-TEST','staff','Test Staff','test-staff','unused','Active'),('RES-TEST','resident','Test Resident','test-resident','unused','Active')");
  $resident=(int)$db->query("SELECT id FROM users WHERE role='resident'")->fetchColumn();
  $insert=$db->prepare("INSERT INTO document_requests(request_code,resident_user_id,resident_name,document_type,copies,status) VALUES (?,?,'Test Resident','Barangay Certification',2,'Pending')");
  $insert->execute(['REQ-1',$resident]);
  foreach(['admin','staff'] as $role){$first=feed_read($name,$role);feed_check($first['ok']&&count($first['choices'])===1&&str_starts_with($first['owner'],$role.':'),"$role receives its authenticated request feed");}
  $insert->execute(['REQ-2',$resident]);$second=feed_read($name,'staff');
  feed_check(count($second['choices'])===2&&str_contains($second['html'],'data-document-request-code="REQ-2"'), 'New resident request appears in the next feed with working action markup');
  $proof = feed_read($name,'admin');
  $proof['html'] = preg_replace('/(<form\b[^>]*\baction=)["\x27][^"\x27]*["\x27]/i', '$1"#"', $proof['html']);
  file_put_contents(__DIR__.'/../storage/ui-previews/live-document-request-feed.json',json_encode($proof));
  $db->exec("UPDATE document_requests SET status='Processing' WHERE request_code='REQ-1'");$third=feed_read($name,'admin');feed_check(str_contains($third['html'],'Processing'),'Request status changes appear in the next feed');
  $db->exec("UPDATE document_requests SET status='Ready for Pickup' WHERE request_code='REQ-1'");
  $db->exec("INSERT INTO issued_documents(document_number,resident_name,document_type,request_code,issuance_status) VALUES ('PREPARED','Test Resident','Barangay Certification','REQ-1','Ready for Pickup')");
  $preparedId=(int)$db->lastInsertId();
  $db->exec("INSERT INTO issued_documents(document_number,resident_name,document_type,request_code,issuance_status) VALUES ('REMOVED','Test Resident','Barangay Certification','REQ-1','Removed')");
  $ready=feed_read($name,'staff');
  feed_check(str_contains($ready['html'],'staff-print-issued-document.php?id='.$preparedId.'"') && !str_contains($ready['html'],'Reprint from reports'),'Pickup action opens the active prepared document and does not offer reports before release');
  $db->exec("UPDATE document_requests SET status='Released' WHERE request_code='REQ-1'");
  $released=feed_read($name,'staff');
  feed_check(str_contains($released['html'],'Reprint from reports') && str_contains($released['html'],'badge-active">Released'),'Released requests have a reprint action and correct status color');
  $db->exec("UPDATE document_requests SET status='Rejected' WHERE request_code='REQ-1'");
  $rejected=feed_read($name,'staff');
  feed_check(str_contains($rejected['html'],'badge-rejected">Rejected'),'Rejected requests have the rejection status color');
  $db->exec("UPDATE document_requests SET status='Processing' WHERE request_code='REQ-1'");
  $db->exec("UPDATE document_requests SET archived_at=NOW() WHERE request_code='REQ-2'");$fourth=feed_read($name,'staff');feed_check(count($fourth['choices'])===1&&!str_contains($fourth['html'],'data-document-request-code="REQ-2"'),'Resident deletion removes the request from the feed');
  $denied=feed_read($name,'resident');feed_check($denied['ok']===false&&!isset($denied['html']),'Residents cannot read the staff request feed');
}finally{$db->exec("DROP DATABASE `$name`");}
echo "Isolated database removed. No production requests changed.\n";
