(() => {
  'use strict';
  document.querySelectorAll('[data-template-auto]').forEach(form => {
    const type = form.querySelector('[name="document_type"]');
    const custom = form.querySelector('[name="document_type_custom"]');
    const name = form.querySelector('[name="name"]');
    const year = form.querySelector('[name="effective_year"]');
    const upload = form.querySelector('[name="template_docx"]');
    const isNew = form.querySelector('[name="action"]').value === 'save_template';
    const originalYear = year.value;
    const currentYear = form.dataset.templateCurrentYear;
    function sync() {
      const label = (type.value === '__new__' ? custom?.value || '' : type.value).trim();
      year.value = isNew || upload?.files?.length ? currentYear : originalYear;
      name.value = label ? `${label} ${year.value} Official Format` : '';
      name.placeholder = label ? '' : 'Enter a document type first';
    }
    type.addEventListener('change', sync);
    custom?.addEventListener('input', sync);
    upload?.addEventListener('change', sync);
    form.addEventListener('submit', sync);
    form.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
  });
})();
