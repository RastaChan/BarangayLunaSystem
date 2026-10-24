import {getDocument, GlobalWorkerOptions} from './vendor/pdfjs/legacy/build/pdf.mjs';

GlobalWorkerOptions.workerSrc = new URL('./vendor/pdfjs/legacy/build/pdf.worker.mjs', import.meta.url).href;
const status = document.getElementById('status');
const pages = document.getElementById('pages');
const open = document.getElementById('open');
let loadingTask;
let residentPreview = false;
try {
  const path = new URLSearchParams(location.search).get('file');
  if (!path) throw new Error('No document selected.');
  const url = new URL(path, location.href);
  const base = new URL('./storage/document-previews/', location.href);
  residentPreview = url.origin === base.origin && url.pathname === new URL('./resident-document-preview.php', location.href).pathname && /^[a-f0-9]{48}$/.test(url.searchParams.get('token') || '');
  const staffPreview = url.origin === base.origin && url.pathname.startsWith(base.pathname) && /^[a-z0-9-]+\.pdf$/i.test(decodeURIComponent(url.pathname.slice(base.pathname.length)));
  if (!residentPreview && !staffPreview) {
    throw new Error('Invalid document preview.');
  }
  if (!residentPreview) open.href = url.href;
  if (residentPreview) document.addEventListener('contextmenu', event => event.preventDefault());
  loadingTask = getDocument({url: url.href, isEvalSupported: false,
    cMapUrl: new URL('./vendor/pdfjs/cmaps/', import.meta.url).href, cMapPacked: true,
    standardFontDataUrl: new URL('./vendor/pdfjs/standard_fonts/', import.meta.url).href,
    wasmUrl: new URL('./vendor/pdfjs/wasm/', import.meta.url).href});
  const pdf = await loadingTask.promise;
  for (let number = 1; number <= pdf.numPages; number++) {
    const page = await pdf.getPage(number);
    const original = page.getViewport({scale: 1});
    // Keep text sharp when phone users zoom, without allocating desktop-sized canvases.
    const width = Math.min(1600, Math.max(800, pages.clientWidth * Math.min(devicePixelRatio || 1, 2)));
    const viewport = page.getViewport({scale: width / original.width});
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', `Document page ${number} of ${pdf.numPages}`);
    pages.appendChild(canvas);
    await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
    if (residentPreview) {
      const context = canvas.getContext('2d');
      context.save(); context.translate(canvas.width / 2, canvas.height / 2); context.rotate(-Math.PI / 6);
      context.font = `bold ${Math.round(canvas.width / 15)}px sans-serif`;
      context.textAlign = 'center'; context.fillStyle = 'rgba(120, 35, 35, .22)';
      context.fillText('PREVIEW ONLY — NOT VALID FOR USE', 0, 0); context.restore();
    }
    page.cleanup();
    status.textContent = `Loaded ${number} of ${pdf.numPages} pages`;
  }
  status.hidden = true;
} catch (error) {
  status.textContent = residentPreview ? 'The document pages could not load. Close this popup and retry the preview.' : 'The document pages could not load. Open the PDF to view it, or retry the preview.';
  if (open.getAttribute('href')) open.hidden = false;
}
addEventListener('pagehide', () => { loadingTask?.destroy(); });
