<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SESSION = [];
$loggedInUser = ['id' => 0, 'role' => 'resident', 'user_code' => 'RES-PREVIEW'];
$profileName = 'Sample Resident With A Longer Name';
$profileContact = 'Not recorded';
$residentProfile = ['id' => 'RES-PREVIEW', 'name' => $profileName, 'purok' => 'Purok-Cortes', 'household' => 'Not recorded', 'account_status' => 'Active', 'address' => 'Barangay Luna, Surigao City, Surigao del Norte, Philippines', 'qr_status' => 'Inactive'];
ob_start();
require __DIR__ . '/../includes/profile-dropdown.php';
$markup = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $markup);
$xpath = new DOMXPath($dom);
$card = $xpath->query('//section[contains(@class,"resident-id-card--profile")]')->item(0);
if (!$card) throw new RuntimeException('Profile card did not render');
$html = str_replace('src="branding-logo.php"', 'src="../../branding-logo.php"', $dom->saveHTML($card));
$output = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"><style>body{display:block;background:#eef2ef;padding:24px;margin:0}.sample{margin:0 0 32px;max-width:100%}.sample h2{font:16px system-ui;margin:0 0 12px}.phone-frame{border:0;display:block;height:650px;max-width:100%}</style></head><body><section class="sample" style="width:740px"><h2>Desktop profile ID</h2>' . $html . '</section><h2>Mobile: 390px</h2><iframe class="phone-frame" title="Mobile profile ID" width="390" src="resident-id-mobile.html"></iframe><h2>Small phone: 320px</h2><iframe class="phone-frame" title="Small phone profile ID" width="320" src="resident-id-mobile.html"></iframe></body></html>';
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$panel = $xpath->query('//div[contains(@class,"resident-profile-panel")]')->item(0);
if (!$panel) throw new RuntimeException('Resident profile panel did not render');
$panelHtml = str_replace('src="branding-logo.php"', 'src="../../branding-logo.php"', $dom->saveHTML($panel));
file_put_contents($dir . '/resident-id-modal.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"></head><body class="app-dashboard"><div class="modal is-open">' . $panelHtml . '</div></body></html>');
file_put_contents($dir . '/resident-id.html', $output);
file_put_contents($dir . '/resident-id-mobile.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"><style>body{display:block;margin:0;padding:12px;background:#eef2ef}</style></head><body>' . $html . '</body></html>');
echo "Rendered desktop and mobile preview fixtures using the actual profile component.\n";
