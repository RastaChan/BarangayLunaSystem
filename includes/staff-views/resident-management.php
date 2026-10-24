<?php
$locationView = 'resident-management';
$residentManagementStaff = true;
$adminResidents = array_map(static function ($resident) {
  $resident['mobile'] = $resident['contact'] ?? '';
  $resident['registered'] = $resident['date'] ?? '';
  return $resident;
}, $residentRecords);
require __DIR__ . '/../views/residents.php';
?>
<div id="viewResidentModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="residentManagementTitle">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel modal-panel--wide">
    <header class="modal-header"><h2 id="residentManagementTitle">Resident Identification Profile</h2><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></header>
    <div class="modal-body"><?php $residentPreview = []; include __DIR__ . '/../resident-id-preview-card.php'; ?></div>
    <footer class="modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </div>
</div>
