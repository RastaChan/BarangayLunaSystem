<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/record-attachments.php';
require_once __DIR__ . '/registration-requirements.php';
require_once __DIR__ . '/resident-locations.php';

function barangay_backup_tables(): array {
  barangay_attachment_schema();
  registration_requirements_schema();
  resident_locations_schema();
  // Only application-owned tables; optional migrations may not exist yet.
  $allowed = ['users', 'announcements', 'document_requests', 'document_templates', 'issued_documents',
    'complaints', 'husay_schedules', 'husay_history', 'husay_calendar_guard', 'feedbacks', 'sms_logs', 'qr_scan_logs', 'roles', 'audit_logs', 'system_settings',
    'document_fees', 'document_payments', 'notification_seen_state', 'record_attachments', 'resident_requirements', 'resident_districts', 'resident_puroks'];
  $existing = barangay_db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  return array_values(array_intersect($allowed, $existing));
}
