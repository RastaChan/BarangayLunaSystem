(() => {
  'use strict';
  const paths = {
    person: 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    gender: 'M14 3h7v7M21 3l-7 7M16 14a6 6 0 1 1-12 0 6 6 0 0 1 12 0',
    group: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M2 21v-3a6 6 0 0 1 12 0v3M15 13a6 6 0 0 1 7 5v3',
    location: 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    card: 'M3 5h18v14H3zM7 10h3v3H7zM14 10h4M14 14h4M7 16h3',
    mail: 'M3 5h18v14H3zM3 6l9 7 9-7',
    phone: 'M5 3h4l2 5-3 2c2 3 3 4 6 6l2-3 5 2v4c-9 2-20-9-16-16z',
    shield: 'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM12 7v8',
    calendar: 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2M8 17h2',
    work: 'M3 7h18v13H3zM8 7V3h8v4M3 12h18M10 12v3h4v-3',
    home: 'M3 10l9-7 9 7M5 9v12h14V9M9 21v-8h6v8',
    document: 'M6 3h8l4 4v14H6zM14 3v5h4M9 12h6M9 16h6',
    edit: 'M4 16l12-12 4 4-12 12H4zM14 6l4 4M4 22h16',
    key: 'M14 3a6 6 0 1 0 0 12 6 6 0 0 0 0-12M9 13l-6 6v3h3l2-2v-2h2l2-2M16 7h.01',
    close: 'M6 6l12 12M18 6L6 18',
    save: 'M5 3h12l4 4v14H3V3zM7 3v6h10V3M7 21v-8h10v8',
    print: 'M7 8V3h10v5M7 17H3V9h18v8h-4M7 14h10v7H7z',
    trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
  };
  function icon(name) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0b6847" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="${paths[name] || paths.document}"/></svg>`;
    return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
  }
  function kind(label) {
    if (/^age$/i.test(label.trim())) return 'calendar';
    if (/^gender$/i.test(label.trim())) return 'gender';
    if (/^civil status$/i.test(label.trim())) return 'group';
    if (/^location$/i.test(label.trim())) return 'location';
    if (/username|email/i.test(label)) return 'mail';
    if (/mobile|phone|contact/i.test(label)) return 'phone';
    if (/status|access|role/i.test(label)) return 'shield';
    if (/date|birth|registered|created/i.test(label)) return 'calendar';
    if (/position|work/i.test(label)) return 'work';
    if (/address|location|purok|district/i.test(label)) return 'home';
    if (/\bid\b|code|number/i.test(label)) return 'card';
    if (/name|resident|staff|age|gender|civil/i.test(label)) return 'person';
    return 'document';
  }
  function decorateDetails(root) {
    Array.from(root.children).forEach(row => {
      if (row.tagName !== 'DIV') return;
      const label = row.querySelector(':scope > span');
      const value = row.querySelector(':scope > strong');
      if (!label || !value) return;
      row.classList.add('portal-detail-card');
      row.style.setProperty('--detail-icon', icon(kind(label.textContent)));
      if (/^status$/i.test(label.textContent.trim())) {
        const status = value.textContent.trim().toLowerCase();
        value.dataset.detailStatus = /^(active|released|paid|approved|verified)$/.test(status) ? 'positive'
          : /^(inactive|rejected|unpaid|deactivated)$/.test(status) ? 'negative' : 'neutral';
      }
    });
  }
  document.querySelectorAll('.modal').forEach(modal => {
    // Resident identification previews retain their established design.
    if (modal.id === 'viewResidentModal' || modal.querySelector('.qr-id-modal-panel')) return;
    modal.querySelectorAll('.modal-panel').forEach(panel => panel.classList.add('portal-dialog-panel'));
    modal.querySelectorAll('.modal-header').forEach(header => {
      header.classList.add('portal-dialog-header');
      const title = header.querySelector('h2')?.textContent || '';
      header.style.setProperty('--header-icon', icon(kind(title)));
    });
    modal.querySelectorAll('.modal-footer .btn').forEach(button => {
      const text = button.textContent.trim();
      const type = /reset password/i.test(text) ? 'key' : /edit/i.test(text) ? 'edit'
        : /save|create/i.test(text) ? 'save' : /close|cancel/i.test(text) ? 'close'
        : /deactivate|activate/i.test(text) ? 'shield' : /delete/i.test(text) ? 'trash'
        : /print/i.test(text) ? 'print' : null;
      if (!type) return;
      button.classList.add('portal-icon-action');
      button.style.setProperty('--action-icon', icon(type));
    });
  });
  document.querySelectorAll('.resident-details-grid, .issuance-resident-summary').forEach(root => {
    decorateDetails(root);
    new MutationObserver(() => decorateDetails(root)).observe(root, {childList: true, subtree: true, characterData: true});
  });
})();
