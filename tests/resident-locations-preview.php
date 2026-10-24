<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SESSION = [];
$loggedInUser = ['role' => 'admin'];
ob_start();
require __DIR__ . '/../includes/resident-location-controls.php';
resident_location_fields($locationLists);
$html = ob_get_clean();
$html .= '<script src="js/location-disclosures.js?v=' . filemtime(__DIR__ . '/../js/location-disclosures.js') . '"></script>';
$doc = new DOMDocument();
@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($doc);
// Keep production residents and session tokens out of the public visual fixture.
foreach ($xpath->query('//select[@name="resident_code"]') as $select) {
  while ($select->firstChild) $select->removeChild($select->firstChild);
}
foreach ($xpath->query('//input[@name="csrf"]') as $input) $input->setAttribute('value', '');
foreach ($xpath->query('//form[@method="post"]') as $form) $form->setAttribute('action', '#');
$html = str_replace('src="js/', 'src="../../js/', $doc->saveHTML());
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($dir . '/resident-locations.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"><link rel="stylesheet" href="../../css/mobile-select.css"></head><body class="app-dashboard" style="display:block;overflow:auto;height:auto;padding:20px"><main style="max-width:850px;margin:auto">' . $html . '</main><script src="../../js/mobile-select.js"></script></body></html>');
echo "Generated location-controls fixture without resident data.\n";
file_put_contents($dir . '/resident-locations-phone.html', '<!doctype html><html><body style="margin:0"><iframe title="Phone location controls" src="resident-locations.html" style="border:0;width:390px;height:800px"></iframe></body></html>');
