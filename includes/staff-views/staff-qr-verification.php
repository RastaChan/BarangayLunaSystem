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
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </header>
    <div class="modal-body">
      <section class="qr-id-preview" id="staffQrIdPreview" aria-live="polite">
        <div class="qr-id-preview__top">
          <img src="branding-logo.php" alt="Barangay Luna seal" onerror="this.style.visibility='hidden';">
          <div>
            <span>Barangay Luna</span>
            <strong>Resident Verification ID</strong>
          </div>
          <span class="badge badge-pending" id="staffQrIdResult">Waiting</span>
        </div>
        <div class="qr-id-preview__body">
          <div class="qr-id-preview__photo" id="staffQrIdPhoto">?</div>
          <div class="qr-id-preview__info">
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
            </dl>
          </div>
          <div class="qr-id-preview__qr" id="staffQrIdQr">
            <div class="resident-id-card__qr-empty">QR</div>
            <span id="staffQrIdQrCode">-</span>
          </div>
        </div>
        <div class="qr-id-preview__address">
          <span>Address</span>
          <strong id="staffQrIdAddress">-</strong>
        </div>
        <p class="qr-id-preview__message" id="staffQrIdMessage">Scan a resident QR code to preview their ID.</p>
      </section>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn btn-primary" data-modal-close>Done</button>
    </footer>
  </div>
</div>

<section class="panel-card" style="margin-top:1.25rem">
  <div class="panel-head"><h3>Scan logs</h3></div>
  <div class="table-wrap">
    <table class="data-table qr-mobile-table">
      <thead><tr><th>Date and time</th><th>Resident</th><th>Result</th><th>Staff</th></tr></thead>
      <tbody id="staffQrScanLogBody">
        <?php foreach ($qrScanLogs as $scan): ?>
        <tr>
          <td data-label="Date and time"><?= htmlspecialchars($scan['date']) ?></td>
          <td data-label="Resident"><?= htmlspecialchars($scan['resident']) ?></td>
          <td data-label="Result"><span class="badge <?= $scan['result'] === 'Verified' ? 'badge-active' : 'badge-open' ?>"><?= htmlspecialchars($scan['result']) ?></span></td>
          <td data-label="Scanner"><?= htmlspecialchars($scan['staff']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$qrScanLogs): ?>
        <tr><td colspan="4">No QR scan logs yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
