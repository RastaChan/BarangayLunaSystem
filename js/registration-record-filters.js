(() => {
  const section = document.getElementById('registrationRecords');
  if (!section) return;
  const form = section.querySelector('.registration-card-filters');
  const results = section.querySelector('[data-registration-results]');
  const count = section.querySelector('[data-registration-count]');
  const heading = section.querySelector('[data-registration-heading]');
  const message = section.querySelector('[data-registration-message]');
  const apply = form.querySelector('[type="submit"]');
  let controller, sequence = 0;
  function filterUrl() {
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    url.hash = 'registrationRecords';
    return url;
  }
  async function update(url) {
    controller?.abort();
    const current = ++sequence;
    controller = new AbortController();
    const requestController = controller;
    const timer = setTimeout(() => requestController.abort(), 15000);
    apply.disabled = true;
    results.setAttribute('aria-busy', 'true');
    message.hidden = false;
    message.textContent = 'Updating records…';
    try {
      const response = await fetch(url, {credentials:'same-origin', cache:'no-store', signal:requestController.signal});
      if (!response.ok || response.redirected) throw new Error('Could not update records. Please retry.');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (current !== sequence) return;
      const next = page.getElementById('registrationRecords');
      const nextResults = next?.querySelector('[data-registration-results]');
      const nextCount = next?.querySelector('[data-registration-count]');
      const nextHeading = next?.querySelector('[data-registration-heading]');
      if (!nextResults || !nextCount || !nextHeading) throw new Error('Could not update records. Please retry.');
      results.replaceChildren(...Array.from(nextResults.childNodes));
      count.textContent = nextCount.textContent;
      heading.textContent = nextHeading.textContent;
      history.replaceState(history.state, '', url);
      message.hidden = true;
      message.textContent = '';
    } catch (error) {
      if (current !== sequence) return;
      message.textContent = error.name === 'AbortError' ? 'The update timed out. Please retry.' : error.message;
    } finally {
      clearTimeout(timer);
      if (current === sequence) {
        apply.disabled = false;
        results.removeAttribute('aria-busy');
      }
    }
  }
  form.addEventListener('submit', event => {event.preventDefault(); update(filterUrl());});
  section.addEventListener('click', event => {
    const clear = event.target.closest('[data-registration-clear]');
    const page = event.target.closest('[data-registration-page]');
    if (!clear && !page) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
    event.preventDefault();
    if (clear) {
      ['registration_search','registration_status','registration_purok'].forEach(name => {
        form.elements[name].value = '';
        form.elements[name].dispatchEvent(new Event('change', {bubbles:true}));
      });
      update(filterUrl());
    } else update(new URL(page.href, window.location.href));
  });
  window.addEventListener('pagehide', () => {
    sequence++;
    controller?.abort();
    apply.disabled = false;
    results.removeAttribute('aria-busy');
    message.hidden = true;
    message.textContent = '';
  });
})();
