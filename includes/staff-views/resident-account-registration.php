<?php
require_once __DIR__ . '/../resident-locations.php';
$locationLists = resident_locations();
$_SESSION['locations_csrf'] ??= bin2hex(random_bytes(32));
$locationAssignments = barangay_db()->query("SELECT user_code, district_id, purok_id FROM users WHERE role = 'resident'")->fetchAll(PDO::FETCH_UNIQUE);
?>
<script src="js/resident-locations.js?v=<?= filemtime(__DIR__ . '/../../js/resident-locations.js') ?>" defer></script>
<div class="page-intro">
  <h2>Resident Account Registration</h2>
  <p>Create resident accounts after checking their details and registration requirements. Saved accounts are active and ready for document issuance and resident services.</p>
</div>

<div class="info-banner">
  <div>
    <strong>Access control reminder</strong>
    Only authorized Barangay Staff and Super Admin can create resident accounts. Residents cannot self-create accounts online.
  </div>
</div>

<section class="panel-card resident-account-create" style="margin-bottom:1.25rem">
  <div class="panel-head">
    <h3>Create resident account</h3>
    <span class="badge badge-pending">Staff encoded</span>
  </div>
  <div class="panel-body">
    <form action="staff-actions.php" method="post" enctype="multipart/form-data" class="form-grid" data-duplicate-resident-form>
      <input type="hidden" name="action" value="create_resident">
      <input type="hidden" name="duplicate_token" value="">
      <div data-duplicate-warning hidden class="info-banner" style="grid-column:1/-1">
        <div><strong>Possible existing resident account</strong><ul data-duplicate-matches></ul>
        <p>Use the existing account if it belongs to this person. Similar names can belong to different people.</p>
        <label class="check-row"><input type="checkbox" name="duplicate_reviewed" value="1"><span>I checked the records and confirmed this is a different person.</span></label></div>
      </div>
      <p data-duplicate-status role="status" aria-live="polite" style="grid-column:1/-1"></p>
      <input type="hidden" name="locations_csrf" value="<?= htmlspecialchars($_SESSION['locations_csrf'], ENT_QUOTES) ?>">
      <input type="hidden" name="redirect" value="resident-account-registration">
      <div class="form-grid-3">
        <div class="field">
          <label>First Name</label>
          <input type="text" name="first_name" placeholder="First name" required>
        </div>
        <div class="field">
          <label>Last Name</label>
          <input type="text" name="last_name" placeholder="Last name" required>
        </div>
        <div class="field">
          <label>Middle Name</label>
          <input type="text" name="middle_name" placeholder="Middle name" data-middle-name-input>
          <label class="check-row">
            <input type="checkbox" name="no_middle_name" value="1" data-no-middle-name>
            <span>No middle name</span>
          </label>
        </div>
      </div>
      <div class="form-grid-3">
        <div class="field">
          <label>Suffix <span class="field-hint">(optional)</span></label>
          <input type="text" name="suffix" placeholder="Jr., Sr., III, leave blank if none">
        </div>
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" placeholder="Leave blank to auto-generate res-001@luna.gov">
          <p class="field-hint">Recommended format: resident ID username such as res-001@luna.gov.</p>
        </div>
        <div class="field">
          <label>Contact Number</label>
          <input type="tel" name="mobile" placeholder="09XX XXX XXXX">
        </div>
      </div>
      <div class="form-grid-3">
        <div class="field">
          <label>Temporary Password</label>
          <input type="password" name="password" required minlength="8">
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required minlength="8">
        </div>
        <div class="field">
          <label>Birthdate</label>
          <input type="date" name="birthdate">
        </div>
      </div>
      <div class="form-grid-3">
        <div class="field">
          <label>Gender</label>
          <select name="gender">
            <option>Female</option>
            <option>Male</option>
          </select>
        </div>
        <div class="field">
          <label>Civil Status</label>
          <select name="civil_status">
            <option>Single</option>
            <option>Married</option>
            <option>Widowed</option>
            <option>Separated</option>
          </select>
        </div>
      </div>
      <?php resident_location_fields($locationLists); ?>
      <div class="form-grid-3">
        <div class="field">
          <label>Household Number</label>
          <input type="text" name="household" placeholder="HH-001">
        </div>
        <div class="field field-span-2">
          <label>Complete Address</label>
          <input type="text" name="address" placeholder="Complete address">
        </div>
      </div>
      <?php include __DIR__ . '/../registration-requirement-fields.php'; ?>
      <p class="field-hint">An account notification is sent automatically to the resident’s contact number after saving.</p>
      <button type="submit" class="btn btn-primary">Save resident account</button>
    </form>
  </div>
</section>

<div>
  <section class="panel-card">
    <div class="panel-head"><h3>Synchronized registration workflow</h3></div>
    <div class="panel-body">
      <div class="registration-status-grid">
        <div>
          <span>New resident accounts</span>
          <strong><?= count($residentRegistrations) ?></strong>
        </div>
        <div>
          <span>Active residents</span>
          <strong><?= count(array_filter($residentRegistrations, static fn(array $resident): bool => ($resident['status'] ?? '') === 'Active')) ?></strong>
        </div>
        <div>
          <span>Active QR profiles</span>
          <strong><?= count(array_filter($residentRegistrations, static fn(array $resident): bool => ($resident['qr_status'] ?? '') === 'Active')) ?></strong>
        </div>
        <div>
          <span>Registered by</span>
          <strong><?= htmlspecialchars($staffName) ?></strong>
        </div>
      </div>
      <p class="field-hint" style="margin-top:1rem">Records saved here are shared with Admin, Resident Profile, QR Verification, Document Requests, and SMS modules.</p>
    </div>
  </section>


</div>

<?php require __DIR__ . '/registration-requirements.php'; ?>

<?php require __DIR__ . '/../registration-record-cards.php'; ?>

<section class="panel-card" style="margin-top:1.25rem">
  <div class="panel-head"><h3>Recent registration activity</h3></div>
  <div class="panel-body">
    <?php $registrationActivity = barangay_db()->query("SELECT action, user_name, created_at FROM audit_logs WHERE module IN ('Resident Account Registration', 'Resident Registration') ORDER BY created_at DESC, id DESC LIMIT 10")->fetchAll(); ?>
    <ul class="registration-activity-list">
      <?php foreach ($registrationActivity as $activity): ?>
      <li><div><?= htmlspecialchars($activity['action']) ?></div><div class="field-hint"><?= htmlspecialchars($activity['user_name']) ?> &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($activity['created_at']))) ?></div></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$registrationActivity): ?><p class="field-hint">No registration activity recorded yet.</p><?php endif; ?>
  </div>
</section>

<script src="js/resident-service-tools.js?v=1" defer></script>
<style>[data-duplicate-warning][hidden]{display:none!important}</style>
