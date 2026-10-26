/* Actions belong to the resident's own ID; staff previews remain read-only. */
(() => {
  const modal = document.getElementById('profileSettingsModal');
  const card = modal?.querySelector('[data-resident-id-card]');
  if (!card) return;
  const photo = card.querySelector('[data-resident-card-photo]');
  const input = modal.querySelector('#profilePhotoUpload');
  const qr = card.querySelector('[data-resident-card-qr]');
  const activate = (element, action) => {
    element.addEventListener('click', action);
    element.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); action(); }
    });
  };
  activate(photo, () => input.click());
  qr.setAttribute('role', 'button'); qr.tabIndex = 0;
  qr.setAttribute('aria-label', 'Download QR code or full resident ID');
  qr.title = 'Download QR code or full ID';
  const options = document.createElement('div');
  options.className = 'resident-id-download-options'; options.hidden = true;
  options.id = 'residentIdDownloadOptions';
  options.innerHTML = '<button type="button" class="btn btn-outline" data-id-download="qr">Download QR</button><button type="button" class="btn btn-primary" data-id-download="card">Download full ID</button><p role="status"></p>';
  modal.append(options);
  options.setAttribute('aria-label', 'ID downloads');
  qr.setAttribute('aria-controls', options.id); qr.setAttribute('aria-expanded', 'false');
  let menuOpen = false;
  let hideTimer;
  function positionMenu() {
    if (!menuOpen) return;
    const rect = qr.getBoundingClientRect();
    const bounds = modal.getBoundingClientRect();
    const width = Math.min(240, window.innerWidth - 24);
    options.style.width = width + 'px';
    const height = options.offsetHeight;
    const left = Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12));
    const top = rect.bottom + height + 10 <= window.innerHeight
      ? rect.bottom + 8 : Math.max(12, rect.top - height - 8);
    options.style.left = (left - bounds.left) + 'px';
    options.style.top = (top - bounds.top) + 'px';
  }
  function closeMenu() {
    menuOpen = false;
    qr.setAttribute('aria-expanded', 'false');
    options.classList.remove('is-open');
    options.inert = true;
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => { if (!menuOpen) options.hidden = true; }, 180);
  }
  activate(qr, () => {
    if (menuOpen) { closeMenu(); return; }
    clearTimeout(hideTimer);
    menuOpen = true; options.hidden = false; options.inert = false;
    qr.setAttribute('aria-expanded', 'true');
    positionMenu();
    void options.offsetWidth;
    options.classList.add('is-open');
    options.querySelector('button').focus({preventScroll: true});
  });
  document.addEventListener('click', event => {
    if (menuOpen && !qr.contains(event.target) && !options.contains(event.target)) closeMenu();
  });
  modal.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menuOpen) {
      event.preventDefault(); event.stopPropagation(); closeMenu(); qr.focus();
    }
  }, true);
  modal.addEventListener('focusout', () => setTimeout(() => {
    if (menuOpen && !qr.contains(document.activeElement) && !options.contains(document.activeElement)) closeMenu();
  }, 0));
  modal.addEventListener('profile-modal-reset', closeMenu);
  new MutationObserver(() => {
    if (modal.getAttribute('aria-hidden') === 'true') closeMenu();
  }).observe(modal, {attributes: true, attributeFilter: ['aria-hidden']});
  window.addEventListener('resize', positionMenu);
  modal.addEventListener('scroll', positionMenu, true);
  const loadImage = src => new Promise((resolve, reject) => {
    const img = new Image(); img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img); img.onerror = () => reject(new Error('Image could not be loaded'));
    img.src = src;
  });
  function save(canvas, filename) {
    return new Promise((resolve, reject) => canvas.toBlob(blob => {
      if (!blob) { reject(new Error('Could not create image')); return; }
      const url = URL.createObjectURL(blob), link = document.createElement('a');
      link.href = url; link.download = filename; link.click();
      setTimeout(() => URL.revokeObjectURL(url), 30000); resolve();
    }, 'image/png'));
  }
  options.addEventListener('click', async event => {
    const button = event.target.closest('[data-id-download]'); if (!button || button.disabled) return;
    const status = options.querySelector('[role="status"]');
    button.disabled = true; status.textContent = 'Preparing download…';
    try {
      const qrSource = qr.querySelector('img')?.src;
      if (!qrSource) throw new Error('No QR code available');
      const qrImage = await loadImage(qrSource);
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const id = card.querySelector('[data-resident-card-id]').textContent.trim().replace(/[^a-z0-9_-]/gi, '-');
      if (button.dataset.idDownload === 'qr') {
        canvas.width = canvas.height = 600;
        ctx.fillStyle = 'white'; ctx.fillRect(0, 0, 600, 600);
        ctx.imageSmoothingEnabled = false; ctx.drawImage(qrImage, 40, 40, 520, 520);
        await save(canvas, id + '-QR.png');
      } else {
        const fields = [...card.querySelectorAll('dl > div')];
        canvas.width = 1200;
        // Measure wrapped text before choosing row heights; long addresses remain complete.
        ctx.font = '600 22px Arial';
        const wrap = (text, width) => {
          const lines = []; let line = '';
          for (const char of text) {
            if (ctx.measureText(line + char).width > width && line) { lines.push(line); line = ''; }
            line += char;
          }
          if (line) lines.push(line); return lines;
        };
        const entries = fields.map(field => ({label: field.querySelector('dt').textContent, lines: wrap(field.querySelector('dd').textContent.trim(), 510)}));
        const heights = [];
        for (let i = 0; i < entries.length; i += 2) heights.push(55 + 29 * Math.max(entries[i].lines.length, entries[i + 1]?.lines.length || 0));
        ctx.font = 'bold 30px Arial';
        const nameLines = wrap(card.querySelector('[data-resident-card-name]').textContent.trim(), 600);
        const detailsY = Math.max(410, 230 + nameLines.length * 36);
        canvas.height = detailsY + heights.reduce((a,b) => a+b+14, 0) + 24;
        ctx.fillStyle = '#d6e8dc'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#0b3521'; ctx.fillRect(0, 0, 1200, 90);
        ctx.fillStyle = '#fff'; ctx.font = 'bold 30px Arial'; ctx.fillText('Barangay Luna · Resident Identification Profile', 30, 56);
        const photoSrc = photo.querySelector('img')?.src;
        if (photoSrc) {
          const img = await loadImage(photoSrc), size = Math.min(img.width, img.height);
          ctx.drawImage(img, (img.width-size)/2, (img.height-size)/2, size, size, 30, 116, 220, 220);
        } else { ctx.fillStyle = '#17623c'; ctx.font = 'bold 64px Arial'; ctx.fillText(photo.textContent.trim(), 60, 240); }
        ctx.fillStyle = '#0b3521'; ctx.font = 'bold 36px Arial';
        const name = nameLines;
        ctx.font = 'bold 30px Arial'; name.forEach((line,i) => ctx.fillText(line, 280, 158+i*36));
        ctx.font = '18px Arial'; ctx.fillText('RESIDENT ID', 280, 178 + name.length*36);
        ctx.font = 'bold 28px Arial'; ctx.fillText(id, 280, 210 + name.length*36);
        ctx.fillStyle = '#fff'; ctx.fillRect(930, 116, 240, 240);
        ctx.imageSmoothingEnabled = false; ctx.drawImage(qrImage, 946, 132, 208, 208);
        ctx.fillStyle = '#486253'; ctx.font = '16px Arial';
        ctx.fillText('Status: ' + (card.querySelector('[data-resident-card-status]')?.textContent.trim() || ''), 30, 380);
        ctx.fillText(qr.querySelector('span')?.textContent.trim() || '', 550, 380, 620);
        let y = detailsY;
        entries.forEach((entry,i) => {
          const x = 30 + (i%2)*577, height = heights[Math.floor(i/2)];
          ctx.fillStyle = '#fff'; ctx.fillRect(x, y, 563, height);
          ctx.fillStyle = '#486253'; ctx.font = '18px Arial'; ctx.fillText(entry.label, x+18, y+27);
          ctx.fillStyle = '#102f20'; ctx.font = '600 22px Arial'; entry.lines.forEach((line,j) => ctx.fillText(line, x+18, y+57+j*29));
          if (i%2) y += height+14;
        });
        await save(canvas, id + '-resident-ID.png');
      }
      status.textContent = 'Download ready.';
    } catch (error) { status.textContent = 'Unable to download. Check your connection and try again.'; }
    finally { button.disabled = false; }
  });
})();
