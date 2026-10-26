<div class="page-intro">
  <h2>Request Document</h2>
  <p>Submit barangay document requests online and wait for staff updates.</p>
</div>

<section class="panel-card">
  <div class="panel-head"><h3>New document request</h3><span class="badge badge-pending">Status after submission: Pending</span></div>
  <div class="panel-body">
    <?php
      $documentTypeRows = $documentTypeRows ?? array_map(
        static fn(string $type): array => ['name' => $type, 'is_default' => true, 'added_at' => ''],
        $documentTypes ?? []
      );
      $selectedDocumentType = $documentTypeRows[0]['name'] ?? ($documentTypes[0] ?? '');
      $selectedDocumentFee = $documentTypeRows[0]['fee_amount'] ?? null;
      $residentDocumentTypeSeenKey = 'residentDocumentTypesSeen:' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($residentProfile['id'] ?? 'resident'));
    ?>
    <?php $_SESSION['resident_preview_csrf'] = $_SESSION['resident_preview_csrf'] ?? bin2hex(random_bytes(32)); ?>
    <form action="resident-actions.php" method="post" enctype="multipart/form-data" class="form-grid" data-resident-request-form data-resident-review="document" data-review-resident-name="<?= htmlspecialchars($residentProfile['name'], ENT_QUOTES) ?>" data-review-resident-address="<?= htmlspecialchars($residentProfile['address'], ENT_QUOTES) ?>">
      <input type="hidden" name="service_csrf" value="<?= htmlspecialchars($_SESSION['resident_service_csrf']) ?>">
      <input type="hidden" name="review_confirmed" value="0">
      <input type="hidden" name="reviewed_copies" value="">
      <input type="hidden" name="preview_csrf" value="<?= htmlspecialchars($_SESSION['resident_preview_csrf'], ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="request_document">
      <input type="hidden" name="redirect" value="request-document">
      <div class="form-grid-2">
        <div class="field">
          <label>Document Type</label>
          <div class="resident-document-type-picker" data-resident-document-type-picker data-seen-key="<?= htmlspecialchars($residentDocumentTypeSeenKey) ?>">
            <input type="hidden" name="document_type" value="<?= htmlspecialchars($selectedDocumentType) ?>" required data-resident-document-type-input>
            <button type="button" class="document-type-trigger" data-resident-document-type-trigger aria-expanded="false">
              <span data-resident-document-type-label><?= htmlspecialchars($selectedDocumentType) ?></span>
              <span aria-hidden="true">v</span>
            </button>
            <div class="document-type-menu resident-document-type-menu" data-resident-document-type-options hidden>
              <?php foreach ($documentTypeRows as $row): ?>
              <?php
                $type = (string) ($row['name'] ?? '');
                if ($type === '') continue;
                $typeKey = strtolower(preg_replace('/\s+/', ' ', trim($type)));
                $isNew = empty($row['is_default']);
              ?>
              <button
                type="button"
                class="document-type-choice resident-document-type-choice"
                data-resident-document-type-choice
                data-value="<?= htmlspecialchars($type) ?>"
                data-type-key="<?= htmlspecialchars($typeKey) ?>"
                data-fee-amount="<?= isset($row['fee_amount']) && $row['fee_amount'] !== null ? htmlspecialchars((string) $row['fee_amount']) : '' ?>"
                data-new="<?= $isNew ? '1' : '0' ?>">
                <?php if ($isNew): ?><span class="document-type-new-badge" data-new-badge>New</span><?php endif; ?>
                <span><?= htmlspecialchars($type) ?></span>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="field">
          <label>Purpose</label>
          <input type="text" name="purpose" placeholder="Employment, school, assistance, business permit..." required>
        </div>
      </div>
      <div class="document-fee-notice" data-resident-document-fee>
        <span>Fee per copy</span>
        <strong data-resident-document-fee-value><?= $selectedDocumentFee === null ? 'Not set yet' : 'PHP ' . number_format((float) $selectedDocumentFee, 2) ?></strong>
        <small data-resident-document-fee-help><?= $selectedDocumentFee === null ? 'Please contact the barangay office for the current fee.' : 'This amount will be included in your request.' ?></small>
      </div>
      <div class="form-grid-2 residency-years-fields" data-resident-residency-years-fields hidden>
        <div class="field">
          <label>Years as resident</label>
          <input type="number" name="residency_years" min="0" step="1" placeholder="8">
        </div>
      </div>
      <div class="form-grid-2">
        <div class="field">
          <label for="requestCopies">Number of copies</label>
          <input id="requestCopies" name="copies" type="number" min="1" max="100" step="1" value="1" required>
          <p class="field-hint">Usually 1 copy is enough. Check the quantity before submitting.</p>
        </div>
        <div class="field">
          <label for="requestTotal">Total fee</label>
          <input id="requestTotal" type="text" readonly>
        </div>
      </div>
      <div class="form-grid-2 indigency-fields" data-resident-indigency-fields hidden>
        <div class="field">
          <label>Requesting resident's gender</label>
          <input type="text" value="<?= htmlspecialchars($residentProfile['gender'] ?: 'Not recorded') ?>" readonly>
          <p class="field-hint">From your resident profile. “His daughter” refers to a male parent; “her daughter” refers to a female parent. For an incorrect profile gender, ask barangay staff to correct your record.</p>
        </div>
        <div class="field">
          <label>Beneficiary name</label>
          <input type="text" name="beneficiary_name" placeholder="Enter beneficiary name">
        </div>
        <div class="field">
          <label>Relationship</label>
          <input type="text" name="beneficiary_relationship" placeholder="son, daughter, spouse..."><p class="field-hint">Relationship to you. Daughter describes the child; his/her describes you, the requesting resident.</p>
        </div>

      </div>
      <div class="form-grid-2 cohabitation-fields" data-resident-cohabitation-fields hidden>
        <div class="field">
          <label>Partner name</label>
          <input type="text" name="partner_name" placeholder="Enter partner name">
        </div>
        <div class="field">
          <label for="residentCohabitationStart">Living together since</label>
          <div class="calendar-date-field">
            <input id="residentCohabitationStart" type="date" name="cohabitation_start_date">
            <svg  viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="phone" class="system-icon"><path d="M223.94,174.08A48.33,48.33,0,0,1,176,216,136,136,0,0,1,40,80,48.33,48.33,0,0,1,81.92,32.06a8,8,0,0,1,8.3,4.8l21.13,47.2a8,8,0,0,1-.66,7.53L89.32,117a7.93,7.93,0,0,0-.54,7.81c8.27,16.93,25.77,34.22,42.75,42.41a7.92,7.92,0,0,0,7.83-.59l25-21.3a8,8,0,0,1,7.59-.69l47.16,21.13A8,8,0,0,1,223.94,174.08Z" opacity="0.2"/><path d="M222.37,158.46l-47.11-21.11-.13-.06a16,16,0,0,0-15.17,1.4,8.12,8.12,0,0,0-.75.56L134.87,160c-15.42-7.49-31.34-23.29-38.83-38.51l20.78-24.71c.2-.25.39-.5.57-.77a16,16,0,0,0,1.32-15.06l0-.12L97.54,33.64a16,16,0,0,0-16.62-9.52A56.26,56.26,0,0,0,32,80c0,79.4,64.6,144,144,144a56.26,56.26,0,0,0,55.88-48.92A16,16,0,0,0,222.37,158.46ZM176,208A128.14,128.14,0,0,1,48,80,40.2,40.2,0,0,1,82.87,40a.61.61,0,0,0,0,.12l21,47L83.2,111.86a6.13,6.13,0,0,0-.57.77,16,16,0,0,0-1,15.7c9.06,18.53,27.73,37.06,46.46,46.11a16,16,0,0,0,15.75-1.14,8.44,8.44,0,0,0,.74-.56L168.89,152l47,21.05h0s.08,0,.11,0A40.21,40.21,0,0,1,176,208Z"/></svg>
          </div>
        </div>
      </div>
      <div class="field"><label>Contact Number</label><input type="tel" name="contact_mobile" value="<?= htmlspecialchars($residentProfile['contact']) ?>"></div>
      <div class="field"><label>Supporting Document Upload (JPG, PNG, PDF; maximum 5 MB)</label><input type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png"></div>
      <?php $customInitialValues = []; include __DIR__ . '/../document-custom-inputs.php'; ?>
      <div class="field"><label>Remarks</label><textarea name="remarks" rows="4" placeholder="Optional notes/message for barangay staff"></textarea></div>
      <div class="info-banner"><div><strong>Request confirmation</strong>Your request will be added to My Document Requests with Pending status. SMS updates may be sent when the status changes.</div></div>
      <div class="resident-request-actions">
        <button type="submit" class="btn btn-primary">Submit request</button>
        <button type="button" class="btn btn-outline" data-resident-request-preview data-modal-open="residentRequestPreviewModal">Generated document preview</button>
      </div>
    </form>
  </div>
</section>

<div id="residentRequestPreviewModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="residentRequestPreviewTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="residentRequestPreviewTitle">Generated document preview</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body"><p class="field-hint">Preview only. The barangay will review your request before issuing the final document.</p><p role="status" data-resident-preview-status></p><iframe title="Resident document preview" data-resident-preview-frame hidden></iframe></div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>
<script src="js/resident-request-preview.js?v=<?= filemtime(__DIR__ . '/../../js/resident-request-preview.js') ?>" defer></script>

<script>
(function () {
  'use strict';

  const picker = document.querySelector('[data-resident-document-type-picker]');
  if (!picker) return;

  const input = picker.querySelector('[data-resident-document-type-input]');
  const trigger = picker.querySelector('[data-resident-document-type-trigger]');
  const label = picker.querySelector('[data-resident-document-type-label]');
  const options = picker.querySelector('[data-resident-document-type-options]');
  const form = picker.closest('form');
  const residencyYearsFields = form ? form.querySelector('[data-resident-residency-years-fields]') : null;
  const indigencyFields = form ? form.querySelector('[data-resident-indigency-fields]') : null;
  const cohabitationFields = form ? form.querySelector('[data-resident-cohabitation-fields]') : null;
  const feeValue = form ? form.querySelector('[data-resident-document-fee-value]') : null;
  const feeHelp = form ? form.querySelector('[data-resident-document-fee-help]') : null;
  const copiesField = form.querySelector('[name="copies"]');
  const totalField = form.querySelector('#requestTotal');
  let unitFee = <?= json_encode($selectedDocumentFee) ?>;
  function updateTotal() {
    const copies = Number(copiesField.value);
    totalField.value = unitFee === null ? 'Not set yet' :
      (Number.isInteger(copies) && copies >= 1 && copies <= 100
        ? `PHP ${(Math.round(Number(unitFee) * 100) * copies / 100).toFixed(2)}` : 'Enter 1 to 100 copies');
  }
  copiesField.addEventListener('input', updateTotal);
  updateTotal();
  const storageKey = picker.getAttribute('data-seen-key') || 'residentDocumentTypesSeen';
  if (!input || !trigger || !label || !options) return;

  function readSeen() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (error) {
      return [];
    }
  }

  function writeSeen(values) {
    localStorage.setItem(storageKey, JSON.stringify(Array.from(new Set(values))));
  }

  function syncBadges() {
    const seen = readSeen();
    picker.querySelectorAll('[data-resident-document-type-choice]').forEach((button) => {
      const typeKey = button.getAttribute('data-type-key') || '';
      const badge = button.querySelector('[data-new-badge]');
      if (!badge) return;
      badge.hidden = seen.includes(typeKey);
    });
  }

  function closeOptions() {
    options.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  }

  function normalizedDocumentType(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function isIndigency(value) {
    return normalizedDocumentType(value).includes('indigency');
  }

  function isBarangayCertification(value) {
    const normalized = normalizedDocumentType(value);
    return normalized === 'barangay certification' || normalized === 'certificate of barangay certification';
  }

  function isCohabitation(value) {
    return normalizedDocumentType(value).includes('cohabitation');
  }

  function toggleSection(section, show) {
    if (!section) return;
    section.hidden = !show;
    section.style.display = show ? '' : 'none';
  }

  function syncRequestFields() {
    const value = input.value;
    toggleSection(indigencyFields, isIndigency(value));
    toggleSection(residencyYearsFields, isIndigency(value) || isBarangayCertification(value));
    toggleSection(cohabitationFields, isCohabitation(value));
  }

  function choose(button) {
    const value = button.getAttribute('data-value') || '';
    const typeKey = button.getAttribute('data-type-key') || '';
    input.value = value;
    label.textContent = value;
    const fee = button.getAttribute('data-fee-amount') || '';
    unitFee = fee === '' ? null : Number(fee);
    updateTotal();
    if (feeValue) feeValue.textContent = fee === '' ? 'Not set yet' : `PHP ${Number(fee).toFixed(2)}`;
    if (feeHelp) feeHelp.textContent = fee === '' ? 'Please contact the barangay office for the current fee.' : 'This amount will be included in your request.';
    syncRequestFields();
    if (button.getAttribute('data-new') === '1' && typeKey) {
      const seen = readSeen();
      seen.push(typeKey);
      writeSeen(seen);
      syncBadges();
    }
    closeOptions();
  }

  trigger.addEventListener('click', () => {
    options.hidden = !options.hidden;
    trigger.setAttribute('aria-expanded', options.hidden ? 'false' : 'true');
  });

  picker.querySelectorAll('[data-resident-document-type-choice]').forEach((button) => {
    button.addEventListener('click', () => choose(button));
  });

  document.addEventListener('click', (event) => {
    if (!picker.contains(event.target)) {
      closeOptions();
    }
  });

  syncBadges();
  syncRequestFields();
})();
</script>

