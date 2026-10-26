<?php include __DIR__ . '/../resident-management-nav.php'; ?>

<div class="qr-verification-grid" data-staff-qr-scanner data-endpoint="staff-qr-lookup.php">
  <section class="panel-card qr-scan-card">
    <div class="panel-head">
      <h3>Phone QR scanner</h3>
    </div>
    <div class="panel-body">
      <div class="qr-camera-shell">
        <video id="staffQrVideo" class="qr-camera-video" autoplay muted playsinline></video>
        <div id="staffQrHtml5Reader" class="qr-html5-reader" hidden></div>
        <div class="qr-camera-frame" aria-hidden="true">
          <span></span>
        </div>
        <p id="staffQrCameraStatus" class="qr-camera-status">Tap Start Scanner and allow camera access.</p>
      </div>

      <div class="toolbar qr-scan-actions">
        <button type="button" class="btn btn-primary" id="startStaffQrScanner">Start Scanner</button>
        <button type="button" class="btn btn-outline" id="stopStaffQrScanner" disabled>Stop</button>
      </div>
      <label class="file-upload-field qr-photo-scan">
        <input type="file" id="staffQrPhotoInput" accept="image/jpeg,image/png,image/webp" capture="environment">
        <span class="btn btn-outline">Scan from phone camera/photo</span>
      </label>

      <form id="staffQrManualForm" action="staff-actions.php" method="post" class="form-grid qr-manual-form">
        <input type="hidden" name="action" value="log_qr_scan">
        <input type="hidden" name="redirect" value="staff-qr-verification">
        <div class="field">
          <label for="staffQrManualInput">QR code or resident ID</label>
          <input type="search" id="staffQrManualInput" name="resident_code" placeholder="LUNA|RES-001|20260616090000 or RES-001" required>
          <p class="field-hint">Use this if the camera cannot scan clearly.</p>
        </div>
        <button type="submit" class="btn btn-primary">Verify resident</button>
      </form>
    </div>
  </section>
</div>

<div id="staffQrIdModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="staffQrIdTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide qr-id-modal-panel">
    <header class="modal-header">
      <h2 id="staffQrIdTitle">Scanned Resident ID</h2>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button>
    </header>
    <div class="modal-body">
      <section class="qr-id-preview resident-id-card resident-id-card--admin" id="staffQrIdPreview" aria-live="polite">
        <div class="qr-id-preview__top resident-id-card__header">
          <img src="branding-logo.php" alt="Barangay Luna seal" onerror="this.style.visibility='hidden';">
          <div>
            <span>Barangay Luna</span>
            <strong>Resident Verification ID</strong>
          </div>
          <span class="badge badge-pending" id="staffQrIdResult">Waiting</span>
        </div>
        <div class="qr-id-preview__body resident-id-card__body">
          <div class="qr-id-preview__photo resident-id-card__photo" id="staffQrIdPhoto">?</div>
          <div class="qr-id-preview__info resident-id-card__info">
            <span>Resident ID</span>
            <strong id="staffQrIdCode">-</strong>
            <h3 id="staffQrIdName">No resident selected</h3>
            <dl>
              <div><dt>Mobile</dt><dd id="staffQrIdMobile">-</dd></div>
              <div><dt>Purok/Sitio</dt><dd id="staffQrIdPurok">-</dd></div>
              <div><dt>Household</dt><dd id="staffQrIdHousehold">-</dd></div>
              <div><dt>Birthdate / Age</dt><dd id="staffQrIdBirthdate">-</dd></div>
              <div><dt>Gender</dt><dd id="staffQrIdGender">-</dd></div>
              <div><dt>Civil Status</dt><dd id="staffQrIdCivilStatus">-</dd></div>
              <div><dt>Account</dt><dd id="staffQrIdAccount">-</dd></div>
              <div><dt>Verification</dt><dd id="staffQrIdVerification">-</dd></div>
              <div class="resident-id-card__address-row"><dt>Address</dt><dd id="staffQrIdAddress">-</dd></div>
            </dl>
          </div>
          <div class="qr-id-preview__qr resident-id-card__qr" id="staffQrIdQr">
            <div class="resident-id-card__qr-empty">QR</div>
            <span id="staffQrIdQrCode">-</span>
          </div>
        </div>
        <p class="qr-id-preview__message" id="staffQrIdMessage">Scan a resident QR code to preview their ID.</p>
      </section>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn btn-primary" data-modal-close>Done</button>
    </footer>
  </div>
</div>


