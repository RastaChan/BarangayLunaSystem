<?php
$documentFees = barangay_document_fee_map();
$documentFeeRows = array_map(static function (string $type) use ($documentFees): array {
  return ['type' => $type, 'fee_amount' => $documentFees[barangay_document_fee_key($type)] ?? null];
}, staff_document_types());
$_SESSION['document_fee_csrf'] = $_SESSION['document_fee_csrf'] ?? bin2hex(random_bytes(32));
?>
<div class="page-intro"><h2>Document Fees</h2></div>
<section class="document-settings-page" id="document-fees">
  <?php if (!$documentFeeRows): ?>
  <p class="empty-state">No document types available yet.</p>
  <?php else: ?>
  <form action="staff-actions.php" method="post" class="fee-bulk-form" data-document-fee-form>
    <input type="hidden" name="action" value="save_document_fees">
    <input type="hidden" name="redirect" value="document-fees">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['document_fee_csrf'], ENT_QUOTES) ?>">
    <div class="field">
      <label id="feeDocumentTypesLabel">Document types</label>
      <details class="fee-type-picker" data-fee-picker>
        <summary aria-labelledby="feeDocumentTypesLabel feeSelectionLabel"><span id="feeSelectionLabel" data-fee-selection-label>Select document types</span><span aria-hidden="true">&#9662;</span></summary>
        <div class="fee-type-menu">
          <label class="fee-type-option fee-type-all"><input type="checkbox" data-fee-select-all><span>Select all document types</span></label>
          <div class="fee-type-options">
            <?php foreach ($documentFeeRows as $feeRow): ?>
            <label class="fee-type-option">
              <input type="checkbox" name="document_types[]" value="<?= htmlspecialchars($feeRow['type'], ENT_QUOTES) ?>">
              <span><?= htmlspecialchars($feeRow['type']) ?><small><?= htmlspecialchars(barangay_format_document_fee($feeRow['fee_amount'])) ?></small></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </details>
      <span class="field-hint" data-fee-selection-error role="alert" hidden>Select at least one document type.</span>
    </div>
    <div class="field">
      <label for="bulkDocumentFee">Set fee amount (PHP)</label>
      <input id="bulkDocumentFee" type="number" name="fee_amount" min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0.00" required>
    </div>
    <button type="submit" class="btn btn-primary">Save fees</button>
  </form>
  <?php endif; ?>
</section>
