<section class="template-preparation-tool" data-template-preparation-tool>
  <div class="custom-builder-heading"><h4>Prepare a Word template automatically</h4><button type="button" class="btn btn-outline" data-template-analyze>Analyze uploaded DOCX</button></div>
  <p class="field-hint">Choose an ordinary Word file above. Review suggested fill-in fields, then download a prepared copy or use it here. Existing tags are kept. Logos, backgrounds and Word formatting remain in the file.</p>
  <p class="custom-setup-status" role="status" aria-live="polite" data-template-preparation-status></p>
  <div data-template-preparation-review hidden>
    <h5>Review text replacements</h5>
    <p class="field-hint">Check each piece of text and the field that will replace it. Uncheck suggestions you want to keep as written.</p>
    <div data-template-replacements></div>
    <button type="button" class="btn btn-outline btn-sm" data-template-manual-add>Add a text replacement</button>
    <details class="template-extra-field"><summary>Add another field choice</summary>
      <div class="form-grid-2"><label class="field">Field label<input type="text" maxlength="100" data-template-extra-label placeholder="Example: Business permit number"></label><label class="field">Field type<select data-template-extra-type><option value="text">Text</option><option value="textarea">Long text</option><option value="number">Number</option><option value="date">Date</option></select></label></div>
      <button type="button" class="btn btn-outline btn-sm" data-template-extra-add>Add field choice</button>
    </details>
    <h5>Text preview</h5>
    <p class="field-hint">Highlighted tags show the selected replacements. This preview shows text; the downloaded DOCX keeps the original design.</p>
    <div class="template-text-preview" data-template-text-preview></div>
    <label class="check check-left"><input type="checkbox" data-template-reviewed><span>I checked the highlighted replacements and existing tags.</span></label>
    <div class="template-preparation-buttons"><button type="button" class="btn btn-outline" data-template-download disabled>Download prepared DOCX</button><button type="button" class="btn btn-primary" data-template-use disabled>Use in Template Management</button></div>
  </div>
</section>
