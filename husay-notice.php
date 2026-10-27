<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/husay-helpers.php';
$user = barangay_current_user();
header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) { http_response_code(403); exit('Staff sign-in is required.'); }
$hearing = null;
foreach (husay_rows() as $row) if ((int)$row['id'] === (int)($_GET['id'] ?? 0)) { $hearing = $row; break; }
if ($hearing && in_array($hearing['status'], ['Pending Approval','Declined'], true)) { http_response_code(403); exit('This appointment is not approved. A notice cannot be printed.'); }
if (!$hearing) { http_response_code(404); exit('Mediation session unavailable.'); }
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mediation schedule notice</title>
<style>body{font:16px/1.6 Georgia,serif;color:#172c21;margin:40px auto;padding:0 24px;max-width:760px}h1{text-align:center;font-size:26px}header{text-align:center;border-bottom:2px solid #205d3a;padding-bottom:16px}dl{display:grid;grid-template-columns:180px 1fr;gap:12px}dt{font-weight:bold}dd{margin:0;overflow-wrap:anywhere;white-space:pre-wrap}.instructions{white-space:pre-wrap;border:1px solid #ccc;padding:16px}.print{font:inherit;padding:10px 20px;background:#176a40;color:white;border:0;border-radius:8px}@media(max-width:500px){dl{grid-template-columns:1fr;gap:4px}dd{margin-bottom:12px}}@media print{button{display:none}body{margin:0;max-width:none}}</style></head><body>
<header><strong>Barangay Luna</strong><h1>Mediation Schedule Notice</h1><p><?= $escape($hearing['complaint_code']) ?> · <?= $escape($hearing['status']) ?></p></header>
<dl><?php foreach (['Complainant'=>$hearing['complainant_name'],'Respondent'=>$hearing['respondent_name'],'Respondent address'=>$hearing['respondent_address'] ?: 'Not provided','Date'=>date('F j, Y',strtotime($hearing['starts_at'])),'Time'=>date('g:i A',strtotime($hearing['starts_at'])).'–'.date('g:i A',strtotime($hearing['ends_at'])) . ' (Philippine time)','Venue'=>$hearing['venue'],'Assigned official'=>$hearing['assigned_name']] as $label=>$value): ?><dt><?= $label ?></dt><dd><?= $escape($value) ?></dd><?php endforeach; ?></dl>
<?php if ($hearing['instructions']): ?><p class="instructions"><?= $escape($hearing['instructions']) ?></p><?php endif; ?>
<p>This is an appointment schedule. Any formal hearing notice, summons, and service must be prepared and handled by the authorized barangay official.</p>
<p>Prepared by: <?= $escape($user['name'] ?? '') ?></p><button type="button" class="print" onclick="window.print()">Print notice</button>
</body></html>
