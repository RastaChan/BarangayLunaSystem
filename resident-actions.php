<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/document-issuance-workflow.php';
require_once __DIR__ . '/includes/resident-functions.php';
require_once __DIR__ . '/includes/profile-photo-helpers.php';
require_once __DIR__ . '/includes/feedback-helpers.php';
require_once __DIR__ . '/includes/document-fee-helpers.php';
require_once __DIR__ . '/includes/record-attachments.php';
require_once __DIR__ . '/includes/account-security.php';

$user = barangay_require_role('resident');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  resident_redirect('resident-dashboard', 'invalid');
}

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? 'resident-dashboard';

try {
  switch ($action) {
    case 'delete_submission':
      resident_check_submission_review($_POST, $_SESSION['resident_service_csrf'] ?? '');
      barangay_issuance_schema();
      $kind = (string) ($_POST['kind'] ?? '');
      $code = trim((string) ($_POST['record_code'] ?? ''));
      $redirect = $kind === 'complaint' ? 'my-complaints' : 'my-document-requests';
      barangay_db()->beginTransaction();
      resident_delete_own_submission($kind, $code, (int) $user['id']);
      resident_log($user, 'Deleted own ' . $kind . ' submission: ' . $code, $kind === 'complaint' ? 'Complaints' : 'Document Requests');
      barangay_db()->commit();
      resident_redirect($redirect, 'deleted');

    case 'update_account_profile':
      $mobile = trim($_POST['mobile'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE users SET mobile = :mobile WHERE id = :id AND role = 'resident'");
      $stmt->execute([':id' => $user['id'], ':mobile' => $mobile]);
      $requests = barangay_db()->prepare(
        "UPDATE document_requests
         SET contact_mobile = :mobile
         WHERE resident_user_id = :id
           AND archived_at IS NULL
           AND status IN ('Pending', 'Processing', 'Approved', 'Ready for Pickup')"
      );
      $requests->execute([':id' => $user['id'], ':mobile' => $mobile]);
      barangay_handle_profile_photo_upload($user);
      $user['contact'] = $mobile;
      barangay_sign_in($user);
      resident_log($user, 'Updated own contact number', 'Profile Settings');
      resident_redirect($redirect, 'updated');

    case 'request_document':
      resident_check_submission_review($_POST, $_SESSION['resident_service_csrf'] ?? '', true);
      barangay_attachment_schema();
      $attachment = barangay_read_attachment_upload($_FILES['supporting_document'] ?? []);
      barangay_ensure_document_request_detail_columns();
      barangay_ensure_document_fee_schema();
      $documentType = trim($_POST['document_type'] ?? '');
      $purpose = trim($_POST['purpose'] ?? '');
      $contact = trim($_POST['contact_mobile'] ?? '');
      $remarks = trim($_POST['remarks'] ?? '');
      $beneficiaryName = trim($_POST['beneficiary_name'] ?? '');
      $beneficiaryRelationship = trim($_POST['beneficiary_relationship'] ?? '');
      $residencyYears = ($_POST['residency_years'] ?? '') === '' ? null : max(0, (int) ($_POST['residency_years'] ?? 0));
      $partnerName = trim($_POST['partner_name'] ?? '');
      $cohabitationStartDate = trim($_POST['cohabitation_start_date'] ?? '');
      // Legacy column remains empty; beneficiary details never override resident gender.
      $residentGenderOverride = null;
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cohabitationStartDate) || strtotime($cohabitationStartDate) === false) {
        $cohabitationStartDate = null;
      }

      if ($documentType === '' || $purpose === '') {
        resident_redirect($redirect, 'missing');
      }
      $documentFee = barangay_document_fee_for_type($documentType);
      try {
        $copies = barangay_document_copies($_POST['copies'] ?? 1);
        $totalFee = barangay_document_total($documentFee ?? 0, $copies);
      } catch (InvalidArgumentException $e) {
        resident_redirect($redirect, 'invalid');
      }

      $customValues = barangay_custom_validate_values(barangay_custom_fields_for_type($documentType), $_POST['custom_fields'] ?? []);
      $recordCode = resident_next_code('document_requests', 'request_code', 'REQ');
      barangay_db()->beginTransaction();
      $stmt = barangay_db()->prepare(
        "INSERT INTO document_requests
          (request_code, resident_user_id, resident_name, contact_mobile, document_type, fee_amount, copies, unit_fee, purpose, remarks,
           beneficiary_name, beneficiary_relationship, residency_years, partner_name, cohabitation_start_date, resident_gender_override,
           status, requested_at)
         VALUES
          (:code, :resident_user_id, :resident_name, :contact_mobile, :document_type, :fee_amount, :copies, :unit_fee, :purpose, :remarks,
           :beneficiary_name, :beneficiary_relationship, :residency_years, :partner_name, :cohabitation_start_date, :resident_gender_override,
           'Pending', NOW())"
      );
      $stmt->execute([
        ':code' => $recordCode,
        ':resident_user_id' => $user['id'],
        ':resident_name' => $user['name'],
        ':contact_mobile' => $contact,
        ':document_type' => $documentType,
        ':fee_amount' => $totalFee,
        ':copies' => $copies,
        ':unit_fee' => $documentFee ?? 0,
        ':purpose' => $purpose,
        ':remarks' => $remarks,
        ':beneficiary_name' => $beneficiaryName,
        ':beneficiary_relationship' => $beneficiaryRelationship,
        ':residency_years' => $residencyYears,
        ':partner_name' => $partnerName,
        ':cohabitation_start_date' => $cohabitationStartDate,
        ':resident_gender_override' => $residentGenderOverride,
      ]);
      $saveCustom = barangay_db()->prepare('UPDATE document_requests SET custom_values_json=? WHERE request_code=?');
      $saveCustom->execute([json_encode($customValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $recordCode]);
      barangay_save_attachment('document', $recordCode, $user['id'], $attachment);
      resident_log($user, 'Submitted document request: ' . $documentType, 'Document Requests');
      barangay_db()->commit();
      barangay_safe_request_sms('received', $recordCode, (int) $user['id']);
      resident_redirect($redirect, 'created');

    case 'submit_complaint':
      resident_check_submission_review($_POST, $_SESSION['resident_service_csrf'] ?? '');
      barangay_attachment_schema();
      $attachment = barangay_read_attachment_upload($_FILES['complaint_evidence'] ?? []);
      $subject = trim($_POST['subject'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $details = trim($_POST['details'] ?? '');
      $location = trim($_POST['location'] ?? '');

      if ($subject === '' || $details === '') {
        resident_redirect($redirect, 'missing');
      }

      $recordCode = resident_next_code('complaints', 'complaint_code', 'CMP');
      barangay_db()->beginTransaction();
      $stmt = barangay_db()->prepare(
        "INSERT INTO complaints
          (complaint_code, resident_user_id, resident_name, subject, category, details, location, status, filed_at)
         VALUES
          (:code, :resident_user_id, :resident_name, :subject, :category, :details, :location, 'Open', NOW())"
      );
      $stmt->execute([
        ':code' => $recordCode,
        ':resident_user_id' => $user['id'],
        ':resident_name' => $user['name'],
        ':subject' => $subject,
        ':category' => $category,
        ':details' => $details,
        ':location' => $location,
      ]);
      barangay_save_attachment('complaint', $recordCode, $user['id'], $attachment);
      resident_log($user, 'Submitted complaint: ' . $subject, 'Complaints');
      barangay_db()->commit();
      resident_redirect($redirect, 'created');

    case 'submit_feedback':
      barangay_ensure_feedback_image_columns();
      if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['feedback_submit_csrf'] ?? '', $_POST['csrf']) || empty($_SESSION['feedback_submit_csrf'])) {
        throw new InvalidArgumentException('Please refresh the feedback form and try again.');
      }
      $rating = barangay_feedback_rating($_POST['rating'] ?? null);
      $category = trim($_POST['category'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $anonymous = isset($_POST['anonymous']) ? 1 : 0;

      if ($message === '') {
        resident_redirect($redirect, 'missing');
      }
      $image = barangay_store_feedback_image('feedback_image');

      $stmt = barangay_db()->prepare(
        "INSERT INTO feedbacks
          (feedback_code, resident_user_id, resident_name, category, message, image_file_name, image_path, is_anonymous, rating, status, submitted_at)
         VALUES
          (:code, :resident_user_id, :resident_name, :category, :message, :image_file_name, :image_path, :is_anonymous, :rating, 'Unread', NOW())"
      );
      $stmt->execute([
        ':code' => resident_next_code('feedbacks', 'feedback_code', 'FDB'),
        ':resident_user_id' => $user['id'],
        ':resident_name' => $anonymous ? 'Anonymous' : $user['name'],
        ':category' => $category,
        ':message' => $message,
        ':image_file_name' => $image['file_name'] ?? null,
        ':image_path' => $image['path'] ?? null,
        ':is_anonymous' => $anonymous,
        ':rating' => $rating,
      ]);
      resident_log($user, 'Submitted feedback', 'Feedbacks');
      resident_redirect($redirect, 'created');

    case 'update_profile':
      $mobile = trim($_POST['mobile'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE users SET mobile = :mobile WHERE id = :id AND role = 'resident'");
      $stmt->execute([':id' => $user['id'], ':mobile' => $mobile]);
      $requests = barangay_db()->prepare(
        "UPDATE document_requests
         SET contact_mobile = :mobile
         WHERE resident_user_id = :id
           AND archived_at IS NULL
           AND status IN ('Pending', 'Processing', 'Approved', 'Ready for Pickup')"
      );
      $requests->execute([':id' => $user['id'], ':mobile' => $mobile]);
      barangay_handle_profile_photo_upload($user);
      $user['contact'] = $mobile;
      barangay_sign_in($user);
      resident_log($user, 'Updated own contact number', 'My Profile');
      resident_redirect($redirect, 'updated');

    case 'change_password':
      if (empty($_SESSION['account_security_csrf']) || !hash_equals($_SESSION['account_security_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        resident_redirect($redirect, 'invalid');
      }
      $currentPassword = (string) ($_POST['current_password'] ?? '');
      $newPassword = (string) ($_POST['new_password'] ?? '');
      $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

      barangay_db()->beginTransaction();
      barangay_change_own_password($user, $currentPassword, $newPassword, $confirmPassword);
      barangay_db()->commit();
      session_regenerate_id(true);
      resident_redirect($redirect, 'password-updated');

    default:
      resident_redirect($redirect, 'invalid');
  }
} catch (Throwable $e) {
  if (barangay_db()->inTransaction()) barangay_db()->rollBack();
  if ($e instanceof InvalidArgumentException) {
    $_SESSION['resident_action_error'] = $e->getMessage();
    resident_redirect($redirect, 'validation-error');
  }
  resident_log($user, 'Failed resident action: ' . $action, 'System', 'Error');
  resident_redirect($redirect, 'invalid');
}
?>

