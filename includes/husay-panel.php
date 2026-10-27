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
  <div class="panel-head husay-panel-heading">
    <div class="husay-heading-text"><h3>Mediation schedules</h3><p class="field-hint">Appointments and session history · Philippine time</p></div>
    <?php if ($husayWorkspace): ?><button type="button" class="btn btn-outline husay-pending-button" data-husay-pending><span>Pending approval</span><strong class="husay-pending-count" data-husay-count="pending"><?= count(array_filter($husayRows, static fn($row) => $row['status'] === 'Pending Approval')) ?></strong></button><?php endif; ?>
  </div>
  <div class="husay-approval-note"><p class="field-hint">Staff proposals require the Barangay Captain's approval. Only approved appointments are shown to residents.</p></div>
  <div class="complaint-search"><label>Search Mediation schedules<input type="search" data-husay-search placeholder="Search complaint, person, date or venue…"></label></div>
  <?php if ($husayWorkspace): ?>
  <div class="husay-workspace-stats" aria-label="Schedule overview">
    <?php foreach (['today'=>"Today's sessions",'upcoming'=>'Upcoming sessions','completed'=>'Completed sessions'] as $key=>$label): ?><div><strong data-husay-count="<?= $key ?>">0</strong><span><?= $label ?></span></div><?php endforeach; ?>
  </div>
  <div class="husay-filters">
    <label class="field">Session status<select data-husay-status><option value="active">Active sessions</option><option value="all">All statuses</option><?php foreach (['Pending Approval','Scheduled','In Progress','Completed','Cancelled','Rescheduled','Declined'] as $status): ?><option><?= $status ?></option><?php endforeach; ?></select></label>
    <label class="field">Assigned official<select data-husay-officer><option value="">All officials</option><?php foreach ($husayOfficers as $official): ?><option value="<?= (int)$official['id'] ?>"><?= $husayEscape($official['full_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field">Dates<select data-husay-period><option value="upcoming">Today and upcoming</option><option value="today">Today</option><option value="all">All dates</option><option value="custom">Custom dates</option></select></label>
    <div class="husay-date-range" data-husay-date-range hidden><label class="field">From<input type="date" data-husay-from></label><label class="field">To<input type="date" data-husay-to></label></div>
    <button type="button" class="btn btn-outline" data-husay-reset>Reset filters</button>
  </div>
  <div class="husay-view-switch" role="group" aria-label="Schedule view"><button type="button" class="btn btn-primary" data-husay-mode="calendar" aria-pressed="true">Calendar</button><button type="button" class="btn btn-outline" data-husay-mode="list" aria-pressed="false">List</button></div>
  <div class="husay-calendar" data-husay-calendar>
    <div class="husay-month-controls"><button type="button" class="btn btn-outline" data-husay-month="-1" aria-label="Previous month"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="chevron-left"><path d="M160,48V208L80,128Z" opacity="0.2"/><path d="M163.06,40.61a8,8,0,0,0-8.72,1.73l-80,80a8,8,0,0,0,0,11.32l80,80A8,8,0,0,0,168,208V48A8,8,0,0,0,163.06,40.61ZM152,188.69,91.31,128,152,67.31Z"/></svg></button><h3 data-husay-month-label></h3><button type="button" class="btn btn-outline" data-husay-month="1" aria-label="Next month"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="chevron-right"><path d="M176,128,96,208V48Z" opacity="0.2"/><path d="M181.66,122.34l-80-80A8,8,0,0,0,88,48V208a8,8,0,0,0,13.66,5.66l80-80A8,8,0,0,0,181.66,122.34ZM104,188.69V67.31L164.69,128Z"/></svg></button><button type="button" class="btn btn-outline" data-husay-today>Today</button></div>
    <p class="field-hint">Select a date to see its sessions below. Counts follow your filters.</p>
    <div class="husay-calendar-grid" data-husay-calendar-grid role="group" aria-label="Choose a Mediation date"></div>
  </div>
  <p class="husay-agenda-label" data-husay-agenda-label aria-live="polite"></p>
  <?php endif; ?>
  <div class="panel-body" data-husay-list><?php require __DIR__ . '/husay-list.php'; ?></div>
  <p class="husay-empty" role="status" data-husay-no-match hidden>No Mediation sessions match your search.</p>
  <p class="husay-error" role="status" data-husay-refresh-error hidden></p>
</section>
<div id="husayScheduleModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="husayScheduleTitle">
  <div class="modal-backdrop" data-modal-close></div><div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="husayScheduleTitle">Schedule Mediation</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <form action="husay-actions.php" method="post" class="modal-body form-grid" id="husayScheduleForm" data-husay-form data-husay-approval="<?= ($husayActor['role'] ?? '') === 'admin' ? 'captain' : 'required' ?>">
      <input type="hidden" name="action" value="schedule_husay"><input type="hidden" name="csrf" value="<?= $husayEscape($husayToken) ?>"><input type="hidden" name="intake_mode" value="linked"><input type="hidden" name="complaint_code"><input type="hidden" name="reschedule_id"><input type="hidden" name="version">
      <input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(16)) ?>">
      <p class="husay-intake-note" data-husay-mode-description></p>
      <p class="field-hint" data-husay-complaint-label></p>
      <label class="field" data-husay-complaint-picker hidden>Existing complaint<select name="existing_complaint_code" disabled><?php require __DIR__ . '/husay-complaint-options.php'; ?></select><span class="field-hint">Choose an open complaint that has no active Mediation session.</span></label>
      <div class="husay-walkin-fields form-grid" data-husay-walkin-fields hidden>
        <h3>New walk-in complaint</h3>
        <p class="field-hint">Record the walk-in complaint and its appointment together. No resident account is required.</p>
        <label class="field">Existing Resident ID (optional)<input name="resident_code" maxlength="30" placeholder="Leave blank if there is no resident account"></label>
        <div class="form-grid-2"><label class="field">Complaint subject<input name="subject" maxlength="180" data-husay-walkin-required></label><label class="field">Category<input name="category" maxlength="100" value="Walk-in concern"></label></div>
        <label class="field">Complaint details<textarea name="details" rows="3" maxlength="10000" data-husay-walkin-required></textarea></label>
        <label class="field">Incident location<input name="location" maxlength="180"></label>
      </div>
      <div class="form-grid-2"><label class="field">Complainant name<input name="complainant_name" maxlength="150" required></label><label class="field">Complainant mobile number<input name="complainant_mobile" type="tel" maxlength="30" placeholder="09xxxxxxxxx" pattern="(09[0-9]{9}|\+639[0-9]{9})" title="Enter a Philippine mobile number: 09xxxxxxxxx or +639xxxxxxxxx." required></label></div>
      <div class="form-grid-2"><label class="field">Respondent name<input name="respondent_name" maxlength="150" required></label><label class="field">Respondent mobile (optional)<input name="respondent_mobile" type="tel" maxlength="30" placeholder="09xxxxxxxxx"></label></div>
      <label class="field">Respondent address (optional)<input name="respondent_address" maxlength="255"></label>
      <div class="form-grid-3"><label class="field">Mediation date<input type="date" name="hearing_date" required></label><label class="field">Start time<input type="time" name="start_time" required></label><label class="field">End time<input type="time" name="end_time" required></label></div>
      <p class="field-hint">Choose an available time. Mediation appointments cannot overlap another active schedule.</p>
      <div class="form-grid-2"><label class="field">Venue<input name="venue" value="Barangay Hall" maxlength="180" required></label><label class="field">Assigned official<select name="assigned_user_id" required><option value="">Select an official</option><?php foreach ($husayOfficers as $official): ?><option value="<?= (int)$official['id'] ?>"><?= $husayEscape($official['full_name'] . ($official['position_title'] ? ' · ' . $official['position_title'] : '')) ?></option><?php endforeach; ?></select></label></div>
      <label class="field">Instructions (visible to the resident)<textarea name="instructions" rows="2" maxlength="2000" placeholder="What the parties should bring or prepare"></textarea></label>
      <label class="field" data-husay-reschedule-reason hidden>Reason for rescheduling (visible to the resident)<textarea name="change_reason" maxlength="1000" rows="2"></textarea></label>
      <?php if (($husayActor['role'] ?? '') === 'admin'): ?><label class="check check-left"><input type="checkbox" name="review_confirmed" value="1" required><span>As Barangay Captain, I reviewed this complaint and approve scheduling it for Mediation.</span></label>
      <label class="check check-left"><input type="checkbox" name="send_sms" value="1"><span>Send an SMS appointment update now to the supplied mobile numbers. Printed notices are handled separately.</span></label><?php else: ?><p class="field-hint">This is a proposal for the Barangay Captain's review. It will not notify the parties or replace an existing appointment until approved.</p><?php endif; ?>
      <p class="husay-error" data-husay-error role="alert" hidden></p>
      <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Mediation schedule</button></footer>
    </form>
  </div>
</div>
<div id="husayDetailsModal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="husayDetailsTitle">
  <div class="modal-backdrop" data-modal-close></div><div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="husayDetailsTitle">Mediation session</h2><button type="button" class="modal-close" data-modal-close aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" class="system-icon" data-system-icon="x"><path d="M216,56V200a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V56A16,16,0,0,1,56,40H200A16,16,0,0,1,216,56Z" opacity="0.2"/><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg></button></header>
    <div class="modal-body" data-husay-details></div>
  </div>
</div>
<?php if ($husayWorkspace): ?><script src="js/husay-calendar.js?v=<?= filemtime(__DIR__ . '/../js/husay-calendar.js') ?>" defer></script><?php endif; ?>
<script src="js/husay.js?v=<?= filemtime(__DIR__ . '/../js/husay.js') ?>" defer></script>
