<?php
require_once __DIR__.'/../includes/revenue-export.php';
function check_export(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$payments = [
  ['PAY-001','BL-001','Barangay Certification',150,200,'000123','2026-10-10 12:31:00'],
  ['PAY-002','BL-002','=HYPERLINK("test")',150,150,'Peña & Sons','2026-10-10 13:31:00'],
];
$rows = barangay_revenue_export_rows('2026-10-01','2026-10-10',['revenue'=>300,'cash_received'=>350,'change_due'=>50],$payments);
check_export($rows[2][1] === 300.0 && count($rows) === 9,'Summary and all payments retained');
$path = tempnam(sys_get_temp_dir(),'luna_export_test_');
try {
  file_put_contents($path,barangay_revenue_export_xlsx($rows));
  $zip = new ZipArchive(); check_export($zip->open($path) === true,'Valid Excel ZIP');
  for ($i=0;$i<$zip->numFiles;$i++) check_export(simplexml_load_string($zip->getFromIndex($i)) !== false,'All workbook XML parses');
  $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
  $sheet->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
  $cell = static function($ref) use($sheet) { return $sheet->xpath('//s:c[@r="'.$ref.'"]')[0]; };
  check_export((string)$cell('G8')['t'] === 'inlineStr' && (string)$cell('G8')->is->t === 'Oct/10/2026 12:31','Dates remain readable explicit text with named months');
  check_export((string)$cell('F8')->is->t === '000123','Receipt leading zeros preserved');
  check_export((string)$cell('F9')->is->t === 'Peña & Sons','Unicode and XML characters retained');
  check_export((string)$cell('C9')['t'] === 'inlineStr' && count($sheet->xpath('//s:f')) === 0,'Untrusted values never become formulas');
  check_export((float)$cell('D8')->v === 150.0 && (string)$cell('D8')['s'] === '2','Money is numeric with decimal formatting');
  check_export((float)$sheet->cols->col[6]['width'] >= 26,'Payment dates have sufficient column width');
  check_export((string)$sheet->autoFilter['ref'] === 'A7:G9','Filter includes every payment');
  $zip->close();
  check_export(count(barangay_revenue_export_rows('2026-10-01','2026-10-10',['revenue'=>0,'cash_received'=>0,'change_due'=>0],[])) === 7,'Empty periods export headings and zero totals');
  echo "PASS: revenue Excel dates, widths, totals, money, receipt IDs, Unicode, formula safety and empty periods.\n";
} finally { if (is_file($path)) unlink($path); }
