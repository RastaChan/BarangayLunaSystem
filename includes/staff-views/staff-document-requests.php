<?php require_once __DIR__ . '/../record-attachments.php'; ?>
<?php $requestListView = ($loggedInUser['role'] ?? '') === 'admin' ? 'documents' : 'staff-document-requests'; ?>
<?php $_SESSION['document_request_csrf'] = $_SESSION['document_request_csrf'] ?? bin2hex(random_bytes(32)); ?>
<?php if (empty($requestsEmbedded)): ?>
<div class="page-intro">
  <h2>Document Requests</h2>
  <p>Review each request and prepare the document. Confirm release after collecting payment and handing it to the resident.</p>
</div>
<?php endif; ?>

<section class="<?= empty($requestsEmbedded) ? 'panel-card' : 'request-list-content' ?>" data-document-request-live-owner="<?= htmlspecialchars(($loggedInUser['role'] ?? '') . ':' . (int) ($loggedInUser['id'] ?? 0), ENT_QUOTES) ?>">
  <div class="panel-head">
    <h3>Request list</h3>
    <a class="btn btn-sm btn-outline" href="staff-export.php?type=document-requests">Export report</a>
  </div>
  <div class="table-wrap request-card-wrap">
    <table class="data-table staff-request-table request-card-list">
      <thead>
        <tr><th>Request ID</th><th>Resident name</th><th>Document type</th><th>Copies</th><th>Total fee</th><th>Request date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody data-document-request-live-list>
        <?php foreach ($staffRequests as $request):
          $requestStatusClass = match ($request['status']) {
            'Rejected' => 'rejected',
            'Approved', 'Ready for Pickup', 'Released' => 'active',
            default => 'pending',
          };
          $nameParts = preg_split('/\s+/u', trim($request['resident']));
          $requestInitials = mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
        ?>
        <tr data-document-request-code="<?= htmlspecialchars($request['id'], ENT_QUOTES) ?>">
          <td class="staff-request-id" data-label="Request ID"><?= htmlspecialchars($request['id']) ?></td>
          <td class="staff-request-resident" data-label="Resident"><span class="request-resident-avatar" aria-hidden="true"><?= htmlspecialchars($requestInitials) ?></span><strong><?= htmlspecialchars($request['resident']) ?></strong></td>
          <td class="staff-request-document" data-label="Document"><?= htmlspecialchars($request['type']) ?></td>
          <td class="staff-request-copies" data-label="Copies"><?= (int) ($request['copies'] ?? 1) ?></td>
          <td class="staff-request-fee" data-label="Total fee">PHP <?= number_format((float) ($request['fee_amount'] ?? 0), 2) ?><br><small class="field-hint">PHP <?= number_format((float) ($request['unit_fee'] ?? $request['fee_amount'] ?? 0), 2) ?> per copy</small></td>
          <td class="staff-request-date" data-label="Requested on"><?= htmlspecialchars($request['date']) ?></td>
          <td class="staff-request-status" data-label="Status"><span class="badge badge-<?= $requestStatusClass ?>"><?= htmlspecialchars($request['status']) ?></span></td>
          <td class="request-action-cell"><div class="record-action-list">
            <?= barangay_attachment_links('document', $request['id']) ?>
            <button type="button" class="btn-icon" data-view-record data-title="Document request" data-json='<?= htmlspecialchars(json_encode($request), ENT_QUOTES) ?>'>View details</button>
            <?php if (in_array($request['status'], ['Pending', 'Processing', 'Approved'], true)): ?>
            <form action="staff-actions.php" method="post" class="inline-form">
              <input type="hidden" name="action" value="document_request_event">
              <input type="hidden" name="event" value="prepare">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
              <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
              <button class="btn-icon" type="submit">Prepare document</button>
            </form>
            <details><summary>Reject request</summary>
              <form action="staff-actions.php" method="post" class="table-edit-form">
                <input type="hidden" name="action" value="document_request_event">
                <input type="hidden" name="event" value="reject">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                <label>Reason for rejection<textarea name="reason" rows="3" aria-label="Reason for rejection" maxlength="1000" required></textarea></label>
                <button class="btn-icon" type="submit">Confirm rejection</button>
              </form>
            </details>
            <?php elseif ($request['status'] === 'Ready for Pickup'): ?>
            <?php $requestIssuedRows = staff_fetch_all("SELECT id FROM issued_documents WHERE issuance_status = 'Ready for Pickup' AND request_code = " . barangay_db()->quote($request['id']) . ' ORDER BY id DESC LIMIT 1'); ?>
            <?php if ($requestIssuedRows): ?>
            <a class="btn-icon" href="staff-print-issued-document.php?id=<?= (int) $requestIssuedRows[0]['id'] ?>">Print / release document</a>
            <?php else: ?>
            <details><summary>Confirm pickup</summary>
              <form action="staff-actions.php" method="post" class="table-edit-form">
                <input type="hidden" name="action" value="document_request_event">
                <input type="hidden" name="event" value="pickup">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                <label class="table-edit-check"><input type="checkbox" name="handed_over" value="1" required> Document handed to the resident</label>
                <button class="btn-icon" type="submit">Confirm pickup</button>
              </form>
            </details>
            <?php endif; ?>
            <?php elseif ($request['status'] === 'Released'): ?>
            <a class="btn-icon" href="<?= htmlspecialchars(staff_view_url('service-reports')) ?>">Reprint from reports</a>
            <?php endif; ?>
          </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$staffRequests): ?>
        <tr><td colspan="8">No document requests yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<script src="js/document-request-live.js?v=<?= filemtime(__DIR__ . '/../../js/document-request-live.js') ?>" defer></script>

<?php if ($requestListView === 'staff-document-requests'): ?>
<div id="viewRecordModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="requestRecordTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="requestRecordTitle" data-record-title>Document request</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body"><div class="auto-fill-grid resident-details-grid" data-record-view></div></div>
  </div>
</div>
<?php endif; ?>
