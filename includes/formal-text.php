<?php
// Capitalize word initials without lowercasing intentional spelling or acronyms.
const BARANGAY_FORMAL_FIELDS = ['first_name','middle_name','last_name','full_name','suffix',
  'address','resident_name','beneficiary_name','partner_name','walkin_name','walkin_address',
  'complainant_name','respondent_name','respondent_address','venue','position','position_title',
  'guardian_name','spouse_name','father_name','mother_name'];

function barangay_formal_text(string $value): string {
  return preg_replace_callback("/(^|[\\s\\-'’(\\/])(\\p{Ll})/u", static fn(array $match): string => $match[1] . mb_strtoupper($match[2], 'UTF-8'), $value) ?? $value;
}

function barangay_formal_inputs(array $input): array {
  foreach (BARANGAY_FORMAL_FIELDS as $key) if (isset($input[$key]) && is_string($input[$key])) $input[$key] = barangay_formal_text($input[$key]);
  return $input;
}
