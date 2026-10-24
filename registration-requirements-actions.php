<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/registration-requirements.php';
require_once __DIR__ . '/includes/staff-functions.php';
$user = barangay_current_user();
if (!$user || !in_array($user['role'], ['admin', 'staff'], true)) { http_response_code(403); exit('Access denied.'); }
$resident = (int) ($_POST['resident_id'] ?? 0);
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['requirements_csrf']) || !hash_equals($_SESSION['requirements_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Refresh the page and try again.');
  registration_requirements_schema();
  $file = barangay_read_attachment_upload($_FILES['requirement_file'] ?? []);
  $db = barangay_db();
  $db->beginTransaction();
  registration_requirement_save($resident, (string) ($_POST['requirement_key'] ?? ''), (string) ($_POST['status'] ?? ''), trim((string) ($_POST['remarks'] ?? '')), $user['id'], $file, isset($_POST['remove_file']));
  staff_log($user, 'Updated registration requirement for resident #' . $resident . ': ' . $_POST['requirement_key'], 'Resident Registration');
  $db->commit();
  $_SESSION['requirements_flash'] = ['success', 'Requirement saved.'];
} catch (Throwable $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $_SESSION['requirements_flash'] = ['error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not save the requirement.'];
}
header('Location: ' . barangay_dashboard_for_role($user['role']) . '?view=resident-account-registration&requirements_resident=' . $resident . '#registrationRequirements');
