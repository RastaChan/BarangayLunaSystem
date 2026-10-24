<?php
if (PHP_SAPI!=='cli') {http_response_code(404); exit;}
$helpers=file_get_contents(__DIR__.'/../includes/document-template-helpers.php');
$helperStart=strpos($helpers,'function staff_current_template_year');
$helperEnd=strpos($helpers,'function staff_ensure_issued_document_file_columns');
eval(substr($helpers,$helperStart,$helperEnd-$helperStart));
function staff_ensure_document_template_file_columns(): void {}
function staff_canonical_document_type(string $type): string {return trim($type);}
function staff_redirect(...$args): never {throw new RuntimeException('Unexpected redirect');}
function barangay_db(): object {return new class {public function prepare($sql): object {return new class {public function execute($args): void {} public function fetch(): array {return ['effective_year'=>2024];}};}};}
$source=file_get_contents(__DIR__.'/../staff-actions.php');
$start=strpos($source,"    case 'save_template':"); $end=strpos($source,'      $templateFileName = null;', $start); $branch=substr($source,$start,$end-$start);
$_POST=['document_type'=>'Certificate of Indigency','name'=>'Forgotten old name','effective_year'=>1900]; $_FILES=[]; $redirect='issue-documents';
eval("switch('save_template'){".$branch.'break;}');
$current=staff_current_template_year();
if($year!==$current || $name!=="Certificate of Indigency $current Official Format") throw new RuntimeException('New metadata trusts stale fields');
echo "PASS: server generates current name/year independently of stale submitted fields. No records changed.\n";
