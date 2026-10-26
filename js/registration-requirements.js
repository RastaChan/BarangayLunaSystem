(() => {
  const previous = new WeakMap();
  document.addEventListener('change', event => {
    const input = event.target.closest('[data-requirement-file]');
    if (!input) return;
    const row = input.closest('[data-registration-requirement]');
    const status = row?.querySelector('[data-requirement-status]');
    if (!status) return;
    if (input.files?.length) {
      if (!previous.has(input)) previous.set(input, status.value);
      status.value = 'Submitted';
      // Notify the shared dropdown UI so its visible label updates as well.
      status.dispatchEvent(new Event('change', {bubbles: true}));
    } else if (previous.has(input)) {
      if (status.value === 'Submitted') {
        status.value = previous.get(input);
        status.dispatchEvent(new Event('change', {bubbles: true}));
      }
      previous.delete(input);
    }
  });
  document.addEventListener('reset', event => {
    event.target.querySelectorAll('[data-requirement-file]').forEach(input => previous.delete(input));
  });
})();
