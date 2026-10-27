<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sms-workspace.php';
$db = barangay_db();
$db->beginTransaction();
try {
  $tag = 'SMSLOOKUP' . bin2hex(random_bytes(5));
  $insert = $db->prepare('INSERT INTO users(user_code,role,full_name,username,password_hash,status,mobile) VALUES(?,?,?,?,?,?,?)');
  foreach ([['A','resident','Active','09171234567'],['B','resident','Inactive','09171234568'],['C','staff','Active','09171234569']] as [$suffix,$role,$status,$mobile]) {
    $insert->execute([$tag.$suffix,$role,$tag.' Person '.$suffix,$tag.$suffix.'@example.test','not-a-login-hash',$status,$mobile]);
  }
  $matches = sms_find_residents(strtolower($tag));
  if (count($matches)!==1 || $matches[0]['user_code']!==$tag.'A') throw new RuntimeException('Search must include only active residents, ignoring case.');
  if (sms_find_residents($tag.'A')[0]['mobile']!=='09171234567') throw new RuntimeException('Resident ID selection must include current mobile.');
  if (!in_array($tag.'A',array_column(sms_find_residents('09171234567'),'user_code'),true)) throw new RuntimeException('Mobile search failed.');
  if (sms_find_residents('')!==[] || sms_find_residents($tag.'%')!==[]) throw new RuntimeException('Empty and literal wildcard searches must not expose every resident.');
  echo "PASS: Current database residents found by name, ID, and mobile; inactive/staff excluded; literal wildcard and blank searches handled.\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
