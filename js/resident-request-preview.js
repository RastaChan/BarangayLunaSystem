(() => {
  'use strict';
  const form = document.querySelector('[data-resident-request-form]');
  const button = document.querySelector('[data-resident-request-preview]');
  const modal = document.getElementById('residentRequestPreviewModal');
  if (!form || !button || !modal) return;
  const frame = modal.querySelector('[data-resident-preview-frame]');
  const status = modal.querySelector('[data-resident-preview-status]');
  let version = 0;
  button.addEventListener('click', async () => {
    const current = ++version;
    frame.hidden = true; frame.removeAttribute('src');
    status.textContent = 'Preparing your document preview…';
    const payload = new FormData();
    for (const name of ['preview_csrf','document_type','purpose','residency_years','beneficiary_name','beneficiary_relationship','partner_name','cohabitation_start_date']) payload.set(name, form.elements[name]?.value || '');
    try {
      const response = await fetch('resident-document-preview.php', {method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json'},signal:AbortSignal.timeout(45000)});
      const result = await response.json();
      if (current !== version) return;
      if (!response.ok || !result.success) throw new Error(result.message || 'Preview could not be prepared. Please retry.');
      frame.src = result.url; frame.hidden = false; status.textContent = '';
    } catch (error) {
      if (current === version) status.textContent = error.name === 'TimeoutError' ? 'The preview is taking longer than expected. Please retry.' : error.message;
    }
  });
  new MutationObserver(() => {
    if (modal.getAttribute('aria-hidden') === 'true') { version++; frame.removeAttribute('src'); frame.hidden = true; }
  }).observe(modal, {attributes:true,attributeFilter:['aria-hidden']});
})();

