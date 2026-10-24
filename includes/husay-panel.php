<?php
require_once __DIR__ . '/husay-helpers.php';
husay_schema();
$_SESSION['husay_csrf'] ??= bin2hex(random_bytes(32));
$husayToken = $_SESSION['husay_csrf'];
$husayActor = $loggedInUser ?? $user ?? [];
$husayRows = husay_rows(); $husayOfficers = husay_officers();
$husayWorkspace = $husayWorkspace ?? false;
$husayEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<section class="panel-card husay-panel <?= $husayWorkspace ? 'husay-workspace' : '' ?>" data-husay-root <?= $husayWorkspace ? 'data-husay-workspace' : '' ?> data-husay-owner="<?= $husayEscape(($husayActor['role'] ?? '') . ':' . (int)($husayActor['id'] ?? 0)) ?>">
  <div class="panel-head"><div><h3>Husay schedules</h3><p class="field-hint">Appointments and session history · Philippine time</p></div></div>
  <div class="complaint-search"><label>Search Husay schedules<input type="search" data-husay-search placeholder="Search complaint, person, date or venue…"></label></div>
  <?php if ($husayWorkspace): ?>
  <div class="husay-workspace-stats" aria-label="Schedule overview">
    <?php foreach (['today'=>"Today's sessions",'upcoming'=>'Upcoming sessions','completed'=>'Completed sessions'] as $key=>$label): ?><div><strong data-husay-count="<?= $key ?>">0</strong><span><?= $label ?></span></div><?php endforeach; ?>
  </div>
  <div class="husay-filters">
    <label class="field">Session status<select data-husay-status><option value="active">Active sessions</option><option value="all">All statuses</option><?php foreach (['Scheduled','In Progress','Completed','Cancelled','Rescheduled'] as $status): ?><option><?= $status ?></option><?php endforeach; ?></select></label>
    <label class="field">Assigned official<select data-husay-officer><option value="">All officials</option><?php foreach ($husayOfficers as $official): ?><option value="<?= (int)$official['id'] ?>"><?= $husayEscape($official['full_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field">Dates<select data-husay-period><option value="upcoming">Today and upcoming</option><option value="today">Today</option><option value="all">All dates</option><option value="custom">Custom dates</option></select></label>
    <div class="husay-date-range" data-husay-date-range hidden><label class="field">From<input type="date" data-husay-from></label><label class="field">To<input type="date" data-husay-to></label></div>
    <button type="button" class="btn btn-outline" data-husay-reset>Reset filters</button>
  </div>
  <div class="husay-view-switch" role="group" aria-label="Schedule view"><button type="button" class="btn btn-primary" data-husay-mode="calendar" aria-pressed="true">Calendar</button><button type="button" class="btn btn-outline" data-husay-mode="list" aria-pressed="false">List</button></div>
  <div class="husay-calendar" data-husay-calendar>
    <div class="husay-month-controls"><button type="button" class="btn btn-outline" data-husay-month="-1" aria-label="Previous month">‹</button><h3 data-husay-month-label></h3><button type="button" class="btn btn-outline" data-husay-month="1" aria-label="Next month">›</button><button type="button" class="btn btn-outline" data-husay-today>Today</button></div>
    <p class="field-hint">Select a date to see its sessions below. Counts follow your filters.</p>
    <div class="husay-calendar-grid" data-husay-calendar-grid role="group" aria-label="Choose a Husay date"></div>
  </div>
  <p class="husay-agenda-label" data-husay-agenda-label aria-live="polite"></p>
  <?php endif; ?>
  <div class="panel-body" data-husay-list><?php require __DIR__ . '/husay-list.php'; ?></div>
  <p class="husay-empty" role="status" data-husay-no-match hidden>No Husay sessions match your search.</p>
  <p class="husay-error" role="status" data-husay-refresh-error hidden></p>
</section>
<div id="husayScheduleModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="husayScheduleTitle">
  <div class="modal-backdrop" data-modal-close></div><div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="husayScheduleTitle">Schedule Husay</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <form action="husay-actions.php" method="post" class="modal-body form-grid" id="husayScheduleForm" data-husay-form>
      <input type="hidden" name="action" value="schedule_husay"><input type="hidden" name="csrf" value="<?= $husayEscape($husayToken) ?>"><input type="hidden" name="intake_mode" value="linked"><input type="hidden" name="complaint_code"><input type="hidden" name="reschedule_id"><input type="hidden" name="version">
      <input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(16)) ?>">
      <p class="husay-intake-note" data-husay-mode-description></p>
      <p class="field-hint" data-husay-complaint-label></p>
      <label class="field" data-husay-complaint-picker hidden>Existing complaint<select name="existing_complaint_code" disabled><?php require __DIR__ . '/husay-complaint-options.php'; ?></select><span class="field-hint">Choose an open complaint that has no active Husay session.</span></label>
      <div class="husay-walkin-fields form-grid" data-husay-walkin-fields hidden>
        <h3>New walk-in complaint</h3>
        <p class="field-hint">Record the walk-in complaint and its appointment together. No resident account is required.</p>
        <label class="field">Existing Resident ID (optional)<input name="resident_code" maxlength="30" placeholder="Leave blank if there is no resident account"></label>
        <div class="form-grid-2"><label class="field">Complaint subject<input name="subject" maxlength="180" data-husay-walkin-required></label><label class="field">Category<input name="category" maxlength="100" value="Walk-in concern"></label></div>
        <label class="field">Complaint details<textarea name="details" rows="3" maxlength="10000" data-husay-walkin-required></textarea></label>
        <label class="field">Incident location<input name="location" maxlength="180"></label>
      </div>
      <div class="form-grid-2"><label class="field">Complainant name<input name="complainant_name" maxlength="150" required></label><label class="field">Complainant mobile (optional)<input name="complainant_mobile" type="tel" maxlength="30" placeholder="09xxxxxxxxx"></label></div>
      <div class="form-grid-2"><label class="field">Respondent name<input name="respondent_name" maxlength="150" required></label><label class="field">Respondent mobile (optional)<input name="respondent_mobile" type="tel" maxlength="30" placeholder="09xxxxxxxxx"></label></div>
      <label class="field">Respondent address (optional)<input name="respondent_address" maxlength="255"></label>
      <div class="form-grid-3"><label class="field">Husay date<input type="date" name="hearing_date" required></label><label class="field">Start time<input type="time" name="start_time" required></label><label class="field">End time<input type="time" name="end_time" required></label></div>
      <div class="form-grid-2"><label class="field">Venue<input name="venue" value="Barangay Hall" maxlength="180" required></label><label class="field">Assigned official<select name="assigned_user_id" required><option value="">Select an official</option><?php foreach ($husayOfficers as $official): ?><option value="<?= (int)$official['id'] ?>"><?= $husayEscape($official['full_name'] . ($official['position_title'] ? ' · ' . $official['position_title'] : '')) ?></option><?php endforeach; ?></select></label></div>
      <label class="field">Instructions (visible to the resident)<textarea name="instructions" rows="2" maxlength="2000" placeholder="What the parties should bring or prepare"></textarea></label>
      <label class="field" data-husay-reschedule-reason hidden>Reason for rescheduling (visible to the resident)<textarea name="change_reason" maxlength="1000" rows="2"></textarea></label>
      <label class="check check-left"><input type="checkbox" name="review_confirmed" value="1" required><span>The authorized barangay official reviewed this complaint and approved scheduling it for Husay.</span></label>
      <label class="check check-left"><input type="checkbox" name="send_sms" value="1"><span>Send an SMS appointment update now to the supplied mobile numbers. Printed notices are handled separately.</span></label>
      <p class="husay-error" data-husay-error role="alert" hidden></p>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Husay schedule</button></footer>
    </form>
  </div>
</div>
<div id="husayDetailsModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="husayDetailsTitle">
  <div class="modal-backdrop" data-modal-close></div><div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="husayDetailsTitle">Husay session</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body" data-husay-details></div>
  </div>
</div>
<?php if ($husayWorkspace): ?><script src="js/husay-calendar.js?v=<?= filemtime(__DIR__ . '/../js/husay-calendar.js') ?>" defer></script><?php endif; ?>
<script src="js/husay.js?v=<?= filemtime(__DIR__ . '/../js/husay.js') ?>" defer></script>
