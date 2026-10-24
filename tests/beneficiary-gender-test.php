<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/document-template-helpers.php';
$template = '{{resident_name}} / {{gender}} / {{pronoun_possessive}} {{beneficiary_relationship}} {{beneficiary_name}}';
foreach (['Female'=>'her','Male'=>'his'] as $gender=>$pronoun) {
  foreach (['son','daughter'] as $relationship) {
    $resident=['full_name'=>'Test Parent','gender'=>$gender,'age'=>35];
    $values=staff_document_template_values($resident,'School','2026-10-13','Captain','TEST',[
      'beneficiary_name'=>'Test Child','beneficiary_relationship'=>$relationship,
      'resident_gender_override'=>$gender==='Female' ? 'Male' : 'Female',
    ]);
    $actual=strtr($template,$values);
    $expected="TEST PARENT / $gender / $pronoun $relationship TEST CHILD";
    if ($actual!==$expected || $resident['gender']!==$gender) throw new RuntimeException("Wrong parent gender for $gender / $relationship: $actual");
    echo "PASS: $gender parent keeps $pronoun for $relationship\n";
  }
}
foreach (['staff-actions.php','staff-document-preview.php','resident-document-preview.php'] as $path) {
  if (str_contains(file_get_contents(__DIR__.'/../'.$path),'resident_gender_override')) throw new RuntimeException("Legacy override accepted by $path");
}
foreach (['includes/staff-views/issue-documents.php','includes/resident-views/request-document.php'] as $path) {
  if (str_contains(file_get_contents(__DIR__.'/../'.$path),'resident_gender_override')) throw new RuntimeException("Duplicate gender selector remains in $path");
}
echo "PASS: issuance and both preview endpoints ignore legacy gender overrides; beneficiary forms have no duplicate gender selector\n";
