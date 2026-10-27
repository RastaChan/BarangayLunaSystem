<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff-functions.php';
require_once __DIR__ . '/includes/qr-sms-helpers.php';
require_once __DIR__ . '/includes/profile-photo-helpers.php';
require_once __DIR__ . '/includes/document-template-helpers.php';
require_once __DIR__ . '/includes/feedback-helpers.php';
require_once __DIR__ . '/includes/document-fee-helpers.php';
require_once __DIR__ . '/includes/document-payment-helpers.php';
require_once __DIR__ . '/includes/resident-locations.php';
require_once __DIR__ . '/includes/registration-requirements.php';
require_once __DIR__ . '/includes/resident-account-notifications.php';
require_once __DIR__ . '/includes/resident-service-tools.php';

$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['staff', 'admin'], true)) {
  header('Location: login.php?error=login_required');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  staff_redirect('staff-dashboard', 'invalid');
}

$_POST = barangay_formal_inputs($_POST);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? 'staff-dashboard';

try {
  switch ($action) {
    case 'update_account_profile':
      $mobile = trim($_POST['mobile'] ?? '');
      $stmt = barangay_db()->prepare("UPDATE users SET mobile = :mobile WHERE id = :id AND role = 'staff'");
      $stmt->execute([':id' => $user['id'], ':mobile' => $mobile]);
      barangay_handle_profile_photo_upload($user);
      $user['contact'] = $mobile;
      barangay_sign_in($user);
      staff_log($user, 'Updated own profile contact number', 'Profile Settings');
      staff_redirect($redirect, 'saved');

    case 'create_resident':
      if (empty($_SESSION['locations_csrf']) || !hash_equals($_SESSION['locations_csrf'], (string) ($_POST['locations_csrf'] ?? ''))) throw new InvalidArgumentException('Refresh the registration form and try again.');
      $residentLocation = resident_location_validate((int) ($_POST['district_id'] ?? 0), (int) ($_POST['purok_id'] ?? 0));
      registration_requirements_schema();
      $initialRequirements = registration_requirement_inputs($_POST, $_FILES);
      barangay_validate_password((string) ($_POST['password'] ?? ''));
      $first = trim($_POST['first_name'] ?? '');
      $middle = isset($_POST['no_middle_name']) ? '' : trim($_POST['middle_name'] ?? '');
      $last = trim($_POST['last_name'] ?? '');
      $suffix = trim($_POST['suffix'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $password = (string) ($_POST['password'] ?? '');
      $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');
      // Staff/Admin completes resident validation during registration.
      $status = 'Active';
      $verificationStatus = 'Verified';
      $qrStatus = 'Active';

      if ($first === '' || $last === '' || strlen($password) < 8 || $password !== $confirmPassword) {
        staff_redirect($redirect, 'missing');
      }

      $fullName = trim($first . ' ' . ($middle !== '' ? $middle . ' ' : '') . $last . ($suffix !== '' ? ' ' . $suffix : ''));
      $allowedStatus = ['Active', 'Inactive', 'Pending'];
      $allowedVerification = ['Pending', 'Verified', 'Rejected'];
      $allowedQr = ['Inactive', 'Active'];

      barangay_require_duplicate_review($_POST);
      $code = staff_next_code('users', 'user_code', 'RES');
      if ($username === '') {
        $username = strtolower($code) . '@luna.gov';
      }
      $qrCode = in_array($qrStatus, $allowedQr, true) && $qrStatus === 'Active' ? staff_generate_resident_qr_value($code) : null;

      barangay_db()->beginTransaction();

      $stmt = barangay_db()->prepare(
        "INSERT INTO users
          (user_code, role, full_name, position_title, username, mobile, address, purok, district_id, purok_id, household, birthdate, gender, civil_status, verification_status, qr_status, qr_code, qr_generated_at, registered_by, password_hash, status)
         VALUES
          (:code, 'resident', :full_name, 'Resident', :username, :mobile, :address, :purok, :district_id, :purok_id, :household, :birthdate, :gender, :civil_status, :verification_status, :qr_status, :qr_code, :qr_generated_at, :registered_by, :password_hash, :status)"
      );
      $stmt->execute([
        ':code' => $code,
        ':full_name' => $fullName,
        ':username' => $username,
        ':mobile' => $mobile,
        ':address' => trim($_POST['address'] ?? ''),
        ':purok' => $residentLocation['purok'],
        ':district_id' => $residentLocation['district_id'],
        ':purok_id' => $residentLocation['purok_id'],
        ':household' => trim($_POST['household'] ?? ''),
        ':birthdate' => ($_POST['birthdate'] ?? '') ?: null,
        ':gender' => trim($_POST['gender'] ?? ''),
        ':civil_status' => trim($_POST['civil_status'] ?? ''),
        ':verification_status' => in_array($verificationStatus, $allowedVerification, true) ? $verificationStatus : 'Pending',
        ':qr_status' => in_array($qrStatus, $allowedQr, true) ? $qrStatus : 'Inactive',
        ':qr_code' => $qrCode,
        ':qr_generated_at' => $qrCode ? date('Y-m-d H:i:s') : null,
        ':registered_by' => $user['id'],
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':status' => in_array($status, $allowedStatus, true) ? $status : 'Pending',
      ]);
      $newResidentId = (int) barangay_db()->lastInsertId();
      registration_save_initial_requirements($newResidentId, $initialRequirements, (int) $user['id']);
      staff_log($user, 'Created resident account and saved requirements: ' . $fullName
        . (($_POST['duplicate_reviewed'] ?? '') === '1' ? ' (staff confirmed a different person after duplicate review)' : ''), 'Resident Account Registration');
      barangay_db()->commit();
      resident_account_notification_flash($code, (int) $user['id']);
      staff_redirect($redirect, 'created');

    case 'generate_resident_qr':
      $id = trim($_POST['id'] ?? '');
      $accountBefore = resident_account_notification_snapshot($id);
      if ($id === '') {
        staff_redirect($redirect, 'missing');
      }
      $qrCode = staff_generate_resident_qr_value($id);
      $stmt = barangay_db()->prepare(
        "UPDATE users
         SET qr_status = 'Active', qr_code = :qr_code, qr_generated_at = NOW()
         WHERE user_code = :id AND role = 'resident'"
      );
      $stmt->execute([':id' => $id, ':qr_code' => $qrCode]);
      staff_log($user, 'Generated QR code for resident: ' . $id, 'Resident Account Registration');
      if ($accountBefore) resident_account_notification_flash($id, (int) $user['id'], $accountBefore);
      staff_redirect($redirect, 'updated');

    case 'update_resident_status':
      $id = trim($_POST['id'] ?? '');
      $accountBefore = resident_account_notification_snapshot($id);
      $status = $_POST['status'] ?? 'Pending';
      $verificationStatus = 'Verified';
      $qrStatus = $_POST['qr_status'] ?? 'Inactive';
      if ($id === '') {
        staff_redirect($redirect, 'missing');
      }
      $normalizedStatus = in_array($status, ['Active', 'Inactive', 'Pending'], true) ? $status : 'Pending';
      resident_require_inactivity_review($id);
      $normalizedVerification = in_array($verificationStatus, ['Pending', 'Verified', 'Rejected'], true) ? $verificationStatus : 'Pending';
      $normalizedQr = in_array($qrStatus, ['Inactive', 'Active'], true) ? $qrStatus : 'Inactive';
      if ($normalizedQr === 'Active') {
        $stmt = barangay_db()->prepare(
          "UPDATE users
           SET reactivated_at = IF(:activation_status = 'Active' AND status <> 'Active', NOW(), reactivated_at),
               inactive_reason = NULL, inactivated_at = IF(:inactivation_status = 'Inactive', NOW(), NULL), status = :status,
               verification_status = :verification_status,
               qr_status = 'Active',
               qr_code = COALESCE(qr_code, :qr_code),
               qr_generated_at = COALESCE(qr_generated_at, NOW())
           WHERE user_code = :id AND role = 'resident'"
        );
        $stmt->execute([
          ':id' => $id,
          ':status' => $normalizedStatus,
          ':activation_status' => $normalizedStatus,
          ':inactivation_status' => $normalizedStatus,
          ':verification_status' => $normalizedVerification,
          ':qr_code' => staff_generate_resident_qr_value($id),
        ]);
      } else {
        $stmt = barangay_db()->prepare(
          "UPDATE users
           SET reactivated_at = IF(:activation_status = 'Active' AND status <> 'Active', NOW(), reactivated_at),
               inactive_reason = NULL, inactivated_at = IF(:inactivation_status = 'Inactive', NOW(), NULL),
               status = :status, verification_status = :verification_status, qr_status = 'Inactive'
           WHERE user_code = :id AND role = 'resident'"
        );
        $stmt->execute([
          ':id' => $id,
          ':status' => $normalizedStatus,
          ':activation_status' => $normalizedStatus,
          ':inactivation_status' => $normalizedStatus,
          ':verification_status' => $normalizedVerification,
        ]);
      }
      staff_log($user, 'Updated resident status: ' . $id, 'Resident Account Registration');
      if ($accountBefore) resident_account_notification_flash($id, (int) $user['id'], $accountBefore);
      staff_redirect($redirect, 'updated');

    case 'verify_resident':
      // Retired action: registration now activates accounts automatically.
      staff_redirect('resident-account-registration', 'invalid');

    case 'create_document_request':
      $resident = trim($_POST['resident_name'] ?? '');
      $type = staff_canonical_document_type(trim($_POST['document_type'] ?? ''));
      if ($resident === '' || $type === '') {
        staff_redirect($redirect, 'missing');
      }
      barangay_ensure_document_fee_schema();
      $documentFee = barangay_document_fee_for_type($type) ?? 0.0;
      $stmt = barangay_db()->prepare(
        "INSERT INTO document_requests (request_code, resident_name, document_type, fee_amount, purpose, status, requested_at)
         VALUES (:code, :resident, :type, :fee_amount, :purpose, 'Processing', NOW())"
      );
      $stmt->execute([
        ':code' => staff_next_code('document_requests', 'request_code', 'REQ'),
        ':resident' => $resident,
        ':type' => $type,
        ':fee_amount' => $documentFee,
        ':purpose' => trim($_POST['purpose'] ?? ''),
      ]);
      staff_log($user, 'Created document request for: ' . $resident, 'Document Requests');
      staff_redirect($redirect, 'created');

    case 'update_document_status':
      staff_redirect($redirect, 'invalid');

    case 'document_request_event':
      if (empty($_SESSION['document_request_csrf']) || !hash_equals($_SESSION['document_request_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        staff_redirect('issue-documents', 'invalid');
      }
      $id = trim((string) ($_POST['id'] ?? ''));
      $event = (string) ($_POST['event'] ?? '');
      if (!in_array($event, ['prepare', 'pickup', 'reject'], true)) staff_redirect('issue-documents', 'invalid');
      if ($event === 'pickup' && ($_POST['handed_over'] ?? '') !== '1') staff_redirect('issue-documents', 'invalid');
      try {
        if ($event === 'pickup') {
          barangay_issuance_schema();
          $issued = barangay_db()->prepare("SELECT id FROM issued_documents WHERE request_code = ? AND issuance_status IN ('Ready for Pickup', 'Released') ORDER BY id DESC LIMIT 1");
          $issued->execute([$id]); $issuedId = (int) $issued->fetchColumn();
          if ($issuedId) $changed = barangay_issuance_release($issuedId, (int) $user['id'], $_POST);
          else {
            $paymentId = barangay_create_payment_for_released_request($id, (int) $user['id']);
            $payment = barangay_find_document_payment($id, null);
            if ((float) ($payment['amount_due'] ?? 0) > 0 && (($payment['status'] ?? '') !== 'Paid' || (float) $payment['amount_paid'] < (float) $payment['amount_due'])) throw new InvalidArgumentException('Record the outstanding payment in Walk-in Payments before release.');
            $changed = barangay_document_request_event($id, 'pickup');
          }
        } else $changed = barangay_document_request_event($id, $event, (string) ($_POST['reason'] ?? ''));
      } catch (InvalidArgumentException $error) {
        $_SESSION['configuration_error'] = $error->getMessage();
        header('Location: ' . staff_view_url('issue-documents', ['requests' => 1, 'status' => 'validation-error']), true, 303);
        exit;
      }
      if ($changed) {
        staff_log($user, 'Document request ' . $id . ': ' . $event, 'Document Requests');
        if ($event === 'reject') barangay_safe_request_sms('rejected', $id, (int) $user['id']);
        if ($event === 'pickup') barangay_safe_request_sms('released', $id, (int) $user['id']);
      }
      $params = $event === 'prepare' ? ['request' => $id] : ['requests' => 1, 'status' => 'updated'];
      header('Location: ' . staff_view_url('issue-documents', $params), true, 303);
      exit;

    case 'save_document_fee':
      if (($user['role'] ?? '') !== 'admin') throw new InvalidArgumentException('Only Admin can change document pricing.');
      barangay_ensure_document_fee_schema();
      $type = staff_canonical_document_type(trim($_POST['document_type'] ?? ''));
      $amountRaw = trim($_POST['fee_amount'] ?? '');
      if ($type === '' || $amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
        staff_redirect($redirect, 'invalid');
      }
      barangay_save_document_fee($type, (float) $amountRaw, (int) $user['id']);
      staff_log($user, 'Set document fee for ' . $type . ' to PHP ' . number_format((float) $amountRaw, 2), 'Document Fees');
      staff_redirect($redirect, 'fee-saved', 'document-fees');

    case 'save_document_fees':
      if (($user['role'] ?? '') !== 'admin') throw new InvalidArgumentException('Only Admin can change document pricing.');
      if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['document_fee_csrf']) || !hash_equals($_SESSION['document_fee_csrf'], $_POST['csrf'])) {
        staff_redirect($redirect, 'invalid');
      }
      $selected = $_POST['document_types'] ?? [];
      $amountRaw = is_string($_POST['fee_amount'] ?? null) ? trim($_POST['fee_amount']) : '';
      try {
        if (!is_array($selected)) throw new InvalidArgumentException('Invalid selection');
        $types = barangay_validate_document_fee_batch($selected, staff_document_types(), $amountRaw);
      } catch (InvalidArgumentException $error) {
        staff_redirect($redirect, 'invalid');
      }
      barangay_ensure_document_fee_schema();
      $feeDb = barangay_db();
      $feeDb->beginTransaction();
      try {
        foreach ($types as $type) barangay_save_document_fee($type, (float) $amountRaw, (int) $user['id']);
        staff_log($user, 'Set document fees to PHP ' . number_format((float) $amountRaw, 2) . ': ' . implode(', ', $types), 'Document Fees');
        $feeDb->commit();
      } catch (Throwable $error) {
        if ($feeDb->inTransaction()) $feeDb->rollBack();
        throw $error;
      }
      staff_redirect($redirect, 'fee-saved', 'document-fees');

    case 'record_walk_in_payment':
      $paymentId = (int) ($_POST['payment_id'] ?? 0);
      $amountRaw = trim($_POST['amount_paid'] ?? '');
      $receiptNumber = trim($_POST['receipt_number'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      if ($paymentId < 1 || $amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
        staff_redirect($redirect, 'invalid');
      }
      if (!barangay_record_walk_in_payment($paymentId, (float) $amountRaw, $receiptNumber, $notes, (int) $user['id'])) {
        staff_redirect($redirect, 'invalid');
      }
      staff_log($user, 'Recorded walk-in payment ID ' . $paymentId . ' for PHP ' . number_format((float) $amountRaw, 2), 'Walk-in Payments');
      staff_redirect($redirect, 'payment-recorded');

    case 'issue_document':
      barangay_issuance_schema();
      barangay_custom_document_schema();
      if (empty($_SESSION['document_request_csrf']) || !hash_equals($_SESSION['document_request_csrf'], (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Please reopen the issuance page and try again.');
      if (($_POST['requirements_checked'] ?? '') !== '1') throw new InvalidArgumentException('Confirm the requirements before issuing the document.');
      staff_ensure_issued_document_file_columns();
      barangay_ensure_document_fee_schema();
      barangay_ensure_document_payment_schema();
      $requestBeforeRelease = null;
      $customValues = [];
      try {
        if (trim($_POST['request_code'] ?? '') !== '') {
          $requestBeforeRelease = barangay_locked_document_request(trim($_POST['request_code']));
          $_POST = barangay_request_issue_input($_POST, $requestBeforeRelease);
  $customValues = barangay_custom_validate_values(barangay_custom_fields_for_type(staff_canonical_document_type(trim($_POST['document_type'] ?? ''))), $_POST['custom_fields'] ?? []);
          if (barangay_document_request_event(trim($_POST['request_code']), 'prepare')) {
            staff_log($user, 'Started preparing document request: ' . $_POST['request_code'], 'Document Requests');
          }
        }
        if (!$requestBeforeRelease) {
  $customValues = barangay_custom_validate_values(barangay_custom_fields_for_type(staff_canonical_document_type(trim($_POST['document_type'] ?? ''))), $_POST['custom_fields'] ?? []);
        }
        $copies = barangay_document_copies($_POST['copies'] ?? 1);
        $unitFee = $requestBeforeRelease
          ? (float) ($requestBeforeRelease['unit_fee'] ?? $requestBeforeRelease['fee_amount'])
          : (barangay_document_fee_for_type(trim($_POST['document_type'] ?? '')) ?? 0);
        $documentFee = $requestBeforeRelease
          ? (float) $requestBeforeRelease['fee_amount'] : barangay_document_total($unitFee, $copies);
        $paymentCapture = barangay_issuance_payment_input($_POST, $documentFee);
      } catch (InvalidArgumentException $e) {
        staff_redirect($redirect, 'invalid');
      }
      $residentCode = trim($_POST['resident_code'] ?? '');
      $documentType = staff_canonical_document_type(trim($_POST['document_type'] ?? ''));
      $purpose = trim($_POST['purpose'] ?? '');
      $documentNumber = trim($_POST['document_number'] ?? '');
      $dateIssued = trim($_POST['date_issued'] ?? date('Y-m-d'));
      $beneficiaryName = trim($_POST['beneficiary_name'] ?? '');
      $beneficiaryRelationship = trim($_POST['beneficiary_relationship'] ?? '');
      $residencyYears = max(0, (int) ($_POST['residency_years'] ?? 0));
      $partnerName = trim($_POST['partner_name'] ?? '');
      $cohabitationStartDate = trim($_POST['cohabitation_start_date'] ?? '');
      $requestCode = trim($_POST['request_code'] ?? '');
      if ($residentCode === '' || $documentType === '' || $documentNumber === '') {
        staff_redirect($redirect, 'missing');
      }
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIssued) || strtotime($dateIssued) === false) {
        $dateIssued = date('Y-m-d');
      }
      $residentStmt = barangay_db()->prepare(
        "SELECT id, user_code, full_name, COALESCE(mobile, '') AS mobile, COALESCE(address, '') AS address,
                TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
                COALESCE(gender, '') AS gender,
                COALESCE(civil_status, '') AS civil_status
         FROM users
         WHERE user_code = :code AND role = 'resident'
         LIMIT 1"
      );
      $residentStmt->execute([':code' => $residentCode]);
      $resident = $residentStmt->fetch();
      if ($requestCode === '' && $residentCode === '__walkin__') {
        try {
          $resident = barangay_walkin_resident($_POST);
        } catch (InvalidArgumentException $e) {
          staff_redirect($redirect, 'invalid');
        }
      }
      if (!$resident) {
        staff_redirect($redirect, 'invalid');
      }
      if ($requestCode === '' && ($_POST['notify_pickup'] ?? '') === '1' && trim((string) $resident['mobile']) === '') {
        throw new InvalidArgumentException('Enter a mobile number for the pickup SMS, or turn off that option.');
      }
      $captain = 'Hon. Barangay Captain';
      $generatedFile = null;
      $templateMissingPlaceholders = false;
      $templateStmt = barangay_db()->prepare(
        "SELECT template_file_path
         FROM document_templates
         WHERE LOWER(TRIM(document_type)) = LOWER(:document_type)
           AND status = 'Active'
           AND template_file_path IS NOT NULL
           AND template_file_path <> ''
         ORDER BY updated_at DESC
         LIMIT 1"
      );
      $templateStmt->execute([':document_type' => $documentType]);
      $templatePath = (string) ($templateStmt->fetchColumn() ?: '');
      if ($templatePath !== '') {
        $values = staff_document_template_values($resident, $purpose, $dateIssued, $captain, $documentNumber, [
          'beneficiary_name' => $beneficiaryName,
          'beneficiary_relationship' => $beneficiaryRelationship,
          'residency_years' => $residencyYears,
          'partner_name' => $partnerName,
          'cohabitation_start_date' => $cohabitationStartDate,
        ]);
        $values = array_merge($values, barangay_custom_template_values($customValues));
        if (staff_docx_contains_placeholders($templatePath, array_keys($values))) {
          $generatedFile = staff_generate_docx_from_template($templatePath, $values, $documentNumber);
        } else {
          $templateMissingPlaceholders = true;
        }
      }
      if ((($_POST['after_issue'] ?? '') === 'print' || $requestCode !== '') && !$generatedFile) {
        staff_redirect($redirect, $templateMissingPlaceholders ? 'print-no-placeholders' : 'print-generation-failed');
      }
      barangay_db()->beginTransaction();
      if ($requestCode !== '') {
        // Serialize issuance so repeated clicks cannot release the same request twice.
        $lock = barangay_db()->prepare('SELECT status FROM document_requests WHERE request_code = :code FOR UPDATE');
        $lock->execute([':code' => $requestCode]);
        if ($lock->fetchColumn() !== 'Processing') {
          barangay_db()->rollBack();
          staff_redirect($redirect, 'invalid');
        }
      }
      $stmt = barangay_db()->prepare(
        'INSERT INTO issued_documents
          (document_number, resident_user_id, resident_name, document_type, purpose, fee_amount, copies, unit_fee, generated_file_name, generated_file_path, issued_by, issued_at, prepared_at)
         VALUES
          (:number, :resident_user_id, :resident_name, :document_type, :purpose, :fee_amount, :copies, :unit_fee, :generated_file_name, :generated_file_path, :issued_by, :issued_at, NOW())'
      );
      $stmt->execute([
        ':number' => $documentNumber,
        ':resident_user_id' => $resident['id'],
        ':resident_name' => $resident['full_name'],
        ':document_type' => $documentType,
        ':purpose' => $purpose,
        ':fee_amount' => $documentFee,
        ':copies' => $copies,
        ':unit_fee' => $unitFee,
        ':generated_file_name' => $generatedFile['name'] ?? null,
        ':generated_file_path' => $generatedFile['path'] ?? null,
        ':issued_by' => $user['id'],
        ':issued_at' => $dateIssued . ' ' . date('H:i:s'),
      ]);
      $issuedDocumentId = (int) barangay_db()->lastInsertId();
      $customSave = barangay_db()->prepare('UPDATE issued_documents SET custom_values_json=? WHERE id=?');
      $customSave->execute([json_encode($customValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $issuedDocumentId]);
      barangay_document_attach_verification($issuedDocumentId, staff_docx_absolute_path($generatedFile['path']));
      $details = barangay_db()->prepare("UPDATE issued_documents SET request_code = ?, issuance_status = 'Ready for Pickup', applicant_details = ?, contact_mobile = ?, requirements_checked = 1 WHERE id = ?");
      $details->execute([$requestCode ?: null, json_encode($resident, JSON_UNESCAPED_UNICODE), $resident['mobile'] ?: ($requestBeforeRelease['contact_mobile'] ?? ''), $issuedDocumentId]);
      $paymentId = null;
      if ($requestCode !== '') {
        barangay_document_request_event($requestCode, 'ready');
        if ($documentFee > 0) $paymentId = barangay_create_payment_for_released_request($requestCode, (int) $user['id'], $documentNumber);
      } else {
        if ($documentFee > 0) $paymentId = barangay_ensure_released_document_payment([
          'document_number' => $documentNumber,
          'resident_user_id' => $resident['id'],
          'resident_name' => $resident['full_name'],
          'document_type' => $documentType,
          'amount_due' => $documentFee,
        ]);
      }
      if ($paymentId && $paymentCapture) {
        $existingPayment = barangay_find_document_payment($requestCode, $documentNumber);
        if (($existingPayment['status'] ?? '') !== 'Paid') barangay_record_walk_in_payment($paymentId, $paymentCapture['amount'], $paymentCapture['receipt'], 'Recorded during issuance', (int) $user['id']);
      }
      barangay_db()->commit();
      if ($requestCode !== '') barangay_safe_request_sms('ready', $requestCode, (int) $user['id']);
      elseif (($_POST['notify_pickup'] ?? '') === '1' && $resident['mobile'] !== '') {
        try { sms_automatic_document('ready', $resident['full_name'], $resident['mobile'], $documentType, $documentFee, $documentNumber, (int) $user['id']); }
        catch (Throwable $error) { error_log('Walk-in pickup notification failed'); }
      }
      staff_log($user, 'Prepared ' . $documentType . ' for ' . $resident['full_name'] . ' (awaiting release)', 'Issue Documents');
      if (($_POST['after_issue'] ?? '') === 'print' && $generatedFile) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'IssuancePopup') {
          header('Content-Type: application/json; charset=UTF-8');
          echo json_encode(['ok' => true, 'url' => 'staff-print-issued-document.php?id=' . $issuedDocumentId . '&popup=1',
            'document' => ['id' => $issuedDocumentId, 'resident_name' => $resident['full_name'], 'document_type' => $documentType, 'document_number' => $documentNumber]]);
          exit;
        }
        header('Location: staff-print-issued-document.php?id=' . $issuedDocumentId, true, 303);
        exit;
      }
      $issueStatus = 'issued';
      if ($templateMissingPlaceholders) {
        $issueStatus = 'docx-no-placeholders';
      } elseif ($templatePath !== '' && !$generatedFile) {
        $issueStatus = 'docx-failed';
      }
      staff_redirect($redirect, $issueStatus);

    case 'remove_prepared_document':
      if (empty($_SESSION['document_request_csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['document_request_csrf'], $_POST['csrf']) || ($_POST['confirm_remove'] ?? '') !== '1') throw new InvalidArgumentException('Confirm removal before continuing.');
      $issuedId = (int) ($_POST['issued_id'] ?? 0);
      if (barangay_remove_prepared_document($issuedId)) staff_log($user, 'Removed prepared document ID ' . $issuedId . ' from awaiting release', 'Issue Documents');
      if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'PreparedRemoval') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'id' => $issuedId]);
        exit;
      }
      staff_redirect($redirect, 'updated');

    case 'release_issued_document':
      if (empty($_SESSION['document_request_csrf']) || !hash_equals($_SESSION['document_request_csrf'], (string) ($_POST['csrf'] ?? '')) || ($_POST['handed_over'] ?? '') !== '1') throw new InvalidArgumentException('Confirm that the document was handed over.');
      $issuedId = (int) ($_POST['issued_id'] ?? 0);
      if (barangay_issuance_release($issuedId, (int) $user['id'], $_POST)) {
        staff_log($user, 'Released issued document ID ' . $issuedId, 'Issue Documents');
        $releasedRequest = barangay_db()->prepare('SELECT request_code FROM issued_documents WHERE id = ?');
        $releasedRequest->execute([$issuedId]); $releasedCode = (string) $releasedRequest->fetchColumn();
        if ($releasedCode !== '') barangay_safe_request_sms('released', $releasedCode, (int) $user['id']);
      }
      header('Location: staff-print-issued-document.php?id=' . $issuedId . (($_POST['popup'] ?? '') === '1' ? '&popup=1' : ''), true, 303);
      exit;

    case 'save_template':
      if (empty($_SESSION['template_fields_csrf']) || !is_string($_POST['template_fields_csrf'] ?? null) || !hash_equals($_SESSION['template_fields_csrf'], $_POST['template_fields_csrf'])) throw new InvalidArgumentException('Reopen Template Management before saving.');
      $customDefinitions = barangay_custom_field_definitions($_POST['custom_field_definitions'] ?? '[]');
      staff_ensure_document_template_file_columns();
      $selectedType = trim($_POST['document_type'] ?? '');
      $customType = trim($_POST['document_type_custom'] ?? '');
      $type = staff_canonical_document_type($selectedType === '__new__' ? $customType : $selectedType);
      $year = staff_current_template_year();
      $name = staff_auto_template_name($type, $year);
      $status = $_POST['status'] ?? 'Draft';
      $templateMissingPlaceholders = false;
      if ($name === '' || $type === '') {
        staff_redirect($redirect, 'missing');
      }

      $templateFileName = null;
      $templateFilePath = null;
      if (!empty($_FILES['template_docx']['name'])) {
        $upload = $_FILES['template_docx'];
        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $extension !== 'docx') {
          staff_redirect($redirect, 'invalid');
        }

        $uploadDir = __DIR__ . '/storage/document-templates';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0775, true);
        }

        $safeType = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($type));
        $safeYear = $year ?: (int) date('Y');
        $storedFile = trim($safeType, '-') . '-' . $safeYear . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.docx';
        $targetPath = $uploadDir . '/' . $storedFile;
        if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
          staff_redirect($redirect, 'invalid');
        }

        $templateFileName = basename((string) $upload['name']);
        $templateFilePath = 'storage/document-templates/' . $storedFile;
        $templateMissingPlaceholders = !staff_docx_contains_placeholders($templateFilePath, array_merge(staff_document_placeholder_keys(), array_map(static fn($field) => '{{'.$field['key'].'}}', $customDefinitions)));
      }

      $checkPath = $templateFilePath;
      if ($checkPath === null) {
        $currentFiles = barangay_db()->query("SELECT document_type,effective_year,template_file_path FROM document_templates ORDER BY FIELD(status,'Active','Draft','Archived'),updated_at DESC,id DESC")->fetchAll();
        foreach ($currentFiles as $currentFile) {
          if ((int)$currentFile['effective_year']===$year && staff_document_type_key($currentFile['document_type'])===staff_document_type_key($type)) {
            $checkPath=(string)($currentFile['template_file_path'] ?? '');break;
          }
        }
      }
      try {
        staff_check_template_custom_tags((string)$checkPath,$customDefinitions);
      } catch (InvalidArgumentException $error) {
        if ($templateFilePath !== null && isset($targetPath) && is_file($targetPath)) unlink($targetPath);
        throw $error;
      }

      staff_save_template_replacement(barangay_db(), [
        'name' => $name, 'type' => $type, 'year' => $year, 'custom_fields' => $customDefinitions,
        'content' => trim($_POST['content'] ?? ''),
        'file_name' => $templateFileName, 'file_path' => $templateFilePath,
        'status' => in_array($status, ['Active', 'Draft', 'Archived'], true) ? $status : 'Draft',
        'user_id' => $user['id'],
      ]);
      staff_log($user, 'Saved document template: ' . $name, 'Document Templates');
      staff_redirect($redirect, $templateMissingPlaceholders ? 'template-no-placeholders' : 'saved', 'template-management');

    case 'update_template':
      // Saved templates are read-only; replacements use the main upload form.
      staff_redirect($redirect, 'invalid', 'template-management');

    case 'delete_template':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id < 1) {
        staff_redirect($redirect, 'invalid');
      }
      $templateStmt = barangay_db()->prepare('SELECT name, template_file_path FROM document_templates WHERE id = :id');
      $templateStmt->execute([':id' => $id]);
      $template = $templateStmt->fetch();
      if (!$template) {
        staff_redirect($redirect, 'invalid');
      }
      if (!empty($template['template_file_path'])) {
        $filePath = __DIR__ . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template['template_file_path']), DIRECTORY_SEPARATOR);
        if (is_file($filePath)) {
          @unlink($filePath);
        }
      }
      $delete = barangay_db()->prepare('DELETE FROM document_templates WHERE id = :id');
      $delete->execute([':id' => $id]);
      staff_log($user, 'Deleted document template: ' . ($template['name'] ?? $id), 'Document Templates');
      staff_redirect($redirect, 'deleted', 'template-management');

    case 'delete_document_type':
      $type = staff_canonical_document_type(trim($_POST['document_type'] ?? ''));
      if ($type === '' || $type === '__new__') {
        staff_redirect($redirect, 'invalid', 'template-management');
      }
      $defaultTypes = array_map(static fn(string $value): string => strtolower($value), staff_default_document_types());
      if (in_array(strtolower($type), $defaultTypes, true)) {
        staff_redirect($redirect, 'default-document-type', 'template-management');
      }

      $templatesStmt = barangay_db()->prepare('SELECT id, name, template_file_path FROM document_templates WHERE LOWER(TRIM(document_type)) = LOWER(:type)');
      $templatesStmt->execute([':type' => $type]);
      $templates = $templatesStmt->fetchAll();
      if (!$templates) {
        staff_redirect($redirect, 'invalid', 'template-management');
      }

      foreach ($templates as $template) {
        if (empty($template['template_file_path'])) {
          continue;
        }
        $filePath = __DIR__ . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template['template_file_path']), DIRECTORY_SEPARATOR);
        if (is_file($filePath)) {
          @unlink($filePath);
        }
      }
      $delete = barangay_db()->prepare('DELETE FROM document_templates WHERE LOWER(TRIM(document_type)) = LOWER(:type)');
      $delete->execute([':type' => $type]);
      staff_log($user, 'Deleted document type and templates: ' . $type, 'Document Templates');
      staff_redirect($redirect, 'deleted', 'template-management');

    case 'set_template_status':
      $id = (int) ($_POST['id'] ?? 0);
      $status = $_POST['status'] ?? 'Draft';
      if ($id < 1 || !in_array($status, ['Active', 'Draft', 'Archived'], true)) {
        staff_redirect($redirect, 'invalid');
      }
      if ($status === 'Active') {
        $typeStmt = barangay_db()->prepare('SELECT document_type FROM document_templates WHERE id = :id');
        $typeStmt->execute([':id' => $id]);
        $type = staff_canonical_document_type((string) $typeStmt->fetchColumn());
        if ($type) {
          $clear = barangay_db()->prepare("UPDATE document_templates SET status = 'Draft' WHERE LOWER(TRIM(document_type)) = LOWER(:type) AND status = 'Active'");
          $clear->execute([':type' => $type]);
        }
      }
      $stmt = barangay_db()->prepare('UPDATE document_templates SET status = :status, updated_by = :updated_by WHERE id = :id');
      $stmt->execute([':id' => $id, ':status' => $status, ':updated_by' => $user['id']]);
      staff_log($user, 'Updated template status ID ' . $id . ' to ' . $status, 'Document Templates');
      staff_redirect($redirect, 'updated', 'template-management');

    case 'update_complaint_status':
      if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['husay_csrf']) || !hash_equals($_SESSION['husay_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Refresh the complaint details and try again.');
      $id = trim($_POST['id'] ?? '');
      husay_update_complaint($_POST, $user);
      staff_log($user, 'Updated complaint: ' . $id . ' to ' . $_POST['status'], 'Complaints');
      staff_redirect($redirect, 'updated');

    case 'mark_feedback_read':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      if ($id === '') {
        staff_redirect($redirect, 'missing');
      }
      $stmt = barangay_db()->prepare("UPDATE feedbacks SET status = 'Read' WHERE feedback_code = :id");
      $stmt->execute([':id' => $id]);
      staff_log($user, 'Marked feedback read: ' . $id, 'Feedbacks');
      staff_redirect($redirect, 'updated');

    case 'mark_feedback_unread':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      if ($id === '') {
        staff_redirect($redirect, 'missing');
      }
      $stmt = barangay_db()->prepare("UPDATE feedbacks SET status = 'Unread' WHERE feedback_code = :id");
      $stmt->execute([':id' => $id]);
      staff_log($user, 'Marked feedback unread: ' . $id, 'Feedbacks');
      staff_redirect($redirect, 'updated');

    case 'delete_feedback':
      barangay_ensure_feedback_image_columns();
      $id = trim($_POST['id'] ?? '');
      if ($id === '') {
        staff_redirect($redirect, 'invalid');
      }
      $stmt = barangay_db()->prepare('SELECT image_path FROM feedbacks WHERE feedback_code = :id LIMIT 1');
      $stmt->execute([':id' => $id]);
      $imagePath = (string) ($stmt->fetchColumn() ?: '');
      $stmt = barangay_db()->prepare('DELETE FROM feedbacks WHERE feedback_code = :id');
      $stmt->execute([':id' => $id]);
      if ($imagePath !== '') {
        $fullPath = __DIR__ . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);
        if (is_file($fullPath)) {
          @unlink($fullPath);
        }
      }
      staff_log($user, 'Deleted feedback: ' . $id, 'Feedbacks');
      staff_redirect($redirect, 'deleted');

    case 'create_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      announcement_create_post($_POST, (int) $user['id']);
      staff_log($user, 'Created community post: ' . trim($_POST['title'] ?? ''), 'Announcements');
      staff_redirect($redirect, 'created');
    case 'update_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      $id = (int) ($_POST['id'] ?? 0);
      announcement_update_post($id, $_POST);
      staff_log($user, 'Updated community post ID: ' . $id, 'Announcements');
      staff_redirect($redirect, 'updated');
    case 'delete_announcement':
      announcement_require_post_management($user, $_POST, $_SESSION['community_post_csrf'] ?? null);
      $id = (int) ($_POST['id'] ?? 0);
      announcement_delete_post($id);
      staff_log($user, 'Deleted community post ID: ' . $id, 'Announcements');
      staff_redirect($redirect, 'deleted');
    case 'send_sms':
      $recipient = trim($_POST['recipient_name'] ?? '');
      $mobile = trim($_POST['recipient_mobile'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $messageType = trim($_POST['message_type'] ?? 'Individual resident SMS');
      if ($recipient === '' || $mobile === '' || $message === '') {
        staff_redirect($redirect, 'missing');
      }
      $_SESSION['sms_draft'] = ['template' => 'custom', 'recipient_name' => $recipient, 'recipient_mobile' => $mobile, 'message' => $message];
      $_SESSION['sms_flash'] = ['success', 'Review the recipient and message before sending. No SMS has been sent yet.'];
      header('Location: ' . ($user['role'] === 'admin' ? 'dashboard.php?view=sms-alerts' : 'staff-dashboard.php?view=staff-sms-alerts'));
      exit;

    case 'log_qr_scan':
      $residentCode = trim($_POST['resident_code'] ?? '');
      if ($residentCode === '') {
        staff_redirect($redirect, 'missing');
      }
      $stmt = barangay_db()->prepare("SELECT id, user_code, full_name, status FROM users WHERE (user_code = :user_code OR qr_code = :qr_code) AND role = 'resident' LIMIT 1");
      $stmt->execute([':user_code' => $residentCode, ':qr_code' => $residentCode]);
      $resident = $stmt->fetch();
      $result = 'Not Found';
      $residentName = 'Unknown resident';
      $residentId = null;
      if ($resident) {
        $residentId = $resident['id'];
        $residentName = $resident['full_name'];
        $result = $resident['status'] === 'Active' ? 'Verified' : 'Inactive';
      }
      $insert = barangay_db()->prepare(
        'INSERT INTO qr_scan_logs (resident_user_id, resident_code, resident_name, result, scanned_by, scanned_at)
         VALUES (:resident_user_id, :resident_code, :resident_name, :result, :scanned_by, NOW())'
      );
      $insert->execute([
        ':resident_user_id' => $residentId,
        ':resident_code' => $resident['user_code'] ?? $residentCode,
        ':resident_name' => $residentName,
        ':result' => $result,
        ':scanned_by' => $user['id'],
      ]);
      staff_log($user, 'QR checked: ' . $residentCode . ' result ' . $result, 'QR Resident Verification', $result === 'Verified' ? 'Success' : 'Warning');
      staff_redirect($redirect, 'updated');

    case 'save_profile':
      $name = trim($_POST['full_name'] ?? '');
      $position = trim($_POST['position'] ?? '');
      $mobile = trim($_POST['mobile'] ?? '');
      if ($name === '' || $position === '') {
        staff_redirect($redirect, 'missing');
      }
      $stmt = barangay_db()->prepare(
        "UPDATE users SET full_name = :name, position_title = :position, mobile = :mobile WHERE id = :id AND role = 'staff'"
      );
      $stmt->execute([':id' => $user['id'], ':name' => $name, ':position' => $position, ':mobile' => $mobile]);
      barangay_handle_profile_photo_upload($user);
      $user['name'] = $name;
      $user['title'] = $position;
      $user['contact'] = $mobile;
      barangay_sign_in($user);
      staff_log($user, 'Updated own staff profile', 'My Profile');
      staff_redirect($redirect, 'saved');

    default:
      staff_redirect($redirect, 'invalid');
  }
} catch (InvalidArgumentException $e) {
  if (barangay_db()->inTransaction()) barangay_db()->rollBack();
  $_SESSION['configuration_error'] = $e->getMessage();
  staff_redirect($redirect, 'validation-error');
} catch (PDOException $e) {
  if (barangay_db()->inTransaction()) barangay_db()->rollBack();
  $status = str_contains($e->getMessage(), 'Duplicate') ? 'duplicate' : 'invalid';
  staff_log($user, 'Failed staff action: ' . $action, 'System', 'Error');
  staff_redirect($redirect, $status);
} catch (RuntimeException $e) {
  if (barangay_db()->inTransaction()) barangay_db()->rollBack();
  $_SESSION['configuration_error'] = $e->getMessage();
  staff_redirect($redirect, 'validation-error');
}
?>

