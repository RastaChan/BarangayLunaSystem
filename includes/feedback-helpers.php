<?php
require_once __DIR__ . '/database.php';

function barangay_ensure_feedback_image_columns(): void {
  $columns = [];
  try {
    $rows = barangay_db()->query(
      "SELECT COLUMN_NAME
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'feedbacks'
         AND COLUMN_NAME IN ('image_file_name', 'image_path', 'rating')"
    )->fetchAll();
    $columns = array_column($rows, 'COLUMN_NAME');
  } catch (Throwable $e) {
    $columns = [];
  }

  if (!in_array('image_file_name', $columns, true)) {
    barangay_db()->exec('ALTER TABLE feedbacks ADD COLUMN image_file_name VARCHAR(255) DEFAULT NULL AFTER message');
  }
  if (!in_array('image_path', $columns, true)) {
    barangay_db()->exec('ALTER TABLE feedbacks ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER image_file_name');
  }
  if (!in_array('rating', $columns, true)) {
    barangay_db()->exec('ALTER TABLE feedbacks ADD COLUMN rating TINYINT UNSIGNED DEFAULT NULL');
  }
}

function barangay_feedback_rating($value): int {
  if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-5]$/D', (string) $value)) {
    throw new InvalidArgumentException('Please select a feedback rating from 1 to 5.');
  }
  return (int) $value;
}

function barangay_store_feedback_image(string $fieldName): ?array {
  if (empty($_FILES[$fieldName]['name']) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  $file = $_FILES[$fieldName];
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    return null;
  }

  $maxBytes = 50 * 1024 * 1024;
  if (($file['size'] ?? 0) > $maxBytes) {
    return null;
  }

  $tmpName = (string) ($file['tmp_name'] ?? '');
  $mime = '';
  if (is_file($tmpName)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
  }

  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];
  if (!isset($extensions[$mime])) {
    return null;
  }

  $uploadDir = dirname(__DIR__) . '/storage/feedback-images';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
  }

  $storedName = 'feedback-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
  $targetPath = $uploadDir . '/' . $storedName;
  if (!move_uploaded_file($tmpName, $targetPath)) {
    return null;
  }

  return [
    'file_name' => basename((string) $file['name']),
    'path' => 'storage/feedback-images/' . $storedName,
  ];
}
?>
