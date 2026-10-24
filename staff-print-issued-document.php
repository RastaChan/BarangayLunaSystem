<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';
require_once __DIR__ . '/includes/document-template-helpers.php';
require_once __DIR__ . '/includes/document-pdf.php';

function print_document_error(string $message, int $status = 404): never {
  http_response_code($status);
  header('Cache-Control: no-store');
  echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Document unavailable</title><style>body{margin:0;padding:24px;font:16px Arial;background:#f5f7f4;color:#102014}main{padding:24px;background:white;border:1px solid #d9e4dc;border-radius:12px}h1{font-size:20px}</style></head><body data-document-print data-preview-error="1"><main><h1>Printable document unavailable</h1><p>' . htmlspecialchars($message) . '</p></main></body></html>';
  exit;
}

$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['staff', 'admin'], true)) {
  print_document_error('Your session has expired. Close this popup and sign in again.', 401);
}
$id = (int) ($_GET['id'] ?? 0);
$popup = ($_GET['popup'] ?? '') === '1';
barangay_issuance_schema();
$_SESSION['document_request_csrf'] ??= bin2hex(random_bytes(32));

if ($id < 1) {
  print_document_error('This prepared document could not be found. Close this popup and refresh the prepared list.');
}

$stmt = barangay_db()->prepare(
  'SELECT id, document_number, resident_name, document_type, copies, generated_file_name, generated_file_path, issuance_status, request_code, fee_amount
   FROM issued_documents
   WHERE id = :id
   LIMIT 1'
);
$stmt->execute([':id' => $id]);
$document = $stmt->fetch();
$payment = $document ? barangay_find_document_payment($document['request_code'], $document['document_number']) : null;

if (!$document || empty($document['generated_file_path'])) {
  print_document_error('There is no printable file for this entry. Close this popup and remove the unwanted entry from Prepared documents awaiting release, then prepare a new document if needed.');
}

if (!in_array($document['issuance_status'], ['Ready for Pickup', 'Released'], true)) {
  print_document_error('This preparation was removed and is no longer available for printing or release.', 410);
}

$docxPath = staff_docx_absolute_path((string) $document['generated_file_path']);
if (!is_file($docxPath)) {
  print_document_error('The saved printable file is missing. Close this popup and remove the unwanted prepared entry, then prepare a new document if needed.');
}

$preparationError = null;
$verification = null;
$pdfUrl = '';
$pdfReady = false;
try {
  $verification = barangay_document_attach_verification($id, $docxPath);
  clearstatcache(true, $docxPath);
  $pdfCache = barangay_pdf_cache($docxPath);
  $pdfUrl = $pdfCache['url'];
  $pdfReady = barangay_pdf_valid($pdfCache['path']);
} catch (Throwable $error) {
  error_log('Document print preparation failed for document ' . $id . ': ' . $error->getMessage());
  $preparationError = $error->getMessage() === 'Configure a valid public portal URL.'
    ? 'The portal address needs to be corrected in System Configuration before the document QR can be prepared.'
    : 'The document could not be prepared. Please retry. If the problem continues, ask the administrator to check document preparation.';
}

staff_log($user, 'Opened print view for issued document: ' . $document['document_number'], 'Issue Documents');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Print <?= htmlspecialchars($document['document_number']) ?> | Barangay Luna</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f5f7f4; color: #102014; }
    .print-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #d9e4dc; position: sticky; top: 0; z-index: 2; }
    .print-toolbar h1 { margin: 0; font-size: 16px; }
    .print-toolbar p { margin: 2px 0 0; font-size: 12px; color: #66756b; }
    .toolbar-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn { border: 1px solid #bdd2c2; border-radius: 6px; background: #fff; color: #064e2a; padding: 9px 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
    .btn-primary { background: #137333; color: #fff; border-color: #137333; }
    [hidden] { display: none !important; }
    .btn:disabled { opacity: .55; cursor: wait; }
    .document-frame { width: 100%; height: calc(100vh - 66px); border: 0; display: block; background: #2b2b2b; }
    .fallback { max-width: 720px; margin: 40px auto; background: #fff; border: 1px solid #d9e4dc; border-radius: 8px; padding: 20px; }
    .release-panel { margin: 12px; padding: 14px 16px; background: #fff; border: 1px solid #bfd3c5; border-radius: 12px; box-shadow: 0 3px 12px rgba(10,46,26,.10); font-size: 14px; line-height: 1.45; }
    .release-panel p { margin: 0 0 8px; }
    .release-panel form { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); align-items: start; gap: 10px 14px; }
    .release-panel label { display: grid; min-width: 0; gap: 5px; font-weight: 600; }
    .release-panel input:not([type="checkbox"]):not([type="hidden"]) { width: 100%; min-width: 0; padding: 9px 11px; border: 1px solid #afc9b8; border-radius: 8px; font: inherit; min-height: 42px; box-sizing: border-box; box-shadow: 0 2px 6px rgba(10,46,26,.13); }
    .release-panel input[readonly] { background: #f2f7f4; }
    .release-panel .handover { grid-column: 1 / -1; display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; border: 1px solid #cadcd0; border-radius: 8px; background: #f7fbf8; box-shadow: 0 2px 5px rgba(10,46,26,.08); font-weight: 400; cursor: pointer; }
    .release-panel .handover input { flex: 0 0 18px; width: 18px; height: 18px; margin: 1px 0 0; accent-color: #137333; }
    .release-panel form > p { grid-column: 1 / -1; margin: 0; color: #506a58; font-size: 13px; }
    .release-panel form > .btn { grid-column: 1 / -1; justify-self: end; min-height: 42px; }
    .btn { box-shadow: 0 2px 6px rgba(10,46,26,.17); transition: background-color 160ms ease, box-shadow 160ms ease; }
    .btn:not(:disabled):hover { box-shadow: 0 3px 9px rgba(10,46,26,.22); }
    .btn:focus-visible, .release-panel input:focus-visible { outline: 2px solid #15803d; outline-offset: 2px; }
    @media(max-width:600px) {
      .print-toolbar { flex-wrap: wrap; }
      .release-panel { margin: 8px; padding: 12px; }
      .release-panel form { grid-template-columns: minmax(0,1fr); }
      .release-panel input { font-size: 16px !important; }
      .release-panel form > .btn { width: 100%; }
      .toolbar-actions { width: 100%; }
      .toolbar-actions .btn { flex: 1 1 140px; text-align: center; }
    }
    @media(prefers-reduced-motion:reduce) { .btn { transition: none; } }
    @media print {
      .print-toolbar, .fallback, .release-panel { display: none; }
      .document-frame { height: 100vh; }
      body { background: #fff; }
    }
  </style>
</head>
<body data-document-print data-document-id="<?= $id ?>" data-issuance-status="<?= htmlspecialchars($document['issuance_status'], ENT_QUOTES) ?>" data-preview-token="<?= htmlspecialchars($_SESSION['document_request_csrf'], ENT_QUOTES) ?>" data-preview-ready="<?= $pdfReady ? '1' : '0' ?>">
  <div class="print-toolbar">
    <div>
      <h1><?= htmlspecialchars($document['document_number']) ?> - <?= htmlspecialchars($document['document_type']) ?></h1>
      <p><?= htmlspecialchars($document['resident_name']) ?> | Copies: <?= (int) ($document['copies'] ?? 1) ?></p>
    </div>
    <div class="toolbar-actions">
      <?php if (!$popup): ?>
      <a class="btn" href="<?= htmlspecialchars(staff_view_url('service-reports')) ?>">Service Reports</a>
      <a class="btn" href="<?= htmlspecialchars(staff_view_url('issue-documents')) ?>">Issue Documents</a>
      <?php endif; ?>
      <button class="btn btn-primary" type="button" data-print-document disabled>Print Document</button>
      <a class="btn" data-open-pdf href="<?= $pdfReady ? htmlspecialchars($pdfUrl) : '#' ?>" target="_blank" rel="noopener" <?= $pdfReady ? '' : 'hidden' ?>>Open PDF</a>
      <a class="btn" href="<?= htmlspecialchars($document['generated_file_path']) ?>" download>Download DOCX</a>
      <?php if ($verification): ?><a class="btn" href="<?= htmlspecialchars($verification['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">Verify document QR</a><?php endif; ?>
    </div>
  </div>
  <section class="release-panel">
    <?php if (isset($_SESSION['issuance_release_error'])): ?><p role="alert"><?= htmlspecialchars($_SESSION['issuance_release_error']) ?></p><?php unset($_SESSION['issuance_release_error']); endif; ?>
    <?php if ($document['issuance_status'] === 'Ready for Pickup'): ?><p>Prepared for printing. Confirm release after payment and handover.</p><?php endif; ?>
    <p><strong><?= htmlspecialchars($document['issuance_status']) ?></strong> · Payment: <?= (float) $document['fee_amount'] <= 0 ? 'No Payment Required' : (($payment['status'] ?? '') === 'Paid' ? 'Paid' : 'Unpaid') ?></p>
    <?php if ($document['issuance_status'] === 'Ready for Pickup' && !$preparationError): ?>
    <form action="staff-actions.php" method="post">
      <input type="hidden" name="action" value="release_issued_document"><input type="hidden" name="redirect" value="issue-documents">
      <input type="hidden" name="issued_id" value="<?= $id ?>"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
      <?php if ($popup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
      <?php if ((float) $document['fee_amount'] > 0 && ($payment['status'] ?? '') !== 'Paid'): ?>
      <p>Printing does not record payment. Confirm collection below before release.</p>
      <input type="hidden" name="payment_mode" value="paid">
      <label>Amount received (PHP)<input type="number" name="amount_paid" min="<?= htmlspecialchars((string) $document['fee_amount']) ?>" max="99999999.99" step="0.01" readonly value="<?= htmlspecialchars((string) $document['fee_amount']) ?>"></label>
      <label>Receipt number (optional)<input name="receipt_number" maxlength="80"></label>
      <label class="handover"><input type="checkbox" name="payment_received_confirmed" value="1" required> I received the payment of PHP <?= htmlspecialchars(number_format((float) $document['fee_amount'], 2)) ?>.</label>
      <?php endif; ?>
      <label class="handover"><input type="checkbox" name="handed_over" value="1" required> I handed the document to the applicant.</label>
      <button type="submit" class="btn btn-primary">Confirm Release</button>
    </form>
    <?php endif; ?>
  </section>
  <div class="fallback" data-preview-status <?= $pdfReady ? 'hidden' : '' ?> aria-live="polite">
    <h2 data-preview-title><?= $preparationError ? 'Could not prepare the document' : 'Preparing print preview' ?></h2>
    <p data-preview-message><?= $preparationError ? htmlspecialchars($preparationError) : 'Your document is saved. The PDF preview will appear here shortly.' ?></p>
    <?php if ($preparationError): ?><a class="btn btn-primary" href="staff-print-issued-document.php?id=<?= $id ?><?= $popup ? '&amp;popup=1' : '' ?>">Retry preparation</a><?php endif; ?>
    <button type="button" class="btn btn-primary" data-retry-preview hidden>Retry preview</button>
    <noscript><p>Enable JavaScript for the PDF preview, or download the DOCX and print it in Microsoft Word.</p></noscript>
  </div>
  <iframe class="document-frame" title="Printable document preview" name="documentFrame" data-document-frame <?= $pdfReady ? 'src="' . htmlspecialchars($pdfUrl) . '"' : 'hidden' ?>></iframe>
  <?php if (!$preparationError): ?><script src="js/document-print.js?v=<?= filemtime(__DIR__.'/js/document-print.js') ?>" defer></script><?php endif; ?>
</body>
</html>
