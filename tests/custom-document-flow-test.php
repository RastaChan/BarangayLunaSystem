<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--action') {
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir());barangay_start_session();
  $role=$argv[2];$query=barangay_db()->prepare('SELECT * FROM users WHERE role=? LIMIT 1');$query->execute([$role]);$row=$query->fetch();
  $_SESSION['barangay_user']=barangay_format_user($row)+['auth_version'=>2];$_SESSION['last_activity']=time();
  $_SESSION['resident_service_csrf']='custom-flow-token';$_SESSION['document_request_csrf']='custom-flow-token';
  $_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_HOST']='localhost';$_SERVER['SCRIPT_NAME']='/staff-actions.php';
  $_POST=json_decode($argv[3],true);$_FILES=[];
  require __DIR__ . '/../' . ($role==='resident'?'resident-actions.php':'staff-actions.php');exit;
}
require_once __DIR__ . '/../includes/staff-functions.php';
require_once __DIR__ . '/../includes/document-template-helpers.php';
function flow_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function action_request(string $role,array $post):void {
 $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--action',$role,json_encode($post)],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
 $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit || $error || str_contains($out,'Fatal error') || str_contains($out,'Warning:'))throw new RuntimeException($out.$error);
}
barangay_custom_document_schema();$db=barangay_db();$actor=(int)$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$db->exec("INSERT INTO system_settings(setting_key,setting_value) VALUES ('sms_enabled','0') ON DUPLICATE KEY UPDATE setting_value='0'");
$relative='storage/document-templates/custom-flow-'.bin2hex(random_bytes(5)).'.docx';$absolute=dirname(__DIR__).'/'.$relative;
if(!is_dir(dirname($absolute)))mkdir(dirname($absolute),0775,true);
try {
 $zip=new ZipArchive();$zip->open($absolute,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $zip->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
 $zip->addFromString('word/document.xml','<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>{{resident_name}} runs {{business_name}} at {{business_location}}.</w:t></w:r></w:p></w:body></w:document>');$zip->close();
 $fields=[['key'=>'business_name','label'=>'Business name','type'=>'text','required'=>true],['key'=>'business_location','label'=>'Business location','type'=>'textarea','required'=>true]];
 staff_save_template_replacement($db,['name'=>'Custom Business 2026','type'=>'Custom Business','year'=>2026,'content'=>'{{business_name}}','file_name'=>'custom.docx','file_path'=>$relative,'status'=>'Active','user_id'=>$actor,'custom_fields'=>$fields]);
 $values=['business_name'=>'Luna Test Shop','business_location'=>'Zone 2, Test Street'];
 $request=['action'=>'request_document','redirect'=>'request-document','service_csrf'=>'custom-flow-token','review_confirmed'=>'1','reviewed_copies'=>'1','document_type'=>'Custom Business','purpose'=>'Test registration','copies'=>'1','custom_fields'=>$values];
 action_request('resident',$request);
 $saved=$db->query("SELECT * FROM document_requests WHERE document_type='Custom Business' ORDER BY id DESC LIMIT 1")->fetch();
 flow_check($saved && json_decode($saved['custom_values_json'],true)===$values,'Real resident submission saves the entered additional details');
 $request['custom_fields']=[];action_request('resident',$request);
 flow_check((int)$db->query("SELECT COUNT(*) FROM document_requests WHERE document_type='Custom Business'")->fetchColumn()===1,'Required additional details block incomplete resident submissions');
 $code='CUSTOM-FLOW-'.strtoupper(bin2hex(random_bytes(5)));
 action_request('staff',['action'=>'issue_document','redirect'=>'issue-documents','csrf'=>'custom-flow-token','requirements_checked'=>'1','request_code'=>$saved['request_code'],'document_type'=>'Custom Business','document_number'=>$code,'date_issued'=>date('Y-m-d'),'payment_mode'=>'unpaid','after_issue'=>'print']);
 $stmt=$db->prepare('SELECT * FROM issued_documents WHERE document_number=?');$stmt->execute([$code]);$issued=$stmt->fetch();
 flow_check($issued && json_decode($issued['custom_values_json'],true)===$values,'Staff issuance uses the saved resident details when no replacement is posted');
 $zip=new ZipArchive();$zip->open(staff_docx_absolute_path($issued['generated_file_path']));$xml=$zip->getFromName('word/document.xml');$zip->close();
 flow_check(str_contains($xml,'Luna Test Shop') && str_contains($xml,'Zone 2, Test Street') && !str_contains($xml,'{{business_'),'Final printable DOCX fills the additional fields through the actual issuance action');
 flow_check($issued['issuance_status']==='Ready for Pickup','Additional fields preserve the prepare-before-release workflow');
} finally {
 if(is_file($absolute))unlink($absolute);
 foreach($db->query("SELECT generated_file_path FROM issued_documents WHERE document_number LIKE 'CUSTOM-FLOW-%'")->fetchAll(PDO::FETCH_COLUMN) as $path) {
   $file=staff_docx_absolute_path($path);if(is_file($file))unlink($file);
 }
}
echo "Temporary records only; SMS disabled throughout the test.\n";
