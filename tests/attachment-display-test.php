<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/record-attachments.php';
foreach (['image/jpeg', 'image/png', 'application/pdf'] as $mime) {
  $file = ['mime_type' => $mime, 'filename' => 'Resident evidence.jpg'];
  $headers = barangay_attachment_display_headers($file);
  if ($headers['Content-Type'] !== $mime || !str_starts_with($headers['Content-Disposition'], 'inline;')) throw new RuntimeException('Preview unavailable for ' . $mime);
  if (!str_starts_with(barangay_attachment_display_headers($file, true)['Content-Disposition'], 'attachment;')) throw new RuntimeException('Explicit download unavailable');
}
$headers = barangay_attachment_display_headers(['mime_type' => 'text/html', 'filename' => "unsafe\r\nheader.html"]);
if ($headers['Content-Type'] !== 'application/octet-stream' || !str_starts_with($headers['Content-Disposition'], 'attachment;') || str_contains($headers['Content-Disposition'], "\r\n")) throw new RuntimeException('Unsupported file must not execute inline or inject headers');
echo "PASS: photos and PDFs open inline, explicit downloads remain available, unsupported types download safely, and filenames cannot inject headers.\n";
