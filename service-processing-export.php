<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report-metrics.php';
$user = barangay_current_user();
if (!$user || !in_array($user['role'],['admin','staff'],true)) { http_response_code(403); exit('Sign in as Admin or Staff.'); }
try { [$from,$to]=barangay_report_dates($_GET); $metrics=barangay_service_metrics($from,$to); }
catch (InvalidArgumentException $error) { http_response_code(422); exit($error->getMessage()); }
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="document-processing-times.csv"');
$out=fopen('php://output','w');
fputcsv($out,['Period',$from,$to]);
fputcsv($out,['Measure','Measured records','Average elapsed seconds']);
foreach (['ready'=>'Request to preparation','release'=>'Request to release','walkin'=>'Walk-in preparation to release'] as $key=>$label) fputcsv($out,[$label,$metrics[$key]['measured'],$metrics[$key]['seconds'] ?? 'Not measured']);
fputcsv($out,['Current open requests',$metrics['pending']['total']]);
fputcsv($out,[]);
fputcsv($out,['Oldest open requests (all dates, up to 20)']);
fputcsv($out,['Request','Document','Status','Submitted','Age seconds']);
foreach ($metrics['open'] as $row) fputcsv($out,array_map(static fn($value)=>preg_match('/^[=+@\-\t\r\n]/',(string)$value) ? "'".$value : $value,array_values($row)));
