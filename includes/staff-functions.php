<?php
require_once __DIR__ . '/document-custom-fields.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/document-issuance-workflow.php';
require_once __DIR__ . '/resident-inactivity.php';
require_once __DIR__ . '/announcement-helpers.php';
require_once __DIR__ . '/document-template-helpers.php';
require_once __DIR__ . '/document-request-helpers.php';
require_once __DIR__ . '/feedback-helpers.php';
require_once __DIR__ . '/document-fee-helpers.php';
require_once __DIR__ . '/document-payment-helpers.php';
require_once __DIR__ . '/husay-helpers.php';

function staff_portal_page(): string {
  $user = function_exists('barangay_current_user') ? barangay_current_user() : null;
  return ($user['role'] ?? '') === 'admin' ? 'dashboard.php' : 'staff-dashboard.php';
}

function staff_view_url(string $view, array $params = []): string {
  $params = array_merge(['view' => $view], $params);
  return staff_portal_page() . '?' . http_build_query($params);
}

function staff_redirect(string $view, string $status = 'saved', string $anchor = ''): void {
  if (($_POST['action'] ?? '') === 'save_template' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'TemplateSetup') {
    $ok = $status === 'saved';
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    http_response_code($ok ? 200 : 422);
    $message = $_SESSION['configuration_error'] ?? match ($status) {
      'missing' => 'Choose a document type before saving.',
      'duplicate' => 'This template was changed by another save. Reopen Saved templates and try again.',
      'saved' => 'Template saved.',
      default => 'Check the document details and upload a valid DOCX file, then try again.',
    };
    unset($_SESSION['configuration_error']);
    echo json_encode(['ok'=>$ok, 'message'=>$message, 'redirect'=>staff_view_url($view, ['status'=>$status]) . ($anchor !== '' ? '#'.rawurlencode($anchor) : '')]);
    exit;
  }
  if (($_POST['action'] ?? '') === 'remove_prepared_document' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'PreparedRemoval') {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $_SESSION['configuration_error'] ?? 'The prepared entry could not be removed. Refresh the list and try again.']);
    unset($_SESSION['configuration_error']);
    exit;
  }
  if (($_POST['action'] ?? '') === 'release_issued_document' && ($_POST['popup'] ?? '') === '1') {
    $_SESSION['issuance_release_error'] = $_SESSION['configuration_error'] ?? 'Release was not saved. Check the payment and handover confirmation.';
    unset($_SESSION['configuration_error']);
    header('Location: staff-print-issued-document.php?id=' . (int) ($_POST['issued_id'] ?? 0) . '&popup=1', true, 303);
    exit;
  }
  if (($_POST['action'] ?? '') === 'issue_document' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'IssuancePopup') {
    $messages = ['print-generation-failed' => 'The printable document could not be generated. Check the document template and try again.', 'print-no-placeholders' => 'The selected template has no usable document placeholders.', 'duplicate' => 'This document number was already prepared. Resume its print view instead.', 'missing' => 'Complete the required applicant and document details.'];
    $message = $_SESSION['configuration_error'] ?? ($messages[$status] ?? 'The document could not be prepared. Check the details and try again.');
    unset($_SESSION['configuration_error']);
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
  }
  $location = staff_view_url($view, ['status' => $status]);
  if ($anchor !== '') {
    $location .= '#' . rawurlencode($anchor);
  }
  header('Location: ' . $location);
  exit;
}

function staff_status_message(): ?array {
  if (isset($_SESSION['registration_sms_flash'])) {
    $message = $_SESSION['registration_sms_flash'];
    unset($_SESSION['registration_sms_flash']);
    return $message;
  }
  if (isset($_SESSION['configuration_error'])) {
    $message = $_SESSION['configuration_error'];
    unset($_SESSION['configuration_error']);
    return ['error', $message];
  }
  $status = $_GET['status'] ?? '';
  $messages = [
    'saved' => ['success', 'Changes saved successfully.'],
    'created' => ['success', 'Record created successfully.'],
    'updated' => ['success', 'Record updated successfully.'],
    'sent' => ['success', 'SMS record saved successfully.'],
    'sms-failed' => ['error', 'SMS was saved as Failed. Check the httpSMS API key, sender number, recipient number, and internet connection.'],
    'issued' => ['success', 'Issued document record saved successfully.'],
    'print-generation-failed' => ['error', 'The document was not issued. Upload an active Word template and check that document generation is working, then try printing again.'],
    'print-no-placeholders' => ['error', 'The document was not issued. Add the supported placeholders to the active Word template before printing.'],
    'fee-saved' => ['success', 'Document fee saved successfully.'],
    'payment-recorded' => ['success', 'Walk-in payment recorded successfully.'],
    'deleted' => ['success', 'Record removed successfully.'],
    'docx-failed' => ['error', 'The record was saved, but the Word file could not be generated. Check that the active template is a valid .docx file and contains the supported placeholders.'],
    'docx-no-placeholders' => ['error', 'The record was saved, but the active Word template has no supported placeholders to auto-fill. Edit the DOCX and add tags like {{resident_name}} and {{purpose}}.'],
    'template-no-placeholders' => ['error', 'Template saved, but the uploaded Word file has no supported placeholders yet. Add tags like {{resident_name}} and {{purpose}} so issued documents can auto-fill.'],
    'verified' => ['success', 'Resident verification saved successfully.'],
    'missing' => ['error', 'Please complete the required fields.'],
    'invalid' => ['error', 'The selected action could not be completed.'],
    'default-document-type' => ['error', 'Default document types cannot be removed. Delete or edit the template instead.'],
    'duplicate' => ['error', 'That username or code is already used.'],
  ];

  return $messages[$status] ?? null;
}

function staff_log(array $user, string $action, string $module, string $status = 'Success'): void {
  $stmt = barangay_db()->prepare(
    'INSERT INTO audit_logs (user_id, user_name, user_role, action, module, status, created_at)
     VALUES (:user_id, :user_name, :user_role, :action, :module, :status, NOW())'
  );
  $stmt->execute([
    ':user_id' => $user['id'] ?? null,
    ':user_name' => $user['name'] ?? 'Staff',
    ':user_role' => $user['title'] ?? 'Staff',
    ':action' => $action,
    ':module' => $module,
    ':status' => $status,
  ]);
}

function staff_next_code(string $table, string $column, string $prefix): string {
  $stmt = barangay_db()->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :prefix ORDER BY id DESC LIMIT 1");
  $stmt->execute([':prefix' => $prefix . '-%']);
  $last = $stmt->fetchColumn();
  $next = 1;

  if ($last && preg_match('/(\d+)$/', $last, $matches)) {
    $next = ((int) $matches[1]) + 1;
  }

  return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function staff_fetch_all(string $sql, array $params = []): array {
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function staff_count(string $table, string $where = '1 = 1', array $params = []): int {
  $stmt = barangay_db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

function staff_default_document_types(): array {
  return [
    'Certificate of Residency',
    'Certificate of Indigency',
    'Barangay Certification',
    'Other Barangay Certificate',
  ];
}

function staff_retired_document_types(): array {
  return [
    'Barangay Clearance',
    'Business Clearance',
  ];
}

function staff_document_type_key(string $type): string {
  return strtolower(preg_replace('/\s+/', ' ', trim($type)));
}

function staff_is_retired_document_type(string $type): bool {
  $key = staff_document_type_key($type);
  foreach (staff_retired_document_types() as $retiredType) {
    if (staff_document_type_key($retiredType) === $key) {
      return true;
    }
  }
  return false;
}

function staff_preferred_document_type_label(string $type): string {
  $type = preg_replace('/\s+/', ' ', trim($type));
  if ($type === '') {
    return '';
  }

  $key = staff_document_type_key($type);
  foreach (staff_default_document_types() as $defaultType) {
    if (staff_document_type_key($defaultType) === $key) {
      return $defaultType;
    }
  }

  if (strtoupper($type) === $type) {
    $words = explode(' ', ucwords(strtolower($type)));
    $minorWords = ['of', 'and', 'for', 'the', 'or'];
    foreach ($words as $index => $word) {
      $lower = strtolower($word);
      if ($index > 0 && in_array($lower, $minorWords, true)) {
        $words[$index] = $lower;
      }
    }
    return implode(' ', $words);
  }
  return $type;
}

function staff_canonical_document_type(string $type): string {
  $type = staff_preferred_document_type_label($type);
  if ($type === '') {
    return '';
  }

  $key = staff_document_type_key($type);
  try {
    $rows = staff_fetch_all(
      "SELECT DISTINCT document_type
       FROM document_templates
       WHERE document_type IS NOT NULL AND document_type <> ''"
    );
    foreach ($rows as $row) {
      $existing = staff_preferred_document_type_label((string) ($row['document_type'] ?? ''));
      if ($existing !== '' && staff_document_type_key($existing) === $key) {
        return $existing;
      }
    }
  } catch (Throwable $e) {
    // Use the cleaned label when templates are not available yet.
  }

  return $type;
}

function staff_document_types(): array {
  $types = staff_default_document_types();
  try {
    $rows = staff_fetch_all(
      "SELECT DISTINCT document_type
       FROM document_templates
       WHERE document_type IS NOT NULL AND document_type <> ''
       ORDER BY document_type"
    );
    foreach ($rows as $row) {
      $type = staff_preferred_document_type_label((string) ($row['document_type'] ?? ''));
      if ($type !== '' && !staff_is_retired_document_type($type)) {
        $types[] = $type;
      }
    }
  } catch (Throwable $e) {
    // Keep the default document types available during first-time setup.
  }

  $unique = [];
  foreach ($types as $type) {
    $key = staff_document_type_key($type);
    if ($key === '' || isset($unique[$key]) || staff_is_retired_document_type($type)) {
      continue;
    }
    $unique[$key] = staff_preferred_document_type_label($type);
  }
  $ordered = array_values($unique);
  usort($ordered, static function (string $left, string $right): int {
    $leftIsOther = strtolower($left) === 'other barangay certificate';
    $rightIsOther = strtolower($right) === 'other barangay certificate';
    if ($leftIsOther && !$rightIsOther) return 1;
    if (!$leftIsOther && $rightIsOther) return -1;
    return strcasecmp($left, $right);
  });
  return $ordered;
}

function staff_generate_resident_qr_value(string $residentCode): string {
  return 'LUNA|' . strtoupper($residentCode) . '|' . date('YmdHis');
}

function staff_qr_image_url(string $qrCode, int $size = 220): string {
  return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($qrCode);
}

function staff_seed_document_templates(int $userId): void {
  staff_ensure_document_template_file_columns();

  if (staff_count('document_templates') > 0) {
    return;
  }

  $stmt = barangay_db()->prepare(
    'INSERT INTO document_templates (name, document_type, effective_year, content, status, updated_by)
     VALUES (:name, :type, :year, :content, :status, :updated_by)'
  );

  foreach (staff_default_document_types() as $type) {
    $stmt->execute([
      ':name' => $type . ' 2026 Official Format',
      ':type' => $type,
      ':year' => date('Y'),
      ':content' => 'This is to certify that {{resident_name}}, {{age}} years old, {{civil_status}}, residing at {{resident_address}}, {{barangay_name}}, is issued this document for {{purpose}} on {{date_issued}}. Document No. {{document_number}}.',
      ':status' => $type === 'Barangay Certification' ? 'Active' : 'Draft',
      ':updated_by' => $userId,
    ]);
  }
}

function staff_ensure_document_template_file_columns(): void {
  barangay_custom_document_schema();
  $columns = staff_fetch_all(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'document_templates'
       AND COLUMN_NAME IN ('template_file_name', 'template_file_path')"
  );
  $existing = array_column($columns, 'COLUMN_NAME');

  if (!in_array('template_file_name', $existing, true)) {
    barangay_db()->exec('ALTER TABLE document_templates ADD COLUMN template_file_name VARCHAR(255) DEFAULT NULL AFTER content');
  }
  if (!in_array('template_file_path', $existing, true)) {
    barangay_db()->exec('ALTER TABLE document_templates ADD COLUMN template_file_path VARCHAR(255) DEFAULT NULL AFTER template_file_name');
  }
}

function staff_load_dashboard_data(array $user): array {
  husay_schema();
  barangay_issuance_schema();
  resident_enforce_inactivity_rule();
  announcement_publish_due();
  barangay_ensure_document_request_detail_columns();
  barangay_ensure_feedback_image_columns();
  barangay_ensure_document_fee_schema();
  barangay_ensure_document_payment_schema();
  staff_seed_document_templates((int) $user['id']);
  staff_ensure_issued_document_file_columns();

  $residentSql = "SELECT
      id AS db_id,
      user_code AS id,
      full_name AS name,
      username,
      mobile AS contact,
      birthdate,
      profile_photo_path,
      COALESCE(address, '') AS address,
      COALESCE(purok, '') AS purok,
      COALESCE(household, '') AS household,
      TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
      COALESCE(gender, '') AS gender,
      COALESCE(civil_status, '') AS civil_status,
      status,
      GREATEST(COALESCE(last_active_at, last_login_at), COALESCE(last_login_at, last_active_at)) AS last_used_at,
      inactive_reason,
      inactivated_at,
      reactivated_at,
      verification_status,
      qr_status,
      qr_code,
      qr_generated_at,
      DATE(created_at) AS date,
      COALESCE((SELECT full_name FROM users staff WHERE staff.id = users.registered_by), 'System') AS registered_by,
      CASE WHEN status = 'Active' THEN 'Eligible' ELSE 'Review needed' END AS eligible
    FROM users
    WHERE role = 'resident'
    ORDER BY created_at DESC";

  $residentRecords = staff_fetch_all($residentSql);

  $documentTemplates = staff_fetch_all(
    "SELECT document_templates.id, name, document_type AS type, effective_year AS year,
            COALESCE(users.full_name, 'System') AS updated_by,
            DATE(document_templates.updated_at) AS updated_at,
            document_templates.status,
            COALESCE(content, '') AS content,
            COALESCE(template_file_name, '') AS template_file_name,
            COALESCE(template_file_path, '') AS template_file_path
     FROM document_templates
     LEFT JOIN users ON users.id = document_templates.updated_by
     ORDER BY FIELD(document_templates.status, 'Active', 'Draft', 'Archived'), document_templates.updated_at DESC"
  );

  foreach ($documentTemplates as &$template) {
    $template['type'] = staff_preferred_document_type_label((string) ($template['type'] ?? ''));
    $docxPreview = '';
    $docxPreviewHtml = '';
    if (!empty($template['template_file_path'])) {
      $docxPreview = staff_extract_docx_text_from_template((string) $template['template_file_path']);
      $docxPreviewHtml = staff_extract_docx_html_from_template((string) $template['template_file_path']);
    }
    $template['preview_content'] = trim($docxPreview) !== ''
      ? $docxPreview
      : (string) ($template['content'] ?? '');
    $template['preview_html'] = $docxPreviewHtml;
  }
  unset($template);

  $documentTypes = staff_document_types();
  $documentFees = barangay_document_fee_map();

  return [
    'documentTypes' => $documentTypes,
    'documentFees' => $documentFees,
    'residentRecords' => $residentRecords,
    'residentRegistrations' => $residentRecords,
    'residentRegistrationRequirements' => [
      ['name' => 'Valid Government-Issued ID', 'type' => 'Required', 'status' => 'Not Submitted', 'remarks' => ''],
      ['name' => 'Proof of Residence', 'type' => 'Required', 'status' => 'Not Submitted', 'remarks' => ''],
      ['name' => 'PSA Birth Certificate', 'type' => 'Required', 'status' => 'Not Submitted', 'remarks' => ''],
      ['name' => 'Recent 1x1 or 2x2 ID Photo', 'type' => 'Optional', 'status' => 'Not Submitted', 'remarks' => ''],
    ],
    'documentTemplates' => $documentTemplates,
    'staffRequests' => barangay_document_requests(),
    'staffComplaints' => staff_fetch_all(
      "SELECT complaint_code AS id, resident_name AS resident, subject, category, details, location, COALESCE(NULLIF(contact_mobile, ''), (SELECT mobile FROM users WHERE users.id=complaints.resident_user_id), '') AS contact_mobile, intake_source, COALESCE(remarks, '') AS remarks, DATE(filed_at) AS date,
              CASE WHEN status = 'Open' THEN 'High' WHEN status = 'In Progress' THEN 'Medium' ELSE 'Low' END AS priority,
              status
       FROM complaints
       WHERE archived_at IS NULL
       ORDER BY filed_at DESC"
    ),
    'staffFeedbacks' => staff_fetch_all(
      'SELECT feedback_code AS id, resident_name AS resident, COALESCE(category, "") AS category, message, rating,
              COALESCE(image_file_name, "") AS image_file_name,
              COALESCE(image_path, "") AS image_path,
              COALESCE(is_anonymous, 0) AS is_anonymous,
              DATE(submitted_at) AS date, status
       FROM feedbacks
       WHERE archived_at IS NULL
       ORDER BY submitted_at DESC'
    ),
    'staffAnnouncements' => staff_fetch_all(
      'SELECT id, title, body, post_type, event_at, event_location, DATE(publish_date) AS date, status,
              DATE_FORMAT(COALESCE(publish_at, CONCAT(publish_date, " 00:00:00")), "%H:%i") AS publish_time,
              COALESCE(photo_file_name, "") AS photo_file_name,
              COALESCE(photo_path, "") AS photo_path
       FROM announcements
       WHERE archived_at IS NULL
       ORDER BY publish_date DESC, created_at DESC'
    ),
    'smsHistory' => staff_fetch_all(
      'SELECT recipient_name AS recipient, message, DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS date, status
       FROM sms_logs
       ORDER BY created_at DESC'
    ),
    'qrScanLogs' => staff_fetch_all(
      "SELECT DATE_FORMAT(qr_scan_logs.scanned_at, '%Y-%m-%d %H:%i') AS date,
              qr_scan_logs.resident_name AS resident,
              qr_scan_logs.result,
              COALESCE(users.full_name, 'System') AS staff
       FROM qr_scan_logs
       LEFT JOIN users ON users.id = qr_scan_logs.scanned_by
       ORDER BY qr_scan_logs.scanned_at DESC"
    ),
    'issuedDocuments' => staff_fetch_all(
      'SELECT id, document_number, resident_name, document_type, purpose, fee_amount, copies, unit_fee, issuance_status,
              COALESCE(generated_file_name, "") AS generated_file_name,
              COALESCE(generated_file_path, "") AS generated_file_path,
              DATE(issued_at) AS date
       FROM issued_documents
       WHERE issuance_status = "Released"
       ORDER BY issued_at DESC'
    ),
    'preparedDocuments' => staff_fetch_all('SELECT id, document_number, resident_name, document_type FROM issued_documents WHERE issuance_status = "Ready for Pickup" ORDER BY issued_at DESC'),
    'walkInPayments' => staff_fetch_all(
      'SELECT document_payments.id, document_payments.payment_code,
              COALESCE(document_payments.request_code, "") AS request_code,
              COALESCE(document_payments.document_number, "") AS document_number,
              document_payments.resident_name, document_payments.document_type,
              document_payments.amount_due, document_payments.amount_paid, document_payments.payment_method,
              COALESCE(document_payments.receipt_number, "") AS receipt_number,
              document_payments.status,
              DATE_FORMAT(document_payments.received_at, "%Y-%m-%d %H:%i") AS received_at,
              DATE_FORMAT(document_payments.created_at, "%Y-%m-%d %H:%i") AS created_at,
              COALESCE(users.full_name, "") AS received_by
       FROM document_payments
       LEFT JOIN users ON users.id = document_payments.received_by
       ORDER BY FIELD(document_payments.status, "Pending", "Paid", "Voided"), document_payments.created_at DESC'
    ),
    'activityLogs' => staff_fetch_all(
      'SELECT DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") AS date, action, module, status
       FROM audit_logs
       WHERE user_id = :user_id
       ORDER BY created_at DESC
       LIMIT 50',
      [':user_id' => $user['id']]
    ),
    'staffStats' => [
      'pending_requests' => staff_count('document_requests', "status IN ('Pending', 'Processing') AND archived_at IS NULL"),
      'issued_today' => staff_count('issued_documents', "issuance_status = 'Released' AND DATE(issued_at) = CURDATE()"),
      'open_complaints' => staff_count('complaints', "status IN ('Open', 'In Progress') AND archived_at IS NULL"),
      'verified_today' => staff_count('users', "role = 'resident' AND DATE(created_at) = CURDATE()"),
    ],
    'serviceReports' => [
      'issued_today' => staff_count('issued_documents', "issuance_status = 'Released' AND DATE(issued_at) = CURDATE()"),
      'issued_month' => staff_count('issued_documents', "issuance_status = 'Released' AND YEAR(issued_at) = YEAR(CURDATE()) AND MONTH(issued_at) = MONTH(CURDATE())"),
      'processed_week' => staff_count('document_requests', "status IN ('Approved', 'Ready for Pickup', 'Released') AND YEARWEEK(requested_at, 1) = YEARWEEK(CURDATE(), 1)"),
      'complaints_week' => staff_count('complaints', "YEARWEEK(filed_at, 1) = YEARWEEK(CURDATE(), 1) AND status <> 'Open'"),
    ],
    'serviceSummary' => staff_fetch_all(
      "SELECT document_type AS service,
              SUM(DATE(issued_at) = CURDATE()) AS today,
              SUM(YEAR(issued_at) = YEAR(CURDATE()) AND MONTH(issued_at) = MONTH(CURDATE())) AS month
       FROM issued_documents
       WHERE issuance_status = 'Released'
       GROUP BY document_type
       ORDER BY document_type"
    ),
  ];
}
?>
