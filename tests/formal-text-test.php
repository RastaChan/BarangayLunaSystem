<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/formal-text.php';
$cases = [
  'juan dela cruz'=>'Juan Dela Cruz',
  "ana-maria o'neill"=>"Ana-Maria O'Neill",
  'josé ñeri'=>'José Ñeri',
  'McDonald III'=>'McDonald III',
  'p-2 sitio looc, brgy. luna'=>'P-2 Sitio Looc, Brgy. Luna',
  '  juan  cruz '=>'  Juan  Cruz ',
];
foreach ($cases as $input=>$expected) if (barangay_formal_text($input)!==$expected) throw new RuntimeException('Formal capitalization mismatch.');
$input=['first_name'=>'juan','address'=>'barangay luna','username'=>'johncalang@luna.gov','password'=>'myPass123','resident_code'=>'res-001','message'=>'keep my text','mobile'=>'09123456789','household'=>'hh-001'];
$normalized=barangay_formal_inputs($input);
if ($normalized['first_name']!=='Juan'||$normalized['address']!=='Barangay Luna') throw new RuntimeException('Formal inputs must normalize when saved.');
foreach (['username','password','resident_code','message','mobile','household'] as $key) if ($normalized[$key]!==$input[$key]) throw new RuntimeException('Sensitive or free-text field modified: '.$key);
echo "PASS: Unicode names, punctuation, addresses, preserved spelling/acronyms and untouched credentials, IDs, messages and numbers.\n";
