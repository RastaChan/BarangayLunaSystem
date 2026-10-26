<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$outputDir = __DIR__ . '/../storage/ui-previews/audit';
if (($argv[1] ?? '') === '--render') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]);
  $role = $argv[3]; $view = $argv[4];
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir());
  barangay_start_session();
  $row = barangay_db()->query("SELECT * FROM users WHERE role = '$role' LIMIT 1")->fetch();
  $_SESSION['barangay_user'] = barangay_format_user($row) + ['auth_version'=>2];
  $_SESSION['last_activity'] = time();
  $_GET = ['view'=>$view]; $_SERVER['REQUEST_METHOD']='GET';
  $_SERVER['HTTP_HOST']='localhost';
  $route = ['admin'=>'dashboard.php','staff'=>'staff-dashboard.php','resident'=>'resident-dashboard.php'][$role];
  $_SERVER['SCRIPT_NAME']='/BARANGAY LUNA SYSTEM/' . $route;
  ob_start(); require __DIR__ . '/../' . $route; $html = ob_get_clean();
  session_destroy();
  $html = str_replace('<head>', '<head><base href="/BARANGAY%20LUNA%20SYSTEM/"><script>window.fetch=async()=>({ok:false,status:403,headers:{get:()=>"application/json"},json:async()=>({ok:false,success:false,message:"Layout fixture"})});</script>', $html);
  // Preview forms can never submit to production handlers.
  $html = preg_replace('/(<form\b[^>]*\baction=)["\x27][^"\x27]*["\x27]/i', '$1"#"', $html);
  if (str_contains($html,'Fatal error') || str_contains($html,'Warning:')) throw new RuntimeException('View emitted a PHP error: ' . $view);
  file_put_contents($outputDir . '/' . $role . '-' . $view . '.html', $html);
  exit;
}
$name = 'luna_layout_' . bin2hex(random_bytes(6));
$server = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$name`");
try {
  $server->exec("USE `$name`");
  $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;','',$schema));
  $server->exec("INSERT INTO users (user_code,role,full_name,username,password_hash,status,address,mobile) VALUES ('ADM-TEST','admin','Layout Test Administrator','layout-admin@example.test','unused','Active','Test address','09123456789'),('STF-TEST','staff','Layout Test Staff','layout-staff@example.test','unused','Active','Test address','09123456789'),('RES-TEST','resident','Layout Test Resident','layout-resident@example.test','unused','Active','A longer test address in Barangay Luna, Surigao City','09123456789')");
  $server->exec("INSERT INTO announcements (title,body,status,publish_date) VALUES ('Community announcement for layout testing','A published test announcement with multiple lines.\nSecond line of the announcement.','Published',NOW())");
  $server->exec("INSERT INTO issued_documents (document_number,resident_name,document_type,issuance_status) VALUES ('LAYOUT-PREPARED','Layout Test Resident','Barangay Certification','Ready for Pickup'),('LAYOUT-RELEASED','Layout Released Resident','Barangay Certification','Released')");
  if (($argv[1] ?? '') === 'resident/request-document') $server->exec("UPDATE users SET gender='Female' WHERE role='resident'");
  if (($argv[1] ?? '') === 'resident/my-document-requests') {
    $residentFixtureId=(int)$server->query("SELECT id FROM users WHERE role = 'resident' LIMIT 1")->fetchColumn();
    $seedRequest=$server->prepare('INSERT INTO document_requests (request_code,resident_user_id,resident_name,document_type,fee_amount,unit_fee,copies,purpose,status,remarks,requested_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $seedRequest->execute(['REQ-002',$residentFixtureId,'Layout Test Resident','Barangay Certification',150,150,1,'School','Released','','2026-10-09 10:00:00']);
    $seedRequest->execute(['REQ-001',$residentFixtureId,'Layout Test Resident','Good Moral Certificate',150,150,1,'School','Rejected','Please bring the required proof of residency.','2026-10-07 10:00:00']);
    $seedRequest->execute(['REQ-003',$residentFixtureId,'Layout Test Resident','Barangay Certification',3000,150,20,'School','Pending','','2026-10-11 10:00:00']);
  }
  if (($argv[1] ?? '') === 'resident/my-complaints') {
    $residentFixtureId=(int)$server->query("SELECT id FROM users WHERE role='resident' LIMIT 1")->fetchColumn();
    $seedComplaint=$server->prepare('INSERT INTO complaints(complaint_code,resident_user_id,resident_name,subject,category,details,status) VALUES (?,?,?,?,?,?,?)');
    $seedComplaint->execute(['CMP-001',$residentFixtureId,'Layout Test Resident','Street light near the school','Street lighting','The street light does not work.','Open']);
    $seedComplaint->execute(['CMP-002',$residentFixtureId,'Layout Test Resident','Drainage concern','Drainage issue','Please inspect the drainage.','In Progress']);
  }
  if (($argv[1] ?? '') === 'resident/sms-updates') {
    $seedSms=$server->prepare('INSERT INTO sms_logs (recipient_name,recipient_mobile,message_type,message,status,created_at) VALUES (?,?,?,?,?,?)');
    foreach ([
      ['Document Released','BRGY LUNA: Your Barangay Certification (REQ-002) has been released. Document fee: PHP 150.00. Thank you.','Sent','2026-10-10 17:53:00'],
      ['Ready for Pickup','BRGY LUNA: Your Barangay Certification (REQ-002) is ready for pickup at the Barangay Luna office. Please bring your resident ID or QR code.','Sent','2026-10-09 17:46:00'],
      ['Request Received','BRGY LUNA: We received your Barangay Certification request (REQ-002). You can track its status in My Document Requests.','Sent','2026-10-08 17:39:00'],
      ['Custom','Please visit the barangay office for assistance with your request.','Failed','2026-09-01 09:00:00'],
    ] as $sms) $seedSms->execute(array_merge(['Layout Test Resident','09123456789'],$sms));
  }
  if (str_ends_with($argv[1] ?? '', '/walk-in-payments') || ($argv[1] ?? '') === 'admin/reports-analytics') {
    $receiver=(int)$server->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
    $seedPayment=$server->prepare('INSERT INTO document_payments (payment_code,document_number,resident_name,document_type,amount_due,amount_paid,status,received_by,received_at,created_at,receipt_number) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach (['Paid','Paid','Paid','Paid','Pending','Voided'] as $index=>$status) {
      $seedPayment->execute(['PAY-20261010-'.($index+1),'BL-20261010-102637-0E920E-'.$index,$index===2 ? 'Christian Deloy' : 'Judith Adolfo Deloy','Barangay Certification',150,$status==='Paid' ? 150 : null,$status,$status==='Paid' ? $receiver : null,$status==='Paid' ? '2026-10-10 16:01:00' : null,'2026-10-09 09:00:00',$status==='Paid' ? 'OR-00'.($index+1) : null]);
    }
  }
  if (!is_dir($outputDir)) mkdir($outputDir,0775,true);
  $views = [
    'admin'=>['dashboard','husay-schedule','residents','qr-verification','user-management','complaints','feedbacks','sms-alerts','reports-analytics','audit-logs','backup-restore','system-settings','template-placeholders','document-fees','issue-documents','resident-account-registration','service-reports','walk-in-payments'],
    'staff'=>['staff-dashboard','husay-schedule','resident-management','staff-qr-verification','issue-documents','resident-account-registration','walk-in-payments','staff-complaints','staff-feedbacks','staff-sms-alerts','service-reports','my-profile','my-activity-logs'],
    'resident'=>['resident-dashboard','request-document','my-document-requests','submit-complaint','my-complaints','send-feedback','sms-updates','my-profile','change-password','my-activity-logs'],
  ];
  foreach ($views as $role=>$pages) foreach ($pages as $view) {
    if (isset($argv[1]) && $argv[1] !== "$role/$view") continue;
    $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'--render',$name,$role,$view],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
    if ($code || $out || $err) echo "CHECK: $role/$view: $out$err\n"; else echo "PASS: rendered $role/$view\n";
  }
} finally { $server->exec("DROP DATABASE `$name`"); }
echo "Layout fixtures use test accounts only. Temporary database removed.\n";
