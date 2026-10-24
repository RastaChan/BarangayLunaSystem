(() => {
  'use strict';
  const search = document.querySelector('[data-request-list-search]');
  const status = document.querySelector('[data-request-list-status]');
  const list = document.querySelector('[data-live-overview="resident-request-tracking"]');
  const empty = document.querySelector('[data-request-list-empty]');
  if (!search || !status || !list || !empty) return;
  function filter() {
    const query = search.value.trim().toLowerCase();
    const rows = [...list.querySelectorAll('[data-request-list-row]')];
    let visible = 0;
    rows.forEach(row => {
      row.hidden = !row.textContent.toLowerCase().includes(query) || (status.value !== '' && row.dataset.requestStatus !== status.value);
      if (!row.hidden) visible++;
    });
    empty.hidden = rows.length === 0 || visible !== 0;
  }
  search.addEventListener('input', filter); status.addEventListener('change', filter);
  new MutationObserver(filter).observe(list, {childList:true,subtree:true});
  filter();
})();
