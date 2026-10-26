<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
require_once __DIR__ . '/../includes/document-template-helpers.php';
function custom_check(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); echo "PASS: $label\n"; }
function custom_reject(callable $callback, string $label): void { try { $callback(); } catch (InvalidArgumentException $error) { custom_check(true,$label); return; } throw new RuntimeException($label); }
barangay_custom_document_schema();
$raw=[['key'=>'business_name','label'=>'Business name','type'=>'text','required'=>true], ['key'=>'capital_amount','label'=>'Capital','type'=>'number'], ['key'=>'opening_date','label'=>'Opening date','type'=>'date'], ['key'=>'business_zone','label'=>'Zone','type'=>'select','options'=>['Zone 1','Zone 2']], ['key'=>'business_notes','label'=>'Notes','type'=>'textarea']];
$fields=barangay_custom_field_definitions($raw);
custom_check(count($fields)===5,'Five custom field types are supported');
custom_reject(fn()=>barangay_custom_field_definitions([['key'=>'resident_name','label'=>'Override','type'=>'text']]),'Standard resident tags cannot be overwritten');
custom_reject(fn()=>barangay_custom_field_definitions([$raw[0],$raw[0]]),'Duplicate custom tags are rejected');
custom_reject(fn()=>barangay_custom_field_definitions([['key'=>'bad tag','label'=>'Bad','type'=>'text']]),'Malformed custom tags are rejected');
custom_reject(fn()=>barangay_custom_validate_values($fields,[]),'Required custom details cannot be omitted');
custom_reject(fn()=>barangay_custom_validate_values($fields,['business_name'=>'Test','opening_date'=>'2026-02-30']),'Invalid calendar dates are rejected');
custom_reject(fn()=>barangay_custom_validate_values($fields,['business_name'=>'Test','capital_amount'=>'bad']),'Invalid number values are rejected');
custom_reject(fn()=>barangay_custom_validate_values($fields,['business_name'=>'Test','business_zone'=>'Other']),'Unlisted dropdown choices are rejected');
custom_reject(fn()=>barangay_custom_validate_values($fields,['business_name'=>['invalid']]),'Array values cannot enter a document');
$input=['business_name'=>'Luna & Sons <Shop>','capital_amount'=>'15000.50','opening_date'=>'2026-10-25','business_zone'=>'Zone 1','business_notes'=>"Line one\nLine two",'resident_name'=>'FORGED'];
$values=barangay_custom_validate_values($fields,$input);
custom_check(!isset($values['resident_name']) && $values['capital_amount']==='15000.50','Unknown fields are ignored and typed values retain precision');
custom_check(barangay_custom_validate_values($fields,[],true)['business_name']==='','Draft previews may leave required fields blank');
$db=barangay_db();$actor=(int)$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$record=['name'=>'Custom Business 2026 Official Format','type'=>'Custom Business','year'=>2026,'content'=>'{{business_name}} / {{capital_amount}}','file_name'=>null,'file_path'=>null,'status'=>'Active','user_id'=>$actor,'custom_fields'=>$fields];
$id=staff_save_template_replacement($db,$record);
custom_check(barangay_custom_fields_for_type(' custom   BUSINESS ') === $fields,'Definitions are saved and retrieved for the chosen document');
$record['content']='Replacement content';unset($record['custom_fields']);staff_save_template_replacement($db,$record);
custom_check(barangay_custom_fields_for_type('Custom Business')===$fields,'Replacing a template without field changes preserves definitions');
$copy=$record;$copy['type']='Private Draft';$copy['status']='Draft';$copy['custom_fields']=$fields;staff_save_template_replacement($db,$copy);
custom_check(barangay_custom_fields_for_type('Private Draft')===[],'Draft-only fields do not appear in resident or issuance forms');
$tags=array_merge(staff_document_template_values(['full_name'=>'Resident Test','age'=>21,'gender'=>'Male'],'Business','2026-10-25','Captain','TEST'),barangay_custom_template_values($values));
$xml='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>{{business_</w:t></w:r><w:r><w:t>name}} / {{capital_amount}} / {{resident_name}}</w:t></w:r></w:p></w:body></w:document>';
$rendered=staff_fill_docx_xml($xml,$tags);
custom_check(str_contains($rendered,'Luna &amp; Sons &lt;Shop&gt;') && str_contains($rendered,'15000.50') && !str_contains($rendered,'{{business_'),'Custom tags, split Word runs, XML escaping and standard tags render together');
$fixtureName = 'custom-fields-test-' . bin2hex(random_bytes(6)) . '.docx';
$fixturePath = 'storage/document-templates/' . $fixtureName;
$fixtureAbsolute = dirname(__DIR__) . '/' . $fixturePath;
if (!is_dir(dirname($fixtureAbsolute))) mkdir(dirname($fixtureAbsolute),0775,true);
$generated = null;
try {
  $zip = new ZipArchive(); $zip->open($fixtureAbsolute,ZipArchive::CREATE | ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
  $zip->addFromString('word/document.xml',$xml); $zip->close();
  $generated=staff_generate_docx_from_template($fixturePath,$tags,'CUSTOM-'.bin2hex(random_bytes(6)));
  custom_check($generated!==null,'A real DOCX is generated with additional field values');
  $zip=new ZipArchive();$zip->open(staff_docx_absolute_path($generated['path']));$output=$zip->getFromName('word/document.xml');$zip->close();
  custom_check(str_contains($output,'Luna &amp; Sons &lt;Shop&gt;') && str_contains($output,'15000.50') && !str_contains($output,'{{business_'),'The downloaded DOCX contains filled custom tags rather than unresolved markers');
} finally {
  if (is_file($fixtureAbsolute)) unlink($fixtureAbsolute);
  if ($generated && is_file(staff_docx_absolute_path($generated['path']))) unlink(staff_docx_absolute_path($generated['path']));
}
$db->beginTransaction();
try {
 $db->prepare("INSERT INTO document_requests(request_code,resident_name,document_type,status,requested_at,custom_values_json) VALUES ('REQ-CUSTOM-TEST','Test','Custom Business','Pending',NOW(),?)")->execute([json_encode($values)]);
 $request=$db->query("SELECT * FROM document_requests WHERE request_code='REQ-CUSTOM-TEST'")->fetch();
 $staffInput=barangay_request_issue_input([], $request);
 custom_check($staffInput['custom_fields']===$values,'Saved resident details carry through to staff issuance and preview');
 $db->prepare("INSERT INTO issued_documents(document_number,resident_name,document_type,custom_values_json) VALUES ('CUSTOM-SNAPSHOT','Test','Custom Business',?)")->execute([json_encode($values)]);
 custom_check(json_decode($db->query("SELECT custom_values_json FROM issued_documents WHERE document_number='CUSTOM-SNAPSHOT'")->fetchColumn(),true)===$values,'Issued records retain custom values for later reference');
} finally {$db->rollBack();}
$record['custom_fields']=[];staff_save_template_replacement($db,$record);
custom_check(barangay_custom_fields_for_type('Custom Business')===[],'An explicit empty definition removes custom fields from future forms');
echo "Temporary database only; no production documents or SMS changed.\n";
