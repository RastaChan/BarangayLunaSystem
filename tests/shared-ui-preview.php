<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SESSION = [];
$systemSettings = ['contact_number' => '', 'hotline_number' => ''];
ob_start();
require __DIR__ . '/../includes/views/system-settings.php';
$settings = ob_get_clean();
$settings = str_replace('branding-logo.php', '../../assets/images/barangay-logo.png', $settings);
$dir = __DIR__ . '/../storage/ui-previews';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$components = <<<'HTML'
<div class="page-intro"><h2>Resident services</h2></div>
<div class="stats-grid"><article class="stat-card"><div class="label">Registered residents</div><div class="value">128</div></article><article class="stat-card"><div class="label">Documents</div><div class="value">24</div></article><article class="stat-card"><div class="label">Complaints</div><div class="value">3</div></article><article class="stat-card"><div class="label">Feedback</div><div class="value">12</div></article></div>
<div class="grid-2"><section class="panel-card"><div class="panel-head"><h3>Resident registration</h3></div><div class="panel-body"><form class="form-grid"><div class="form-grid-2"><div class="field"><label>First name<input value="Sample resident"></label></div><div class="field"><label>Last name<input value="Long family name"></label></div></div><div class="field"><label>Address<input value="Barangay Luna, Surigao City, Surigao del Norte, Philippines"></label></div><div class="form-grid-2"><div class="field"><label>District<select><option>District 1</option></select></label></div><div class="field"><label>Purok<select><option>A longer purok name for layout testing</option></select></label></div></div><button type="button" class="btn btn-primary">Save resident account</button></form></div></section><section class="panel-card"><div class="panel-head"><h3>Document requests</h3><button class="btn btn-outline" type="button">View all requests</button></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Resident</th><th>Document</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td>Sample resident</td><td>Barangay Certification</td><td><span class="badge badge-pending">Pending</span></td><td class="actions"><button class="btn-icon">View</button><button class="btn-icon">Process request</button><button class="btn-icon btn-danger">Delete</button></td></tr></tbody></table></div></section></div>
HTML;
foreach (['components' => $components, 'settings' => $settings] as $name => $content) {
  $shell = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../../css/dashboard.css"><link rel="stylesheet" href="../../css/mobile-select.css"></head><body class="app-dashboard role-admin"><aside class="sidebar"><div class="sidebar-brand"><img class="sidebar-logo" src="../../assets/images/barangay-logo.png" alt="Barangay Luna seal"><div class="sidebar-brand-text"><strong>Barangay Administrator</strong><span>Admin Portal</span></div></div><nav class="sidebar-nav"><a class="nav-link active">Dashboard</a><a class="nav-link">Resident Management</a><a class="nav-link">System Settings</a></nav></aside><main class="main-area"><header class="top-header"><div class="header-title"><h1>Barangay Luna</h1><p>Surigao City, Surigao del Norte</p></div><button class="btn btn-outline" type="button">Account</button></header><div class="content-area">' . $content . '</div></main><script src="../../js/mobile-select.js"></script></body></html>';
  file_put_contents($dir . '/shared-' . $name . '.html', $shell);
  file_put_contents($dir . '/shared-' . $name . '-phone.html', '<!doctype html><html><body style="margin:0"><iframe title="Phone layout" src="shared-' . $name . '.html" style="border:0;width:390px;height:820px"></iframe><iframe title="Small phone layout" src="shared-' . $name . '.html" style="border:0;width:320px;height:820px"></iframe></body></html>');
}
echo "Generated shared layout and actual settings previews with test-only values.\n";
