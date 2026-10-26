/* Shared Phosphor duotone UI icons. Sources and license are bundled in js/vendor/. */
(() => {
  'use strict';
  const icons = window.barangayIcons;
  if (!icons) return;
  const aliases = {person:'user', group:'users', gender:'users-round', location:'map-pin', card:'id-card', shield:'shield-check', calendar:'calendar-days', work:'briefcase-business', home:'house', document:'file-text', edit:'pencil', key:'key-round', close:'x', print:'printer', trash:'trash-2', id:'id-card', user:'user', date:'calendar-days', cash:'hand-coins', message:'message-square-text', success:'circle-check', failed:'circle-x', pending:'clock', qr:'qr-code', attachment:'paperclip', released:'file-text', pickup:'bell', received:'circle-check'};
  const nav = {
    dashboard:'layout-dashboard', 'staff-dashboard':'layout-dashboard', 'resident-dashboard':'layout-dashboard',
    'user-management':'user-round-cog', residents:'contact-round', 'resident-management':'contact-round',
    'qr-verification':'qr-code', 'staff-qr-verification':'qr-code', 'issue-documents':'file-plus-2',
    'resident-registration':'user-round-plus', 'staff-resident-registration':'user-round-plus',
    'sms-alerts':'message-square-text', 'staff-sms-alerts':'message-square-text',
    'walk-in-payments':'wallet', 'husay-schedule':'calendar-clock', complaints:'triangle-alert', 'staff-complaints':'triangle-alert', 'my-complaints':'triangle-alert',
    feedbacks:'messages-square', 'staff-feedbacks':'messages-square', 'my-feedbacks':'messages-square',
    'reports-analytics':'chart-no-axes-combined', 'resident-account-registration':'user-round-plus', 'template-placeholders':'file-text', 'document-fees':'hand-coins', 'my-activity-logs':'clock', reports:'chart-no-axes-combined', 'service-reports':'chart-column', 'audit-logs':'clipboard-list',
    'system-settings':'settings', 'backup-restore':'database', 'my-profile':'circle-user-round',
    'my-document-requests':'file-text', 'staff-document-requests':'file-text', documents:'file-text',
    'resident-announcements':'megaphone', announcements:'megaphone', events:'calendar-days',
    'document-templates':'file-text', 'document-pricing':'hand-coins', 'activity-logs':'clock'
  };
  const svgMarkup = (name, color='currentColor') => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="${color}" stroke="none" data-system-icon="${aliases[name] || name}" aria-hidden="true">${icons[aliases[name] || name] || icons['file-text']}</svg>`;
  window.barangayIconUrl = name => `url("data:image/svg+xml,${encodeURIComponent(svgMarkup(name, '#0b6847'))}")`;
  function kind(text) {
    text = text.trim().toLowerCase();
    const rules = [
      [/password|security|\bkey\b/, 'key-round'], [/logout|log out|sign out/, 'log-out'],
      [/mediation|husay|hearing/, 'calendar-clock'], [/qr|scan/, 'qr-code'], [/dashboard/, 'layout-dashboard'],
      [/notification|\bbell\b/, 'bell'], [/close|cancel/, 'x'], [/reprint|print/, 'printer'],
      [/download|docx/, 'download'], [/upload/, 'upload'], [/attachment|photo|image/, 'image'],
      [/delete|remove/, 'trash-2'], [/save/, 'save'], [/edit|update/, 'pencil'], [/view|preview|details/, 'eye'],
      [/search|find/, 'search'], [/refresh|retry|reload|reset/, 'refresh-cw'], [/next|forward/, 'chevron-right'], [/previous|back/, 'chevron-left'],
      [/complaint/, 'triangle-alert'], [/feedback/, 'messages-square'], [/sms|send|message/, 'message-square-text'],
      [/payment|pricing|fee|amount/, 'wallet'], [/report|analytics/, 'chart-column'], [/audit/, 'clipboard-list'],
      [/backup|database|restore/, 'database'], [/setting|configuration/, 'settings'], [/announcement|post/, 'megaphone'],
      [/calendar|event|birth|date|registered/, 'calendar-days'], [/time|clock|last used/, 'clock'],
      [/username|email/, 'mail'], [/phone|mobile|contact/, 'phone'], [/address|purok|sitio|home/, 'house'],
      [/location|venue/, 'map-pin'], [/gender|civil status/, 'users-round'], [/status|verified|verification|role|active/, 'shield-check'],
      [/resident id|\bid\b|code|number/, 'id-card'], [/resident|staff|user|name|profile/, 'user'], [/document|request/, 'file-text']
    ];
    return rules.find(([pattern]) => pattern.test(text))?.[1];
  }
  function replace(svg, name) {
    if (!icons[name] || svg.dataset.systemIcon) return;
    svg.dataset.systemIcon = name;
    // Keep the original SVG element: focus, event targets and dimensions remain stable.
    const slash = svg.querySelector('[data-password-eye-slash]');
    svg.innerHTML = icons[name];
    if (slash) { slash.setAttribute('transform', 'scale(10.6666667)'); svg.append(slash); }
    for (const [key,value] of Object.entries({viewBox:'0 0 256 256',fill:'currentColor',stroke:'none','stroke-width':'0','stroke-linecap':'round','stroke-linejoin':'round','aria-hidden':'true'})) svg.setAttribute(key,value);
    svg.classList.add('system-icon');
  }
  function identify(svg) {
    if (svg.dataset.iconName) return aliases[svg.dataset.iconName] || (icons[svg.dataset.iconName] ? svg.dataset.iconName : kind(svg.dataset.iconName));
    if (svg.classList.contains('input-icon')) {
      const input = svg.parentElement.querySelector('input, select');
      if (input) return /password/i.test(input.id) ? 'lock-keyhole' : /role/i.test(input.id) ? 'users' : 'user';
    }
    if (svg.closest('.feature-icon')) {
      const heading = svg.parentElement.parentElement.querySelector('h2, h3, h4, strong');
      return kind(heading?.textContent || '') || 'file-text';
    }
    if (svg.closest('.requirement-icon')) return kind(svg.closest('.requirement-item')?.querySelector('h3')?.textContent || '') || 'file-text';
    if (svg.closest('.requirements-alert')) return 'badge-check';
    if (svg.closest('[data-password-toggle]') || svg.querySelector('[data-password-eye-slash]')) return 'eye';
    if (svg.closest('[data-notification-toggle]')) return 'bell';
    if (svg.closest('[data-sidebar-toggle], .sidebar-toggle, .mobile-menu-btn')) return 'menu';
    if (svg.classList.contains('profile-caret')) return 'chevron-down';
    const link = svg.closest('[data-nav-view]');
    if (link) return nav[link.dataset.navView] || kind(link.querySelector('.nav-label, .nav-link-label')?.textContent || link.textContent);
    const option = svg.closest('[data-post-toggle], [data-post-menu-toggle], [data-post-options-toggle], [data-notification-options-toggle], [data-complaint-options-toggle]');
    if (option) return 'ellipsis-vertical';
    const control = svg.closest('button, a, summary');
    if (control) {
      const text = (control.getAttribute('aria-label') || control.getAttribute('title') || control.textContent).trim();
      if (/^[.…]+$/.test(text)) return 'ellipsis-vertical';
      if (text === '×' || text === '✕') return 'x';
      if (svg.closest('.user-menu')) return 'chevron-down';
      return kind(text);
    }
    const field = svg.closest('.staff-account-info > div, .registration-record-field, .request-card-field, .payment-card-field, .field, .form-group, .stat-card, .summary-card');
    if (field) return kind(field.querySelector('dt, label, .stat-label, small, span:not(:has(svg))')?.textContent || field.textContent);
    const row = svg.closest('.backup-action-card, .action-card, .feature-item') || svg.parentElement?.parentElement;
    return kind(row?.querySelector('dt, h3, h4')?.textContent || svg.getAttribute('aria-label') || '');
  }
  function decorate(root) {
    const all = selector => [...(root.matches?.(selector) ? [root] : []), ...root.querySelectorAll(selector)];
    all('svg').forEach(svg => {
      if (svg.dataset.systemIcon) return;
      if (svg.closest('.chart-container, .chart, [data-chart]') || !['0 0 24 24', '0 0 20 20', '0 0 256 256', null].includes(svg.getAttribute('viewBox'))) return;
      const name = identify(svg);
      if (name) replace(svg,name);
    });
    all('img[src*="icons/"]').forEach(img => {
      const filename = decodeURIComponent(img.getAttribute('src').split('/').pop()).toLowerCase();
      const name = {'person.png':'user', 'people group.png':'users', 'map pin.png':'map-pin', 'id-card.png':'id-card', 'house.png':'house', 'gender.png':'users-round', 'envelope.png':'mail', 'calendar.png':'calendar-days', 'phone.png':'phone', 'shield.png':'shield-check'}[filename];
      if (!name || img.dataset.systemIcon) return;
      img.dataset.systemIcon = name;
      img.src = 'data:image/svg+xml,' + encodeURIComponent(svgMarkup(name, '#0b6847'));
    });
    all('.modal-close, [data-notification-options-toggle], [data-complaint-options-toggle], [data-post-menu-toggle], .notification-more').forEach(button => {
      if (button.querySelector('svg')) return;
      const name = button.classList.contains('modal-close') ? 'x' : 'ellipsis-vertical';
      // Replace the symbol text only; preserve accessibility and action attributes.
      [...button.childNodes].filter(node => node.nodeType === 3).forEach(node => node.remove());
      button.insertAdjacentHTML('afterbegin',svgMarkup(name));
    });
  }
  decorate(document);
  new MutationObserver(records => {
    const roots = new Set();
    records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType !== 1 || node.closest?.('svg')) return;
      roots.add(node);
    }));
    roots.forEach(decorate);
  }).observe(document.body, {childList:true, subtree:true});
})();
