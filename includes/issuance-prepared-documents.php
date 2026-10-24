<section class="panel-card issuance-prepared-panel" data-prepared-documents aria-labelledby="preparedDocumentsTitle">
  <div class="panel-head"><h3 id="preparedDocumentsTitle">Prepared documents awaiting release (<span data-prepared-count><?= count($preparedDocuments ?? []) ?></span>)</h3></div>
  <div class="panel-body">
    <div class="issuance-prepared-list" data-prepared-list>
      <?php foreach ($preparedDocuments ?? [] as $prepared): ?>
      <article class="issuance-prepared-record" data-prepared-document-id="<?= (int) $prepared['id'] ?>">
        <p><?= htmlspecialchars($prepared['resident_name'] . ' — ' . $prepared['document_type'] . ' · ' . $prepared['document_number']) ?></p>
        <div class="issuance-prepared-actions"><button type="button" class="btn btn-outline" data-modal-open="issuancePrintModal" data-issuance-preview-url="staff-print-issued-document.php?id=<?= (int) $prepared['id'] ?>&amp;popup=1">Print / Confirm Release</button>
        <button type="button" class="btn btn-danger" data-remove-prepared>Remove</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="field-hint" data-prepared-empty <?= !empty($preparedDocuments) ? 'hidden' : '' ?>>No prepared documents awaiting release.</p>
  </div>
</section>
<div id="removePreparedModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="removePreparedTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel">
    <header class="modal-header"><h2 id="removePreparedTitle">Remove prepared document</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <form action="staff-actions.php" method="post" class="modal-body form-grid" data-remove-prepared-form>
      <input type="hidden" name="action" value="remove_prepared_document">
      <input type="hidden" name="redirect" value="issue-documents">
      <input type="hidden" name="issued_id">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_request_csrf']) ?>">
      <p data-remove-prepared-name></p>
      <p>This removes the entry from awaiting release. It does not confirm release. Existing document and payment records are kept for audit. An online request can be prepared again.</p>
      <label class="checkbox-line"><input type="checkbox" name="confirm_remove" value="1" required> I want to remove this prepared entry.</label>
      <p role="alert" data-remove-prepared-error hidden></p>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Remove entry</button></footer>
    </form>
  </div>
</div>
<script src="js/prepared-documents.js?v=<?= filemtime(__DIR__ . '/../js/prepared-documents.js') ?>" defer></script>
