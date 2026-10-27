<?php
require_once __DIR__ . '/husay-helpers.php';
husay_require_staff($loggedInUser);
$husayWorkspace = true;
?>
<div class="page-intro">
  <h2>Barangay Mediation</h2><p>Review complaints, propose appointments, and track approved sessions. Staff proposals need the Barangay Captain's approval. All times are Philippine time.</p>
</div>
<div class="husay-toolbar" role="group" aria-label="Add a Mediation appointment"><button type="button" class="btn btn-outline" data-husay-existing><?= $loggedInUser['role'] === 'admin' ? 'Schedule existing complaint' : 'Propose schedule for complaint' ?></button><button type="button" class="btn btn-primary" data-husay-walkin><?= $loggedInUser['role'] === 'admin' ? 'Add walk-in Mediation' : 'Record walk-in and propose schedule' ?></button></div>
<?php require __DIR__ . '/husay-panel.php'; ?>
