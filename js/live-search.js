/* Server-backed searches keep their input focused while only results change. */
(() => {
  document.querySelectorAll('[data-live-search-form]').forEach(form => {
    const key = form.dataset.liveSearchForm;
    const regions = [...document.querySelectorAll(`[data-live-search-results="${key}"]`)];
    if (!regions.length) return;
    const status = document.createElement('p');
    status.className = 'field-hint';
    status.setAttribute('role', 'status');
    status.hidden = true;
    form.insertAdjacentElement('afterend', status);
    let timer, controller, sequence = 0;
    function cancel() {
      clearTimeout(timer);
      sequence++;
      controller?.abort();
    }
    function urlForForm() {
      const url = new URL(form.getAttribute('action') || location.href, location.href);
      url.search = new URLSearchParams(new FormData(form)).toString();
      url.hash = '';
      return url;
    }
    async function update(url) {
      cancel();
      const version = sequence;
      controller = new AbortController();
      const currentController = controller;
      const timeout = setTimeout(() => currentController.abort(), 15000);
      regions.forEach(region => region.setAttribute('aria-busy', 'true'));
      status.hidden = false;
      status.textContent = 'Searching…';
      try {
        const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', signal: currentController.signal});
        if (!response.ok || response.redirected) throw Error('Search could not load. Please try again.');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (version !== sequence) return;
        const next = [...page.querySelectorAll(`[data-live-search-results="${key}"]`)];
        if (next.length !== regions.length) throw Error('Search could not load. Please refresh and try again.');
        regions.forEach((region, index) => region.replaceChildren(...next[index].childNodes));
        form.querySelectorAll('[data-live-search-link]').forEach(link => {
          const nextLink = page.querySelector(`[data-live-search-form="${key}"] [data-live-search-link="${link.dataset.liveSearchLink}"]`);
          if (nextLink) link.href = new URL(nextLink.getAttribute('href'), url).href;
        });
        history.replaceState(history.state, '', url);
        status.hidden = true;
        status.textContent = '';
      } catch (error) {
        if (version !== sequence) return;
        status.textContent = error.name === 'AbortError' ? 'Search timed out. Please try again.' : error.message;
      } finally {
        clearTimeout(timeout);
        if (version === sequence) regions.forEach(region => region.removeAttribute('aria-busy'));
      }
    }
    function schedule(event) {
      cancel(); // Invalidate an old response immediately, before the debounce ends.
      if (event.isComposing) return;
      timer = setTimeout(() => update(urlForForm()), 220);
    }
    form.querySelectorAll('input[type="search"]').forEach(input => {
      input.addEventListener('input', schedule);
      input.addEventListener('compositionend', schedule);
    });
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', schedule));
    form.addEventListener('submit', event => {event.preventDefault(); update(urlForForm());});
    regions.forEach(region => region.addEventListener('click', event => {
      const link = event.target.closest('[data-live-search-page]');
      if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
      event.preventDefault();
      update(new URL(link.href, location.href));
    }));
    window.addEventListener('pagehide', () => {
      cancel();
      regions.forEach(region => region.removeAttribute('aria-busy'));
      status.hidden = true;
    });
  });
})();
