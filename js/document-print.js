(() => {
  const root = document.querySelector('[data-document-print]');
  if (!root) return;
  const frame = document.querySelector('[data-document-frame]');
  const print = document.querySelector('[data-print-document]');
  const open = document.querySelector('[data-open-pdf]');
  const panel = document.querySelector('[data-preview-status]');
  const title = document.querySelector('[data-preview-title]');
  const message = document.querySelector('[data-preview-message]');
  const retry = document.querySelector('[data-retry-preview]');
  let preparing = false;
  frame.addEventListener('load', () => { if (frame.getAttribute('src')) print.disabled = false; });
  async function prepare() {
    if (preparing) return;
    preparing = true;
    panel.hidden = false;
    retry.hidden = true;
    title.textContent = 'Preparing print preview';
    message.textContent = 'Your document is saved. The PDF preview will appear here shortly.';
    panel.setAttribute('aria-busy', 'true');
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 26000);
    try {
      const response = await fetch('staff-document-pdf.php', {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({id: root.dataset.documentId, csrf: root.dataset.previewToken})
      });
      let result;
      try { result = await response.json(); }
      catch (_) { throw new Error('The server could not return the preview. Please refresh and try again.'); }
      if (!response.ok || !result.ok) throw new Error(result.message || 'The print preview could not be prepared. Please retry.');
      const url = new URL(result.url, location.href);
      if (url.origin !== location.origin || !url.pathname.includes('/storage/document-previews/')) throw new Error('The server returned an invalid preview. Please retry.');
      open.href = url.href;
      open.hidden = false;
      frame.src = url.href;
      frame.hidden = false;
      panel.hidden = true;
    } catch (error) {
      title.textContent = 'Print preview is not available';
      message.textContent = error.name === 'AbortError'
        ? 'The preview took too long. Retry, or download the DOCX and print it in Microsoft Word.'
        : error instanceof TypeError
          ? 'Check your connection and retry, or download the DOCX and print it in Microsoft Word.'
          : error.message || 'The preview could not be prepared. Please retry.';
      retry.hidden = false;
    } finally {
      clearTimeout(timer);
      preparing = false;
      panel.removeAttribute('aria-busy');
    }
  }
  retry.addEventListener('click', prepare);
  print.addEventListener('click', () => {
    try { frame.contentWindow.focus(); frame.contentWindow.print(); }
    catch (_) { panel.hidden = false; title.textContent = 'Open the PDF to print'; message.textContent = 'Use Open PDF, then choose Print in your browser.'; }
  });
  if (root.dataset.previewReady !== '1') prepare();
  else print.disabled = false;
})();
