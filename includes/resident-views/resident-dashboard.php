<div class="page-intro">
  <h2>Welcome, <?= htmlspecialchars($residentName) ?></h2>
  <p>View barangay updates, track your service requests, and manage your resident account.</p>
</div>

<div class="stats-grid" data-live-overview="stats" data-overview-owner="<?= (int) $loggedInUser['id'] ?>">
  <article class="stat-card"><div class="label">Account status</div><div class="value"><?= htmlspecialchars($residentProfile['account_status']) ?></div><div class="trend">Resident profile</div></article>
  <article class="stat-card"><div class="label">QR status</div><div class="value"><?= htmlspecialchars($residentProfile['qr_status']) ?></div><div class="trend">Personal QR profile</div></article>
  <article class="stat-card"><div class="label">Pending requests</div><div class="value"><?= (int) $pendingRequests ?></div><div class="trend">Document requests</div></article>
  <article class="stat-card"><div class="label">Approved requests</div><div class="value"><?= (int) $approvedRequests ?></div><div class="trend">Approved or released</div></article>
</div>

<div class="toolbar">
  <a href="resident-dashboard.php?view=request-document" class="btn btn-primary">Request Document</a>
  <button type="button" class="btn btn-outline" data-modal-open="profileSettingsModal">View Profile ID</button>
  <a href="resident-dashboard.php?view=submit-complaint" class="btn btn-outline">Submit Complaint</a>
  <a href="#resident-announcements" class="btn btn-outline" data-nav-view="resident-announcements">View Announcements</a>
</div>

<div class="grid-2">
  <section class="panel-card" id="resident-announcements" style="scroll-margin-top:100px">
    <div class="panel-head"><h3>Announcements</h3></div>
    <?php $residentAnnouncementBadgeCount = (int) ($residentAnnouncementBadgeCount ?? 0); ?>
    <div class="panel-body" style="padding-bottom:0" data-announcement-dashboard-shell <?= $residentAnnouncementBadgeCount > 0 ? '' : 'hidden' ?>>
      <span class="badge badge-pending" data-notification-dashboard-count><?= (int) $residentAnnouncementBadgeCount ?> new announcement<?= (int) $residentAnnouncementBadgeCount === 1 ? '' : 's' ?></span>
    </div>
    <div class="panel-body">
      <div class="announcement-cards" data-live-overview="announcements">
        <?php foreach ($residentAnnouncements as $announcement): ?>
        <article class="ann-card">
          <?php if (!empty($announcement['photo_path'])): ?>
          <img src="<?= htmlspecialchars($announcement['photo_path']) ?>" alt="<?= htmlspecialchars($announcement['title']) ?>" class="announcement-photo">
          <?php endif; ?>
          <h4><?= htmlspecialchars($announcement['title']) ?></h4>
          <time><?= htmlspecialchars($announcement['date']) ?> · <?= htmlspecialchars($announcement['category']) ?></time>
          <p><?= htmlspecialchars($announcement['body']) ?></p>
        </article>
        <?php endforeach; ?>
        <?php if (!$residentAnnouncements): ?>
        <p class="empty-state">No published announcements yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel-card mobile-record-panel" id="upcoming-events" style="scroll-margin-top:100px">
    <div class="panel-head"><h3>Upcoming events</h3></div>
    <div class="table-wrap">
      <table class="data-table mobile-record-table" role="table" aria-label="Upcoming events">
        <thead><tr><th>Event</th><th>Date</th><th>Status</th></tr></thead>
        <tbody data-live-overview="events">
          <?php foreach ($upcomingEvents as $event): ?>
          <tr><td data-label="Event"><?= htmlspecialchars($event['title']) ?></td><td data-label="Date"><?= htmlspecialchars($event['date']) ?></td><td data-label="Status"><span class="badge badge-active"><?= htmlspecialchars($event['status']) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="panel-card mobile-record-panel" style="margin-top:1.25rem">
  <div class="panel-head"><h3>Recent notifications</h3><span class="field-hint">Open the bell in the top bar to view alerts.</span></div>
  <div class="table-wrap">
    <table class="data-table mobile-record-table" role="table" aria-label="Recent notifications">
      <thead><tr><th>Title</th><th>Message</th><th>Date</th><th>Status</th></tr></thead>
      <tbody data-live-overview="notifications">
        <?php foreach (array_slice($residentNotifications, 0, 3) as $notification): ?>
        <tr>
          <td data-label="Title"><?= htmlspecialchars($notification['title']) ?></td>
          <td data-label="Message"><?= htmlspecialchars($notification['message']) ?></td>
          <td data-label="Date"><?= htmlspecialchars($notification['date']) ?></td>
          <td data-label="Status"><span class="badge <?= $notification['status'] === 'Unread' ? 'badge-pending' : 'badge-active' ?>"><?= htmlspecialchars($notification['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
