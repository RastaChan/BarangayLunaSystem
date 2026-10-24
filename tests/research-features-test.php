<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$testName = 'luna_features_test_' . bin2hex(random_bytes(6));
$server = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$testName`");
putenv('BARANGAY_DB_NAME='.$testName); putenv('BARANGAY_PUBLIC_URL=http://localhost/BARANGAY%20LUNA%20SYSTEM');
$fixture = __DIR__.'/qr-document-fixture.docx';
function feature_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function feature_reject(callable $call, string $message): void { $ok=false; try { $call(); } catch (InvalidArgumentException $error) { $ok=true; } feature_check($ok,$message); }
try {
  $server->exec("USE `$testName`");
  $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  require_once __DIR__.'/../includes/document-issuance-workflow.php';
  require_once __DIR__.'/../includes/report-metrics.php';
  require_once __DIR__.'/../includes/system-configuration.php';
  $db=barangay_db(); barangay_issuance_schema();
  putenv('BARANGAY_PUBLIC_URL');
  $db->exec("DELETE FROM system_settings WHERE setting_key = 'portal_public_url'");
  $_SERVER['HTTP_HOST'] = '192.168.1.5'; $_SERVER['HTTPS'] = 'off';
  $testToken = str_repeat('a',64);
  foreach (['/BARANGAY LUNA SYSTEM/staff-print-issued-document.php','/BARANGAY%20LUNA%20SYSTEM/staff-print-issued-document.php'] as $script) {
    $_SERVER['SCRIPT_NAME'] = $script;
    feature_check(barangay_document_verification_url($testToken) === 'http://192.168.1.5/BARANGAY%20LUNA%20SYSTEM/verify-document.php?token=' . $testToken, 'Decoded and encoded project folder names produce the same valid QR link');
  }
  $_SERVER['HTTP_HOST'] = 'localhost:8080'; $_SERVER['HTTPS'] = 'on'; $_SERVER['SCRIPT_NAME'] = '/staff-print-issued-document.php';
  feature_check(barangay_document_verification_url($testToken) === 'https://localhost:8080/verify-document.php?token=' . $testToken, 'Root installation preserves HTTPS and the server port');
  putenv('BARANGAY_PUBLIC_URL=http://localhost/BARANGAY LUNA SYSTEM');
  feature_check(barangay_document_verification_url($testToken) === 'http://localhost/BARANGAY%20LUNA%20SYSTEM/verify-document.php?token=' . $testToken, 'Configured folder spaces are encoded once');
  foreach (['javascript:bad','http://user:password@localhost/portal','http://localhost/portal?redirect=bad','http://localhost/portal#fragment'] as $unsafeUrl) {
    putenv('BARANGAY_PUBLIC_URL=' . $unsafeUrl); $rejected = false;
    try { barangay_document_verification_url($testToken); } catch (RuntimeException $error) { $rejected = true; }
    feature_check($rejected, 'Invalid portal addresses remain rejected');
  }
  putenv('BARANGAY_PUBLIC_URL'); $_SERVER['HTTP_HOST'] = '192.168.1.5'; $_SERVER['HTTPS'] = 'off'; $_SERVER['SCRIPT_NAME'] = '/BARANGAY LUNA SYSTEM/staff-print-issued-document.php';
  $db->exec("INSERT INTO issued_documents (document_number,resident_name,document_type,purpose,fee_amount,issuance_status) VALUES ('TEST-QR','Private Applicant','Barangay Certification','Test',150,'Ready for Pickup')");
  $id=(int)$db->lastInsertId();
  $zip=new ZipArchive(); $zip->open($fixture,ZipArchive::CREATE|ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
  $zip->addFromString('word/document.xml','<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Document test</w:t></w:r></w:p><w:sectPr/></w:body></w:document>'); $zip->close();
  $verification=barangay_document_attach_verification($id,$fixture);
  feature_check(str_starts_with($verification['url'],'http://192.168.1.5/BARANGAY%20LUNA%20SYSTEM/verify-document.php?token='), 'Preparing an existing document succeeds in a folder containing spaces');
  feature_check(strlen($verification['token'])===64 && str_contains($verification['url'],'verify-document.php?token='),'Document receives an unpredictable verification link');
  $pending=barangay_document_verification_public($verification['token']);
  feature_check($pending['issuance_status']==='Ready for Pickup' && !isset($pending['resident_name']) && !isset($pending['contact_mobile']),'Public verification shows pending status and excludes private information');
  feature_check(barangay_document_verification_public("' OR 1=1 --")===null && barangay_document_verification_public(str_repeat('f',64))===null,'Invalid and unknown tokens cannot reveal records');
  $db->exec("UPDATE issued_documents SET issuance_status='Released', released_at=NOW() WHERE id=$id");
  feature_check(barangay_document_verification_public($verification['token'])['issuance_status']==='Released','Same QR reflects Confirm Release');
  $repeat=barangay_document_attach_verification($id,$fixture);
  feature_check($repeat===$verification,'Reprinting preserves the original QR link');
  $zip->open($fixture); $xml=$zip->getFromName('word/document.xml'); $png=$zip->getFromName('word/media/barangay-verification.png');
  feature_check(substr_count($xml,'<w:drawing>')===1,'Repeated preparation does not duplicate QR images');
  foreach (['word/document.xml','word/_rels/document.xml.rels','[Content_Types].xml'] as $part) { $dom=new DOMDocument(); feature_check($dom->loadXML($zip->getFromName($part)), "$part remains valid XML"); }
  $zip->close(); feature_check(getimagesizefromstring($png)['mime']==='image/png','Embedded QR is a valid PNG');
  $zip->open($fixture); $zip->addFromString('word/document.xml',str_replace('Document verification QR v2','Document verification QR',$xml)); $zip->close();
  feature_check(barangay_document_attach_verification($id,$fixture)===$verification,'Older QR stamps are upgraded without changing their verification link');
  $zip->open($fixture); $updated=$zip->getFromName('word/document.xml'); $relations=$zip->getFromName('word/_rels/document.xml.rels'); $zip->close();
  feature_check(str_contains($updated,'Document verification QR v2') && substr_count($updated,'<w:drawing>')===1 && substr_count($relations,'Id="rIdBarangayVerification"')===1,'QR layout upgrade preserves exactly one image and relationship');
  feature_check(strlen(barangay_document_qr_png('https://example.com/'.str_repeat('a',450)))>100,'Long portal links support QR versions above 10');
  $payment=$db->prepare('INSERT INTO document_payments (payment_code,resident_name,document_type,amount_due,amount_paid,status,received_at) VALUES (?,\'Test\',\'Barangay Certification\',?,?,?,?)');
  $today=date('Y-m-d');
  $payment->execute(['PAID',150,200,'Paid',$today.' 23:59:59']);
  $payment->execute(['UNPAID',80,null,'Pending',null]);
  $payment->execute(['VOID',70,70,'Voided',$today.' 12:00:00']);
  $payment->execute(['OLD',40,40,'Paid','2000-01-01 12:00:00']);
  $metrics=barangay_revenue_metrics($today,$today);
  feature_check((float)$metrics['revenue']===150.0 && (int)$metrics['transactions']===1,'Revenue excludes unpaid, voided and out-of-period payments');
  feature_check((float)$metrics['cash_received']===200.0 && (float)$metrics['change_due']===50.0,'Change does not inflate fee revenue');
  feature_check((float)$metrics['unpaid_balance']===80.0 && count($metrics['daily'])===1 && count($metrics['types'])===1,'Revenue breakdowns match recorded payments');
  feature_reject(fn()=>barangay_report_dates(['from'=>'2026-02-30','to'=>$today]),'Invalid dates are rejected');
  feature_reject(fn()=>barangay_report_dates(['from'=>'2030-01-01','to'=>'2000-01-01']),'Reversed date ranges are rejected');
  barangay_ensure_feedback_image_columns();
  $db->exec('DELETE FROM feedbacks');
  foreach ([1,2,3,4,5] as $score) feature_check(barangay_feedback_rating((string)$score)===$score,"Rating $score accepted");
  foreach ([null,0,6,'1.5','5anything',[],true] as $invalid) feature_reject(fn()=>barangay_feedback_rating($invalid),'Invalid rating rejected');
  $feedback=$db->prepare("INSERT INTO feedbacks (feedback_code,resident_name,message,rating,archived_at) VALUES (?,'Anonymous','Test',?,?)");
  $feedback->execute(['RATE-1',1,null]); $feedback->execute(['RATE-5',5,null]); $feedback->execute(['LEGACY',null,null]); $feedback->execute(['ARCHIVED',5,$today.' 00:00:00']);
  $ratings=barangay_feedback_metrics();
  feature_check($ratings['count']===2 && (float)$ratings['average']===3.0 && $ratings['unrated']===1,'Rating average excludes archived and unrated feedback');
  putenv('BARANGAY_PUBLIC_URL'); $db->exec("INSERT INTO system_settings (setting_key,setting_value) VALUES ('portal_public_url','http://192.168.1.5/portal') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
  feature_check(str_starts_with(barangay_document_verification_url($verification['token']),'http://192.168.1.5/portal/verify-document.php'),'Saved portal address supports phone verification');
  feature_reject(fn()=>barangay_configuration_values(['portal_public_url'=>'javascript:bad'],[],[]),'Unsafe portal addresses are rejected');
  $db->exec('DELETE FROM document_payments'); $empty=barangay_revenue_metrics($today,$today); feature_check((float)$empty['revenue']===0.0 && !$empty['daily'],'Empty revenue report is valid');
} finally { if (is_file($fixture)) unlink($fixture); $server->exec("DROP DATABASE `$testName`"); }
echo "Isolated database removed. No live records changed.\n";
