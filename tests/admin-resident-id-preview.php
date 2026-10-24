<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents(__DIR__ . '/../dashboard.php');
$start = strpos($source, '<section class="resident-id-card resident-id-card--admin"');
$end = strpos($source, '</section>', $start);
$card = substr($source, $start, $end - $start + 10);
$card = str_replace('branding-logo.php', '../../assets/images/barangay-logo.png', $card);
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($dir . '/admin-resident-id.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css?v=' . filemtime(__DIR__ . '/../css/dashboard.css') . '"></head><body class="app-dashboard" style="display:block;padding:16px;overflow:auto;height:auto">' . $card . '</body></html>');
file_put_contents($dir . '/admin-resident-id-phone.html', '<!doctype html><html><body><iframe title="Phone ID preview" src="admin-resident-id.html" style="width:320px;height:700px;border:0"></iframe></body></html>');
echo "Generated actual Admin ID markup with no resident data.\n";
