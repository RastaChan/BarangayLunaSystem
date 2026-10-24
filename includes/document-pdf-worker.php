<?php
// This converter is CLI-only. Neither source paths nor commands come from HTTP input.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = realpath($argv[1] ?? '');
$destination = $argv[2] ?? '';
$storage = str_replace('\\','/', realpath(dirname(__DIR__) . '/storage'));
$normalized = str_replace('\\','/', $source ?: '');
$destinationDir = realpath(dirname($destination));
if (!$source || strtolower(pathinfo($source,PATHINFO_EXTENSION)) !== 'docx' ||
    (!str_starts_with(strtolower($normalized),strtolower($storage.'/generated-documents/')) && !str_starts_with(strtolower($normalized),strtolower($storage.'/document-previews/'))) ||
    $destinationDir !== realpath(dirname(__DIR__).'/storage/document-previews') || !preg_match('/^\.conversion-[a-f0-9]{24}\.pdf$/D',basename($destination))) exit(2);
require_once __DIR__.'/document-pdf.php';
if (!class_exists('COM') || in_array('com',array_map('strtolower',array_map('trim',explode(',',(string)ini_get('disable_classes')))),true)) exit(3);
$word = null; $opened = null; $ok = false;
try {
  $word = new COM('Word.Application');
  $word->Visible = false;
  $word->DisplayAlerts = 0;
  $word->AutomationSecurity = 3;
  $word->Options->UpdateLinksAtOpen = false;
  $word->Options->SaveNormalPrompt = false;
  // Do not add generated documents to the user's recent-file list.
  $opened = $word->Documents->Open($source, false, true, false);
  $opened->ExportAsFixedFormat($destination, 17);
  $ok = barangay_pdf_valid($destination);
} catch (Throwable $error) {
  fwrite(STDERR, "Word conversion failed.\n");
} finally {
  if ($opened) { try { $opened->Close(false); } catch (Throwable $ignored) {} }
  if ($word) { try { $word->Quit(); } catch (Throwable $ignored) {} }
}
exit($ok ? 0 : 1);
