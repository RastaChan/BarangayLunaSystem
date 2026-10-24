<?php
$postActionUrl = $currentRole === 'admin' ? 'admin-actions.php' : 'staff-actions.php';
$postRedirect = $currentRole === 'admin' ? 'dashboard' : 'staff-dashboard';
$_SESSION['community_post_csrf'] ??= bin2hex(random_bytes(32));
?>
<form id="announcementForm" class="modal-body form-grid" action="<?= $postActionUrl ?>" method="post" enctype="multipart/form-data" data-server-form="true">
  <input type="hidden" name="action" value="create_announcement">
  <input type="hidden" name="redirect" value="<?= $postRedirect ?>">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['community_post_csrf'], ENT_QUOTES) ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
  <div class="field">
    <label for="communityPostType">Post type</label>
    <select id="communityPostType" name="post_type" data-post-type>
      <option value="announcement">Announcement</option>
      <option value="event">Event</option>
    </select>
  </div>
  <div class="field">
    <label for="communityPostTitle">Title</label>
    <input id="communityPostTitle" type="text" name="title" maxlength="180" required>
  </div>
  <div class="field">
    <label for="communityPostBody">Content</label>
    <textarea id="communityPostBody" name="body" rows="4" required></textarea>
  </div>
  <div class="form-grid-2" data-event-fields hidden>
    <div class="field">
      <label for="communityEventDate">Event date and time</label>
      <input id="communityEventDate" type="datetime-local" name="event_at" disabled>
    </div>
    <div class="field">
      <label for="communityEventLocation">Event location</label>
      <input id="communityEventLocation" type="text" name="event_location" maxlength="255" disabled>
    </div>
  </div>
  <div class="field">
    <label for="announcementPhotoUpload">Post photo</label>
    <div class="file-upload-field">
      <input type="file" id="announcementPhotoUpload" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-file-input data-empty-label="No photo selected">
      <label for="announcementPhotoUpload" class="btn btn-outline btn-sm">Upload Photo</label>
      <span data-file-name>No photo selected</span>
    </div>
    <small class="field-hint">Optional. JPG, PNG, WEBP, or GIF up to 50 MB.</small>
  </div>
  <div class="field">
    <label class="check check-left">
      <input type="checkbox" name="schedule_enabled" value="1" data-schedule-toggle data-schedule-reset="true">
      <span>Schedule publication</span>
    </label>
  </div>
  <div class="form-grid-2">
    <div class="field" data-schedule-panel hidden>
      <label for="communityPublishDate">Publish date</label>
      <input id="communityPublishDate" type="date" name="publish_date" value="<?= date('Y-m-d') ?>" data-schedule-date readonly required>
    </div>
    <div class="field" data-schedule-panel hidden>
      <label for="communityPublishTime">Publish time</label>
      <input id="communityPublishTime" type="time" name="publish_time" value="<?= date('H:i') ?>" data-schedule-time readonly required>
    </div>
  </div>
  <p class="field-hint">Posts appear on the resident portal immediately, or at the scheduled publication time. Event dates are separate from publication dates.</p>
  <footer class="modal-footer">
    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
    <button type="submit" class="btn btn-primary" data-publish-label>Publish post</button>
  </footer>
</form>
