<?php
require_once __DIR__ . '/database.php';

function sms_automatic_defaults(): array {
  return [
    'received' => 'We received your {{document_type}} request ({{reference}}). You can track its status in My Document Requests.',
    'rejected' => 'Your {{document_type}} request ({{reference}}) was rejected. Please view the remarks in My Document Requests for the reason.',
    'ready' => 'Your {{document_type}} ({{reference}}) is ready for pickup at the Barangay Luna office. Document fee: {{fee}}. Please bring your resident ID or QR code.',
    'released' => 'Your {{document_type}} ({{reference}}) has been released. Document fee: {{fee}}. Thank you.',
  ];
}

function sms_validate_automatic_template(string $template): string {
  $template = trim($template);
  if ($template === '' || strlen($template) > 600) throw new RuntimeException('Each message must contain between 1 and 600 bytes.');
  $remaining = str_replace(['{{resident_name}}', '{{document_type}}', '{{reference}}', '{{fee}}'], '', $template);
  if (str_contains($remaining, '{') || str_contains($remaining, '}')) {
    throw new RuntimeException('Use only these placeholders: {{resident_name}}, {{document_type}}, {{reference}}, {{fee}}.');
  }
  return $template;
}

function sms_render_automatic(string $event, array $values, array $settings): string {
  $defaults = sms_automatic_defaults();
  if (!isset($defaults[$event])) throw new RuntimeException('Unknown automatic SMS event.');
  $template = sms_validate_automatic_template((string) ($settings['sms_template_' . $event] ?? $defaults[$event]));
  return strtr($template, [
    '{{resident_name}}' => (string) ($values['name'] ?? ''),
    '{{document_type}}' => (string) ($values['type'] ?? ''),
    '{{reference}}' => (string) ($values['reference'] ?? ''),
    '{{fee}}' => 'PHP ' . number_format(max(0, (float) ($values['fee'] ?? 0)), 2),
  ]);
}

function sms_save_automatic_settings(array $user, array $input): void {
  if (!in_array($user['role'] ?? '', ['admin', 'staff'], true)) throw new RuntimeException('Access denied.');
  $values = [];
  foreach (sms_automatic_defaults() as $event => $default) {
    $values['sms_auto_' . $event] = isset($input[$event]) ? '1' : '0';
    $values['sms_template_' . $event] = sms_validate_automatic_template((string) ($input['templates'][$event] ?? $default));
  }
  $stmt = barangay_db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
  foreach ($values as $key => $value) $stmt->execute([$key, $value, $user['id']]);
}
