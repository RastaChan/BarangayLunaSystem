<?php
/** Format user-facing timestamps without changing stored values or identity keys. */
function barangay_display_datetime(?string $value): string {
  $value = $value ?? '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/', $value)) return $value;
  $timestamp = strtotime($value);
  return $timestamp === false ? $value : date('M j, Y g:i A', $timestamp);
}
