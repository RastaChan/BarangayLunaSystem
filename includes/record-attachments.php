<?php
require_once __DIR__ . '/database.php';

function barangay_attachment_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_db()->exec("CREATE TABLE IF NOT EXISTS record_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_type VARCHAR(20) NOT NULL,
    record_code VARCHAR(100) NOT NULL,
    owner_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    content_base64 LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX record_lookup (record_type, record_code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $ready = true;
}

function barangay_read_attachment_upload(array $file): ?array {
  $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($error === UPLOAD_ERR_NO_FILE) return null;
  if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
    throw new InvalidArgumentException('The attachment could not be uploaded.');
  }
  $path = $file['tmp_name'];
  if (filesize($path) < 1 || filesize($path) > 5 * 1024 * 1024) {
    throw new InvalidArgumentException('Attachments must be between 1 byte and 5 MB.');
  }
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
  if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    throw new InvalidArgumentException('Only JPG, PNG, and PDF attachments are allowed.');
  }
  $name = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'attachment')));
  $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name);
  return ['filename' => substr($name, 0, 240), 'mime' => $mime, 'content' => base64_encode(file_get_contents($path))];
}

function barangay_save_attachment(string $type, string $code, int $owner, ?array $file): void {
  if (!$file) return;
  if (!in_array($type, ['complaint', 'document', 'registration'], true)) throw new InvalidArgumentException('Invalid attachment type.');
  $stmt = barangay_db()->prepare('INSERT INTO record_attachments (record_type, record_code, owner_id, filename, mime_type, content_base64) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([$type, $code, $owner, $file['filename'], $file['mime'], $file['content']]);
}

function barangay_attachment_authorized(array $user, array $attachment): bool {
  if (!in_array($user['role'] ?? '', ['admin', 'staff', 'resident'], true)) return false;
  if ($user['role'] === 'resident' && (int) $attachment['owner_id'] !== (int) $user['id']) return false;
  if ($attachment['record_type'] === 'registration') {
    $stmt = barangay_db()->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'resident'");
    $stmt->execute([$attachment['owner_id']]);
    return (bool) $stmt->fetchColumn();
  }
  $parent = match ($attachment['record_type']) {
    'complaint' => ['complaints', 'complaint_code'],
    'document' => ['document_requests', 'request_code'],
    default => null,
  };
  if (!$parent) return false;
  $stmt = barangay_db()->prepare("SELECT 1 FROM {$parent[0]} WHERE {$parent[1]} = ? AND resident_user_id = ?");
  $stmt->execute([$attachment['record_code'], $attachment['owner_id']]);
  return (bool) $stmt->fetchColumn();
}

function barangay_attachment_files(string $type, string $code): array {
  barangay_attachment_schema();
  $stmt = barangay_db()->prepare('SELECT id, filename, mime_type FROM record_attachments WHERE record_type = ? AND record_code = ? ORDER BY id');
  $stmt->execute([$type, $code]);
  return $stmt->fetchAll();
}

function barangay_attachment_links(string $type, string $code, ?array $files = null): string {
  $files ??= barangay_attachment_files($type, $code);
  $html = '';
  foreach ($files as $file) {
    $label = str_starts_with($file['mime_type'], 'image/') ? 'View photo: ' : 'View attachment: ';
    $html .= '<a class="btn-icon file-link" href="record-attachment.php?id=' . (int) $file['id'] . '" target="_blank" rel="noopener">' . $label . htmlspecialchars($file['filename'], ENT_QUOTES) . '</a>';
  }
  return $html;
}

function barangay_attachment_display_headers(array $file, bool $download = false): array {
  $canPreview = in_array($file['mime_type'], ['image/jpeg', 'image/png', 'application/pdf'], true);
  $disposition = $canPreview && !$download ? 'inline' : 'attachment';
  return [
    'Content-Type' => $canPreview ? $file['mime_type'] : 'application/octet-stream',
    'Content-Disposition' => "$disposition; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($file['filename']),
  ];
}
