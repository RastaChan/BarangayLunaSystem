<?php
require_once __DIR__ . '/husay-helpers.php';
husay_require_staff($loggedInUser);
$husayWorkspace = true;
?>
<div class="page-intro">
  <h2>Husay Schedule</h2><p>Plan appointments, check availability, and keep track of each session. All times are Philippine time.</p>
</div>
<div class="husay-toolbar" role="group" aria-label="Add a Husay appointment"><button type="button" class="btn btn-outline" data-husay-existing>Schedule existing complaint</button><button type="button" class="btn btn-primary" data-husay-walkin>Add walk-in Husay</button></div>
<?php require __DIR__ . '/husay-panel.php'; ?>
