<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';

$user = barangay_require_role('staff');

header('Content-Type: application/json');

function staff_qr_json(array $payload, int $statusCode = 200): void {
  http_response_code($statusCode);
  echo json_encode($payload);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  staff_qr_json(['ok' => false, 'message' => 'Invalid QR lookup request.'], 405);
}

$qrCode = trim((string) ($_POST['qr_code'] ?? $_POST['resident_code'] ?? ''));
$qrCode = substr($qrCode, 0, 180);

if ($qrCode === '') {
  staff_qr_json(['ok' => false, 'message' => 'Please scan a QR code or enter a resident ID.'], 422);
}

$residentCode = '';
if (preg_match('/^LUNA\|([^|]+)\|/i', $qrCode, $matches)) {
  $residentCode = strtoupper(trim($matches[1]));
}

try {
  $stmt = barangay_db()->prepare(
    "SELECT
        id,
        user_code,
        full_name,
        username,
        mobile,
        address,
        purok,
        household,
        birthdate,
        TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
        gender,
        civil_status,
        status,
        verification_status,
        qr_status,
        qr_code,
        qr_generated_at,
        profile_photo_path
     FROM users
     WHERE role = 'resident'
       AND (
       qr_code = :qr_code
        OR user_code = :raw_code
        OR (:resident_code_filter <> '' AND user_code = :resident_code)
       )
     LIMIT 1"
  );
  $stmt->execute([
    ':qr_code' => $qrCode,
    ':raw_code' => strtoupper($qrCode),
    ':resident_code_filter' => $residentCode,
    ':resident_code' => $residentCode,
  ]);
  $resident = $stmt->fetch();

  $result = 'Not Found';
  $message = 'No resident record matched the scanned QR code.';
  $logResidentId = null;
  $logResidentCode = substr($residentCode !== '' ? $residentCode : $qrCode, 0, 30);
  $logResidentName = 'Unknown resident';
  $responseResident = null;

  if ($resident) {
    $isVerifiedQr = $resident['status'] === 'Active'
      && $resident['qr_status'] === 'Active';

    $result = $isVerifiedQr ? 'Verified' : 'Inactive';
    $message = $isVerifiedQr
      ? 'Resident verified successfully.'
      : 'Resident found, but the account or QR status needs review.';
    $logResidentId = (int) $resident['id'];
    $logResidentCode = $resident['user_code'];
    $logResidentName = $resident['full_name'];
    $responseResident = [
      'id' => $resident['user_code'],
      'name' => $resident['full_name'],
      'username' => $resident['username'],
      'mobile' => $resident['mobile'] ?? '',
      'address' => $resident['address'] ?? '',
      'purok' => $resident['purok'] ?? '',
      'household' => $resident['household'] ?? '',
      'birthdate' => $resident['birthdate'] ?? '',
      'age' => $resident['age'] ?? '',
      'gender' => $resident['gender'] ?? '',
      'civil_status' => $resident['civil_status'] ?? '',
      'status' => $resident['status'],
      'verification_status' => $resident['verification_status'],
      'qr_status' => $resident['qr_status'],
      'qr_generated_at' => $resident['qr_generated_at'] ?? '',
      'profile_photo_path' => $resident['profile_photo_path'] ?? '',
      'qr_image_url' => !empty($resident['qr_code']) ? staff_qr_image_url($resident['qr_code'], 180) : '',
    ];
  }

  $insert = barangay_db()->prepare(
    'INSERT INTO qr_scan_logs (resident_user_id, resident_code, resident_name, result, scanned_by, scanned_at)
     VALUES (:resident_user_id, :resident_code, :resident_name, :result, :scanned_by, NOW())'
  );
  $insert->execute([
    ':resident_user_id' => $logResidentId,
    ':resident_code' => $logResidentCode,
    ':resident_name' => $logResidentName,
    ':result' => $result,
    ':scanned_by' => $user['id'],
  ]);

  staff_log($user, 'QR scanned: ' . $qrCode . ' result ' . $result, 'QR Resident Verification', $result === 'Verified' ? 'Success' : 'Warning');

  staff_qr_json([
    'ok' => true,
    'result' => $result,
    'message' => $message,
    'scanned_code' => $qrCode,
    'resident' => $responseResident,
    'scan_log' => [
      'date' => date('Y-m-d H:i'),
      'resident' => $logResidentName,
      'result' => $result,
      'staff' => $user['name'] ?? 'Staff',
    ],
  ]);
} catch (PDOException $e) {
  staff_log($user, 'Failed QR scan lookup', 'QR Resident Verification', 'Error');
  staff_qr_json(['ok' => false, 'message' => 'QR lookup could not be completed.'], 500);
}
?>
