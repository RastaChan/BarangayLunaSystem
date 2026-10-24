<?php
require_once __DIR__ . '/database.php';

function barangay_audit_filters(array $input): array {
  $text = static fn(string $key): string => is_string($input[$key] ?? null) ? mb_substr(trim($input[$key]), 0, 150) : '';
  return ['search' => $text('search'), 'audit_action' => $text('audit_action')];
}

function barangay_audit_rows(array $filters, ?int $limit = 100): array {
  $where = [];
  $params = [];
  if ($filters['search'] !== '') {
    // Bound parameters keep search text out of SQL; escape LIKE wildcards literally.
    $pattern = '%' . strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $parts = [];
    foreach (['user_name', 'user_role', 'action', 'module', 'status'] as $index => $column) {
      $parts[] = "$column LIKE :search$index ESCAPE '!'";
      $params[":search$index"] = $pattern;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
  }
  if ($filters['audit_action'] !== '') {
    $where[] = 'action = :action';
    $params[':action'] = $filters['audit_action'];
  }
  $sql = 'SELECT created_at AS date, user_name AS user, user_role AS role, action, module, status FROM audit_logs';
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY created_at DESC, id DESC';
  if ($limit !== null) $sql .= ' LIMIT ' . max(1, min(1000, $limit));
  $statement = barangay_db()->prepare($sql);
  $statement->execute($params);
  return $statement->fetchAll();
}
