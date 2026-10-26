<?php
/** Phosphor duotone icons, bundled locally under the MIT license. */
function barangay_ui_icon(string $name): string {
  static $icons;
  $icons ??= json_decode(file_get_contents(__DIR__ . '/../icons/system/icons.json'), true, 512, JSON_THROW_ON_ERROR);
  $aliases = [
    'id'=>'id-card', 'person'=>'user', 'group'=>'users', 'date'=>'calendar-days', 'calendar'=>'calendar-days',
    'document'=>'file-text', 'cash'=>'hand-coins', 'message'=>'message-square-text', 'released'=>'file-text', 'pickup'=>'bell', 'received'=>'circle-check',
    'dashboard'=>'layout-dashboard', 'staff-dashboard'=>'layout-dashboard', 'resident-dashboard'=>'layout-dashboard',
    'user-management'=>'user-round-cog', 'residents'=>'contact-round', 'resident-management'=>'contact-round',
    'qr-verification'=>'qr-code', 'staff-qr-verification'=>'qr-code', 'issue-documents'=>'file-plus-2',
    'resident-account-registration'=>'user-round-plus', 'sms-alerts'=>'message-square-text', 'staff-sms-alerts'=>'message-square-text',
    'walk-in-payments'=>'wallet', 'husay-schedule'=>'calendar-clock', 'staff-complaints'=>'triangle-alert', 'complaints'=>'triangle-alert', 'my-complaints'=>'triangle-alert', 'submit-complaint'=>'triangle-alert',
    'staff-feedbacks'=>'messages-square', 'feedbacks'=>'messages-square', 'send-feedback'=>'messages-square',
    'request-document'=>'file-text', 'my-document-requests'=>'file-text', 'sms-updates'=>'message-square-text',
    'reports-analytics'=>'chart-no-axes-combined', 'service-reports'=>'chart-column', 'audit-logs'=>'clipboard-list',
    'system-settings'=>'settings', 'backup-restore'=>'database', 'my-activity-logs'=>'clock',
    'phone'=>'phone', 'home'=>'house', 'qr'=>'qr-code', 'clock'=>'clock'
  ];
  $key = $aliases[$name] ?? $name;
  if (!isset($icons[$key])) $key = 'file-text';
  return '<svg viewBox="0 0 256 256" fill="currentColor" stroke="none" class="system-icon" aria-hidden="true" data-system-icon="' . $key . '">' . $icons[$key] . '</svg>';
}
