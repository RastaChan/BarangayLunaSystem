<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/document-template-helpers.php';
$source=__DIR__.'/../storage/document-previews/word-smoke-test.docx';
$cache=null;
try {
  $values=staff_document_template_values(['full_name'=>'TEST APPLICANT','address'=>'TEST ADDRESS','age'=>25,'gender'=>'Male','civil_status'=>'Single'],'SYSTEM PRINT TEST',date('Y-m-d'),'TEST OFFICIAL','PRINT-TEST');
  $template=__DIR__.'/../storage/document-templates/barangay-certification-2026-20261007152952.docx';
  if (!staff_generate_docx_with_ziparchive($template,$source,$values)) throw new RuntimeException('Test document creation failed');
  require_once __DIR__.'/../includes/document-verification.php';
  barangay_embed_document_qr($source,'http://localhost/verify-document.php?token='.str_repeat('0',64));
  $cache=barangay_pdf_cache($source); $start=microtime(true);
  $result=barangay_prepare_document_pdf($source,20);
  echo json_encode(['elapsed'=>round(microtime(true)-$start,2),'result'=>$result],JSON_PRETTY_PRINT)."\n";
  if ($result['ok']) {
    // Keep this synthetic PDF for visual verification; the source contains no real resident data.
    $saved=__DIR__.'/../storage/document-previews/word-smoke-test.pdf';
    copy($cache['path'],$saved);
  }
} finally {
  if (is_file($source)) unlink($source);
  if ($cache && is_file($cache['path'])) unlink($cache['path']);
}
