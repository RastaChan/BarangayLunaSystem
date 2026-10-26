<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$testName='luna_service_test_'.bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName`"); putenv('BARANGAY_DB_NAME='.$testName);
function service_check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
function service_reject(callable $call,string $label): void { $rejected=false; try { $call(); } catch (InvalidArgumentException $error) { $rejected=true; } service_check($rejected,$label); }
try {
  $server->exec("USE `$testName`");
  $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  require_once __DIR__.'/../includes/resident-service-tools.php';
  require_once __DIR__.'/../includes/report-metrics.php';
  $db=barangay_db();
  // Exercise upgrades without manufacturing times for historical records.
  $db->exec('ALTER TABLE document_requests DROP COLUMN ready_at');
  $db->exec('ALTER TABLE issued_documents DROP COLUMN prepared_at');
  barangay_issuance_schema();
  $user=$db->prepare("INSERT INTO users (user_code,role,full_name,username,password_hash,birthdate,status,qr_status,qr_code) VALUES (?,'resident',?,?,'test-only',?,'Active','Active',?)");
  $user->execute(['RES-TEST','Alex  Test','test-a','2000-01-01','LUNA|RES-TEST|current']);
  $residentId=(int)$db->lastInsertId();
  $user->execute(['RES-OTHER','Alex Test','test-b','2001-01-01','LUNA|RES-OTHER|current']);
  service_check(count(barangay_issue_resident_search('alex')['residents'])===2,'Partial resident names return matching people without auto-selecting');
  service_check(count(barangay_issue_resident_search('res-te')['residents'])===1,'Resident IDs can be searched without loading a full directory');
  service_check(barangay_issue_resident_search('%%')['residents']===[],'Search wildcard characters are treated as literal text');
  service_check(count(barangay_issue_resident_search('A')['residents'])===2,'A single letter returns partial matches');
  service_reject(fn()=>barangay_issue_resident_search(''),'Empty searches are rejected');
  for ($index=1;$index<=25;$index++) $user->execute(['SEARCH-'.$index,'Many Matches '.$index,'search-'.$index,'2005-01-01','LUNA|SEARCH-'.$index.'|current']);
  $many=barangay_issue_resident_search('Many');
  service_check(count($many['residents'])===20 && $many['more'] && !isset($many['residents'][0]['address']),'Large searches are capped at 20 and return only name and ID');
  service_check(barangay_issue_resident_lookup('res-test')['user_code']==='RES-TEST','Resident ID lookup selects the correct account');
  service_check(barangay_issue_resident_lookup('LUNA|RES-TEST|current')['user_code']==='RES-TEST','Full QR selects the correct account');
  service_check(barangay_issue_resident_lookup('LUNA|RES-TEST|old')===null,'Old or altered QR cannot resolve through its ID fragment');
  service_check(barangay_issue_resident_lookup("' OR 1=1 --")===null,'Lookup does not accept SQL injection');
  $db->exec("UPDATE users SET qr_status='Inactive' WHERE user_code='RES-TEST'");
  service_reject(fn()=>barangay_issue_resident_lookup('LUNA|RES-TEST|current'),'Inactive QR rejected');
  service_check(barangay_issue_resident_lookup('RES-TEST')!==null,'An active account can still be found by its ID');
  $db->exec("UPDATE users SET status='Inactive' WHERE user_code='RES-TEST'");
  service_check(count(barangay_issue_resident_search('Alex')['residents'])===1,'Inactive accounts are excluded from registered-resident search');
  service_reject(fn()=>barangay_issue_resident_lookup('RES-TEST'),'Inactive account rejected');
  $input=['first_name'=>' ALEX ','last_name'=>'TEST','no_middle_name'=>'1','birthdate'=>'2000-01-01'];
  $matches=barangay_resident_matches(barangay_resident_name($input),$input['birthdate']);
  service_check(count($matches)===1 && $matches[0]['user_code']==='RES-TEST','Case and whitespace normalized; different birthdate excluded; inactive duplicate included');
  service_reject(fn()=>barangay_require_duplicate_review($input),'Saving a matching resident requires review on the server');
  $input['duplicate_token']=barangay_duplicate_token(barangay_resident_name($input),$input['birthdate'],$matches);
  $input['duplicate_reviewed']='1'; barangay_require_duplicate_review($input);
  service_check(true,'Reviewed different-person match may proceed');
  service_reject(fn()=>barangay_require_duplicate_review(array_replace($input,['duplicate_token'=>'forged'])),'Forged review cannot bypass the duplicate warning');
  $user->execute(['RES-NEW','Alex Test','test-c','2000-01-01','LUNA|RES-NEW|current']);
  service_reject(fn()=>barangay_require_duplicate_review($input),'New matching records invalidate a stale review');
  service_reject(fn()=>barangay_resident_matches('Alex Test','2026-02-30'),'Invalid birthdate rejected');
  barangay_require_duplicate_review(['first_name'=>'Different','last_name'=>'Person','birthdate'=>'2000-01-01']);
  service_check(true,'An unrelated resident needs no duplicate override');
  $request=$db->prepare("INSERT INTO document_requests (request_code,resident_user_id,resident_name,document_type,status,requested_at,ready_at,archived_at) VALUES (?,?,'Test','Certification',?,?,?,?)");
  $request->execute(['READY',$residentId,'Released','2026-09-30 10:00:00','2026-10-01 10:00:00',null]);
  $request->execute(['REJECTED',$residentId,'Rejected','2026-09-01 10:00:00',null,null]);
  $request->execute(['OPEN',$residentId,'Pending','2026-09-02 10:00:00',null,null]);
  $request->execute(['ARCHIVED',$residentId,'Pending','2026-09-01 10:00:00',null,'2026-10-01 10:00:00']);
  $request->execute(['EVENT',$residentId,'Processing','2026-10-02 10:00:00',null,null]);
  service_check(barangay_document_request_event('EVENT','ready'),'Preparing a request records its actual ready timestamp');
  $readyAt=$db->query("SELECT ready_at FROM document_requests WHERE request_code='EVENT'")->fetchColumn();
  service_check($readyAt && !barangay_document_request_event('EVENT','ready') && $readyAt===$db->query("SELECT ready_at FROM document_requests WHERE request_code='EVENT'")->fetchColumn(),'Repeated ready action preserves timing');
  $doc=$db->prepare("INSERT INTO issued_documents (document_number,resident_name,document_type,request_code,issuance_status,prepared_at,released_at) VALUES (?,'Test','Certification',?,'Released',?,?)");
  $doc->execute(['ONLINE','READY','2026-10-01 10:00:00','2026-10-02 10:00:00']);
  $doc->execute(['WALKIN',null,'2026-10-02 10:00:00','2026-10-02 10:10:00']);
  $doc->execute(['LEGACY',null,null,'2026-10-02 10:20:00']);
  $doc->execute(['OUTSIDE',null,'2026-09-01 10:00:00','2026-09-01 10:20:00']);
  $metrics=barangay_service_metrics('2026-10-01','2026-10-02');
  service_check((int)$metrics['ready']['measured']===1 && (float)$metrics['ready']['seconds']===86400.0,'Preparation average uses preparation date, even for earlier submissions');
  service_check((int)$metrics['release']['measured']===1 && (float)$metrics['release']['seconds']===172800.0,'Online release average includes pickup delay and uses release date');
  service_check((int)$metrics['walkin']['released']===2 && (int)$metrics['walkin']['measured']===1 && (float)$metrics['walkin']['seconds']===600.0,'Walk-in average excludes unmeasured history and out-of-period records');
  service_check((int)$metrics['pending']['total']===2 && $metrics['open'][0]['request_code']==='OPEN','Open requests cover all dates, excluding rejected and archived records');
  service_check(barangay_service_duration(null)==='Not measured' && barangay_service_duration(600)==='10 min','Empty timing and elapsed minutes render clearly');
  $db->exec('DELETE FROM issued_documents'); $db->exec('DELETE FROM document_requests');
  $empty=barangay_service_metrics('2026-10-01','2026-10-02');
  service_check($empty['ready']['seconds']===null && (int)$empty['pending']['total']===0,'Empty service report has no fabricated average');
} finally { $server->exec("DROP DATABASE `$testName`"); }
echo "Isolated database removed. No live accounts, documents, or SMS changed.\n";
