(() => {
  document.querySelectorAll('[data-auto-sms-editor]').forEach(editor => {
    const input = editor.querySelector('[data-auto-sms-template]');
    const preview = editor.querySelector('[data-auto-sms-preview]');
    const defaults = editor.querySelector('[data-auto-sms-default]');
    const values = {resident_name: 'Sample Resident', document_type: 'Barangay Certification', reference: 'REQ-SAMPLE', fee: 'PHP 50.00'};
    function update() {
      const template = input.value.trim();
      const remaining = template.replace(/\{\{(resident_name|document_type|reference|fee)\}\}/g, '');
      const error = !template ? 'Enter a message.' : /[{}]/.test(remaining) ? 'Use only the listed placeholders.' : new TextEncoder().encode(template).length > 600 ? 'The message exceeds 600 bytes.' : '';
      input.setCustomValidity(error);
      const body = template.replace(/\{\{(resident_name|document_type|reference|fee)\}\}/g, (_, key) => values[key]);
      const prefix = editor.dataset.prefix;
      preview.textContent = prefix && !body.toLowerCase().startsWith(prefix.toLowerCase() + ':') ? `${prefix}: ${body}` : body;
    }
    input.addEventListener('input', update);
    defaults.addEventListener('click', () => { input.value = defaults.dataset.autoSmsDefault; update(); });
    update();
  });
})();
