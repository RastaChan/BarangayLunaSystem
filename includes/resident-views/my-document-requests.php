<?php require_once __DIR__ . '/../record-attachments.php'; ?>
<div class="page-intro">
  <h2>My Document Requests</h2>
  <p>Track your submitted requests, staff remarks, pickup instructions, and SMS updates.</p>
</div>

<?php
$requestStatusLabels = [
  'Pending' => ['pending', 'Waiting for staff to start your request.'],
  'Processing' => ['pending', 'Staff is preparing your document.'],
  'Approved' => ['active', 'Approved and waiting for preparation.'],
  'Ready for Pickup' => ['active', 'Your document is ready. Visit the barangay office to collect it.'],
  'Released' => ['active', 'Staff has confirmed that your document was handed over.'],
  'Rejected' => ['rejected', 'Your request was rejected. Select View remarks to see the reason.'],
];
?>
<section class="panel-card" data-overview-owner="resident-requests-<?= (int) ($loggedInUser['id'] ?? 0) ?>">
  <div class="panel-head"><h3>Request list</h3></div>
  <div class="resident-request-filters">
    <input type="search" placeholder="Search requests…" aria-label="Search document requests" data-request-list-search>
    <select aria-label="Filter requests by status" data-request-list-status>
      <option value="">All statuses</option>
      <?php foreach (array_keys($requestStatusLabels) as $label): ?><option><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
    </select>
  </div>
  <p class="resident-request-no-matches" role="status" data-request-list-empty hidden>No requests match your search or status filter.</p>
  <div class="table-wrap">
    <table class="data-table resident-request-table">
      <thead><tr><th>Request ID</th><th>Document type</th><th>Copies</th><th>Total fee</th><th>Purpose</th><th>Date requested</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody data-live-overview="resident-request-tracking" aria-live="polite">
        <?php foreach ($myDocumentRequests as $request): ?>
        <tr data-request-list-row data-request-status="<?= htmlspecialchars($request['status'], ENT_QUOTES) ?>">
          <td class="request-cell-id" data-label="Request ID"><div><?= htmlspecialchars($request['id']) ?></div></td>
          <td class="request-cell-document" data-label="Document type"><div><?= htmlspecialchars($request['type']) ?><?= barangay_attachment_links('document', $request['id']) ?></div></td>
          <td class="request-cell-copies" data-label="Copies"><div><?= (int) ($request['copies'] ?? 1) ?></div></td>
          <td class="request-cell-fee" data-label="Total fee"><div>PHP <?= number_format((float) ($request['fee_amount'] ?? 0), 2) ?><br><small class="field-hint">PHP <?= number_format((float) ($request['unit_fee'] ?? $request['fee_amount'] ?? 0), 2) ?> per copy</small></div></td>
          <td class="request-cell-purpose" data-label="Purpose"><div><?= htmlspecialchars($request['purpose'] ?? '') ?></div></td>
          <td class="request-cell-date" data-label="Date requested"><div><?= htmlspecialchars(date('M/j/Y', strtotime($request['date']))) ?></div></td>
          <?php [$statusClass, $statusDescription] = $requestStatusLabels[$request['status']] ?? ['pending', '']; ?>
          <td class="request-cell-status" data-label="Status"><div class="resident-request-status"><span class="badge badge-<?= $statusClass ?>" title="<?= htmlspecialchars($statusDescription, ENT_QUOTES) ?>"><?= htmlspecialchars($request['status']) ?></span></div></td>
          <td class="request-cell-remarks <?= $request['status'] === 'Rejected' ? 'has-rejection-remarks' : '' ?>" data-label="Remarks"><div class="resident-request-remarks">
            <?php if ($request['status'] === 'Rejected'): ?>
            <button type="button" class="btn btn-sm btn-outline request-remarks-button" data-request-remarks data-request-id="<?= htmlspecialchars($request['id'], ENT_QUOTES) ?>" data-request-type="<?= htmlspecialchars($request['type'], ENT_QUOTES) ?>" data-remarks="<?= htmlspecialchars($request['remarks'] ?? '', ENT_QUOTES) ?>">
              <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="eye" class="system-icon"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Zm0,112a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/></svg>
              <span>View remarks</span>
            </button>
            <?php else: ?>
            <?= htmlspecialchars($request['remarks'] ?: '—') ?>
            <?php endif; ?>
          </div>
          <?php if ($request['status'] === 'Pending' && (int) ($request['resident_user_id'] ?? 0) === (int) $loggedInUser['id']): ?>
          <?= resident_delete_submission_form('document', $request['id'], $request['id'] . ' — ' . $request['type'] . ' · ' . (int) ($request['copies'] ?? 1) . ' copies') ?>
          <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$myDocumentRequests): ?>
        <tr><td colspan="8">No document requests yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<script src="js/resident-request-list.js?v=<?= filemtime(__DIR__ . '/../../js/resident-request-list.js') ?>" defer></script>

<div id="requestRejectionRemarksModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="requestRejectionRemarksTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header">
      <h2 id="requestRejectionRemarksTitle">Rejection remarks</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button>
    </header>
    <div class="modal-body">
      <p class="field-hint" data-rejection-request></p>
      <p class="request-rejection-remarks" data-rejection-remarks></p>
    </div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>
