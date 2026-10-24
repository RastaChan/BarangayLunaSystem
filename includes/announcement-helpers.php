<?php
require_once __DIR__ . '/database.php';

function announcement_ensure_photo_columns(): void {
  $columns = barangay_db()->prepare(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'announcements'
       AND COLUMN_NAME IN ('photo_file_name', 'photo_path', 'publish_at', 'post_type', 'event_at', 'event_location')"
  );
  $columns->execute();
  $existing = array_column($columns->fetchAll(), 'COLUMN_NAME');

  foreach (['post_type' => "VARCHAR(20) NOT NULL DEFAULT 'announcement'", 'event_at' => 'DATETIME DEFAULT NULL', 'event_location' => 'VARCHAR(255) DEFAULT NULL'] as $column => $definition) {
    if (!in_array($column, $existing, true)) {
      barangay_db()->exec("ALTER TABLE announcements ADD COLUMN {$column} {$definition}");
    }
  }

  if (!in_array('photo_file_name', $existing, true)) {
    barangay_db()->exec('ALTER TABLE announcements ADD COLUMN photo_file_name VARCHAR(255) DEFAULT NULL AFTER body');
  }
  if (!in_array('photo_path', $existing, true)) {
    barangay_db()->exec('ALTER TABLE announcements ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER photo_file_name');
  }
  if (!in_array('publish_at', $existing, true)) {
    barangay_db()->exec('ALTER TABLE announcements ADD COLUMN publish_at DATETIME DEFAULT NULL AFTER publish_date');
    barangay_db()->exec("UPDATE announcements SET publish_at = CONCAT(publish_date, ' 00:00:00') WHERE publish_at IS NULL");
  }

  $statusColumn = barangay_db()->prepare(
    "SELECT DATA_TYPE, COLUMN_TYPE
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'announcements'
       AND COLUMN_NAME = 'status'
     LIMIT 1"
  );
  $statusColumn->execute();
  $statusInfo = $statusColumn->fetch() ?: [];
  if (($statusInfo['DATA_TYPE'] ?? '') === 'enum' && strpos((string) ($statusInfo['COLUMN_TYPE'] ?? ''), 'Scheduled') === false) {
    barangay_db()->exec("ALTER TABLE announcements MODIFY status VARCHAR(30) NOT NULL DEFAULT 'Draft'");
  }
}

function announcement_publish_due(): void {
  announcement_ensure_photo_columns();

  barangay_db()->exec(
    "UPDATE announcements
     SET status = 'Published'
     WHERE status = 'Scheduled'
       AND archived_at IS NULL
       AND publish_at IS NOT NULL
       AND publish_at <= NOW()"
  );
}

function announcement_publish_at(string $date, string $time): string {
  $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
  $time = preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '00:00';

  return $date . ' ' . $time . ':00';
}

function announcement_post_details(array $input): array {
  $type = $input['post_type'] ?? 'announcement';
  if (!in_array($type, ['announcement', 'event'], true)) {
    throw new InvalidArgumentException('Choose an announcement or event.');
  }
  $eventAt = null;
  $location = null;
  if ($type === 'event') {
    $raw = (string) ($input['event_at'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw);
    $location = trim((string) ($input['event_location'] ?? ''));
    if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $raw || $location === '' || strlen($location) > 255) {
      throw new InvalidArgumentException('Enter a valid event date, time, and location (up to 255 characters).');
    }
    $eventAt = $parsed->format('Y-m-d H:i:s');
  }
  return [':post_type' => $type, ':event_at' => $eventAt, ':event_location' => $location];
}

function announcement_post_input(array $input): array {
  $title = trim((string) ($input['title'] ?? ''));
  $body = trim((string) ($input['body'] ?? ''));
  if ($title === '' || strlen($title) > 180 || $body === '') {
    throw new InvalidArgumentException('Enter a title (up to 180 characters) and content.');
  }
  $details = announcement_post_details($input);
  $publishAt = date('Y-m-d H:i:s');
  if (!empty($input['schedule_enabled'])) {
    $raw = ($input['publish_date'] ?? '') . ' ' . ($input['publish_time'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $raw);
    if (!$parsed || $parsed->format('Y-m-d H:i') !== $raw) {
      throw new InvalidArgumentException('Enter a valid publish date and time.');
    }
    $publishAt = $parsed->format('Y-m-d H:i:s');
  }
  return $details + [
    ':title' => $title, ':body' => $body,
    ':publish_date' => substr($publishAt, 0, 10), ':publish_at' => $publishAt,
    ':status' => strtotime($publishAt) <= time() ? 'Published' : 'Scheduled',
  ];
}

function announcement_create_post(array $input, int $authorId): void {
  $params = announcement_post_input($input);
  announcement_ensure_photo_columns();
  $photo = announcement_store_uploaded_photo();
  $stmt = barangay_db()->prepare(
    'INSERT INTO announcements (title, body, photo_file_name, photo_path, publish_date, publish_at, status, author_id, post_type, event_at, event_location, created_at)
     VALUES (:title, :body, :photo_file_name, :photo_path, :publish_date, :publish_at, :status, :author_id, :post_type, :event_at, :event_location, NOW())'
  );
  $stmt->execute($params + [
    ':photo_file_name' => $photo['file_name'] ?? null, ':photo_path' => $photo['path'] ?? null,
    ':author_id' => $authorId,
  ]);
}

function announcement_require_post_management(array $user, array $input, ?string $token): void {
  if (!in_array($user['role'] ?? '', ['admin', 'staff'], true)) throw new InvalidArgumentException('Only Admin and Staff can manage posts and events.');
  if (!$token || !is_string($input['csrf'] ?? null) || !hash_equals($token, $input['csrf'])) throw new InvalidArgumentException('Refresh the page and try again.');
}

function announcement_remove_unused_photo(string $path): void {
  if ($path === '') return;
  $root = realpath(dirname(__DIR__) . '/storage/announcement-photos');
  $target = realpath(dirname(__DIR__) . '/' . $path);
  if (!$root || !$target || strncasecmp($target, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0 || !is_file($target)) return;
  $read = barangay_db()->prepare('SELECT COUNT(*) FROM announcements WHERE photo_path = ?');
  $read->execute([$path]);
  if ((int) $read->fetchColumn() === 0) @unlink($target);
}

function announcement_update_post(int $id, array $input): void {
  if ($id < 1) throw new InvalidArgumentException('Select a valid post.');
  $values = announcement_post_input($input);
  announcement_ensure_photo_columns();
  $db = barangay_db();
  $photo = null;
  $db->beginTransaction();
  try {
    $read = $db->prepare('SELECT * FROM announcements WHERE id = ? FOR UPDATE');
    $read->execute([$id]); $existing = $read->fetch();
    if (!$existing || !empty($existing['archived_at'])) throw new InvalidArgumentException('This post is no longer available for editing.');
    if (empty($input['schedule_enabled']) && $existing['status'] === 'Published') {
      $values[':publish_date'] = $existing['publish_date'];
      $values[':publish_at'] = $existing['publish_at'] ?: $existing['publish_date'] . ' 00:00:00';
    }
    $photo = announcement_store_uploaded_photo();
    $sql = 'UPDATE announcements SET title = :title, body = :body, publish_date = :publish_date, publish_at = :publish_at, status = :status, post_type = :post_type, event_at = :event_at, event_location = :event_location';
    $params = $values + [':id' => $id];
    if ($photo) {
      $sql .= ', photo_file_name = :photo_file_name, photo_path = :photo_path';
      $params[':photo_file_name'] = $photo['file_name'];
      $params[':photo_path'] = $photo['path'];
    }
    $db->prepare($sql . ' WHERE id = :id')->execute($params);
    $db->commit();
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    if ($photo) announcement_remove_unused_photo($photo['path']);
    throw $error;
  }
  if ($photo) announcement_remove_unused_photo((string) ($existing['photo_path'] ?? ''));
}

function announcement_delete_post(int $id): void {
  if ($id < 1) throw new InvalidArgumentException('Select a valid post.');
  $db = barangay_db();
  $db->beginTransaction();
  try {
    $read = $db->prepare('SELECT photo_path FROM announcements WHERE id = ? FOR UPDATE');
    $read->execute([$id]); $existing = $read->fetch();
    if (!$existing) throw new InvalidArgumentException('This post has already been removed.');
    $db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    $db->commit();
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
  }
  announcement_remove_unused_photo((string) ($existing['photo_path'] ?? ''));
}

function announcement_store_uploaded_photo(string $fieldName = 'photo'): ?array {
  if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  $file = $_FILES[$fieldName];
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    throw new RuntimeException('Announcement photo upload failed.');
  }

  $maxBytes = 50 * 1024 * 1024;
  if (($file['size'] ?? 0) > $maxBytes) {
    throw new RuntimeException('Announcement photo is too large.');
  }

  $imageInfo = @getimagesize($file['tmp_name']);
  $mime = $imageInfo['mime'] ?? '';
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];

  if (!isset($extensions[$mime])) {
    throw new RuntimeException('Announcement photo type is not supported.');
  }

  $storageDir = dirname(__DIR__) . '/storage/announcement-photos';
  if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
  }

  $originalName = basename((string) ($file['name'] ?? 'announcement-photo'));
  $filename = 'announcement-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
  $target = $storageDir . '/' . $filename;

  if (!move_uploaded_file($file['tmp_name'], $target)) {
    throw new RuntimeException('Announcement photo could not be saved.');
  }

  return [
    'file_name' => $originalName,
    'path' => 'storage/announcement-photos/' . $filename,
  ];
}

function announcement_fetch_public(int $limit = 3): array {
  announcement_publish_due();

  $sql = "SELECT id, title, body, DATE(publish_date) AS date, IF(post_type = 'event', 'Event', 'Public Advisory') AS category,
                 COALESCE(photo_file_name, '') AS photo_file_name,
                 COALESCE(photo_path, '') AS photo_path
          FROM announcements
          WHERE status = 'Published' AND archived_at IS NULL
          ORDER BY publish_date DESC, created_at DESC";
  if ($limit > 0) {
    $sql .= ' LIMIT ' . (int) $limit;
  }

  $stmt = barangay_db()->prepare($sql);
  $stmt->execute();
  return $stmt->fetchAll();
}
?>
