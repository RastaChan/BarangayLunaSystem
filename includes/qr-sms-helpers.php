<?php
/**
 * QR Verification & SMS Alerts Helper Functions
 * Barangay Luna MIS
 */
require_once __DIR__ . '/database.php';

class QRVerificationHelper {
  /**
   * Generate unique QR code for resident
   * @param string $resident_id
   * @param string $resident_name
   * @return string QR code data
   */
  public static function generateQRCode($resident_id, $resident_name) {
    $qr_code = 'LUNA|' . strtoupper($resident_id) . '|' . date('YmdHis');
    return $qr_code;
  }

  /**
   * Generate QR code image URL (using external QR service)
   * @param string $qr_data
   * @param int $size
   * @return string Image URL
   */
  public static function generateQRImageURL($qr_data, $size = 300) {
    // Using qr-server.com temporarily - replace with your QR library in production
    // For production, use library like: endroid/qr-code or phpqrcode
    $encoded_data = urlencode($qr_data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded_data}";
  }

  /**
   * Store QR code in database
   * @param string $resident_id
   * @param string $qr_code
   * @param string $qr_image_url
   * @return array Result
   */
  public static function storeQRCode($resident_id, $qr_code, $qr_image_url = null) {
    $stmt = barangay_db()->prepare(
      "UPDATE users
       SET qr_status = 'Active', qr_code = :qr_code, qr_generated_at = NOW()
       WHERE user_code = :resident_id AND role = 'resident'"
    );
    $stmt->execute([':resident_id' => $resident_id, ':qr_code' => $qr_code]);

    return [
      'success' => true,
      'message' => 'QR code generated and stored successfully',
      'qr_code' => $qr_code,
      'qr_image_url' => $qr_image_url ?? self::generateQRImageURL($qr_code)
    ];
  }

  /**
   * Log QR scan
   * @param string $resident_id
   * @param string $staff_id
   * @param string $scan_result
   * @return array Result
   */
  public static function logQRScan($resident_id, $staff_id, $scan_result = 'Verified') {
    $resident = self::getResidentByQRCode($resident_id);
    $stmt = barangay_db()->prepare(
      'INSERT INTO qr_scan_logs (resident_user_id, resident_code, resident_name, result, scanned_by, scanned_at)
       VALUES (:resident_user_id, :resident_code, :resident_name, :result, :scanned_by, NOW())'
    );
    $stmt->execute([
      ':resident_user_id' => $resident['db_id'] ?? null,
      ':resident_code' => $resident['id'] ?? $resident_id,
      ':resident_name' => $resident['name'] ?? 'Unknown resident',
      ':result' => in_array($scan_result, ['Verified', 'Not Found', 'Inactive'], true) ? $scan_result : 'Verified',
      ':scanned_by' => is_numeric($staff_id) ? (int) $staff_id : null,
    ]);

    return [
      'success' => true,
      'message' => 'Scan logged successfully',
      'log_id' => barangay_db()->lastInsertId(),
      'timestamp' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Update QR status
   * @param string $resident_id
   * @param string $new_status
   * @param string $reason
   * @return array Result
   */
  public static function updateQRStatus($resident_id, $new_status, $reason = '') {
    $valid_statuses = ['Active', 'Inactive'];
    
    if (!in_array($new_status, $valid_statuses)) {
      return [
        'success' => false,
        'message' => 'Invalid QR status'
      ];
    }

    $stmt = barangay_db()->prepare(
      'UPDATE users SET qr_status = :status, qr_generated_at = COALESCE(qr_generated_at, NOW()) WHERE user_code = :resident_id AND role = "resident"'
    );
    $stmt->execute([':status' => $new_status, ':resident_id' => $resident_id]);

    return [
      'success' => true,
      'message' => "QR status updated to {$new_status}",
      'new_status' => $new_status,
      'updated_at' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Get resident by QR code
   * @param string $qr_code
   * @return array|null Resident data or null
   */
  public static function getResidentByQRCode($qr_code) {
    $stmt = barangay_db()->prepare(
      "SELECT id AS db_id, user_code AS id, full_name AS name, mobile AS contact, household, purok, address, qr_status, qr_code
       FROM users
       WHERE role = 'resident' AND (qr_code = :qr_code OR user_code = :user_code)
       LIMIT 1"
    );
    $stmt->execute([':qr_code' => $qr_code, ':user_code' => $qr_code]);
    return $stmt->fetch() ?: null;
  }
}

class SMSAlertsHelper {
  private const HTTPSMS_SEND_URL = 'https://api.httpsms.com/v1/messages/send';

  public static function getSettings(): array {
    $stmt = barangay_db()->query('SELECT setting_key, setting_value FROM system_settings');
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
      $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
  }

  /**
   * Send SMS to resident
   * @param string $phone_number
   * @param string $message
   * @param string $staff_id
   * @param string $message_type
   * @return array Result
   */
  public static function sendSMS($phone_number, $message, $staff_id, $message_type = 'custom') {
    $settings = self::getSettings();
    $provider = strtolower(trim((string) ($settings['sms_provider'] ?? 'httpsms')));
    $enabled = (string) ($settings['sms_enabled'] ?? '1') === '1';
    $apiKey = trim((string) ($settings['sms_api_key'] ?? ''));
    $fromNumber = self::normalizePhilippineMobile((string) ($settings['sms_sender_number'] ?? ''));
    $senderName = self::cleanSenderName((string) ($settings['sms_sender_name'] ?? 'BRGY LUNA'));
    $toNumber = self::normalizePhilippineMobile((string) $phone_number);
    $message = trim((string) $message);
    if ($message === '') {
      return ['success' => false, 'message' => 'Message cannot be empty.', 'status' => 'Failed'];
    }
    if ($message_type !== 'Resident Account SMS' && $senderName !== '' && stripos($message, $senderName . ':') !== 0) {
      $message = $senderName . ': ' . $message;
    }

    if (!$enabled) {
      return [
        'success' => false,
        'message' => 'SMS alerts are disabled in System Settings.',
        'status' => 'Failed'
      ];
    }

    if ($provider !== 'httpsms') {
      return [
        'success' => false,
        'message' => 'Set SMS Provider to httpSMS in System Settings before sending.',
        'status' => 'Failed'
      ];
    }

    if ($apiKey === '' || $fromNumber === '') {
      return [
        'success' => false,
        'message' => 'Missing httpSMS API key or sender number in System Settings.',
        'status' => 'Failed'
      ];
    }

    if ($toNumber === '') {
      return [
        'success' => false,
        'message' => 'Invalid phone number format',
        'status' => 'Failed'
      ];
    }

    if ($message === '' || strlen($message) > 918) {
      return [
        'success' => false,
        'message' => 'Message is empty or too long for SMS.',
        'status' => 'Failed'
      ];
    }

    return self::sendViaHttpSms($apiKey, $fromNumber, $toNumber, $message, $message_type);
  }

  private static function sendViaHttpSms(string $apiKey, string $fromNumber, string $toNumber, string $message, string $messageType): array {
    if (!function_exists('curl_init')) {
      return [
        'success' => false,
        'message' => 'PHP cURL extension is required to send SMS.',
        'status' => 'Failed'
      ];
    }

    $payload = [
      'content' => $message,
      'from' => $fromNumber,
      'to' => $toNumber,
    ];

    $ch = curl_init(self::HTTPSMS_SEND_URL);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'x-api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
      ],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 25,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
      return [
        'success' => false,
        'message' => $curlError ?: 'Could not connect to httpSMS.',
        'uncertain' => true,
        'status' => 'Failed'
      ];
    }

    $decoded = json_decode((string) $body, true);
    $remoteMessage = is_array($decoded)
      ? (string) ($decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? '')
      : '';

    if ($statusCode < 200 || $statusCode >= 300) {
      return [
        'success' => false,
        'message' => $remoteMessage !== '' ? $remoteMessage : 'httpSMS rejected the message.',
        'status' => 'Failed',
        'http_status' => $statusCode,
        'uncertain' => $statusCode >= 500 || $statusCode === 408,
        'response' => $decoded ?: $body,
      ];
    }

    return [
      'success' => true,
      'message' => 'SMS sent through httpSMS.',
      'status' => 'Sent',
      'sms_id' => is_array($decoded) ? ($decoded['data']['id'] ?? $decoded['id'] ?? $decoded['message_id'] ?? null) : null,
      'provider' => 'httpSMS',
      'message_type' => $messageType,
      'sent_at' => date('Y-m-d H:i:s'),
      'response' => $decoded ?: $body,
    ];
  }

  /**
   * Send bulk SMS
   * @param array $recipients
   * @param string $message
   * @param string $staff_id
   * @param string $message_type
   * @return array Result
   */
  public static function sendBulkSMS($recipients, $message, $staff_id, $message_type = 'custom') {
    $results = [
      'total' => count($recipients),
      'sent' => 0,
      'failed' => 0,
      'logs' => []
    ];

    foreach ($recipients as $recipient) {
      $result = self::sendSMS($recipient['phone'], $message, $staff_id, $message_type);
      
      if ($result['success']) {
        $results['sent']++;
      } else {
        $results['failed']++;
      }

      $results['logs'][] = [
        'recipient' => $recipient['name'],
        'phone' => $recipient['phone'],
        'status' => $result['status'],
        'sms_id' => $result['sms_id'] ?? null
      ];
    }

    return $results;
  }

  /**
   * Send scheduled SMS
   * @param string $phone_number
   * @param string $message
   * @param string $scheduled_time
   * @param string $staff_id
   * @return array Result
   */
  public static function scheduleSMS($phone_number, $message, $scheduled_time, $staff_id) {
    // MySQL: INSERT INTO scheduled_sms (phone_number, message, scheduled_time, staff_id, status)
    // VALUES (?, ?, ?, ?, 'Scheduled')
    
    return [
      'success' => true,
      'message' => 'SMS scheduled successfully',
      'status' => 'Scheduled',
      'sms_id' => 'SCHEDULED-' . time(),
      'scheduled_for' => $scheduled_time
    ];
  }

  /**
   * Save SMS template
   * @param string $template_name
   * @param string $template_content
   * @param string $category
   * @return array Result
   */
  public static function saveTemplate($template_name, $template_content, $category) {
    // MySQL: INSERT INTO sms_templates (template_name, template_content, category, created_at)
    // VALUES (?, ?, ?, NOW())
    
    return [
      'success' => true,
      'message' => 'Template saved successfully',
      'template_id' => 'TEMPLATE-' . time(),
      'created_at' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Get SMS templates by category
   * @param string $category
   * @return array Templates
   */
  public static function getTemplatesByCategory($category) {
    // MySQL: SELECT * FROM sms_templates WHERE category = ?
    
    $templates = [
      'announcement' => [
        ['id' => 'template-1', 'name' => 'Barangay Assembly Notice', 'content' => 'All residents are invited to attend...'],
      ],
      'emergency' => [
        ['id' => 'template-2', 'name' => 'Flood Warning Alert', 'content' => 'ALERT: Severe flooding expected...'],
        ['id' => 'template-3', 'name' => 'Fire Alert', 'content' => 'ALERT: Fire reported in the area...'],
      ],
      'request_update' => [
        ['id' => 'template-6', 'name' => 'Certificate Ready', 'content' => 'Your certificate is ready...'],
        ['id' => 'template-7', 'name' => 'Request Approved', 'content' => 'Your request has been approved...'],
      ]
    ];

    return $templates[$category] ?? [];
  }

  /**
   * Get SMS logs with filters
   * @param array $filters
   * @return array Logs
   */
  public static function getSMSLogs($filters = []) {
    $stmt = barangay_db()->query(
      "SELECT sms_logs.recipient_name AS recipient,
              sms_logs.recipient_mobile AS phone,
              sms_logs.message,
              COALESCE(users.full_name, 'System') AS sender,
              DATE_FORMAT(sms_logs.created_at, '%Y-%m-%d %h:%i %p') AS sent_at,
              sms_logs.status
       FROM sms_logs
       LEFT JOIN users ON users.id = sms_logs.sent_by
       ORDER BY sms_logs.created_at DESC
       LIMIT 100"
    );
    return $stmt->fetchAll();
  }

  /**
   * Validate phone number format
   * @param string $phone
   * @return bool
   */
  private static function validatePhoneNumber($phone) {
    return self::normalizePhilippineMobile($phone) !== '';
  }

  public static function normalizePhilippineMobile(string $phone): string {
    $clean = preg_replace('/[^\d+]/', '', trim($phone));
    if ($clean === '') {
      return '';
    }

    if (preg_match('/^09\d{9}$/', $clean)) {
      return '+63' . substr($clean, 1);
    }

    if (preg_match('/^639\d{9}$/', $clean)) {
      return '+' . $clean;
    }

    if (preg_match('/^\+639\d{9}$/', $clean)) {
      return $clean;
    }

    return '';
  }

  public static function cleanSenderName(string $senderName): string {
    $senderName = strtoupper(trim($senderName));
    $senderName = preg_replace('/[^A-Z0-9 ]/', '', $senderName);
    $senderName = preg_replace('/\s+/', ' ', $senderName);
    return substr($senderName, 0, 20);
  }

  /**
   * Get SMS balance (for integrated SMS service)
   * @return array Balance info
   */
  public static function getSMSBalance() {
    // API call to SMS service provider
    // Replace this with an SMS provider balance API call in production
    
    return [
      'balance' => '1,250',
      'currency' => 'PHP',
      'last_updated' => date('Y-m-d H:i:s')
    ];
  }
}

// Automatic QR generation on resident creation (hook)
function generateQROnResidentCreation($resident_id, $resident_name) {
  $qr_code = QRVerificationHelper::generateQRCode($resident_id, $resident_name);
  $qr_image_url = QRVerificationHelper::generateQRImageURL($qr_code);
  return QRVerificationHelper::storeQRCode($resident_id, $qr_code, $qr_image_url);
}

// Send SMS on request status update (hook)
function sendSMSOnRequestStatusUpdate($resident_phone, $resident_name, $status) {
  $messages = [
    'approved' => "Good news! Your service request has been approved. Contact the barangay office for details. - Barangay Luna",
    'rejected' => "Your service request has been reviewed. Visit the barangay office for more information. - Barangay Luna",
    'ready' => "Your requested document is ready for pickup at the Barangay Office. - Barangay Luna"
  ];

  if (isset($messages[$status])) {
    return SMSAlertsHelper::sendSMS($resident_phone, $messages[$status], 'system', 'request_update');
  }

  return ['success' => false, 'message' => 'Invalid status'];
}
?>
