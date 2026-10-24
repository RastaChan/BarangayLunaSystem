<div class="issuance-review-controls" data-issuance-review>
  <h4>Requirements and payment</h4>
  <?php if ($selectedRequest): ?>
  <div class="issuance-attachments"><?= barangay_attachment_links('document', $selectedRequest['id']) ?></div>
  <?php endif; ?>
  <label class="check check-left"><input type="checkbox" name="requirements_checked" value="1" form="issueDocumentForm" required><span>I checked the applicant's details and required documents.</span></label>
  <div class="field"><label for="issuancePaymentMode">Payment</label>
    <select id="issuancePaymentMode" name="payment_mode" form="issueDocumentForm" data-issuance-payment-mode>
      <option value="unpaid">Unpaid — collect before release</option>
      <option value="paid">Record payment received now</option>
    </select>
    <p class="field-hint" data-issuance-payment-hint>Total fee: PHP <?= number_format($selectedDocumentFee, 2) ?>. Free documents need no payment.</p>
  </div>
  <div class="form-grid-2" data-issuance-payment-fields hidden>
    <div class="field"><label for="issuanceAmount">Amount received (PHP)</label><input id="issuanceAmount" type="text" name="amount_paid" form="issueDocumentForm" readonly value="<?= htmlspecialchars(number_format($selectedDocumentFee, 2, '.', '')) ?>"></div>
    <div class="field"><label for="issuanceReceipt">Receipt number (optional)</label><input id="issuanceReceipt" name="receipt_number" form="issueDocumentForm" maxlength="80"></div>
  </div>
  <?php if ($issueMode === 'walkin'): ?>
  <label class="check check-left"><input type="checkbox" name="notify_pickup" value="1" form="issueDocumentForm"><span>Send a pickup SMS if the applicant will collect later and provided a mobile number.</span></label>
  <?php else: ?>
  <p class="field-hint">Successful generation changes the request to Ready for Pickup and sends the pickup SMS when automatic alerts are enabled.</p>
  <?php endif; ?>
  <button type="submit" form="issueDocumentForm" class="btn btn-primary" data-issuance-generate>Prepare printable document</button>
  <p class="field-hint">The printable preview opens in a popup. Only Confirm Release adds the document to issued records.</p>
</div>
