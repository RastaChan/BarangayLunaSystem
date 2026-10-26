<?php
require_once __DIR__ . '/database.php';

const BARANGAY_STANDARD_DOCUMENT_KEYS = ['resident_name', 'resident_name_proper', 'resident_address', 'age', 'gender', 'civil_status', 'pronoun_subject', 'pronoun_subject_lower', 'pronoun_object', 'pronoun_possessive', 'beneficiary_name', 'beneficiary_name_proper', 'beneficiary_relationship', 'beneficiary_relationship_upper', 'residency_years', 'residency_years_words', 'residency_years_text', 'partner_name', 'partner_name_proper', 'cohabitation_start_date', 'cohabitation_start_day', 'cohabitation_start_day_ordinal', 'cohabitation_start_month', 'cohabitation_start_month_upper', 'cohabitation_start_year', 'cohabitation_years', 'cohabitation_years_words', 'cohabitation_years_text', 'purpose', 'date_issued', 'date_day', 'date_day_ordinal', 'date_month', 'date_month_upper', 'date_year', 'barangay_name', 'barangay_captain', 'document_number'];

function barangay_custom_document_schema(?PDO $db = null): void {
  $db ??= barangay_db();
  static $ready = [];
  $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
  if (isset($ready[$database])) return;
  $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  foreach (['document_templates'=>'custom_fields_json', 'document_requests'=>'custom_values_json', 'issued_documents'=>'custom_values_json'] as $table=>$column) {
    if (!in_array($table, $tables, true)) continue;
    $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($column, $columns, true)) $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` MEDIUMTEXT NULL");
  }
  $ready[$database] = true;
}
function barangay_custom_type_key(string $type): string { return mb_strtolower(preg_replace('/\s+/u', ' ', trim($type))); }
function barangay_custom_json($raw): array {
  if (is_array($raw)) return $raw;
  if ($raw === null || $raw === '') return [];
  $value = json_decode((string)$raw, true);
  if (!is_array($value)) throw new InvalidArgumentException('The additional document fields could not be read. Refresh the page and try again.');
  return $value;
}
function barangay_custom_field_definitions($raw): array {
  $fields = barangay_custom_json($raw);
  if (!array_is_list($fields) || count($fields)>20) throw new InvalidArgumentException('Use up to 20 additional document fields.');
  $reserved = BARANGAY_STANDARD_DOCUMENT_KEYS;
  $result = []; $seen = [];
  foreach ($fields as $field) {
    if (!is_array($field)) throw new InvalidArgumentException('Invalid additional document field.');
    foreach (['key','label','type'] as $part) {
      if (isset($field[$part]) && !is_string($field[$part])) throw new InvalidArgumentException('Enter a valid label, Word tag and field type.');
    }
    if (isset($field['required']) && !is_bool($field['required']) && !in_array($field['required'], [0,1,'0','1'], true)) throw new InvalidArgumentException('Choose whether the additional field is required.');
    $key = trim((string)($field['key'] ?? '')); $label = trim((string)($field['label'] ?? '')); $type = (string)($field['type'] ?? 'text');
    if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/D',$key) || in_array($key,$reserved,true) || isset($seen[$key])) throw new InvalidArgumentException('Use unique Word tags with lowercase letters, numbers and underscores. Standard document tags cannot be replaced.');
    if ($label==='' || mb_strlen($label)>100 || !in_array($type,['text','textarea','number','date','select'],true)) throw new InvalidArgumentException('Enter a label and valid field type for each additional detail.');
    $options=[];
    if ($type==='select') {
      $input=$field['options'] ?? [];
      if (!is_array($input)) $input=preg_split('/\r?\n/',(string)$input);
      foreach ($input as $option) {
        if (!is_string($option) || mb_strlen($option)>100) throw new InvalidArgumentException('Keep dropdown choices within 100 characters.');
        if (trim($option)!=='') $options[]=trim($option);
      }
      $options=array_values(array_unique($options));
      if (!$options || count($options)>30) throw new InvalidArgumentException('Add between 1 and 30 dropdown choices.');
    }
    $result[]=['key'=>$key,'label'=>$label,'type'=>$type,'required'=>!empty($field['required']),'options'=>$options];$seen[$key]=true;
  }
  return $result;
}
function barangay_custom_fields_map(bool $activeOnly = true): array {
  barangay_custom_document_schema();
  $where=$activeOnly ? "WHERE status='Active'" : '';
  $rows=barangay_db()->query("SELECT document_type,custom_fields_json FROM document_templates $where ORDER BY FIELD(status,'Active','Draft','Archived'),updated_at DESC,id DESC")->fetchAll();
  $map=[];
  foreach ($rows as $row) {
    $key=barangay_custom_type_key($row['document_type']);
    if (!array_key_exists($key,$map)) $map[$key]=barangay_custom_field_definitions($row['custom_fields_json']);
  }
  return $map;
}
function barangay_custom_fields_for_type(string $type): array { return barangay_custom_fields_map()[barangay_custom_type_key($type)] ?? []; }
function barangay_custom_validate_values(array $fields, $raw, bool $preview = false): array {
  $input=barangay_custom_json($raw); $result=[];
  foreach ($fields as $field) {
    $rawValue=$input[$field['key']] ?? '';
    if (!is_scalar($rawValue) && $rawValue!==null) throw new InvalidArgumentException('Enter a valid value for '.$field['label'].'.');
    $value=trim((string)$rawValue);
    if ($value==='') {
      if ($field['required'] && !$preview) throw new InvalidArgumentException('Enter '.$field['label'].'.');
    } else {
      if (mb_strlen($value)>($field['type']==='textarea'?2000:250)) throw new InvalidArgumentException('Please shorten '.$field['label'].'.');
      if ($field['type']==='number' && (!preg_match('/^-?\d+(?:\.\d+)?$/D',$value) || !is_finite((float)$value))) throw new InvalidArgumentException('Enter a valid number for '.$field['label'].'.');
      if ($field['type']==='date') {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Choose a valid date for '.$field['label'].'.');
      }
      if ($field['type']==='select' && !in_array($value,$field['options'],true)) throw new InvalidArgumentException('Choose a listed option for '.$field['label'].'.');
    }
    $result[$field['key']]=$value;
  }
  return $result;
}
function barangay_custom_template_values(array $values): array {
  $tags=[];
  foreach ($values as $key=>$value) $tags['{{'.$key.'}}']=(string)$value;
  return $tags;
}
