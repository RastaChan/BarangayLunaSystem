<?php
function staff_view_url(string $view, array $params = []): string { return 'dashboard.php?' . http_build_query(['view'=>$view] + $params); }
$residentRegistrations = [];
for ($i = 1; $i <= 45; $i++) $residentRegistrations[] = [
  'id'=>"RES-$i",'db_id'=>$i,'name'=>"Resident $i",'contact'=>'09123456789','address'=>'Test address',
  'purok'=>$i % 2 ? 'Purok One' : 'Purok Two','household'=>"HH-$i",
  'status'=>$i % 2 ? 'Active' : 'Inactive','qr_status'=>'Active','qr_code'=>"QR-$i",
  'date'=>'2026-10-10','registered_by'=>'Test Staff',
];
$locationLists = ['districts'=>[]]; $locationAssignments = [];
$render = static function () use (&$residentRegistrations, &$locationLists, &$locationAssignments) {
  ob_start(); require __DIR__ . '/../includes/registration-record-cards.php'; return ob_get_clean();
};
$_GET = ['registration_page'=>'2'];
$html = $render();
if (substr_count($html, '<article ') !== 20 || !str_contains($html,'id="resident-status-RES-21"')) throw new RuntimeException('Incorrect page');
if (!str_contains($html,'form="resident-status-RES-21"') || substr_count($html,'value="generate_resident_qr"') !== 20) throw new RuntimeException('Save or Generate QR action was lost');
if (!str_contains($html,'requirements_resident=21')) throw new RuntimeException('Requirements link was lost');
$_GET = ['registration_search'=>'HH-45','registration_status'=>'Active','registration_purok'=>'One'];
$html = $render();
if (substr_count($html,'<article ') !== 1 || !str_contains($html,'RES-45')) throw new RuntimeException('Combined filters failed');
$_GET['registration_status'] = 'Inactive';
if (!str_contains($render(),'No residents match')) throw new RuntimeException('Empty result message missing');
echo "PASS: registration card filters, pagination, Requirements, QR generation and Save associations.\n";
