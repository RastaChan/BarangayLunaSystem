<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name='luna_resident_delete_test_' . bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$name`"); putenv('BARANGAY_DB_NAME='.$name);
function submission_check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function submission_reject(callable $fn,string $message): void { $rejected=false; try {$fn();} catch(InvalidArgumentException $e) {$rejected=true;} submission_check($rejected,$message); }
try {
  $server->exec("USE `$name`");
  $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  require_once __DIR__.'/../includes/resident-functions.php';
  require_once __DIR__.'/../includes/document-issuance-workflow.php';
  barangay_issuance_schema();
  $db=barangay_db();
  $db->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('RES-A','resident','Test Resident','test-a','unused','Active'),('RES-B','resident','Other Resident','test-b','unused','Active')");
  $owner=(int)$db->query("SELECT id FROM users WHERE user_code='RES-A'")->fetchColumn();
  $other=(int)$db->query("SELECT id FROM users WHERE user_code='RES-B'")->fetchColumn();
  $request=$db->prepare("INSERT INTO document_requests(request_code,resident_user_id,resident_name,document_type,status) VALUES (?,?,'Test Resident','Barangay Certification',?)");
  foreach(['Pending','Processing','Approved','Ready for Pickup','Released','Rejected'] as $index=>$status) $request->execute(['REQ-'.$index,$owner,$status]);
  submission_reject(fn()=>resident_delete_own_submission('document','REQ-0',$other),'Cannot delete another resident request');
  foreach(range(1,5) as $index) submission_reject(fn()=>resident_delete_own_submission('document','REQ-'.$index,$owner),'Request in non-pending status is protected: '.$index);
  resident_delete_own_submission('document','REQ-0',$owner);
  submission_check((int)$db->query("SELECT COUNT(*) FROM document_requests WHERE request_code='REQ-0' AND archived_at IS NOT NULL")->fetchColumn()===1,'Own pending request is removed without erasing audit record');
  submission_reject(fn()=>resident_delete_own_submission('document','REQ-0',$owner),'Repeated deletion cannot alter the record again');
  $request->execute(['REQ-PREPARED',$owner,'Pending']);
  $db->exec("INSERT INTO issued_documents(document_number,resident_name,document_type,request_code,issuance_status) VALUES ('TEST-PREPARED','Test Resident','Barangay Certification','REQ-PREPARED','Ready for Pickup')");
  submission_reject(fn()=>resident_delete_own_submission('document','REQ-PREPARED',$owner),'Prepared printable document prevents deletion even if request status is stale');
  $complaint=$db->prepare("INSERT INTO complaints(complaint_code,resident_user_id,resident_name,subject,status) VALUES (?,?,'Test Resident','Test concern',?)");
  foreach(['Open','In Progress','Resolved','Closed'] as $index=>$status) $complaint->execute(['CMP-'.$index,$owner,$status]);
  submission_reject(fn()=>resident_delete_own_submission('complaint','CMP-0',$other),'Cannot delete another resident complaint');
  foreach(range(1,3) as $index) submission_reject(fn()=>resident_delete_own_submission('complaint','CMP-'.$index,$owner),'Complaint already handled by staff is protected: '.$index);
  resident_delete_own_submission('complaint','CMP-0',$owner);
  submission_check((int)$db->query("SELECT COUNT(*) FROM complaints WHERE complaint_code='CMP-0' AND archived_at IS NOT NULL")->fetchColumn()===1,'Own open complaint is removed without erasing audit record');
  $input=['service_csrf'=>'token','review_confirmed'=>'1','copies'=>'20','reviewed_copies'=>'20'];
  resident_check_submission_review($input,'token',true);
  submission_reject(fn()=>resident_check_submission_review(array_replace($input,['reviewed_copies'=>'1']),'token',true),'Changing copies after review requires another confirmation');
  submission_reject(fn()=>resident_check_submission_review(array_replace($input,['review_confirmed'=>'0']),'token',true),'Submission requires explicit review confirmation');
  submission_reject(fn()=>resident_check_submission_review($input,'different',true),'Invalid CSRF cannot submit or delete');
  submission_check(count(resident_load_dashboard_data(['id'=>$owner,'name'=>'Test Resident','role'=>'resident','user_code'=>'RES-A'])['myDocumentRequests'])===6,'Deleted request disappears from resident list');
} finally {$server->exec("DROP DATABASE `$name`");}
echo "Isolated database removed. No production requests or complaints changed.\n";
