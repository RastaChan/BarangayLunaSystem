(() => {
  'use strict';
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.querySelectorAll('[data-report-date-picker]').forEach(input => {
    const field = input.closest('.report-date-field');
    const text = field.querySelector('[data-report-date-text]');
    function update() {
      const parts = input.value.split('-');
      text.textContent = parts.length === 3 && months[Number(parts[1])-1] ? `${months[Number(parts[1])-1]}/${Number(parts[2])}/${parts[0]}` : 'Choose date';
    }
    input.addEventListener('input', update);
    input.addEventListener('change', update);
    input.addEventListener('click', () => {
      if (typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch { /* The native calendar remains available. */ }
      }
    });
    update();
  });
})();
