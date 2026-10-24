<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/husay-helpers.php';

function resident_check_submission_review(array $input, string $token, bool $document = false): void {
  if ($token === '' || !is_string($input['service_csrf'] ?? null) || !hash_equals($token, $input['service_csrf'])) throw new InvalidArgumentException('Refresh the page and try again.');
  if (($input['review_confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Review and confirm your details before submitting.');
  if ($document && (string) ($input['reviewed_copies'] ?? '') !== (string) ($input['copies'] ?? '1')) throw new InvalidArgumentException('The number of copies changed. Review your request again.');
}

function resident_delete_own_submission(string $kind, string $code, int $userId): void {
  if (!in_array($kind, ['document','complaint'], true) || $code === '' || $userId < 1) throw new InvalidArgumentException('Submission not found.');
  if ($kind === 'document') {
    $sql = "UPDATE document_requests SET archived_at = NOW() WHERE request_code = ? AND resident_user_id = ? AND archived_at IS NULL AND status = 'Pending'
      AND NOT EXISTS (SELECT 1 FROM issued_documents WHERE issued_documents.request_code = document_requests.request_code AND issuance_status IN ('Ready for Pickup','Released'))";
  } else {
    husay_schema();
    $sql = "UPDATE complaints SET archived_at = NOW() WHERE complaint_code = ? AND resident_user_id = ? AND archived_at IS NULL AND status = 'Open'
      AND NOT EXISTS (SELECT 1 FROM husay_schedules WHERE husay_schedules.complaint_id=complaints.id AND husay_schedules.status IN ('Scheduled','In Progress'))";
  }
  $stmt = barangay_db()->prepare($sql);
  $stmt->execute([$code, $userId]);
  if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('This submission cannot be deleted. Staff may have started processing it, or it was already deleted. Refresh your list.');
}

function resident_delete_submission_form(string $kind, string $code, string $label): string {
  $escape = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES);
  return '<form action="resident-actions.php" method="post" class="resident-delete-submission" data-resident-review="delete" data-delete-kind="' . $escape($kind) . '" data-review-label="' . $escape($label) . '">'
    . '<input type="hidden" name="action" value="delete_submission"><input type="hidden" name="kind" value="' . $escape($kind) . '">'
    . '<input type="hidden" name="record_code" value="' . $escape($code) . '"><input type="hidden" name="review_confirmed" value="0">'
    . '<input type="hidden" name="service_csrf" value="' . $escape($_SESSION['resident_service_csrf']) . '">'
    . '<button type="submit" class="btn btn-danger btn-sm">Delete</button></form>';
}
