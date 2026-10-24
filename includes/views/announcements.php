<?php
$communityPostRole = $currentRole ?? ($loggedInUser['role'] ?? '');
if (!in_array($communityPostRole, ['admin', 'staff'], true)) return;
$communityPostActionUrl = $communityPostRole === 'admin' ? 'admin-actions.php' : 'staff-actions.php';
$communityPostRedirect = $communityPostRole === 'admin' ? 'dashboard' : 'staff-dashboard';
$communityPosts = $communityPosts ?? $adminAnnouncements ?? [];
$_SESSION['community_post_csrf'] ??= bin2hex(random_bytes(32));
?>
<section id="community-posts" class="community-posts">
          <div class="recent-posts-heading"><h3>Recent posts</h3></div>
          <div class="recent-posts-feed">
            <?php foreach ($communityPosts as $a): ?>
            <article class="ann-card recent-post-card">
              <div class="recent-post-options" data-post-options>
                <button type="button" class="recent-post-toggle" data-post-toggle aria-controls="postActions<?= (int) $a['id'] ?>" aria-expanded="false" aria-label="Post options for <?= htmlspecialchars($a['title'], ENT_QUOTES) ?>" title="Post options">
                  <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                </button>
              <div id="postActions<?= (int) $a['id'] ?>" class="recent-post-actions" data-post-actions hidden>
                <?php if (empty($a['archived_at'])): ?>
                <button type="button" class="btn btn-sm btn-outline" data-modal-open="editAnnouncementModal<?= (int) $a['id'] ?>">Edit</button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-danger" data-modal-open="deleteAnnouncementModal<?= (int) $a['id'] ?>">Delete</button>
              </div>
              </div>
              <?php $recentPost = $a; include __DIR__ . '/../community-post-card-content.php'; ?>
            </article>
            <div id="deleteAnnouncementModal<?= (int) $a['id'] ?>" class="modal" aria-hidden="true" role="dialog">
              <div class="modal-backdrop" data-modal-close></div>
              <div class="modal-panel">
                <header class="modal-header"><h2>Delete post</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
                <div class="modal-body">
                  <p>Are you sure you want to delete <strong><?= htmlspecialchars($a['title']) ?></strong>?</p>
                  <p class="field-hint">This will permanently remove this post and its uploaded photo.</p>
                </div>
                <form id="deleteAnnouncementForm<?= (int) $a['id'] ?>" action="<?= $communityPostActionUrl ?>" method="post" data-server-form="true">
                  <input type="hidden" name="action" value="delete_announcement">
                  <input type="hidden" name="redirect" value="<?= $communityPostRedirect ?>">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['community_post_csrf'], ENT_QUOTES) ?>">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                </form>
                <footer class="modal-footer">
                  <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                  <button type="submit" form="deleteAnnouncementForm<?= (int) $a['id'] ?>" class="btn btn-danger">Delete</button>
                </footer>
              </div>
            </div>
            <div id="editAnnouncementModal<?= (int) $a['id'] ?>" class="modal" aria-hidden="true" role="dialog">
              <div class="modal-backdrop" data-modal-close></div>
              <div class="modal-panel">
                <header class="modal-header"><h2>Edit post</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
                <form action="<?= $communityPostActionUrl ?>" method="post" enctype="multipart/form-data" class="modal-body form-grid" data-server-form="true">
                  <input type="hidden" name="action" value="update_announcement">
                  <input type="hidden" name="redirect" value="<?= $communityPostRedirect ?>">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['community_post_csrf'], ENT_QUOTES) ?>">
                  <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <div class="field">
                    <label for="editPostType<?= (int) $a['id'] ?>">Post type</label>
                    <select id="editPostType<?= (int) $a['id'] ?>" name="post_type" data-post-type>
                      <option value="announcement" <?= ($a['post_type'] ?? 'announcement') === 'announcement' ? 'selected' : '' ?>>Announcement</option>
                      <option value="event" <?= ($a['post_type'] ?? '') === 'event' ? 'selected' : '' ?>>Event</option>
                    </select>
                  </div>
                  <div class="form-grid-2" data-event-fields <?= ($a['post_type'] ?? '') === 'event' ? '' : 'hidden' ?>>
                    <div class="field">
                      <label>Event date and time</label>
                      <input type="datetime-local" name="event_at" value="<?= !empty($a['event_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($a['event_at']))) : '' ?>">
                    </div>
                    <div class="field">
                      <label>Event location</label>
                      <input type="text" name="event_location" maxlength="255" value="<?= htmlspecialchars($a['event_location'] ?? '') ?>">
                    </div>
                  </div>
                  <div class="field">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($a['title']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Content</label>
                    <textarea name="body" rows="5" required><?= htmlspecialchars($a['body']) ?></textarea>
                  </div>
                  <div class="field">
                    <label>Replace photo</label>
                    <div class="file-upload-field">
                      <input type="file" id="announcementPhotoEdit<?= (int) $a['id'] ?>" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-file-input data-empty-label="<?= htmlspecialchars($a['photo_file_name'] ?: 'No new photo selected') ?>">
                      <label for="announcementPhotoEdit<?= (int) $a['id'] ?>" class="btn btn-outline btn-sm">Upload Photo</label>
                      <span data-file-name><?= htmlspecialchars($a['photo_file_name'] ?: 'No new photo selected') ?></span>
                    </div>
                    <small class="field-hint">Leave blank to keep the current photo. Max 50 MB.</small>
                  </div>
                  <?php $isScheduledAnnouncement = ($a['status'] ?? '') === 'Scheduled'; ?>
                  <div class="field">
                    <label>Schedule publication</label>
                    <label class="check check-left">
                      <input type="checkbox" name="schedule_enabled" value="1" data-schedule-toggle data-schedule-reset="true" <?= $isScheduledAnnouncement ? 'checked' : '' ?>>
                      <span>Enable schedule</span>
                    </label>
                  </div>
                  <div class="form-grid-2">
                    <div class="field" data-schedule-panel <?= $isScheduledAnnouncement ? '' : 'hidden' ?>>
                      <label>Publish date</label>
                      <input type="date" name="publish_date" value="<?= htmlspecialchars($a['date']) ?>" data-schedule-date <?= $isScheduledAnnouncement ? '' : 'readonly' ?> required>
                    </div>
                    <div class="field" data-schedule-panel <?= $isScheduledAnnouncement ? '' : 'hidden' ?>>
                      <label>Publish time</label>
                      <input type="time" name="publish_time" value="<?= htmlspecialchars($a['publish_time'] ?? '00:00') ?>" data-schedule-time <?= $isScheduledAnnouncement ? '' : 'readonly' ?> required>
                    </div>
                  </div>
                  <footer class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                  </footer>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$communityPosts): ?>
              <p class="empty-state">No announcements yet.</p>
            <?php endif; ?>
          </div>
</section>
<?php include __DIR__ . '/../community-post-modal.php'; ?>
