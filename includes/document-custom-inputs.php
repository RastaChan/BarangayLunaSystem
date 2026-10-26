<?php
require_once __DIR__ . '/document-custom-fields.php';
$customFieldMap = barangay_custom_fields_map();
$customInitialValues = $customInitialValues ?? [];
?>
<section class="document-custom-fields" data-document-custom-fields data-custom-map="<?= htmlspecialchars(json_encode($customFieldMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>" data-custom-values="<?= htmlspecialchars(json_encode($customInitialValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>" hidden>
  <h4>Additional document details</h4>
  <div class="form-grid-2" data-custom-inputs></div>
</section>
