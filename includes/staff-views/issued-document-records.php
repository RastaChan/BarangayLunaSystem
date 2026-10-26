<div id="issuedDocumentRecordsModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="issuedDocumentRecordsTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel issued-records-panel">
    <header class="modal-header"><h2 id="issuedDocumentRecordsTitle">Issued document records</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body">
  <div class="table-wrap">
    <table class="data-table issued-records-table">
      <thead><tr><th>Document No.</th><th>Resident</th><th>Document type</th><th>Copies</th><th>Total fee</th><th>Purpose</th><th>Date issued</th><th>Status and actions</th></tr></thead>
      <tbody>
        <?php foreach ($issuedDocuments as $document): ?>
        <tr>
          <td class="issued-record-number" data-label="Document number"><?= htmlspecialchars($document['document_number']) ?></td>
          <td data-label="Resident"><?= htmlspecialchars($document['resident_name']) ?></td>
          <td data-label="Document"><?= htmlspecialchars($document['document_type']) ?></td>
          <td class="issued-record-copies" data-label="Copies"><?= (int) ($document['copies'] ?? 1) ?></td>
          <td class="issued-record-fee" data-label="Total fee">PHP <?= number_format((float) ($document['fee_amount'] ?? 0), 2) ?><br><small class="field-hint">PHP <?= number_format((float) ($document['unit_fee'] ?? $document['fee_amount'] ?? 0), 2) ?> per copy</small></td>
          <td data-label="Purpose"><?= htmlspecialchars($document['purpose'] ?? '') ?: '—' ?></td>
          <td data-label="Date issued"><?= htmlspecialchars($document['date']) ?></td>
          <td class="issued-record-actions-cell" data-label="Status and actions">
            <div class="issued-record-status">
            <span class="badge <?= ($document['issuance_status'] ?? 'Released') === 'Released' ? 'badge-active' : 'badge-pending' ?>"><?= htmlspecialchars($document['issuance_status'] ?? 'Released') ?></span>
            <?php if (!empty($document['generated_file_path'])): ?>
            <div class="actions">
              <a href="staff-print-issued-document.php?id=<?= (int) $document['id'] ?>" class="btn-icon file-link"><?= ($document['issuance_status'] ?? 'Released') === 'Released' ? 'Reprint' : 'Print / Confirm Release' ?></a>
              <a href="<?= htmlspecialchars($document['generated_file_path']) ?>" class="btn-icon file-link" download title="<?= htmlspecialchars($document['generated_file_name'] ?: 'Download DOCX') ?>">Download DOCX</a>
            </div>
            <?php else: ?>
            <span class="field-hint">No DOCX</span>
            <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$issuedDocuments): ?>
        <tr><td colspan="8">No issued documents yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
    </div>
  </div>
</div>
