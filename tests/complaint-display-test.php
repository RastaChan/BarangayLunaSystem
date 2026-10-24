<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = 'luna_complaint_display_' . bin2hex(random_bytes(6));
$server = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$name`");
putenv('BARANGAY_DB_NAME=' . $name);
function complaint_check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
function barangay_current_user(): array { return $GLOBALS['complaintViewer']; }
try {
  $server->exec("USE `$name`");
  $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS barangay_luna_system.*?;/s', '', file_get_contents(__DIR__ . '/../database/schema.sql'));
  $server->exec(str_replace('USE barangay_luna_system;', '', $schema));
  require_once __DIR__ . '/../includes/staff-functions.php';
  require_once __DIR__ . '/../includes/record-attachments.php';
  $db = barangay_db();
  $db->exec("INSERT INTO users(user_code,role,full_name,username,password_hash,status) VALUES ('RES-PHOTO','resident','Test Resident','test-photo','unused','Active')");
  $owner = (int) $db->lastInsertId();
  $db->prepare("INSERT INTO complaints(complaint_code,resident_user_id,resident_name,subject,status) VALUES ('CMP-PHOTO',?,'Test Resident','Photo concern','In Progress')")->execute([$owner]);
  barangay_attachment_schema();
  barangay_save_attachment('complaint', 'CMP-PHOTO', $owner, ['filename' => 'evidence <photo>.png', 'mime' => 'image/png', 'content' => base64_encode('test-only-photo-bytes')]);
  $fileId = (int) $db->lastInsertId();
  $file = $db->query('SELECT * FROM record_attachments WHERE id = ' . $fileId)->fetch();
  complaint_check(barangay_attachment_authorized(['id' => $owner, 'role' => 'resident'], $file), 'The resident can view their own photo');
  complaint_check(!barangay_attachment_authorized(['id' => $owner + 1, 'role' => 'resident'], $file), 'Another resident cannot view the complaint photo');
  complaint_check(barangay_attachment_authorized(['id' => 0, 'role' => 'staff'], $file), 'Staff can view photo evidence attached to an existing complaint');
  $complaint = ['id' => 'CMP-PHOTO', 'resident' => 'Test Resident', 'resident_user_id' => $owner,
    'subject' => 'A longer complaint subject', 'category' => 'Other', 'details' => 'A detailed concern', 'location' => 'Test area',
    'date' => '2026-10-24', 'priority' => 'Medium', 'status' => 'In Progress', 'remarks' => 'Reviewing evidence'];
  $_SESSION = [];
  foreach (['admin' => 'views/complaints.php', 'staff' => 'staff-views/staff-complaints.php', 'resident' => 'resident-views/my-complaints.php'] as $role => $view) {
    $complaintViewer = ['id' => $role === 'resident' ? $owner : 0, 'role' => $role];
    $loggedInUser = $complaintViewer;
    $adminComplaints = $staffComplaints = $myComplaints = [$complaint];
    ob_start(); require __DIR__ . '/../includes/' . $view; $html = ob_get_clean();
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
    complaint_check($xpath->query('//table[contains(@class,"complaint-list-table")]/tbody/tr')->length === 1 && $xpath->query('//td[@data-label]')->length >= ($role === 'admin' ? 5 : 6), "$role complaint list renders vertical cards with readable field labels");
    if ($role === 'resident') complaint_check($xpath->query('//a[@href="record-attachment.php?id=' . $fileId . '" and not(@download)]')->length === 1, 'Resident attachment link opens the protected viewer');
    if ($role !== 'resident') {
      $button = $xpath->query('//button[@data-view-record]')->item(0);
      $metadata = json_decode($button->getAttribute('data-record-attachments'), true, 512, JSON_THROW_ON_ERROR);
      complaint_check((int) $metadata[0]['id'] === $fileId && $metadata[0]['mime_type'] === 'image/png' && !isset($metadata[0]['content_base64']), "$role View receives photo metadata without embedding image data in the list");
      complaint_check($xpath->query('//button[@data-post-toggle]')->length === 1 && $xpath->query('//div[@data-post-actions and @hidden]//button[@data-view-record]')->length === 1 && $xpath->query('//div[@data-post-actions and @hidden]//button[@data-husay-schedule-complaint]')->length === 1, "$role card has one three-dot menu with View details and Schedule this complaint");
      $scheduleData = json_decode($xpath->query('//button[@data-husay-schedule-complaint]')->item(0)->getAttribute('data-husay-schedule-complaint'), true);
      complaint_check($scheduleData['id']==='CMP-PHOTO' && $scheduleData['resident']==='Test Resident', "$role schedule shortcut passes the selected complaint and complainant");
      complaint_check($xpath->query('//button[@data-view-record and @data-complaint-update-url="' . $role . '-actions.php"]')->length === 1 && $xpath->query('//select[@name="status"] | //textarea[@name="remarks"]')->length === 0, "$role opens status and remarks in the popup instead of a large card form");
    }
  }
  $complaintActionRecord = array_replace($complaint,['status'=>'Resolved']);
  ob_start(); require __DIR__ . '/../includes/complaint-actions.php'; $closedHtml=ob_get_clean();
  complaint_check(!str_contains($closedHtml,'data-husay-schedule-complaint'),'Resolved complaints do not offer new Husay scheduling');
} finally { $server->exec("DROP DATABASE `$name`"); }
echo "Isolated database removed. No production complaints changed.\n";
