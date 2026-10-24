<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/staff-functions.php';
require_once __DIR__.'/includes/document-template-helpers.php';
require_once __DIR__.'/includes/document-pdf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$respond = static function (array $data, int $status=200): never { http_response_code($status); echo json_encode($data); exit; };
$user = barangay_current_user();
if (!$user || !in_array($user['role'] ?? '',['admin','staff'],true)) $respond(['ok'=>false,'message'=>'Please sign in again to prepare the print preview.'],401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $respond(['ok'=>false,'message'=>'Use the print preview button.'],405);
if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['document_request_csrf']) || !hash_equals($_SESSION['document_request_csrf'],$_POST['csrf'])) $respond(['ok'=>false,'message'=>'Refresh the print screen and try again.'],403);
$id = filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) $respond(['ok'=>false,'message'=>'Document not found.'],404);
// Release the session lock before Word starts so other portal actions can continue.
session_write_close();
try {
  $statement = barangay_db()->prepare('SELECT generated_file_path FROM issued_documents WHERE id = ?'); $statement->execute([$id]);
  $path=$statement->fetchColumn();
  if (!$path) $respond(['ok'=>false,'message'=>'Printable document not found.'],404);
  $result=barangay_prepare_document_pdf(staff_docx_absolute_path($path));
  $respond($result);
} catch (Throwable $error) { $respond(['ok'=>false,'message'=>'The print preview could not be prepared. Please retry or download the DOCX.'],500); }
