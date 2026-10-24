<?php
require_once __DIR__ . '/includes/document-issuance-workflow.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
$record = null; $unavailable = false;
try {
  barangay_issuance_schema();
  $record = barangay_document_verification_public(is_string($_GET['token'] ?? null) ? $_GET['token'] : '');
} catch (Throwable $error) { $unavailable = true; http_response_code(503); }
if (!$record && !$unavailable) http_response_code(404);
$released = ($record['issuance_status'] ?? '') === 'Released';
function document_verify_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Barangay Luna Document Verification</title>
<style>body{margin:0;background:#f3f7f5;color:#18382a;font:16px/1.6 system-ui,sans-serif}main{max-width:560px;margin:40px auto;padding:24px;background:white;border:1px solid #d8e5dd;border-radius:14px;box-sizing:border-box}h1{font-size:1.5rem;line-height:1.3}header{font-weight:600;color:#176735}.status{padding:14px;border-radius:8px;background:<?= $released ? '#e7f5eb' : '#fff5de' ?>;font-weight:600}dl div{padding:12px 0;border-bottom:1px solid #e0e9e3}dt{color:#607468;font-size:.85rem}dd{margin:0;font-weight:600;overflow-wrap:anywhere}.hint{color:#607468;font-size:.9rem}@media(max-width:600px){main{margin:16px;padding:20px}}</style></head><body><main><header>Barangay Luna Smart Portal</header><h1>Document verification</h1>
<?php if ($record): ?>
<p class="status"><?= $released ? 'Released document — record found' : 'Not released — this document is not yet valid for release' ?></p>
<dl><div><dt>Document number</dt><dd><?= document_verify_escape($record['document_number']) ?></dd></div><div><dt>Document type</dt><dd><?= document_verify_escape($record['document_type']) ?></dd></div><div><dt>Copies</dt><dd><?= (int) $record['copies'] ?></dd></div><div><dt>Status</dt><dd><?= document_verify_escape($record['issuance_status']) ?></dd></div>
<?php if ($released): ?><div><dt>Release date</dt><dd><?= document_verify_escape($record['release_date']) ?></dd></div><?php endif; ?></dl>
<p class="hint">Compare the document number and type with the printed document. This confirms a portal record; contact the barangay office if the printed contents differ.</p>
<?php else: ?><p class="status"><?= $unavailable ? 'Verification is temporarily unavailable. Please try again later.' : 'Document not found. Check the QR code or contact the barangay office.' ?></p><?php endif; ?>
<p class="hint">Personal contact details are not displayed on this public page.</p></main></body></html>
