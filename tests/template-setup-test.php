<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--setup') {
  require_once __DIR__ . '/../includes/auth.php';
  session_save_path(sys_get_temp_dir());barangay_start_session();
  $role=$argv[2];$query=barangay_db()->prepare('SELECT * FROM users WHERE role=? LIMIT 1');$query->execute([$role]);$user=$query->fetch();
  $_SESSION['barangay_user']=barangay_format_user($user)+['auth_version'=>2];$_SESSION['last_activity']=time();$_SESSION['template_fields_csrf']='setup-test-token';
  $_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_REQUESTED_WITH']='TemplateSetup';$_POST=json_decode($argv[3],true);$_FILES=[];
  require __DIR__ . '/../staff-actions.php';exit;
}
require_once __DIR__ . '/../includes/staff-functions.php';
function setup_check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function setup_request(string $role,array $post):array {
 $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--setup',$role,json_encode($post)],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
 $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit || $err)throw new RuntimeException($out.$err);
 return $out!==''?json_decode($out,true,512,JSON_THROW_ON_ERROR):[];
}
$fields=barangay_custom_field_definitions([['key'=>'business_name','label'=>'Business name','type'=>'text','required'=>true]]);
setup_check(staff_template_tag_problems('{{resident_name}} owns {{business_name}}',$fields)===[],'Standard and additional Word tags are recognized together');
setup_check(str_contains(implode(' ',staff_template_tag_problems('{{business_nam}}',$fields)),'business_nam') && str_contains(implode(' ',staff_template_tag_problems('{{resident_name}}',$fields)),'Business name'),'Unmatched Word tags and missing field tags explain exactly what to fix');
setup_check(str_contains(implode(' ',staff_template_tag_problems('{{business_name}',$fields)),'incomplete'),'Incomplete braces are reported');
setup_check(str_contains(implode(' ',staff_template_tag_problems('{{ business_name }}',$fields)),'extra spaces'),'Misformatted tags are reported');
setup_check(str_contains(implode(' ',staff_template_tag_problems('Plain document text',[])),'No fill-in tags'),'Templates without fill-in tags cannot be mistaken for a working template');
$db=barangay_db();barangay_custom_document_schema();$actor=(int)$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$relative='storage/document-templates/setup-check-'.bin2hex(random_bytes(5)).'.docx';$absolute=dirname(__DIR__).'/'.$relative;if(!is_dir(dirname($absolute)))mkdir(dirname($absolute),0775,true);
try {
 $zip=new ZipArchive();$zip->open($absolute,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $zip->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
 $zip->addFromString('word/document.xml','<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>{{resident_name}} operates {{business_</w:t></w:r><w:r><w:t>name}}</w:t></w:r></w:p></w:body></w:document>');$zip->close();
 staff_check_template_custom_tags($relative,$fields);
 setup_check(true,'Tag checks read Word tags split across formatting runs');
 staff_save_template_replacement($db,['name'=>'Setup Test','type'=>'Setup Test','year'=>staff_current_template_year(),'content'=>'{{business_name}}','file_name'=>'setup.docx','file_path'=>$relative,'status'=>'Active','user_id'=>$actor,'custom_fields'=>$fields]);
 $post=['action'=>'save_template','redirect'=>'issue-documents','template_fields_csrf'=>'setup-test-token','document_type'=>'Setup Test','content'=>'{{business_name}}','status'=>'Active','custom_field_definitions'=>json_encode($fields)];
 foreach(['staff','admin'] as $role) {
  $response=setup_request($role,$post);setup_check(($response['ok']??false) && str_contains($response['redirect'],'template-management'),"$role can save a checked template without a page-changing validation error");
 }
 $bad=$post;$bad['custom_field_definitions']=json_encode([['key'=>'business_location','label'=>'Business location','type'=>'text']]);
 $response=setup_request('staff',$bad);setup_check(!($response['ok']??true) && str_contains($response['message'],'business_location') && str_contains($response['message'],'business_name'),'Save rejects mismatched fields with clear messages in the setup window');
 setup_check(barangay_custom_fields_for_type('Setup Test')===$fields,'A failed check preserves the previously saved template and field definitions');
 $bad=$post;$bad['template_fields_csrf']='wrong';$response=setup_request('staff',$bad);setup_check(!($response['ok']??true),'Template setup retains its CSRF protection');
 $response=setup_request('resident',$post);setup_check($response===[] && barangay_custom_fields_for_type('Setup Test')===$fields,'Residents cannot edit document templates');
} finally {if(is_file($absolute))unlink($absolute);}
echo "Temporary database only; no live SMS or templates changed.\n";
