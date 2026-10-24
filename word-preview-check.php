<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/document-template-helpers.php';

barangay_require_role('admin');

header('Content-Type: text/plain; charset=utf-8');

echo "Barangay Luna Word Preview Check\n";
echo "================================\n\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Loaded php.ini: " . (php_ini_loaded_file() ?: 'none') . "\n";
echo "extension_dir: " . ini_get('extension_dir') . "\n\n";

echo "ZIP extension: " . (class_exists('ZipArchive') ? 'OK' : 'MISSING') . "\n";
echo "COM extension: " . (class_exists('COM') ? 'OK' : 'MISSING') . "\n\n";

if (!class_exists('COM')) {
  echo "Result: Microsoft Word preview cannot work until extension=com_dotnet is enabled in the loaded php.ini.\n";
  exit;
}

try {
  $word = new COM('Word.Application');
  $word->Visible = false;
  $word->DisplayAlerts = 0;
  echo "Microsoft Word COM: OK\n";
  $word->Quit();
  echo "\nResult: PHP can open Microsoft Word. If preview still fails, check that the selected document type has an active uploaded DOCX template.\n";
} catch (Throwable $e) {
  echo "Microsoft Word COM: FAILED\n";
  echo "Error: " . $e->getMessage() . "\n\n";
  echo "Result: Open Microsoft Word manually once on this laptop, finish setup/sign-in/license prompts, close Word, then restart Laragon.\n";
}
?>
