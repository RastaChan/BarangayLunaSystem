<?php
require_once __DIR__ . '/../includes/time-format.php';
date_default_timezone_set('Asia/Manila');
foreach (['2026-10-28 00:00:00'=>'Oct 28, 2026 12:00 AM','2026-10-28 12:00'=>'Oct 28, 2026 12:00 PM','2026-10-28 23:15:00'=>'Oct 28, 2026 11:15 PM','2026-10-28'=>'2026-10-28','Unread'=>'Unread'] as $input=>$expected) {
  if (barangay_display_datetime($input)!==$expected) throw new RuntimeException('Time display mismatch for '.$input);
}
echo "PASS: 12-hour timestamp formatting and unchanged date-only/status values.\n";
