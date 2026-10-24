(() => {
  'use strict';
  document.querySelectorAll('[data-complaint-list]').forEach(root => {
    if (root.dataset.complaintSearchReady) return;
    root.dataset.complaintSearchReady = 'true';
    const input = root.querySelector('[data-complaint-search-input]');
    const list = root.querySelector('tbody');
    const empty = root.querySelector('[data-complaint-search-empty]');
    if (!input || !list || !empty) return;
    function filter() {
      const words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      const rows = [...list.querySelectorAll('[data-complaint-search]')];
      let visible = 0;
      rows.forEach(row => {
        const text = row.dataset.complaintSearch.toLowerCase();
        row.hidden = !words.every(word => text.includes(word));
        if (!row.hidden) visible++;
      });
      empty.hidden = rows.length === 0 || visible > 0;
    }
    input.addEventListener('input', filter);
    input.addEventListener('search', filter);
    new MutationObserver(filter).observe(list, {childList: true, subtree: true, characterData: true});
    filter();
  });
})();
