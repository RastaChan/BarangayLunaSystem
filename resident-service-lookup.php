<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/resident-service-tools.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = barangay_current_user();
if (!$user || !in_array($user['role'], ['admin', 'staff'], true)) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Sign in as Admin or Staff.']); exit; }
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); throw new InvalidArgumentException('Use the lookup form.'); }
  $kind = $_POST['kind'] ?? '';
  $key = $kind === 'duplicates' ? 'locations_csrf' : 'document_request_csrf';
  if (empty($_SESSION[$key]) || !hash_equals($_SESSION[$key], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); throw new InvalidArgumentException('Refresh the page before trying again.'); }
  if ($kind === 'duplicates') {
    $name = barangay_resident_name($_POST); $date = (string) ($_POST['birthdate'] ?? '');
    $matches = barangay_resident_matches($name, $date);
    echo json_encode(['ok'=>true,'matches'=>array_map(static fn($r)=>['code'=>$r['user_code'],'name'=>$r['full_name'],'birthdate'=>$r['birthdate'],'status'=>$r['status']], $matches), 'token'=>$matches ? barangay_duplicate_token($name,$date,$matches) : '']);
  } elseif ($kind === 'resident-search') {
    echo json_encode(['ok'=>true] + barangay_issue_resident_search((string) ($_POST['search'] ?? '')));
  } elseif ($kind === 'issue') {
    $row = barangay_issue_resident_lookup((string) ($_POST['code'] ?? ''));
    if (!$row) throw new InvalidArgumentException('No resident matched. Unregistered walk-ins can use the walk-in form.');
    echo json_encode(['ok'=>true,'resident'=>$row]);
  } else throw new InvalidArgumentException('Invalid lookup.');
} catch (InvalidArgumentException $error) {
  if ((http_response_code() ?: 200) === 200) http_response_code(422);
  echo json_encode(['ok'=>false,'message'=>$error->getMessage()]);
} catch (Throwable $error) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Lookup is temporarily unavailable. Try again.']); }
