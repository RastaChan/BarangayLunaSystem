(() => {
  const list = document.querySelector('[data-staff-directory]');
  const sort = document.querySelector('[data-staff-sort]');
  if (!list || !sort) return;
  const cards = [...list.querySelectorAll('[data-staff-card]')];
  sort.addEventListener('change', () => {
    const ordered = [...cards].sort((a, b) => {
      if (sort.value === 'name') return a.dataset.staffName.localeCompare(b.dataset.staffName, undefined, {sensitivity: 'base'});
      const dates = a.dataset.staffCreated.localeCompare(b.dataset.staffCreated);
      return (sort.value === 'oldest' ? dates : -dates) || a.dataset.staffName.localeCompare(b.dataset.staffName);
    });
    ordered.forEach(card => list.appendChild(card));
  });
})();
