/* Display explicit 12-hour controls; keep server values in their existing format. */
(() => {
  const parse = value => {
    const match = /^(?:(\d{4}-\d{2}-\d{2})T)?(\d{2}):(\d{2})(?::\d{2})?$/.exec(value);
    if (!match || +match[2] > 23 || +match[3] > 59) return null;
    return {date: match[1] || '', clock: `${+match[2] % 12 || 12}:${match[3]}`, period: +match[2] >= 12 ? 'PM' : 'AM'};
  };
  const serialize = (clock, period, date = '', datetime = false) => {
    const match = /^(0?[1-9]|1[0-2]):([0-5]\d)$/.exec(clock.trim());
    if (!match || !['AM','PM'].includes(period) || (datetime && !date)) return '';
    const hour = (+match[1] % 12) + (period === 'PM' ? 12 : 0);
    return (datetime ? date + 'T' : '') + String(hour).padStart(2, '0') + ':' + match[2];
  };
  function enhance(original) {
    if (original.dataset.timeControlReady) return;
    original.dataset.timeControlReady = 'true';
    const datetime = original.type === 'datetime-local';
    const initialValue = original.defaultValue;
    const labels = [...(original.labels || [])];
    const valueProperty = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    const group = document.createElement('span'); group.className = 'time-control-12';
    group.setAttribute('role', 'group');
    const label = original.labels?.[0]?.textContent.trim() || original.name.replaceAll('_', ' ') || 'Time';
    group.setAttribute('aria-label', label);
    const clock = document.createElement('input'); clock.type = 'text'; clock.placeholder = 'hh:mm';
    clock.pattern = '(0?[1-9]|1[0-2]):[0-5][0-9]'; clock.maxLength = 5;
    clock.title = 'Use hours 1–12 and minutes, for example 2:30, then choose AM or PM.';
    clock.setAttribute('aria-label', label + ' (12-hour time)');
    clock.autocomplete = 'off';
    const period = document.createElement('select'); period.setAttribute('aria-label', label + ' AM or PM');
    period.dataset.nativeSelect = 'true';
    ['AM','PM'].forEach(value => {const option = document.createElement('option'); option.value = option.textContent = value; period.appendChild(option);});
    let date = null;
    if (datetime) {date = document.createElement('input'); date.type = 'date'; date.setAttribute('aria-label', label + ' date'); group.appendChild(date);}
    group.append(clock, period);
    original.insertAdjacentElement('afterend', group);
    original.type = 'hidden';
    function reflect() {
      const parsed = parse(valueProperty.get.call(original));
      clock.value = parsed?.clock || ''; period.value = parsed?.period || 'AM';
      clock.setCustomValidity('');
      if (date) date.value = parsed?.date || '';
      [clock, period, date].filter(Boolean).forEach(control => {
        control.disabled = original.disabled || original.readOnly;
        control.required = original.required && !control.disabled;
      });
    }
    Object.defineProperty(original, 'value', {
      configurable: true,
      get() {return valueProperty.get.call(this);},
      set(value) {valueProperty.set.call(this, value); reflect();}
    });
    let committing = false;
    function commit() {
      const serialized = serialize(clock.value, period.value, date?.value || '', datetime);
      valueProperty.set.call(original, serialized);
      clock.setCustomValidity(clock.value && !serialize(clock.value, period.value) ? 'Enter a time from 1:00 to 12:59 and choose AM or PM.' : '');
      committing = true;
      try {
        original.dispatchEvent(new Event('input', {bubbles: true}));
        original.dispatchEvent(new Event('change', {bubbles: true}));
      } finally { committing = false; }
    }
    clock.addEventListener('input', commit);
    period.addEventListener('change', commit);
    date?.addEventListener('change', commit);
    original.addEventListener('change', () => {if (!committing) reflect();});
    original.addEventListener('input', () => {
      // Keep partial typed times intact until valid, while reflecting external edits.
      if (!committing) reflect();
    });
    original.form?.addEventListener('reset', () => {original.defaultValue = initialValue; setTimeout(reflect, 0);});
    new MutationObserver(reflect).observe(original, {attributes: true, attributeFilter: ['disabled','readonly','required']});
    labels.forEach(element => element.addEventListener('click', event => {
      if (event.target === element) {event.preventDefault(); clock.focus();}
    }));
    reflect();
  }
  const scan = root => root.querySelectorAll('input[type="time"],input[type="datetime-local"]').forEach(enhance);
  scan(document);
  new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
    if (node.nodeType !== 1) return;
    if (node.matches('input[type="time"],input[type="datetime-local"]')) enhance(node);
    scan(node);
  }))).observe(document.body, {childList: true, subtree: true});
})();
