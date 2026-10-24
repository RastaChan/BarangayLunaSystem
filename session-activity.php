<?php
require_once __DIR__ . '/includes/auth.php';
$user = barangay_current_user();
header('Cache-Control: no-store');
if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['account_security_csrf']) || !hash_equals($_SESSION['account_security_csrf'], (string) ($_POST['csrf'] ?? ''))) {
  http_response_code(403); exit;
}
$_SESSION['last_activity'] = time();
resident_record_account_activity($user);
http_response_code(204);
