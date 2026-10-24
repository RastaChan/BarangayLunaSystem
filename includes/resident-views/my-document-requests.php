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
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true"><path d="M4 5h16v12H9l-5 4V5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
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
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <div class="modal-body">
      <p class="field-hint" data-rejection-request></p>
      <p class="request-rejection-remarks" data-rejection-remarks></p>
    </div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>
