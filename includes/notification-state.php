<?php
require_once __DIR__ . '/database.php';

function barangay_notification_state_schema(): void {
  static $ready = false;
  if ($ready) return;
  barangay_db()->exec("CREATE TABLE IF NOT EXISTS notification_seen_state (
    user_id INT NOT NULL,
    state_kind VARCHAR(12) NOT NULL,
    notification_key VARCHAR(190) NOT NULL,
    PRIMARY KEY (user_id, state_kind, notification_key)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $ready = true;
}

function barangay_notification_state(int $userId): array {
  barangay_notification_state_schema();
  $state = ['seen' => [], 'read' => [], 'unread' => [], 'dismissed' => []];
  $stmt = barangay_db()->prepare('SELECT state_kind, notification_key FROM notification_seen_state WHERE user_id = ?');
  $stmt->execute([$userId]);
  foreach ($stmt->fetchAll() as $row) {
    if (isset($state[$row['state_kind']])) $state[$row['state_kind']][] = $row['notification_key'];
  }
  return $state;
}

function barangay_save_notification_state(int $userId, string $kind, array $ids): void {
  if (!in_array($kind, ['seen', 'read', 'unread', 'dismissed'], true)) throw new InvalidArgumentException('Invalid notification state');
  barangay_notification_state_schema();
  $stmt = barangay_db()->prepare('INSERT IGNORE INTO notification_seen_state (user_id, state_kind, notification_key) VALUES (?, ?, ?)');
  foreach ($ids as $id) {
    if (!is_string($id) || strlen($id) > 190 || !str_starts_with($id, 'resident-')) continue;
    $db = barangay_db();
    $db->beginTransaction();
    try {
      if (in_array($kind, ['read', 'unread'], true)) {
        $delete = $db->prepare('DELETE FROM notification_seen_state WHERE user_id = ? AND state_kind = ? AND notification_key = ?');
        $delete->execute([$userId, $kind === 'read' ? 'unread' : 'read', $id]);
      }
      $stmt->execute([$userId, $kind, $id]);
      $db->commit();
    } catch (Throwable $error) {
      $db->rollBack();
      throw $error;
    }
  }
}
