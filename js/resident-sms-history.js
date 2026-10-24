(() => {
  'use strict';
  const history = document.querySelector('[data-sms-history]');
  if (!history) return;
  const search = history.querySelector('[data-sms-search]');
  const status = history.querySelector('[data-sms-status]');
  const dates = history.querySelector('[data-sms-dates]');
  const rows = [...history.querySelectorAll('[data-sms-row]')];
  const empty = history.querySelector('[data-sms-empty]');
  function filter() {
    const query = search.value.trim().toLowerCase();
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const start = new Date(today);
    if (dates.value && dates.value !== 'today') start.setDate(start.getDate() - Number(dates.value) + 1);
    let visible = 0;
    rows.forEach(row => {
      const date = new Date(row.dataset.date + 'T00:00:00');
      const matchesDate = !dates.value || (date >= start && date <= today);
      row.hidden = !row.querySelector('[data-sms-message]').textContent.toLowerCase().includes(query)
        || (status.value !== '' && row.dataset.status !== status.value) || !matchesDate;
      if (!row.hidden) visible++;
    });
    empty.hidden = visible > 0;
  }
  search.addEventListener('input', filter);
  status.addEventListener('change', filter);
  dates.addEventListener('change', filter);
  history.addEventListener('click', event => {
    const button = event.target.closest('[data-sms-view]');
    if (!button) return;
    const row = button.closest('[data-sms-row]');
    document.getElementById('residentSmsViewTitle').textContent = row.querySelector('h3').textContent;
    const time = row.querySelector('time');
    document.querySelector('[data-sms-view-meta]').textContent = time.firstChild.textContent + ' · ' + (time.querySelector('small')?.textContent || '') + ' · ' + row.dataset.status;
    document.querySelector('[data-sms-view-message]').textContent = row.querySelector('[data-sms-message]').textContent;
  });
  filter();
})();
