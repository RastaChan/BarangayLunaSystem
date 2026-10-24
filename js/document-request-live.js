(() => {
  'use strict';
  const region = document.querySelector('[data-document-request-live-owner]');
  const list = region?.querySelector('[data-document-request-live-list]');
  if (!list || region.dataset.liveRequestsReady) return;
  region.dataset.liveRequestsReady = '1';
  const owner = region.dataset.documentRequestLiveOwner;
  let pending = false;
  const protectedRow = row => row.contains(document.activeElement) || !!row.querySelector('details[open]') ||
    (window.getSelection()?.anchorNode && row.contains(window.getSelection().anchorNode) && window.getSelection().toString());
  function syncRows(next) {
    const existing = new Map([...list.children].map(row => [row.dataset.documentRequestCode || '', row]));
    const keep = new Set();
    let cursor = list.firstElementChild;
    for (const fresh of [...next.children]) {
      const old = existing.get(fresh.dataset.documentRequestCode || '');
      const row = old && (protectedRow(old) || old.outerHTML === fresh.outerHTML) ? old : fresh;
      keep.add(row);
      if (row === cursor) cursor = cursor.nextElementSibling;
      else list.insertBefore(row, cursor);
    }
    for (const row of [...list.children]) {
      if (!keep.has(row) && !protectedRow(row)) row.remove();
    }
  }
  function syncChoices(choices) {
    const select = document.getElementById('issueRequest');
    if (!select || document.activeElement === select) return;
    const chosen = select.value;
    const current = [...select.options].find(option => option.value === chosen);
    const values = [{id:'',label:'Select a request'}, ...choices];
    if (chosen && !values.some(option => option.id === chosen) && current) values.push({id:chosen,label:current.textContent});
    const signature = JSON.stringify(values);
    if (select.dataset.liveChoices === signature) return;
    const options = values.map(value => {
      const option = document.createElement('option'); option.value = value.id; option.textContent = value.label; return option;
    });
    select.replaceChildren(...options); select.value = chosen;
    select.dataset.liveChoices = signature;
  }
  async function refresh() {
    if (pending || document.hidden || !navigator.onLine) return;
    pending = true;
    try {
      const response = await fetch('document-request-feed.php', {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:AbortSignal.timeout(15000)});
      if (!response.ok || response.redirected || !(response.headers.get('Content-Type') || '').includes('application/json')) return;
      const data = await response.json();
      if (!data.ok || data.owner !== owner || document.hidden) return;
      const page = new DOMParser().parseFromString(data.html, 'text/html');
      const next = page.querySelector('[data-document-request-live-list]');
      if (!next) return;
      syncRows(next);
      syncChoices(Array.isArray(data.choices) ? data.choices : []);
    } catch (_) {
      // Keep the usable list and retry automatically after a connection failure.
    } finally { pending = false; }
  }
  refresh();
  window.setInterval(refresh, 5000);
  window.addEventListener('focus', refresh);
  window.addEventListener('online', refresh);
  document.addEventListener('visibilitychange', () => {if (!document.hidden) refresh();});
  document.addEventListener('document-notifications-updated', refresh);
  const modal = document.getElementById('documentRequestsModal');
  if (modal) new MutationObserver(() => {if (modal.getAttribute('aria-hidden') === 'false') refresh();}).observe(modal, {attributes:true,attributeFilter:['aria-hidden']});
})();
