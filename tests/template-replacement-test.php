<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/staff-functions.php';
$db = new PDO('mysql:host=' . (getenv('BARANGAY_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('BARANGAY_DB_PORT') ?: '3306'), getenv('BARANGAY_DB_USER') ?: 'root', getenv('BARANGAY_DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$database = 'template_replace_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$database`");
function verify_template(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
  $db->exec("USE `$database`");
  $db->exec('CREATE TABLE document_templates (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180), document_type VARCHAR(100), effective_year INT, content TEXT, template_file_name VARCHAR(255), template_file_path VARCHAR(255), status VARCHAR(20), updated_by INT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB');
  $values = ['name'=>'Certificate 2026 Official Format','type'=>'Certificate','year'=>2026,'content'=>'Text','file_name'=>'first.docx','file_path'=>'first.docx','status'=>'Active','user_id'=>1];
  $first = staff_save_template_replacement($db, $values);
  $values['file_name'] = $values['file_path'] = 'replacement.docx';
  verify_template(staff_save_template_replacement($db, $values) === $first, 'Replacement created another record');
  verify_template((int)$db->query('SELECT COUNT(*) FROM document_templates')->fetchColumn() === 1, 'Duplicate after repeated save');
  verify_template($db->query('SELECT template_file_path FROM document_templates')->fetchColumn() === 'replacement.docx', 'File not replaced');
  $values['file_name'] = $values['file_path'] = null;
  staff_save_template_replacement($db, $values);
  verify_template($db->query('SELECT template_file_path FROM document_templates')->fetchColumn() === 'replacement.docx', 'Blank upload erased file');
  $db->exec("INSERT INTO document_templates (document_type,effective_year,status,template_file_path,updated_at) VALUES (' CERTIFICATE ',2026,'Draft','old.docx',NOW())");
  staff_save_template_replacement($db, $values);
  verify_template((int)$db->query("SELECT COUNT(*) FROM document_templates WHERE status='Active'")->fetchColumn() === 1, 'Legacy duplicate still active');
  verify_template((int)$db->query("SELECT COUNT(*) FROM document_templates WHERE status='Archived'")->fetchColumn() === 1, 'Legacy duplicate not retired');
  $values['year'] = 2027;
  staff_save_template_replacement($db, $values);
  verify_template((int)$db->query('SELECT COUNT(*) FROM document_templates')->fetchColumn() === 3, 'New year did not retain old year');
  $saved = staff_saved_uploaded_templates([
    ['type'=>'Certificate','year'=>2026,'template_file_path'=>'replacement.docx'],
    ['type'=>' certificate ','year'=>2026,'template_file_path'=>'old.docx'],
    ['type'=>'Other','year'=>2026,'template_file_path'=>''],
  ]);
  verify_template(count($saved) === 1, 'Saved list includes duplicates or empty defaults');
  $view = file_get_contents(__DIR__ . '/../includes/staff-views/issue-documents.php');
  verify_template(!str_contains($view, 'editTemplateModal') && !str_contains($view, 'deleteTemplateModal'), 'Saved templates still editable');
  echo "PASS: repeated saves replace files, empty uploads retain files, legacy duplicates retire, new years remain separate, and saved list is read-only. Isolated database only.\n";
} finally { $db->exec("DROP DATABASE `$database`"); }
