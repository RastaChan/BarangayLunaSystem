(() => {
  'use strict';
  async function lookup(body) {
    const response = await fetch('resident-service-lookup.php', {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}, signal: AbortSignal.timeout(12000)});
    let result;
    try { result = await response.json(); } catch { throw new Error('Lookup could not be completed. Refresh the page and try again.'); }
    if (!response.ok || !result.ok) throw new Error(result.message || 'Lookup failed.');
    return result;
  }
  document.querySelectorAll('[data-issue-resident-lookup]').forEach(container => {
    const form = container.closest('form');
    const resident = form.querySelector('[data-autofill-resident]');
    const input = container.querySelector('#issueResidentQr');
    const button = container.querySelector('[data-issue-find-resident]');
    const message = container.querySelector('[data-issue-lookup-status]');
    const hint = container.querySelector('#issueResidentQrHint');
    const results = container.querySelector('[data-issue-resident-results]');
    const capture = container.querySelector('[data-issue-qr-capture]');
    if (!resident) return;
    let epoch = 0, timer = null, searching = false, selected = null;
    function selectionMatchesInput() {
      return selected && resident.value === selected.id && input.value.trim() === selected.label;
    }
    function invalidate() {
      epoch++;
      clearTimeout(timer); timer = null;
      container.dataset.lookupBusy = 'false';
      button.disabled = false;
      results.replaceChildren(); results.hidden = true;
      searching = false;
      input.setAttribute('aria-expanded','false');
    }
    function unsetResident() {
      selected = null;
      resident.value = '__walkin__';
      resident.dispatchEvent(new Event('change', {bubbles:true}));
      input.setCustomValidity(input.value.trim() ? 'Choose a matching resident, or clear the search to enter walk-in details manually.' : '');
    }
    function clearSelection() {
      invalidate();
      input.value = ''; input.readOnly = false;
      input.required = false;
      input.placeholder = 'Search name or Resident ID';
      capture.hidden = false;
      hint.textContent = 'Search if the resident has an account. Otherwise, leave this empty and enter their details below.';
      message.textContent = '';
      unsetResident();
      container.dispatchEvent(new CustomEvent('resident-search-reset'));
    }
    function body(kind) {
      const data = new FormData(); data.set('kind',kind); data.set('csrf',form.elements.csrf.value); return data;
    }
    async function choose(code) {
      invalidate();
      unsetResident();
      const version = epoch;
      container.dataset.lookupBusy = 'true'; button.disabled = true;
      message.textContent = 'Loading resident details…';
      try {
        const data = body('issue'); data.set('code',code);
        const result = await lookup(data);
        if (version !== epoch) return;
        const row = result.resident;
        const details = {id:row.user_code,name:row.full_name,address:row.address || '',age:row.age ?? '',gender:row.gender || '',civil_status:row.civil_status || ''};
        form.dispatchEvent(new CustomEvent('resident-details-selected',{detail:details}));
        resident.value = details.id; input.value = `${details.name} — ${details.id}`;
        selected = {id: details.id, name: details.name, label: input.value.trim()};
        input.setCustomValidity('');
        resident.dispatchEvent(new Event('change',{bubbles:true}));
        message.textContent = `${details.name} selected. Review the saved details below.`;
      } catch (error) {
        if (version === epoch) { unsetResident(); message.textContent = error.name === 'TimeoutError' ? 'Lookup timed out. Try again.' : error.message; }
      } finally {
        if (version === epoch) { container.dataset.lookupBusy = 'false'; button.disabled = false; }
      }
    }
    async function search() {
      const query = input.value.trim();
      if (selectionMatchesInput()) {
        invalidate();
        input.setCustomValidity('');
        message.textContent = `${selected.name} selected. Review the saved details below.`;
        return;
      }
      invalidate();
      if (!query) { message.textContent = 'Enter a name or Resident ID.'; return; }
      unsetResident();
      if (/^LUNA\|/i.test(query)) { choose(query); return; }
      const version = epoch;
      searching = true;
      container.dataset.lookupBusy = 'true'; button.disabled = true; message.textContent = 'Searching residents…';
      try {
        const data = body('resident-search'); data.set('search',query);
        const result = await lookup(data);
        if (version !== epoch || input.value.trim() !== query) return;
        result.residents.forEach(row => {
          const choice = document.createElement('button'); choice.type = 'button'; choice.className = 'issue-resident-result';
          choice.textContent = `${row.full_name} — ${row.user_code}`;
          choice.addEventListener('click',() => choose(row.user_code)); results.appendChild(choice);
        });
        results.hidden = result.residents.length === 0;
        input.setAttribute('aria-expanded',String(!results.hidden));
        message.textContent = result.residents.length === 0 ? 'No active registered resident matched. Check the spelling, or clear the search and enter their details manually.'
          : result.more ? 'Showing the first 20 matches. Type more of the name to narrow the results.' : 'Choose a name from the search suggestions.';
      } catch (error) {
        if (version === epoch) message.textContent = error.name === 'TimeoutError' ? 'Search timed out. Try again.' : error.message;
      } finally {
        if (version === epoch) { searching = false; container.dataset.lookupBusy = 'false'; button.disabled = false; }
      }
    }
    button.addEventListener('click',search);
    input.addEventListener('input',() => {
      if (selectionMatchesInput()) return;
      invalidate(); unsetResident();
      container.dispatchEvent(new CustomEvent('resident-search-reset'));
      message.textContent = '';
      if (input.value.trim().length >= 1) timer = setTimeout(search,220);
    });
    input.addEventListener('keydown',event => {
      if (event.key === 'ArrowDown' && !results.hidden) { event.preventDefault(); results.firstElementChild?.focus(); }
      if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
    function dismissSuggestions() {
      if (searching || !results.hidden || timer !== null) { invalidate(); message.textContent = ''; }
    }
    container.addEventListener('keydown',event => {
      if (event.key === 'Escape' && (searching || !results.hidden || timer !== null)) { event.preventDefault(); dismissSuggestions(); input.focus(); }
    });
    document.addEventListener('pointerdown',event => { if (!container.contains(event.target)) dismissSuggestions(); });
    container.addEventListener('focusout',event => { if (event.relatedTarget && !container.contains(event.relatedTarget)) dismissSuggestions(); });
    container.addEventListener('resident-qr-decoded',event => {
      if (container.dataset.lookupBusy !== 'true' && typeof event.detail === 'string') choose(event.detail);
    });
    clearSelection();
  });
  document.querySelectorAll('[data-duplicate-resident-form]').forEach(form => {
    const panel = form.querySelector('[data-duplicate-warning]');
    const list = panel.querySelector('[data-duplicate-matches]');
    const check = form.elements.duplicate_reviewed;
    const token = form.elements.duplicate_token;
    const message = form.querySelector('[data-duplicate-status]');
    let busy = false, authorized = false;
    const identityFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'no_middle_name', 'birthdate'];
    identityFields.forEach(name => form.elements[name]?.addEventListener('input', () => { check.checked = false; token.value = ''; panel.hidden = true; }));
    form.addEventListener('submit', async event => {
      if (authorized) { authorized = false; return; }
      event.preventDefault();
      if (busy) return;
      busy = true; message.textContent = 'Checking for an existing resident…';
      const fingerprint = () => JSON.stringify(identityFields.map(name => { const field = form.elements[name]; return field.type === 'checkbox' ? field.checked : field.value; }));
      const before = fingerprint();
      const button = event.submitter;
      if (button) button.disabled = true;
      try {
        const data = new FormData();
        identityFields.forEach(name => { const field = form.elements[name]; if (field.type !== 'checkbox' || field.checked) data.set(name, field.value); });
        data.set('kind', 'duplicates'); data.set('csrf', form.elements.locations_csrf.value);
        const result = await lookup(data);
        if (before !== fingerprint()) throw new Error('Resident details changed. Save again to check the updated details.');
        if (result.matches.length && (!check.checked || token.value !== result.token)) {
          token.value = result.token; check.checked = false; list.replaceChildren();
          result.matches.forEach(row => { const item = document.createElement('li'); item.textContent = `${row.code} — ${row.name} — ${row.birthdate} — ${row.status}`; list.append(item); });
          panel.hidden = false; panel.scrollIntoView({behavior: 'smooth', block: 'center'});
          message.textContent = 'Review the matching account. Confirm only if this is a different person, then press Save again.';
          return;
        }
        panel.hidden = true; message.textContent = 'Saving resident account…';
        authorized = true;
        if (button) button.disabled = false;
        form.requestSubmit(button || undefined);
      } catch (error) { message.textContent = error.name === 'TimeoutError' ? 'The duplicate check timed out. Try saving again.' : error.message; }
      finally { busy = false; if (button) button.disabled = false; }
    });
  });
})();
