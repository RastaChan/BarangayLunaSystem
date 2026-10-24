(() => {
  'use strict';
  const iso = date => date.toISOString().slice(0, 10);
  const asDate = value => new Date(value + 'T00:00:00Z');
  const todayInManila = () => new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'}).format(new Date());
  function matches(row, filters, today) {
    const active = ['Scheduled', 'In Progress'].includes(row.status);
    return (!filters.query || row.text.toLowerCase().includes(filters.query)) &&
      (!filters.officer || row.officer === filters.officer) &&
      (filters.status === 'all' || (filters.status === 'active' ? active : row.status === filters.status)) &&
      (filters.period !== 'today' || row.date === today) &&
      (filters.period !== 'upcoming' || row.date >= today) &&
      (filters.period !== 'custom' || ((!filters.from || row.date >= filters.from) && (!filters.to || row.date <= filters.to)));
  }
  window.barangayHusayWorkspace = (root, list, search) => {
    const find = attribute => root.querySelector('[data-husay-' + attribute + ']');
    const controls = {status: root.querySelector('select[data-husay-status]'), officer: root.querySelector('select[data-husay-officer]'), period: find('period'), from: find('from'), to: find('to')};
    let mode = 'calendar', selected = todayInManila(), month = selected.slice(0, 7);
    function rows() {
      return [...list.querySelectorAll('[data-husay-search-text]')].map(element => ({element, date: element.dataset.husayDate, status: element.dataset.husayStatus, officer: element.dataset.husayOfficer, text: element.dataset.husaySearchText}));
    }
    function calendar(matching, today) {
      const grid = find('calendar-grid'), focused = document.activeElement?.dataset?.husayDay;
      const first = asDate(month + '-01'), offset = first.getUTCDay();
      const days = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
      const totals = new Map();
      matching.forEach(row => totals.set(row.date, (totals.get(row.date) || 0) + 1));
      find('month-label').textContent = new Intl.DateTimeFormat('en-PH', {month: 'long', year: 'numeric', timeZone: 'UTC'}).format(first);
      const fragment = document.createDocumentFragment();
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const label = document.createElement('span'); label.className = 'husay-weekday'; label.textContent = day; fragment.appendChild(label);
      });
      const cells = Math.ceil((offset + days) / 7) * 7;
      for (let index = 0; index < cells; index++) {
        const day = index - offset + 1;
        if (day < 1 || day > days) { const blank = document.createElement('span'); blank.className = 'husay-calendar-blank'; fragment.appendChild(blank); continue; }
        const value = month + '-' + String(day).padStart(2, '0'), count = totals.get(value) || 0;
        const button = document.createElement('button'); button.type = 'button'; button.className = 'husay-day'; button.dataset.husayDay = value;
        button.setAttribute('aria-pressed', String(value === selected));
        button.setAttribute('aria-label', new Intl.DateTimeFormat('en-PH', {dateStyle: 'full', timeZone: 'UTC'}).format(asDate(value)) + ': ' + count + (count === 1 ? ' session' : ' sessions'));
        if (value === today) button.setAttribute('aria-current', 'date');
        const number = document.createElement('strong'); number.textContent = day;
        const badge = document.createElement('span'); badge.textContent = count || '—'; badge.className = count ? 'husay-day-count' : 'husay-day-empty';
        button.append(number, badge); fragment.appendChild(button);
      }
      grid.replaceChildren(fragment);
      if (focused) grid.querySelector('[data-husay-day="' + focused + '"]')?.focus({preventScroll: true});
    }
    function filter() {
      const today = todayInManila(), all = rows();
      const filters = {query: search.value.trim().toLowerCase(), ...Object.fromEntries(Object.entries(controls).map(([name, input]) => [name, input.value]))};
      const matching = all.filter(row => matches(row, filters, today));
      const visible = new Set(matching.filter(row => mode === 'list' || row.date === selected).map(row => row.element));
      all.forEach(row => { row.element.hidden = !visible.has(row.element); });
      const empty = root.querySelector('[data-husay-no-match]');
      empty.textContent = mode === 'calendar' ? 'No sessions on this date match your filters.' : 'No Husay sessions match your filters.';
      empty.hidden = visible.size > 0;
      const baseEmpty = list.querySelector('.empty-state'); if (baseEmpty) baseEmpty.hidden = true;
      find('date-range').hidden = controls.period.value !== 'custom';
      find('calendar').hidden = mode !== 'calendar';
      find('agenda-label').textContent = mode === 'calendar' ? new Intl.DateTimeFormat('en-PH', {dateStyle: 'full', timeZone: 'UTC'}).format(asDate(selected)) + ' · ' + visible.size + (visible.size === 1 ? ' session' : ' sessions') : visible.size + ' matching sessions';
      const totals = {
        today: all.filter(row => row.date === today && !['Cancelled', 'Rescheduled'].includes(row.status)).length,
        upcoming: all.filter(row => row.date > today && ['Scheduled', 'In Progress'].includes(row.status)).length,
        completed: all.filter(row => row.status === 'Completed').length
      };
      Object.entries(totals).forEach(([key, value]) => { root.querySelector('[data-husay-count="' + key + '"]').textContent = value; });
      root.querySelectorAll('[data-husay-mode]').forEach(button => {
        const pressed = button.dataset.husayMode === mode;
        button.setAttribute('aria-pressed', String(pressed)); button.classList.toggle('btn-primary', pressed); button.classList.toggle('btn-outline', !pressed);
      });
      calendar(matching, today);
    }
    function jumpToday() { selected = todayInManila(); month = selected.slice(0, 7); }
    root.addEventListener('click', event => {
      const day = event.target.closest('[data-husay-day]'), switcher = event.target.closest('[data-husay-mode]'), move = event.target.closest('[data-husay-month]');
      if (day) selected = day.dataset.husayDay;
      else if (switcher) mode = switcher.dataset.husayMode;
      else if (move) {
        const date = asDate(month + '-01'); date.setUTCMonth(date.getUTCMonth() + Number(move.dataset.husayMonth));
        if (date.getUTCFullYear() < 1900 || date.getUTCFullYear() > 2199) return;
        month = iso(date).slice(0, 7); selected = month + '-01';
      } else if (event.target.closest('[data-husay-today]')) { jumpToday(); controls.period.value = 'all'; controls.period.dispatchEvent(new Event('change', {bubbles: true})); }
      else if (event.target.closest('[data-husay-reset]')) {
        search.value = ''; controls.status.value = 'active'; controls.officer.value = ''; controls.period.value = 'upcoming'; controls.from.value = ''; controls.to.value = ''; jumpToday();
        Object.values(controls).forEach(input => input.dispatchEvent(new Event('change', {bubbles: true})));
      } else return;
      filter();
    });
    Object.values(controls).forEach(input => input.addEventListener('change', () => {
      if (controls.period.value === 'today') jumpToday();
      if (controls.period.value === 'custom' && controls.from.value) { month = controls.from.value.slice(0, 7); selected = controls.from.value; }
      controls.to.min = controls.from.value; controls.from.max = controls.to.value;
      filter();
    }));
    return {filter};
  };
})();
