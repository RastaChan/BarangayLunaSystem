(() => {
  'use strict';
  const ledger = document.querySelector('[data-payment-directory]');
  if (!ledger) return;
  const rows = [...ledger.querySelectorAll('[data-payment-card]')];
  const search = ledger.querySelector('[data-payment-search]');
  const from = ledger.querySelector('[data-payment-from]');
  const to = ledger.querySelector('[data-payment-to]');
  const previous = ledger.querySelector('[data-payment-previous]');
  const next = ledger.querySelector('[data-payment-next]');
  let page = 1;
  const pageSize = 20;
  function render() {
    const query = search.value.trim().toLowerCase();
    const invalid = from.value && to.value && from.value > to.value;
    ledger.querySelector('[data-payment-date-error]').hidden = !invalid;
    const matches = rows.filter(row => row.textContent.toLowerCase().includes(query)
      && (invalid || ((!from.value || row.dataset.date >= from.value) && (!to.value || (row.dataset.date && row.dataset.date <= to.value)))));
    const pages = Math.max(1, Math.ceil(matches.length / pageSize));
    page = Math.min(page, pages);
    rows.forEach(row => { row.hidden = true; });
    matches.slice((page - 1) * pageSize, page * pageSize).forEach(row => { row.hidden = false; });
    ledger.querySelector('[data-payment-empty]').hidden = matches.length > 0;
    ledger.querySelector('[data-payment-count]').textContent = matches.length ? `Showing ${(page - 1) * pageSize + 1}–${Math.min(page * pageSize, matches.length)} of ${matches.length} payments` : 'Showing 0 payments';
    ledger.querySelector('[data-payment-page]').textContent = `${page} / ${pages}`;
    previous.disabled = page === 1; next.disabled = page === pages;
  }
  const filter = () => { page = 1; render(); };
  search.addEventListener('input', filter);
  from.addEventListener('change', filter); to.addEventListener('change', filter);
  previous.addEventListener('click', () => { if (page > 1) { page--; render(); } });
  next.addEventListener('click', () => { page++; render(); });
  const toggle = ledger.querySelector('[data-payment-date-toggle]');
  toggle.addEventListener('click', () => {
    const filters = ledger.querySelector('#paymentDateFilters');
    filters.hidden = !filters.hidden; toggle.setAttribute('aria-expanded', String(!filters.hidden));
  });
  ledger.querySelector('[data-payment-date-reset]').addEventListener('click', () => { from.value = ''; to.value = ''; filter(); });
  render();
})();
