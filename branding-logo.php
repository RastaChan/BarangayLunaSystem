<?php
require_once __DIR__ . '/includes/record-attachments.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
  barangay_attachment_schema();
  $logo = barangay_db()->query("SELECT mime_type, content_base64 FROM record_attachments WHERE record_type = 'branding' AND record_code = 'logo' ORDER BY id DESC LIMIT 1")->fetch();
  if ($logo && in_array($logo['mime_type'], ['image/jpeg','image/png'], true)) {
    header('Content-Type: ' . $logo['mime_type']);
    echo base64_decode($logo['content_base64'], true);
    exit;
  }
} catch (Throwable $e) { /* Default branding remains available during database downtime. */ }
header('Content-Type: image/png');
readfile(__DIR__ . '/assets/images/barangay-logo.png');
