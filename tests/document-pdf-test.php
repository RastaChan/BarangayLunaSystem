<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--worker') {
  $output = $argv[2]; $mode=$argv[3];
  if ($mode==='hang') { sleep(8); exit; }
  if ($mode==='invalid') { file_put_contents($output,'not a PDF'); exit; }
  file_put_contents($output,"%PDF-1.4\nTest worker result\n%%EOF\n");
  if ($mode==='close-hang') sleep(8);
  exit;
}
require_once __DIR__.'/../includes/document-pdf.php';
function pdf_check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
$prefix=__DIR__.'/../storage/document-previews/pdf-test-'.bin2hex(random_bytes(6));
$source=$prefix.'.docx'; $output=$prefix.'.pdf'; $cache=null; $lock=null;
try {
  pdf_check(barangay_pdf_php_binary() !== null && is_file(barangay_pdf_php_binary()),'Laragon resolves its installed PHP worker despite its compiled C:\\php path');
  file_put_contents($source,'TEST DOCX CONTENT');
  $cache=barangay_pdf_cache($source);
  $result=barangay_pdf_run_worker([PHP_BINARY,__FILE__,'--worker',$output,'success'],$output,2);
  pdf_check($result['ok'] && barangay_pdf_valid($output),'Successful conversion produces a complete PDF'); unlink($output);
  $start=microtime(true);
  $result=barangay_pdf_run_worker([PHP_BINARY,__FILE__,'--worker',$output,'hang'],$output,.3);
  pdf_check(!$result['ok'] && $result['code']==='timeout' && microtime(true)-$start<2,'A hung converter is stopped within the configured deadline');
  $result=barangay_pdf_run_worker([PHP_BINARY,__FILE__,'--worker',$output,'close-hang'],$output,.3);
  pdf_check($result['ok'] && barangay_pdf_valid($output),'A complete PDF remains usable if Word stalls during closing'); unlink($output);
  $result=barangay_pdf_run_worker([PHP_BINARY,__FILE__,'--worker',$output,'invalid'],$output,2);
  pdf_check(!$result['ok'] && $result['code']==='failed','Malformed output is rejected'); unlink($output);
  pdf_check(!is_file($output.'.log'),'Worker logs are cleaned up');
  $result=barangay_pdf_run_worker([PHP_BINARY,__FILE__,'--worker',$output,'success'],$output,2);
  pdf_check($result['ok'],'A conversion succeeds after a timeout');
  rename($output,$cache['path']);
  $result=barangay_prepare_document_pdf($source);
  pdf_check($result['ok'] && $result['cached']===true && $result['url']===$cache['url'],'Cached PDFs skip Word entirely');
  file_put_contents($source,'CHANGED DOCX CONTENT');
  pdf_check(barangay_pdf_cache($source)['path']!==$cache['path'],'Changing the DOCX invalidates the previous preview');
  $lock=fopen(dirname($output).'/.word-conversion.lock','c'); flock($lock,LOCK_EX);
  $result=barangay_prepare_document_pdf($source);
  pdf_check(!$result['ok'] && $result['code']==='busy','Concurrent conversion returns a retry message immediately');
  flock($lock,LOCK_UN); fclose($lock); $lock=null;
  $bad=$prefix.'-truncated.pdf'; file_put_contents($bad,'%PDF-1.4 partial document'); pdf_check(!barangay_pdf_valid($bad),'Truncated PDF files cannot become ready previews'); unlink($bad);
  try { barangay_prepare_document_pdf(__FILE__); throw new RuntimeException('Unexpected path accepted'); } catch (InvalidArgumentException $error) { echo "PASS: Invalid source paths are rejected\n"; }
} finally {
  if (is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); }
  foreach ([$source,$output,$output.'.log',$cache['path']??''] as $path) if ($path && is_file($path)) unlink($path);
}
