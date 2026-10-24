<?php
// Optional scheduler entry point. Portal requests enforce the same rule automatically.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/resident-inactivity.php';
try {
  $count = resident_deactivate_unused_accounts();
  echo "Resident inactivity check complete: {$count} account(s) deactivated.\n";
} catch (Throwable $e) {
  fwrite(STDERR, "Resident inactivity check failed: " . $e->getMessage() . "\n");
  exit(1);
}
