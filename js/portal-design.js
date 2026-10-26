(() => {
  'use strict';
  const iconNames = {person:'user', group:'users', gender:'users-round', location:'map-pin', card:'id-card', shield:'shield-check', calendar:'calendar-days', work:'briefcase-business', home:'house', document:'file-text', edit:'pencil', key:'key-round', close:'x', print:'printer', trash:'trash-2', mail:'mail', phone:'phone', save:'save'};
  function icon(name) {
    if (typeof window !== 'undefined' && window.barangayIconUrl) return window.barangayIconUrl(name);
    return `url("icons/system/${iconNames[name] || 'file-text'}.svg?v=2.1.1")`;
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
