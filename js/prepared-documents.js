(() => {
  const list = document.querySelector('[data-prepared-list]');
  const form = document.querySelector('[data-remove-prepared-form]');
  if (!list || !form) return;
  const modal = document.getElementById('removePreparedModal');
  const error = form.querySelector('[data-remove-prepared-error]');
  const submit = form.querySelector('[type="submit"]');
  let removing = false;
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-prepared]');
    if (!button || removing) return;
    const row = button.closest('[data-prepared-document-id]');
    form.reset();
    form.querySelector('[name="issued_id"]').value = row.dataset.preparedDocumentId;
    form.querySelector('[data-remove-prepared-name]').textContent = row.querySelector('p').textContent;
    error.hidden = true;
    window.barangayOpenModal(modal.id);
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (removing) return;
    removing = true;
    submit.disabled = true;
    error.hidden = true;
    const id = form.querySelector('[name="issued_id"]').value;
    try {
      const response = await fetch(form.getAttribute('action'), {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: {'X-Requested-With': 'PreparedRemoval'}
      });
      if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Your session may have expired. Sign in again and refresh the prepared list.');
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'The entry could not be removed. Try again.');
      list.querySelector(`[data-prepared-document-id="${Number(id)}"]`)?.remove();
      const count = list.querySelectorAll('[data-prepared-document-id]').length;
      document.querySelector('[data-prepared-count]').textContent = String(count);
      document.querySelector('[data-prepared-empty]').hidden = count > 0;
      const printModal = document.getElementById('issuancePrintModal');
      if (Number(printModal?.dataset.documentId) === Number(id)) {
        delete printModal.dataset.prepared;
        delete printModal.dataset.previewUrl;
        const frame = printModal.querySelector('[data-issuance-print-frame]');
        frame.hidden = true;
        frame.removeAttribute('src');
        const number = document.querySelector('#issueDocumentForm [name="document_number"]');
        if (number) {
          number.value = 'BL-' + new Date().toISOString().replace(/\D/g, '').slice(0, 14) + '-' + crypto.getRandomValues(new Uint32Array(1))[0].toString(16).padStart(8, '0').toUpperCase();
          number.dispatchEvent(new Event('input', {bubbles: true}));
        }
      }
      modal.querySelector('.modal-close').click();
    } catch (failure) {
      error.textContent = failure.message || 'Check your connection and try again.';
      error.hidden = false;
    } finally {
      removing = false;
      submit.disabled = false;
    }
  });
})();
