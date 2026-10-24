<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms-workspace.php';
require_once __DIR__ . '/includes/staff-functions.php';

$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '', ['admin', 'staff'], true)) {
  http_response_code(403);
  exit('Access denied.');
}
$account = barangay_db()->prepare("SELECT role FROM users WHERE id = :id AND status = 'Active'");
$account->execute([':id' => $user['id']]);
if ($account->fetchColumn() !== $user['role']) {
  http_response_code(403);
  exit('This account is no longer authorized to send SMS.');
}
$page = $user['role'] === 'admin' ? 'dashboard.php?view=sms-alerts' : 'staff-dashboard.php?view=staff-sms-alerts';
$tab = 'send';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['sms_csrf'] ?? '', (string) ($_POST['csrf'] ?? '')) || empty($_SESSION['sms_csrf'])) {
    throw new RuntimeException('Your session expired. Refresh the page and try again.');
  }
  sms_workspace_schema();
  $action = (string) ($_POST['action'] ?? '');
  if ($action === 'settings') {
    $tab = 'automatic';
    $db = barangay_db();
    $db->beginTransaction();
    sms_save_automatic_settings($user, $_POST);
    staff_log($user, 'Updated automatic document SMS templates and preferences', 'SMS Alerts');
    $db->commit();
    $_SESSION['sms_flash'] = ['success', 'Automatic messages saved. Changes apply to future alerts.'];
  } elseif ($action === 'send' || $action === 'retry') {
    if (empty($_POST['confirmed'])) throw new RuntimeException('Review the recipient and message before sending.');
    if ($action === 'retry') {
      $tab = 'history';
      $stmt = barangay_db()->prepare("SELECT s.*, child.id AS retry_id, TIMESTAMPDIFF(SECOND, s.created_at, NOW()) AS age_seconds FROM sms_logs s LEFT JOIN sms_logs child ON child.delivery_key = CONCAT('retry:', s.id) WHERE s.id = :id");
      $stmt->execute([':id' => (int) ($_POST['id'] ?? 0)]);
      $row = $stmt->fetch();
      if (!$row) throw new RuntimeException('SMS record was not found.');
      if ($problem = sms_retry_problem($row)) throw new RuntimeException($problem);
      if ($row['request_code']) {
        $request = barangay_db()->prepare('SELECT status FROM document_requests WHERE request_code = :code AND archived_at IS NULL');
        $request->execute([':code' => $row['request_code']]);
        $requestStatus = $request->fetchColumn();
        if (!$requestStatus || (in_array($row['message_type'], ['Document ready for pickup', 'Pickup reminder'], true) && $requestStatus !== 'Ready for Pickup') || ($row['message_type'] === 'Document released' && $requestStatus !== 'Released')) {
          throw new RuntimeException('This document request changed. Compose a new message with its current details.');
        }
      }
      if ($row['message_type'] !== 'Resident Account SMS' && sms_full_message($row['message']) !== $row['message']) throw new RuntimeException('The SMS sender prefix changed. Compose a new message before sending.');
      $data = ['name' => $row['recipient_name'], 'mobile' => $row['recipient_mobile'], 'type' => $row['message_type'],
        'message' => $row['message'], 'source' => $row['source'], 'key' => 'retry:' . $row['id'],
        'retry_of' => $row['id'], 'request_code' => $row['request_code']];
    } else {
      $_SESSION['sms_draft'] = array_intersect_key($_POST, array_flip(['template', 'request_code', 'request_status', 'request_fee', 'resident_code', 'recipient_name', 'recipient_mobile', 'message']));
      $template = (string) ($_POST['template'] ?? '');
      if (!isset(sms_templates()[$template])) throw new RuntimeException('Choose a message template.');
      $nonce = (string) ($_POST['nonce'] ?? '');
      if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) throw new RuntimeException('Refresh the form before sending.');
      $name = trim((string) ($_POST['recipient_name'] ?? ''));
      $mobile = trim((string) ($_POST['recipient_mobile'] ?? ''));
      $residentCode = trim((string) ($_POST['resident_code'] ?? ''));
      $requestCode = '';
      if (in_array($template, ['ready', 'reminder', 'status'], true)) {
        $requestCode = trim((string) ($_POST['request_code'] ?? ''));
        $stmt = barangay_db()->prepare('SELECT d.*, u.mobile AS current_mobile, u.status AS account_status FROM document_requests d LEFT JOIN users u ON u.id = d.resident_user_id WHERE d.request_code = :code AND d.archived_at IS NULL');
        $stmt->execute([':code' => $requestCode]);
        $row = $stmt->fetch();
        if (!$row || ($row['account_status'] !== null && $row['account_status'] !== 'Active')) throw new RuntimeException('Choose a document request belonging to an active resident.');
        if (in_array($template, ['ready', 'reminder'], true) && $row['status'] !== 'Ready for Pickup') throw new RuntimeException('This document is not marked Ready for Pickup. Update the request first.');
        if ((string) ($_POST['request_status'] ?? '') !== $row['status'] || !is_numeric($_POST['request_fee'] ?? '') || (float) $_POST['request_fee'] !== (float) $row['fee_amount']) {
          throw new RuntimeException('The request status or fee changed. Select the request again and review its updated message.');
        }
        $name = $row['resident_name'];
        $mobile = $row['current_mobile'] ?: $row['contact_mobile'];
      } elseif ($residentCode !== '') {
        $stmt = barangay_db()->prepare("SELECT full_name, mobile FROM users WHERE user_code = :code AND role = 'resident' AND status = 'Active'");
        $stmt->execute([':code' => $residentCode]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('This resident is no longer active. Choose another recipient.');
        $name = $row['full_name'];
        $mobile = $row['mobile'];
      }
      $message = trim((string) ($_POST['message'] ?? ''));
      if (in_array($message, ['Barangay Luna announcement:', 'Barangay Luna emergency advisory:'], true)) throw new RuntimeException('Complete the message before sending.');
      if ($name !== trim((string) ($_POST['recipient_name'] ?? '')) || SMSAlertsHelper::normalizePhilippineMobile((string) $mobile) !== SMSAlertsHelper::normalizePhilippineMobile((string) ($_POST['recipient_mobile'] ?? ''))) {
        throw new RuntimeException('The recipient details changed. Review the current name and mobile number before sending.');
      }
      $sender = SMSAlertsHelper::cleanSenderName(SMSAlertsHelper::getSettings()['sms_sender_name'] ?? 'BRGY LUNA');
      if ($sender !== (string) ($_POST['sender_prefix'] ?? '')) throw new RuntimeException('The SMS sender name changed. Refresh and review the message again.');
      if ($name === '' || strlen($name) > 150 || $message === '') throw new RuntimeException('Complete the recipient and message fields.');
      if (!SMSAlertsHelper::normalizePhilippineMobile((string) $mobile)) throw new RuntimeException('Enter a valid Philippine mobile number.');
      if (strlen(sms_full_message($message)) > 918) throw new RuntimeException('The full message, including the sender prefix, exceeds the 918-byte limit. Shorten it before sending.');
      $data = ['name' => $name, 'mobile' => $mobile, 'type' => sms_templates()[$template], 'message' => $message,
        'source' => 'Manual', 'key' => 'manual:' . $user['id'] . ':' . $nonce, 'request_code' => $requestCode ?: null];
    }
    $data['user'] = (int) $user['id'];
    $result = sms_record_send($data);
    unset($_SESSION['sms_draft']);
    $tab = 'history';
    $accepted = !empty($result['success']);
    $_SESSION['sms_flash'] = [$accepted ? 'success' : 'error', $accepted
      ? 'Message accepted by httpSMS. Delivery to the resident has not been confirmed.'
      : ($result['failure_reason'] ?? $result['message'] ?? 'Check SMS History for the result.')];
    staff_log($user, 'SMS attempt ' . ($result['id'] ?? 'already recorded') . ': ' . ($result['delivery_state'] ?? 'Duplicate'), 'SMS Alerts', $accepted ? 'Success' : 'Warning');
  } else {
    throw new RuntimeException('Unknown SMS action.');
  }
} catch (RuntimeException $error) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $_SESSION['sms_flash'] = ['error', $error instanceof PDOException ? 'SMS records could not be updated. Check history before trying again.' : $error->getMessage()];
} catch (Throwable $error) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $_SESSION['sms_flash'] = ['error', 'The action could not be completed. Check SMS History before sending again.'];
}
header('Location: ' . $page . '&sms_tab=' . $tab);
exit;
