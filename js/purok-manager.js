document.querySelectorAll('[data-purok-manager]').forEach(form => {
  const district = form.querySelector('[data-manager-district]');
  const add = form.querySelector('[data-add-purok]');
  const list = form.querySelector('[data-manager-puroks]');
  let next = 0;
  const refresh = () => {
    add.disabled = !district.value || district.value === 'unassigned';
    form.querySelectorAll('[data-manager-name]').forEach(field => {
      field.hidden = field.dataset.managerName !== district.value;
    });
    list.querySelectorAll('[data-manager-purok]').forEach(row => {
      const value = row.querySelector('[data-purok-district]').value;
      row.hidden = !district.value || (value || 'unassigned') !== district.value;
    });
  };
  district.addEventListener('change', refresh);
  add.addEventListener('click', () => {
    if (!district.value || district.value === 'unassigned') return;
    const row = form.querySelector('[data-purok-template]').content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-new-field]').forEach(input => {
      input.name = `new_puroks[${next}][${input.dataset.newField}]`;
    });
    next++;
    row.querySelector('[data-purok-district]').value = district.value;
    row.querySelector('[data-discard-purok]').addEventListener('click', () => row.remove());
    const name = row.querySelector('[data-new-field="name"]');
    name.addEventListener('input', () => { row.querySelector('summary').textContent = name.value.trim() || 'New purok'; });
    list.append(row);
    name.focus();
  });
  // Reveal invalid fields even when their district or purok is collapsed.
  form.addEventListener('invalid', event => {
    const row = event.target.closest('[data-manager-purok]');
    const field = event.target.closest('[data-manager-name]');
    if (row) {
      district.value = row.querySelector('[data-purok-district]').value || 'unassigned';
      refresh();
      row.hidden = false;
      row.open = true;
    } else if (field) {
      district.value = field.dataset.managerName;
      refresh();
    }
  }, true);
  refresh();
});
