<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/template-preparation.php';
header('Cache-Control: private, no-store');
$user=barangay_current_user();
if(!$user || !in_array($user['role']??'', ['admin','staff'],true)) {http_response_code(403);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>'Sign in as Staff or Admin to prepare a template.']);exit;}
try {
  barangay_template_cleanup();
  if(($_SERVER['REQUEST_METHOD']??'')==='GET') {
    $token=is_string($_GET['token']??null)?$_GET['token']:'';$record=$_SESSION['template_preparation'][$token]??null;
    if(!$record || $record['user']!==(int)$user['id'] || $record['kind']!=='prepared' || $record['expires']<time())throw new InvalidArgumentException('This download has expired. Prepare the template again.');
    $path=barangay_template_work_path($token,'prepared');if(!is_file($path))throw new InvalidArgumentException('Prepare the template again to create a new download.');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="'.$record['name'].'"');header('Content-Length: '.filesize($path));session_write_close();readfile($path);exit;
  }
  header('Content-Type: application/json; charset=utf-8');
  if(($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Use the template preparation form.']);exit;}
  if(empty($_SESSION['template_fields_csrf']) || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['template_fields_csrf'],$_POST['csrf'])) {http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Refresh Template Management and try again.']);exit;}
  $action=$_POST['action']??'';
  if($action==='analyze') {
    $file=$_FILES['template_docx']??[];
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='docx' || !is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Choose a Word DOCX file in Upload Word template first.');
    $definitions=barangay_custom_field_definitions($_POST['definitions']??'[]');
    barangay_template_read_package($file['tmp_name']);
    $token=bin2hex(random_bytes(24));$path=barangay_template_work_path($token,'source');
    if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('The upload could not be stored. Please retry.');
    try {$plan=barangay_template_analyze($path,$definitions);}catch(Throwable $error){@unlink($path);throw $error;}
    while(count($_SESSION['template_preparation']??[])>=15)array_shift($_SESSION['template_preparation']);
    $name=basename(str_replace('\\','/',(string)$file['name']));
    $_SESSION['template_preparation'][$token]=['kind'=>'source','user'=>(int)$user['id'],'expires'=>time()+1800,'name'=>$name,'definitions'=>$definitions];
    echo json_encode(['ok'=>true,'token'=>$token,'name'=>$name,'paragraphs'=>array_values($plan['paragraphs']),'suggestions'=>$plan['suggestions'],'existing_tags'=>$plan['existing_tags'],'catalog'=>array_values($plan['catalog'])],JSON_INVALID_UTF8_SUBSTITUTE);exit;
  }
  if($action==='prepare') {
    if(($_POST['reviewed']??'')!=='1')throw new InvalidArgumentException('Review the highlighted replacements and check the confirmation box first.');
    $token=is_string($_POST['token']??null)?$_POST['token']:'';$record=$_SESSION['template_preparation'][$token]??null;
    if(!$record || $record['user']!==(int)$user['id'] || $record['kind']!=='source' || $record['expires']<time())throw new InvalidArgumentException('This analysis has expired. Upload the Word file again.');
    $mappings=barangay_custom_json($_POST['mappings']??'[]');if(!array_is_list($mappings))throw new InvalidArgumentException('Review the replacement list and try again.');
    $newFields=barangay_custom_field_definitions($_POST['new_fields']??'[]');
    $byKey=[];foreach($record['definitions'] as $field)$byKey[$field['key']]=$field;
    foreach($newFields as $field) {
      if(isset($byKey[$field['key']]))throw new InvalidArgumentException('A new field uses an existing tag. Choose that existing field instead.');
      $byKey[$field['key']]=$field;
    }
    $definitions=array_values($byKey);
    $resultToken=bin2hex(random_bytes(24));$target=barangay_template_work_path($resultToken,'prepared');
    try {$result=barangay_template_prepare(barangay_template_work_path($token,'source'),$target,$mappings,$definitions);}catch(Throwable $error){if(is_file($target))@unlink($target);throw $error;}
    $stem=trim(preg_replace('/[^a-z0-9_-]+/i','-',pathinfo($record['name'],PATHINFO_FILENAME)),'-');$name=substr($stem?:'document',0,100).'-prepared.docx';
    $_SESSION['template_preparation'][$resultToken]=['kind'=>'prepared','user'=>(int)$user['id'],'expires'=>time()+1800,'name'=>$name];
    echo json_encode(['ok'=>true,'url'=>'template-preparation.php?token='.$resultToken,'name'=>$name]+$result,JSON_INVALID_UTF8_SUBSTITUTE);exit;
  }
  throw new InvalidArgumentException('Choose Analyze or Prepare template.');
} catch(InvalidArgumentException $error) {http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>$error->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);}
catch(Throwable $error) {http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>'The template could not be prepared. Please try again with a fresh DOCX file.']);}
