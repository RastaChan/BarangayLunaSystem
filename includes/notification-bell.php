<?php
/** Shared header notification bell. */
$notificationItems = $notificationItems ?? [];
$notificationCount = $notificationCount ?? count($notificationItems);
$notificationCountLabel = $notificationCount > 99 ? '99+' : (string) $notificationCount;
$notificationAccount = $loggedInUser ?? $user ?? [];
$persistentNotifications = ($notificationAccount['role'] ?? '') === 'resident';
if ($persistentNotifications) {
  require_once __DIR__ . '/notification-state.php';
  $_SESSION['notification_csrf'] = $_SESSION['notification_csrf'] ?? bin2hex(random_bytes(32));
  $notificationState = barangay_notification_state((int) $notificationAccount['id']);
}
?>
<div class="notification-menu" data-notification-menu data-notification-owner="<?= htmlspecialchars(($notificationAccount['role'] ?? '') . ':' . (int) ($notificationAccount['id'] ?? 0), ENT_QUOTES) ?>" <?php if ($persistentNotifications): ?>data-notification-account="<?= (int) $notificationAccount['id'] ?>" data-notification-token="<?= htmlspecialchars($_SESSION['notification_csrf'], ENT_QUOTES) ?>" data-notification-state="<?= htmlspecialchars(json_encode($notificationState), ENT_QUOTES) ?>"<?php endif; ?>>
  <button type="button" class="header-icon-btn notification-btn" data-notification-toggle aria-label="Notifications" aria-controls="headerNotificationPanel" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" width="20" height="20" aria-hidden="true"><path d="M15 17H9l-1 2h8l-1-2ZM18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z" stroke="currentColor" stroke-width="1.5"/></svg>
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
          <small><?= htmlspecialchars($notification['date'] ?? ($notification['status'] ?? '')) ?></small>
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
