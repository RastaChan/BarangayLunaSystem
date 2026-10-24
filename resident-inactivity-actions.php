<?php
require_once __DIR__ . '/includes/auth.php';
$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['admin', 'staff'], true)) {
  http_response_code(403); exit;
}
$destination = $user['role'] === 'admin' ? 'dashboard.php?view=residents' : 'staff-dashboard.php?view=resident-management';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['resident_review_csrf']) || !hash_equals($_SESSION['resident_review_csrf'], (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit;
  }
  resident_reactivate_after_review($user, trim((string) ($_POST['resident_code'] ?? '')), (string) ($_POST['review_note'] ?? ''), ($_POST['residency_confirmed'] ?? '') === '1');
  $_SESSION['resident_inactivity_flash'] = ['success', 'Residency confirmed and account reactivated. The resident has a new 6-month period to use the account.'];
} catch (InvalidArgumentException $e) {
  $_SESSION['resident_inactivity_flash'] = ['error', $e->getMessage()];
} catch (Throwable $e) {
  $_SESSION['resident_inactivity_flash'] = ['error', 'The review could not be saved. Please try again.'];
}
header('Location: ' . $destination);
exit;
