<?php
require_once __DIR__ . '/document-custom-fields.php';
$_SESSION['template_fields_csrf'] ??= bin2hex(random_bytes(32));
?>
<section class="document-custom-builder" data-custom-builder data-custom-reserved="<?= htmlspecialchars(json_encode(BARANGAY_STANDARD_DOCUMENT_KEYS), ENT_QUOTES) ?>" data-custom-builder-map="<?= htmlspecialchars(json_encode(barangay_custom_fields_map(false), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>">
  <input type="hidden" name="template_fields_csrf" value="<?= htmlspecialchars($_SESSION['template_fields_csrf'], ENT_QUOTES) ?>">
  <input type="hidden" name="custom_field_definitions" value="[]">
  <div class="custom-builder-heading"><h4>Additional document fields</h4><button type="button" class="btn btn-outline btn-sm" data-custom-add>Add field</button></div>
  <p class="field-hint">Enter the details this document needs. Tags are created automatically. Click Copy tag, paste it into your Word document where the answer belongs, then upload the DOCX and save.</p>
  <div data-custom-builder-rows></div>
  <p class="field-hint" data-custom-builder-empty>No additional fields. Standard resident and document details still fill automatically.</p>
  <section class="custom-form-preview" aria-label="Resident form preview">
    <h5>Resident form preview</h5>
    <p class="field-hint">Residents and staff will see these fields when they select this document.</p>
    <div class="form-grid-2" data-custom-form-preview></div>
    <p class="field-hint" data-custom-preview-empty>Add a field above to preview it here.</p>
  </section>
  <p class="custom-setup-status" role="status" aria-live="polite" data-custom-setup-status></p>
</section>
