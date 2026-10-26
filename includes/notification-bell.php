<?php
require_once __DIR__ . '/time-format.php';
/** Shared header notification bell. */
$notificationItems = $notificationItems ?? [];
$notificationCount = $notificationCount ?? count($notificationItems);
$notificationCountLabel = $notificationCount > 99 ? '99+' : (string) $notificationCount;
$notificationAccount = $loggedInUser ?? $user ?? [];
$persistentNotifications = !empty($notificationAccount['id']);
if ($persistentNotifications) {
  require_once __DIR__ . '/notification-state.php';
  $_SESSION['notification_csrf'] = $_SESSION['notification_csrf'] ?? bin2hex(random_bytes(32));
  $notificationState = barangay_notification_state((int) $notificationAccount['id']);
}
// Render the saved unseen count instead of flashing the total on every reload.
$notificationState = $notificationState ?? ['seen' => [], 'read' => [], 'unread' => [], 'dismissed' => []];
$notificationCount = 0;
foreach ($notificationItems as $entry) {
  $key = $entry['id'] ?? hash('sha1', implode('|', [$entry['title'] ?? '', $entry['message'] ?? '', $entry['date'] ?? '', $entry['href'] ?? '']));
  $unread = (($entry['status'] ?? 'Unread') === 'Unread' || in_array($key, $notificationState['unread'], true))
    && !in_array($key, $notificationState['read'], true);
  if ($unread && !in_array($key, $notificationState['seen'], true) && !in_array($key, $notificationState['dismissed'], true)) $notificationCount++;
}
$notificationCountLabel = $notificationCount > 99 ? '99+' : (string) $notificationCount;
?>
<div class="notification-menu" data-notification-menu data-notification-owner="<?= htmlspecialchars(($notificationAccount['role'] ?? '') . ':' . (int) ($notificationAccount['id'] ?? 0), ENT_QUOTES) ?>" <?php if ($persistentNotifications): ?>data-notification-account="<?= (int) $notificationAccount['id'] ?>" data-notification-token="<?= htmlspecialchars($_SESSION['notification_csrf'], ENT_QUOTES) ?>" data-notification-state="<?= htmlspecialchars(json_encode($notificationState), ENT_QUOTES) ?>"<?php endif; ?>>
  <button type="button" class="header-icon-btn notification-btn" data-notification-toggle aria-label="Notifications" aria-controls="headerNotificationPanel" aria-expanded="false">
    <svg width="20" height="20" viewBox="0 0 256 256" fill="currentColor" stroke="none" aria-hidden="true" data-system-icon="bell" class="system-icon"><path d="M208,192H48a8,8,0,0,1-6.88-12C47.71,168.6,56,139.81,56,104a72,72,0,0,1,144,0c0,35.82,8.3,64.6,14.9,76A8,8,0,0,1,208,192Z" opacity="0.2"/><path d="M221.8,175.94C216.25,166.38,208,139.33,208,104a80,80,0,1,0-160,0c0,35.34-8.26,62.38-13.81,71.94A16,16,0,0,0,48,200H88.81a40,40,0,0,0,78.38,0H208a16,16,0,0,0,13.8-24.06ZM128,216a24,24,0,0,1-22.62-16h45.24A24,24,0,0,1,128,216ZM48,184c7.7-13.24,16-43.92,16-80a64,64,0,1,1,128,0c0,36.05,8.28,66.73,16,80Z"/></svg>
    <?php if ($notificationCount > 0): ?>
    <span class="notification-count"><?= htmlspecialchars($notificationCountLabel) ?></span>
    <?php endif; ?>
  </button>
  <div id="headerNotificationPanel" class="notification-dropdown" data-notification-dropdown role="region" aria-label="Notifications">
    <div class="notification-dropdown-head">
      <strong>Notifications</strong>
      <span data-notification-summary>
        <?php if ($notificationCount > 0): ?>
        <span data-notification-new-count><?= (int) $notificationCount ?></span> new
        <?php else: ?>
        No new alerts
        <?php endif; ?>
      </span>
    </div>
    <div class="notification-list">
      <?php foreach ($notificationItems as $notification): ?>
      <?php
        $notificationId = $notification['id'] ?? hash('sha1', implode('|', [
          $notification['title'] ?? '',
          $notification['message'] ?? '',
          $notification['date'] ?? '',
          $notification['href'] ?? '',
        ]));
        $isUnread = ($notification['status'] ?? 'Unread') === 'Unread';
        $notificationView = $notification['view'] ?? '';
      ?>
      <div class="notification-row" data-notification-row>
        <a class="notification-item <?= $isUnread ? 'is-new' : '' ?>" href="<?= htmlspecialchars($notification['href'] ?? '#') ?>" data-notification-id="<?= htmlspecialchars($notificationId) ?>" data-notification-new="<?= $isUnread ? 'true' : 'false' ?>" data-notification-view="<?= htmlspecialchars($notificationView) ?>">
          <strong><?= htmlspecialchars($notification['title'] ?? 'Notification') ?></strong>
          <span><?= htmlspecialchars($notification['message'] ?? '') ?></span>
          <small><?= htmlspecialchars(barangay_display_datetime($notification['date'] ?? ($notification['status'] ?? ''))) ?></small>
        </a>
        <button type="button" class="notification-more" data-notification-options-toggle="<?= htmlspecialchars($notificationId, ENT_QUOTES) ?>" aria-label="Notification options" aria-expanded="false">...</button>
        <div class="notification-options" data-notification-options hidden><button type="button" class="notification-unread-action" data-notification-unread="<?= htmlspecialchars($notificationId, ENT_QUOTES) ?>" data-mark-unread="<?= $isUnread ? 'false' : 'true' ?>"><?= $isUnread ? 'Mark as read' : 'Mark as unread' ?></button><button type="button" class="notification-remove-action" data-notification-dismiss="<?= htmlspecialchars($notificationId, ENT_QUOTES) ?>">Remove notification</button></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$notificationItems): ?>
      <div class="notification-empty">No notifications yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
