<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--race') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]); require_once __DIR__ . '/../includes/husay-helpers.php';
  try { echo json_encode(['ok' => true] + husay_schedule(json_decode($argv[4], true), ['id' => (int)$argv[3], 'role' => 'admin'])); }
  catch (Throwable $e) { echo json_encode(['ok' => false, 'message' => $e->getMessage()]); }
  exit;
}
if (($argv[1] ?? '') === '--approval-race') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]); require_once __DIR__ . '/../includes/husay-helpers.php';
  try { echo json_encode(['ok'=>true] + husay_decide_schedule(json_decode($argv[4],true), ['id'=>(int)$argv[3],'role'=>'admin'])); }
  catch (Throwable $e) { echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); }
  exit;
}
if (($argv[1] ?? '') === '--endpoint') {
  putenv('BARANGAY_DB_NAME=' . $argv[2]); require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir()); barangay_start_session();
  $read = barangay_db()->prepare('SELECT * FROM users WHERE role=? LIMIT 1'); $read->execute([$argv[3]]);
  $_SESSION['barangay_user'] = barangay_format_user($read->fetch()) + ['auth_version' => 2]; $_SESSION['last_activity'] = time(); $_SESSION['husay_csrf'] = 'test-token';
  $_SERVER['HTTP_ACCEPT'] = 'application/json';
  $data = json_decode($argv[5], true);
  if ($argv[4] === 'actions') { $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $data; require __DIR__ . '/../husay-actions.php'; }
  elseif ($argv[4] === 'feed') { $_GET = $data; require __DIR__ . '/../husay-feed.php'; }
  elseif ($argv[4] === 'page') { $_GET = ['view'=>'husay-schedule']; require __DIR__ . ($argv[3] === 'admin' ? '/../dashboard.php' : '/../staff-dashboard.php'); }
  else { $_GET = $data; require __DIR__ . '/../husay-notice.php'; }
  exit;
}
function hc(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
function hr(callable $fn, string $message): void { try {$fn();} catch (InvalidArgumentException $e) {hc(true,$message);return;} throw new RuntimeException($message); }
function husay_test_process(array $args): array { $pipes=[]; $p=proc_open([PHP_BINARY,__FILE__,...$args],[1=>['pipe','w'],2=>['pipe','w']],$pipes); return [$p,$pipes]; }
function husay_test_output(array $process): string { [$p,$pipes]=$process; $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)||$err)throw new RuntimeException($out.$err);return $out; }
function husay_test_endpoint(string $name,string $role,string $path,array $data): string {return husay_test_output(husay_test_process(['--endpoint',$name,$role,$path,json_encode($data)]));}
$name='luna_husay_test_'.bin2hex(random_bytes(6));
$server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$server->exec("CREATE DATABASE `$name`");
putenv('BARANGAY_DB_NAME='.$name);
try {
  $server->exec("USE `$name`");$schema=preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s','',file_get_contents(__DIR__.'/../database/schema.sql'));$server->exec(str_replace('USE barangay_luna_system;','',$schema));
  require_once __DIR__.'/../includes/husay-helpers.php';husay_schema();$db=barangay_db();
  $insert=$db->prepare("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES (?,?,?,?, 'unused','Active')");
  foreach (['staff'=>'Official One','admin'=>'Official Two','resident'=>'Linked Resident'] as $role=>$full) {$insert->execute(['TEST-'.$role,$role,$full,'husay-'.$role]);$ids[$role]=(int)$db->lastInsertId();}
  $staff=['id'=>$ids['staff'],'role'=>'staff'];$admin=['id'=>$ids['admin'],'role'=>'admin'];$resident=['id'=>$ids['resident'],'role'=>'resident'];
  $date=(new DateTimeImmutable('tomorrow',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
  $base=['intake_mode'=>'walkin','complainant_name'=>'Walk-in Visitor','complainant_mobile'=>'09123456789','respondent_name'=>'Respondent','subject'=>'Neighbor dispute','details'=>'Test intake','location'=>'Test purok','venue'=>'Barangay Hall','assigned_user_id'=>$ids['admin'],'hearing_date'=>$date,'start_time'=>'09:00','end_time'=>'10:00','review_confirmed'=>'1','request_key'=>bin2hex(random_bytes(16))];
  // Captain approval workflow uses a separate date from the legacy regression cases.
  $approvalDate=(new DateTimeImmutable('+2 days',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
  $proposalInput=array_replace($base,['hearing_date'=>$approvalDate,'start_time'=>'08:00','end_time'=>'09:00','request_key'=>bin2hex(random_bytes(16)),'resident_code'=>'TEST-resident','review_confirmed'=>'0']);
  $proposal=husay_schedule($proposalInput,$staff);
  hc($proposal['status']==='Pending Approval','Staff can only propose a schedule, even without a claimed approval checkbox');
  $proposalRow=$db->query('SELECT * FROM husay_schedules WHERE id='.$proposal['id'])->fetch();
  hc($proposalRow['reviewed_by']===null && $db->query('SELECT status FROM complaints WHERE id='.$proposalRow['complaint_id'])->fetchColumn()==='Open','A staff proposal has no reviewer and does not claim the complaint is in a confirmed hearing');
  hc(husay_public_rows($ids['resident'])===[] && husay_resident_notifications($ids['resident'])===[],'Pending proposals are hidden from resident schedules and notifications');
  $staffSubmit=json_decode(husay_test_endpoint($name,'staff','actions',array_replace($proposalInput,['action'=>'schedule_husay','csrf'=>'test-token','send_sms'=>'1','review_confirmed'=>'1'])),true);
  hc($staffSubmit['ok'] && $staffSubmit['status']==='Pending Approval' && (int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn()===0,'Crafted staff SMS and approval fields cannot confirm a proposal or send appointment texts');
  $notice=husay_test_endpoint($name,'staff','notice',['id'=>$proposal['id']]);
  hc(str_contains($notice,'not approved'),'Unapproved proposals cannot be printed as appointment notices');
  $decision=['action'=>'approve_husay','hearing_id'=>$proposal['id'],'version'=>1,'review_confirmed'=>'1'];
  hr(fn()=>husay_decide_schedule($decision,$staff),'Staff cannot approve schedules through the backend');
  hr(fn()=>husay_decide_schedule($decision,['id'=>$ids['staff'],'role'=>'admin']),'A forged Administrator role is rejected against the database');
  hr(fn()=>husay_decide_schedule(array_replace($decision,['review_confirmed'=>'0']),$admin),'The Captain must explicitly confirm approval');
  foreach (['staff','admin'] as $role) {
    $detail=json_decode(husay_test_endpoint($name,$role,'feed',['hearing'=>$proposal['id']]),true)['html'];
    hc(str_contains($detail,'Approve schedule')===($role==='admin'),"$role sees only its permitted proposal actions");
  }
  husay_decide_schedule($decision,$admin);
  hc(count(husay_public_rows($ids['resident']))===1,'Captain approval publishes the appointment to the linked resident');
  hr(fn()=>husay_decide_schedule($decision,$admin),'An already reviewed proposal cannot be approved twice');
  $attendance=['action'=>'record_husay','hearing_id'=>$proposal['id'],'version'=>2,'complainant_attendance'=>'Present','respondent_attendance'=>'Present','private_notes'=>'Staff attendance note'];
  husay_record_session($attendance,$staff);
  hr(fn()=>husay_record_session(array_replace($attendance,['version'=>3,'outcome'=>'Settled','public_summary'=>'Staff attempted settlement']),$staff),'Staff attendance permission does not allow final outcome confirmation');
  hr(fn()=>husay_record_session(['action'=>'cancel_husay','hearing_id'=>$proposal['id'],'version'=>3,'change_reason'=>'Staff cancellation'],$staff),'Staff cannot cancel a confirmed hearing');
  $rescheduleInput=array_replace($proposalInput,['reschedule_id'=>$proposal['id'],'version'=>3,'start_time'=>'09:00','end_time'=>'10:00','change_reason'=>'Parties requested a change','request_key'=>bin2hex(random_bytes(16))]);
  $rescheduleProposal=husay_schedule($rescheduleInput,$staff);
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$proposal['id'])->fetchColumn()==='In Progress','A staff rescheduling proposal leaves the original appointment active');
  $blocking=husay_schedule(array_replace($base,['hearing_date'=>$approvalDate,'start_time'=>'09:00','end_time'=>'10:00','request_key'=>bin2hex(random_bytes(16))]),$admin);
  $rescheduleDecision=['action'=>'approve_husay','hearing_id'=>$rescheduleProposal['id'],'version'=>1,'review_confirmed'=>'1'];
  hr(fn()=>husay_decide_schedule($rescheduleDecision,$admin),'Approval rechecks conflicts created after a proposal was submitted');
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$proposal['id'])->fetchColumn()==='In Progress','A failed approval preserves the original appointment');
  hr(fn()=>husay_decide_schedule(array_replace($rescheduleDecision,['action'=>'decline_husay']),$admin),'Declining a proposal requires instructions or a reason');
  husay_decide_schedule(array_replace($rescheduleDecision,['action'=>'decline_husay','decision_reason'=>'Choose another time']),$admin);
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$rescheduleProposal['id'])->fetchColumn()==='Declined','Captain can decline a proposal with an auditable reason');
  $nextProposal=husay_schedule(array_replace($rescheduleInput,['start_time'=>'10:00','end_time'=>'11:00','request_key'=>bin2hex(random_bytes(16))]),$staff);
  husay_decide_schedule(['action'=>'approve_husay','hearing_id'=>$nextProposal['id'],'version'=>1,'review_confirmed'=>'1'],$admin);
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$proposal['id'])->fetchColumn()==='Rescheduled','Only Captain approval supersedes the original hearing');
  husay_record_session(['action'=>'record_husay','hearing_id'=>$nextProposal['id'],'version'=>2,'complainant_attendance'=>'Not applicable','respondent_attendance'=>'Not applicable','outcome'=>'Settled','public_summary'=>'Captain confirmed the reported outside settlement.'],$admin);
  $proposalComplaint=$db->query('SELECT complaint_code FROM complaints WHERE id='.$proposalRow['complaint_id'])->fetchColumn();
  hr(fn()=>husay_update_complaint(['id'=>$proposalComplaint,'status'=>'Resolved','remarks'=>'Staff finalization'],$staff),'Staff cannot bypass Captain outcome confirmation by resolving the complaint');
  husay_update_complaint(['id'=>$proposalComplaint,'status'=>'Resolved','remarks'=>'Captain confirmed settlement'],$admin);
  hr(fn()=>husay_update_complaint(['id'=>$proposalComplaint,'status'=>'Open','remarks'=>'Reopen'],$staff),'Staff cannot reopen a complaint finalized by the Captain');
  $blockedDetail=json_decode(husay_test_endpoint($name,'staff','feed',['hearing'=>$blocking['id']]),true)['html'];
  hc(str_contains($blockedDetail,'Complainant attendance') && !str_contains($blockedDetail,'name="outcome"') && !str_contains($blockedDetail,'Confirm cancellation'),'Staff detail form permits attendance and reschedule requests, with no outcome or cancellation controls');
  $before=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
  $previousHearingCount=(int)$db->query('SELECT COUNT(*) FROM husay_schedules')->fetchColumn();
  $one=husay_schedule($base,$admin);
  $parent=$db->query('SELECT c.* FROM complaints c JOIN husay_schedules h ON h.complaint_id=c.id WHERE h.id='.$one['id'])->fetch();
  hc($parent['intake_source']==='Walk-in' && $parent['status']==='In Progress' && $parent['resident_user_id']===null && (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===$before,'Walk-in intake creates a complaint and appointment without creating an account');
  hc(husay_schedule($base,$admin)['id']===$one['id'] && (int)$db->query('SELECT COUNT(*) FROM husay_schedules')->fetchColumn()===$previousHearingCount+1,'Repeated save returns the same appointment without duplicate walk-in complaints');
  $activeFeed=json_decode(husay_test_endpoint($name,'staff','feed',['complaint'=>$parent['complaint_code']]),true);
  hc($activeFeed['ok'] && $activeFeed['hasActive'] && $activeFeed['activeId']===$one['id'],'The complaint shortcut can find the existing appointment instead of scheduling a duplicate');
  $fresh=array_replace($base,['request_key'=>bin2hex(random_bytes(16))]);
  hr(fn()=>husay_schedule($fresh,$resident),'Residents cannot create or change Mediation schedules');
  hr(fn()=>husay_schedule(array_replace($fresh,['review_confirmed'=>'0']),$admin),'Scheduling requires review confirmation');
  hr(fn()=>husay_schedule(array_replace($fresh,['complainant_mobile'=>'']),$admin),'A complainant phone number is required before saving');
  hr(fn()=>husay_schedule(array_replace($fresh,['complainant_mobile'=>'12345']),$admin),'An invalid complainant phone number is rejected');
  hr(fn()=>husay_schedule(array_replace($fresh,['end_time'=>'08:00']),$admin),'An end time before the start is rejected');
  hr(fn()=>husay_schedule(array_replace($fresh,['hearing_date'=>'2026-02-30']),$admin),'Invalid calendar dates are rejected');
  hr(fn()=>husay_schedule(array_replace($fresh,['assigned_user_id'=>$ids['resident']]),$admin),'Assigned official must be an active Captain account');
  $complaintCount=(int)$db->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
  hr(fn()=>husay_schedule(array_replace($fresh,['assigned_user_id'=>$ids['admin'],'venue'=>'  barangay   hall ']),$admin),'Overlapping venues are rejected even with another official and different capitalization');
  hr(fn()=>husay_schedule(array_replace($fresh,['venue'=>'Another room']),$admin),'One official cannot be double-booked across venues');
  hr(fn()=>husay_schedule(array_replace($fresh,['assigned_user_id'=>$ids['admin'],'venue'=>'Different room']),$admin),'An identical time is blocked even for a different official and venue');
  foreach ([['08:30','09:30'],['09:30','10:30'],['09:15','09:45'],['08:30','10:30']] as [$from,$to]) {
    hr(fn()=>husay_schedule(array_replace($fresh,['assigned_user_id'=>$ids['admin'],'venue'=>'Different room','start_time'=>$from,'end_time'=>$to]),$admin),'Partial or contained overlaps cannot save: '.$from.'–'.$to);
  }
  hc((int)$db->query('SELECT COUNT(*) FROM complaints')->fetchColumn()===$complaintCount,'A conflicting walk-in appointment rolls back its complaint intake');
  $two=husay_schedule(array_replace($fresh,['request_key'=>bin2hex(random_bytes(16)),'resident_code'=>'TEST-resident','start_time'=>'10:00','end_time'=>'11:00']),$admin);
  hr(fn()=>husay_schedule(array_replace($fresh,['request_key'=>bin2hex(random_bytes(16)),'reschedule_id'=>$one['id'],'version'=>1,'start_time'=>'10:30','end_time'=>'11:30','change_reason'=>'Conflicting change']),$admin),'Rescheduling also rejects an overlapping appointment');
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$one['id'])->fetchColumn()==='Scheduled','A rejected reschedule keeps the original appointment');
  $linked=$db->query('SELECT c.* FROM complaints c JOIN husay_schedules h ON h.complaint_id=c.id WHERE h.id='.$two['id'])->fetch();
  hc((int)$linked['resident_user_id']===$ids['resident'] && $linked['resident_name']==='Linked Resident','Adjacent time slots work, and walk-in intake can link an existing Resident ID');
  hc(count(husay_public_rows($ids['resident']))===3 && husay_public_rows($ids['resident']+100)===[],'Residents see only appointments explicitly linked to their own account');
  hr(fn()=>husay_schedule(array_replace($fresh,['intake_mode'=>'linked','complaint_code'=>$linked['complaint_code'],'start_time'=>'13:00','end_time'=>'14:00']),$admin),'A complaint cannot have two active sessions');
  $early=husay_schedule(array_replace($base,['request_key'=>bin2hex(random_bytes(16)),'start_time'=>'18:00','end_time'=>'19:00']),$admin);
  $earlyInput=['action'=>'record_husay','hearing_id'=>$early['id'],'version'=>1,'complainant_attendance'=>'Not applicable','respondent_attendance'=>'Not applicable','outcome'=>'Settled','public_summary'=>'Complainant reported an outside settlement; no hearing held.'];
  hr(fn()=>husay_record_session(array_replace($earlyInput,['public_summary'=>'']),$admin),'Early settlement still requires a written summary');
  husay_record_session($earlyInput,$admin);
  $earlyRow=$db->query('SELECT * FROM husay_schedules WHERE id='.$early['id'])->fetch();
  hc($earlyRow['status']==='Completed' && $earlyRow['outcome']==='Settled' && $earlyRow['respondent_attendance']==='Not applicable','Future hearing can be settled without inventing attendance');
  hr(fn()=>husay_update_complaint(['id'=>$linked['complaint_code'],'status'=>'Resolved','remarks'=>'Done'],$admin),'An active session prevents premature complaint resolution');
  hr(fn()=>husay_update_complaint(['id'=>$linked['complaint_code'],'status'=>'Open','remarks'=>'Reopen'],$admin),'An active Mediation complaint cannot be reset to Open');
  require_once __DIR__.'/../includes/resident-submission-helpers.php';
  $db->exec("UPDATE complaints SET status='Open' WHERE id=".$linked['id']);
  hr(fn()=>resident_delete_own_submission('complaint',$linked['complaint_code'],$ids['resident']),'An active session prevents resident deletion even if a complaint status is stale');
  $db->exec("UPDATE complaints SET status='In Progress' WHERE id=".$linked['id']);
  $rescheduled=husay_schedule(array_replace($fresh,['request_key'=>bin2hex(random_bytes(16)),'reschedule_id'=>$one['id'],'version'=>1,'start_time'=>'12:00','end_time'=>'13:00','change_reason'=>'Parties requested another time']),$admin);
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$one['id'])->fetchColumn()==='Rescheduled' && $rescheduled['id']!==$one['id'],'Rescheduling keeps the old session and creates a linked replacement');
  hr(fn()=>husay_record_session(['action'=>'cancel_husay','hearing_id'=>$one['id'],'version'=>1,'change_reason'=>'Stale'],$admin),'Stale versions and superseded appointments cannot be changed');
  hr(fn()=>husay_record_session(['action'=>'cancel_husay','hearing_id'=>$rescheduled['id'],'version'=>1],$admin),'Cancellation requires a resident-visible reason');
  husay_record_session(['action'=>'cancel_husay','hearing_id'=>$rescheduled['id'],'version'=>1,'change_reason'=>'Appointment cancelled by parties'],$admin);
  hc($db->query('SELECT status FROM husay_schedules WHERE id='.$rescheduled['id'])->fetchColumn()==='Cancelled','Cancellation records the change without resolving the complaint');
  hr(fn()=>husay_record_session(['action'=>'record_husay','hearing_id'=>$two['id'],'version'=>1],$admin),'Attendance fields are still required for early recording');
  $session=['action'=>'record_husay','hearing_id'=>$two['id'],'version'=>1,'complainant_attendance'=>'Present','respondent_attendance'=>'Absent','public_summary'=>'Follow-up needed','private_notes'=>'STAFF-ONLY private case note'];
  husay_record_session($session,$admin);
  hr(fn()=>husay_record_session(array_replace($session,['version'=>2,'outcome'=>'For follow-up','public_summary'=>'']),$admin),'Completing a session requires an outcome summary');
  husay_record_session(array_replace($session,['version'=>2,'outcome'=>'For follow-up']),$admin);
  hc($db->query('SELECT status FROM complaints WHERE id='.$linked['id'])->fetchColumn()==='In Progress','Completing Mediation does not automatically close or resolve the complaint');
  hc(!str_contains(json_encode(husay_public_rows($ids['resident'])),'STAFF-ONLY') && !str_contains(json_encode(husay_resident_notifications($ids['resident'])),'STAFF-ONLY'),'Private notes never appear in resident schedule data or notifications');
  $notice=husay_test_endpoint($name,'staff','notice',['id'=>$two['id']]);
  hc(str_contains($notice,'Mediation Schedule Notice') && !str_contains($notice,'STAFF-ONLY'),'Printable appointment notices exclude private session notes');
  $denied=json_decode(husay_test_endpoint($name,'resident','feed',[]),true);
  hc(!$denied['ok'],'Residents cannot retrieve the staff scheduling feed');
  $denied=json_decode(husay_test_endpoint($name,'staff','actions',['action'=>'cancel_husay','hearing_id'=>$two['id'],'version'=>3,'csrf'=>'wrong']),true);
  hc(!$denied['ok'],'Invalid form tokens cannot change a Mediation session');
  hr(fn()=>husay_update_complaint(['id'=>$linked['complaint_code'],'status'=>'Closed','remarks'=>''],$admin),'Closing a complaint requires a written resolution summary');
  husay_update_complaint(['id'=>$linked['complaint_code'],'status'=>'Resolved','remarks'=>'Concern resolved after follow-up'],$admin);
  hc($db->query('SELECT status FROM complaints WHERE id='.$linked['id'])->fetchColumn()==='Resolved','The Captain can explicitly resolve a complaint with a summary after sessions end');
  $loggedInUser=$resident;
  $myComplaints=[['id'=>$linked['complaint_code'],'resident_user_id'=>$ids['resident'],'subject'=>'Neighbor dispute','category'=>'Test','date'=>$date,'status'=>'Resolved','remarks'=>'Concern resolved after follow-up']];
  ob_start();require __DIR__.'/../includes/resident-views/my-complaints.php';$residentHtml=ob_get_clean();
  hc(str_contains($residentHtml,'Mediation appointments') && str_contains($residentHtml,'Follow-up needed') && !str_contains($residentHtml,'STAFF-ONLY') && !str_contains($residentHtml,'data-husay-form'),'The resident complaint page shows appointments and public updates without private notes or editing controls');
  foreach(['RACE-1','RACE-2'] as $code)$db->prepare("INSERT INTO complaints(complaint_code,resident_name,subject,status) VALUES (?,'Test','Concurrent schedule','Open')")->execute([$code]);
  $raceBase=array_replace($fresh,['intake_mode'=>'linked','venue'=>'Concurrency test room','start_time'=>'14:00','end_time'=>'15:00']);
  $a=husay_test_process(['--race',$name,(string)$ids['admin'],json_encode(array_replace($raceBase,['complaint_code'=>'RACE-1','assigned_user_id'=>$ids['admin'],'request_key'=>bin2hex(random_bytes(16))]))]);
  $b=husay_test_process(['--race',$name,(string)$ids['admin'],json_encode(array_replace($raceBase,['complaint_code'=>'RACE-2','assigned_user_id'=>$ids['admin'],'venue'=>'Another concurrency room','request_key'=>bin2hex(random_bytes(16))]))]);
  $results=[json_decode(husay_test_output($a),true),json_decode(husay_test_output($b),true)];
  hc(count(array_filter($results,fn($r)=>$r['ok']))===1,'Concurrent requests cannot reserve overlapping times even across different venues and officials');
  hc((int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn()===0,'Tests schedule and record sessions without sending SMS');
  $db->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES ('sms_enabled','0') ON DUPLICATE KEY UPDATE setting_value='0'");
  $smsInput=array_replace($base,['request_key'=>bin2hex(random_bytes(16)),'start_time'=>'18:00','end_time'=>'19:00','action'=>'schedule_husay','csrf'=>'test-token','send_sms'=>'1']);
  $smsResult=json_decode(husay_test_endpoint($name,'admin','actions',$smsInput),true);
  hc($smsResult['ok'] && (int)$db->query("SELECT COUNT(*) FROM sms_logs WHERE status='Failed'")->fetchColumn()===1,'Disabled SMS is reported without undoing a successfully saved appointment');
  $smsRetry=json_decode(husay_test_endpoint($name,'admin','actions',$smsInput),true);
  hc($smsRetry['ok'] && $smsRetry['id']===$smsResult['id'] && (int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn()===1,'Retrying a saved form does not create another appointment or duplicate SMS attempt');
  $db->exec("INSERT INTO complaints(complaint_code,resident_name,subject,status) VALUES ('CMP-WAITING','Visitor <script>','Waiting concern','Open'),('CMP-CLOSED','Visitor','Closed concern','Closed')");
  $available = array_column(husay_available_complaints(),'complaint_code');
  hc(in_array('CMP-WAITING',$available,true) && !in_array('CMP-CLOSED',$available,true) && !in_array($smsResult['complaint_code'],$available,true),'The schedule picker lists open complaints without an active Mediation session');
  $feed=json_decode(husay_test_endpoint($name,'staff','feed',[]),true);
  hc($feed['ok'] && str_contains($feed['complaintsHtml'],'CMP-WAITING') && str_contains($feed['complaintsHtml'],'&lt;script&gt;') && !str_contains($feed['complaintsHtml'],'<script>'),'The live schedule feed refreshes eligible complaints and escapes resident-provided text');
  foreach (['admin','staff'] as $role) {
    $page=husay_test_endpoint($name,$role,'page',[]);
    $dom=new DOMDocument();@$dom->loadHTML($page);$xpath=new DOMXPath($dom);
    hc($xpath->query('//*[@data-husay-workspace]')->length===1 && $xpath->query('//a[@data-nav-view="husay-schedule" and contains(@class,"is-active")]')->length===1,"$role can open the dedicated schedule page with the correct active sidebar link");
    hc(str_contains($page, 'name="review_confirmed"')===($role==='admin') && str_contains($page, 'name="send_sms"')===($role==='admin'), "$role schedule form enforces Captain-only confirmation and notifications");
    if ($role === 'admin') hc($xpath->query('//*[@data-record-view]/parent::div[contains(@class,"modal-body") and not(contains(@class,"auto-fill-grid"))]')->length===1,'Admin complaint details keep attachment and Mediation sections outside the narrow detail grid');
    hc($xpath->query('//div[contains(@class,"husay-toolbar")]/button[@data-husay-existing]')->length===1 && $xpath->query('//div[contains(@class,"husay-toolbar")]/button[@data-husay-walkin]')->length===1 && $xpath->query('//div[contains(@class,"husay-toolbar")]/following-sibling::section[1][@data-husay-workspace]')->length===1,"$role scheduling buttons sit together immediately above Mediation schedules");
    hc($xpath->query('//button[@data-husay-mode]')->length===2 && $xpath->query('//*[@data-husay-calendar-grid]')->length===1 && $xpath->query('//select[@name="existing_complaint_code"]')->length===1,"$role schedule page includes calendar/list controls and existing-complaint intake");
  }
  $db->prepare('UPDATE husay_schedules SET starts_at=?,ends_at=? WHERE id=?')->execute([(new DateTimeImmutable('today',new DateTimeZone('Asia/Manila')))->format('Y-m-d').' 09:00:00',(new DateTimeImmutable('today',new DateTimeZone('Asia/Manila')))->format('Y-m-d').' 10:00:00',$smsResult['id']]);
  foreach (['admin','staff'] as $role) {
    $loggedInUser=['id'=>$ids[$role],'role'=>$role];ob_start();require __DIR__.'/../includes/husay-today.php';$summary=ob_get_clean();
    hc(str_contains($summary,"Today's Mediation") && str_contains($summary,'&amp;husay='.$smsResult['id']) && !str_contains($summary,'STAFF-ONLY'),"$role dashboard summary links directly to today's session without exposing private notes");
  }
  $approvalRaceInput=array_replace($base,['hearing_date'=>(new DateTimeImmutable('+4 days',new DateTimeZone('Asia/Manila')))->format('Y-m-d'),'request_key'=>bin2hex(random_bytes(16)),'review_confirmed'=>'0']);
  $raceProposalA=husay_schedule($approvalRaceInput,$staff);
  $raceProposalB=husay_schedule(array_replace($approvalRaceInput,['request_key'=>bin2hex(random_bytes(16))]),$staff);
  $decideBase=['action'=>'approve_husay','version'=>1,'review_confirmed'=>'1'];
  $approveA=husay_test_process(['--approval-race',$name,(string)$ids['admin'],json_encode($decideBase+['hearing_id'=>$raceProposalA['id']])]);
  $approveB=husay_test_process(['--approval-race',$name,(string)$ids['admin'],json_encode($decideBase+['hearing_id'=>$raceProposalB['id']])]);
  $approvalRaceResults=[json_decode(husay_test_output($approveA),true),json_decode(husay_test_output($approveB),true)];
  hc(count(array_filter($approvalRaceResults,fn($r)=>$r['ok']))===1,'Concurrent Captain approvals cannot confirm overlapping proposals');
  $approvalSmsInput=array_replace($base,['hearing_date'=>(new DateTimeImmutable('+3 days',new DateTimeZone('Asia/Manila')))->format('Y-m-d'),'request_key'=>bin2hex(random_bytes(16)),'review_confirmed'=>'0']);
  $approvalSms=husay_schedule($approvalSmsInput,$staff);
  $smsBefore=(int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn();
  $approvalResult=json_decode(husay_test_endpoint($name,'admin','actions',['action'=>'approve_husay','csrf'=>'test-token','hearing_id'=>$approvalSms['id'],'version'=>1,'review_confirmed'=>'1','send_sms'=>'1']),true);
  hc($approvalResult['ok'] && $approvalResult['status']==='Scheduled' && (int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn()===$smsBefore+1,'Only Captain approval may attempt appointment SMS; disabled transport remains safe');
} finally {$server->exec("DROP DATABASE `$name`");}
echo "Isolated database removed. No production complaints or appointments changed.\n";
