/**
 * Barangay Luna MIS â€” Admin Dashboard
 */

(function () {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const issuanceForm = document.getElementById('issueDocumentForm');
  const issuancePaymentMode = document.querySelector('[data-issuance-payment-mode]');
  const issuanceModal = document.getElementById('issuancePrintModal');
  if (issuanceModal) {
    const frame = issuanceModal.querySelector('[data-issuance-print-frame]');
    const feedback = issuanceModal.querySelector('[data-issuance-feedback]');
    const generate = document.querySelector('[data-issuance-generate]');
    const preparedList = document.querySelector('[data-prepared-list]');
    const refreshPreparedCount = () => {
      if (!preparedList) return;
      const count = preparedList.querySelectorAll('[data-prepared-document-id]').length;
      document.querySelector('[data-prepared-count]').textContent = String(count);
      document.querySelector('[data-prepared-empty]').hidden = count > 0;
    };
    const addPreparedDocument = record => {
      if (!preparedList || !record || !Number.isInteger(record.id)) return;
      if (preparedList.querySelector(`[data-prepared-document-id="${record.id}"]`)) return;
      const row = document.createElement('article');
      row.className = 'issuance-prepared-record';
      row.dataset.preparedDocumentId = String(record.id);
      const label = document.createElement('p');
      label.textContent = `${record.resident_name} — ${record.document_type} · ${record.document_number}`;
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-outline';
      button.textContent = 'Print / Confirm Release';
      button.addEventListener('click', () => {
        preparedInput = '';
        showPreview(`staff-print-issued-document.php?id=${record.id}&popup=1`);
        openModal(issuanceModal.id);
      });
      const remove = document.createElement('button');
      remove.type = 'button'; remove.className = 'btn btn-danger';
      remove.textContent = 'Remove'; remove.dataset.removePrepared = '';
      const actions = document.createElement('div');
      actions.className = 'issuance-prepared-actions'; actions.append(button, remove);
      row.append(label, actions); preparedList.prepend(row); refreshPreparedCount();
    };
    let preparedInput = '';
    const inputSnapshot = () => JSON.stringify(Array.from(new FormData(issuanceForm).entries()));
    const showPreview = url => {
      const documentId = /[?&]id=(\d+)/.exec(url)?.[1];
      issuanceModal.dataset.documentId = documentId || '';
      issuanceModal.dataset.released = 'false';
      frame.hidden = false;
      feedback.hidden = false;
      feedback.textContent = 'Preparing the print preview…';
      frame.src = url;
      issuanceModal.dataset.previewUrl = url;
      issuanceModal.dataset.prepared = 'true';
    };
    frame.addEventListener('load', () => {
      if (!frame.hidden) feedback.hidden = true;
      try {
        if (frame.contentDocument?.body && !frame.contentDocument.body.hasAttribute('data-document-print')) {
          frame.hidden = true;
          feedback.hidden = false;
          feedback.textContent = 'The printable document could not be opened. Your session may have expired. Close this popup and refresh the prepared list.';
          return;
        }
        issuanceModal.dataset.released = frame.contentDocument?.body?.dataset.issuanceStatus === 'Released' ? 'true' : 'false';
        if (issuanceModal.dataset.released === 'true' && preparedList) {
          const id = Number(frame.contentDocument.body.dataset.documentId);
          if (Number.isInteger(id) && id > 0) preparedList.querySelector(`[data-prepared-document-id="${id}"]`)?.remove();
          refreshPreparedCount();
        }
      } catch (_) { /* A session redirect may temporarily make the frame unavailable. */ }
    });
    document.querySelectorAll('[data-issuance-preview-url]').forEach(button => {
      button.addEventListener('click', () => { preparedInput = ''; showPreview(button.dataset.issuancePreviewUrl); });
    });
    issuanceForm?.addEventListener('submit', async event => {
      event.preventDefault();
      if (generate.disabled) return;
      const requestCode = issuanceForm.querySelector('[name="request_code"]')?.value;
      if (issuanceModal.dataset.prepared === 'true' && (preparedInput === inputSnapshot() || (preparedInput && requestCode))) {
        openModal(issuanceModal.id);
        return;
      }
      if (issuanceModal.dataset.prepared === 'true') {
        // Edited applicant details are a new preparation; retain the previous saved document.
        const number = issuanceForm.querySelector('[name="document_number"]');
        number.value = 'BL-' + new Date().toISOString().replace(/\D/g, '').slice(0, 14) + '-' + crypto.getRandomValues(new Uint32Array(1))[0].toString(16).padStart(8, '0').toUpperCase();
        number.dispatchEvent(new Event('input', {bubbles: true}));
        delete issuanceModal.dataset.prepared;
      }
      const submittedInput = inputSnapshot();
      generate.disabled = true;
      issuanceModal.dataset.generating = 'true';
      issuanceModal.dataset.released = 'false';
      generate.textContent = 'Preparing document…';
      frame.hidden = true;
      feedback.hidden = false;
      feedback.textContent = 'Generating the printable file. The document is not released yet.';
      openModal(issuanceModal.id);
      try {
        // The hidden input named "action" shadows the form.action property.
        const response = await fetch(issuanceForm.getAttribute('action'), {
          method: 'POST', body: new FormData(issuanceForm),
          headers: {'X-Requested-With': 'IssuancePopup'}
        });
        if (response.redirected && new URL(response.url).pathname.endsWith('/login.php')) {
          throw new Error('Your session has expired. Sign in again, then reopen Issue Documents.');
        }
        if (!(response.headers.get('Content-Type') || '').includes('application/json')) {
          throw new Error(response.status === 404
            ? 'The document generation page could not be found. Refresh Issue Documents and try again.'
            : 'The server could not prepare the document. Check prepared documents before retrying.');
        }
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Unable to prepare this document.');
        showPreview(result.url);
        preparedInput = submittedInput;
        addPreparedDocument(result.document);
      } catch (error) {
        feedback.textContent = error instanceof SyntaxError
          ? 'The server did not return a printable document. Check prepared documents before retrying.' : error.message;
      } finally {
        delete issuanceModal.dataset.generating;
        generate.disabled = false;
        generate.textContent = 'Prepare printable document';
      }
    });
  }
  if (issuanceForm && issuancePaymentMode) {
    const paymentFields = document.querySelector('[data-issuance-payment-fields]');
    const paymentAmount = document.getElementById('issuanceAmount');
    const feeField = issuanceForm.querySelector('[data-autofill-document-fee]');
    const syncIssuancePayment = () => {
      const fee = Number((feeField?.value || '0').replace(/[^0-9.]/g, '')) || 0;
      paymentAmount.value = fee.toFixed(2);
      issuancePaymentMode.disabled = fee === 0;
      issuancePaymentMode.options[0].textContent = fee === 0 ? 'No Payment Required' : 'Unpaid — collect before release';
      if (fee === 0) issuancePaymentMode.value = 'unpaid';
      const paid = fee > 0 && issuancePaymentMode.value === 'paid';
      paymentFields.hidden = !paid;
      paymentAmount.required = paid;
      paymentAmount.disabled = !paid;
      document.querySelector('[data-issuance-payment-hint]').textContent = fee === 0
        ? 'No Payment Required — this document is free.'
        : `Total fee: PHP ${fee.toFixed(2)}. Record payment now or collect it before release.`;
    };
    issuancePaymentMode.addEventListener('change', syncIssuancePayment);
    issuanceForm.addEventListener('input', syncIssuancePayment);
    issuanceForm.addEventListener('change', syncIssuancePayment);
    syncIssuancePayment();
  }
  document.querySelectorAll('details').forEach((section) => {
    section.addEventListener('toggle', () => {
      if (!section.open || reducedMotion.matches) return;
      Array.from(section.children).filter((child) => child.tagName !== 'SUMMARY').forEach((child) => {
        child.animate?.([{opacity: 0, transform: 'translateY(-4px)'}, {opacity: 1, transform: 'translateY(0)'}],
          {duration: 200, easing: 'cubic-bezier(.2,.8,.2,1)'});
      });
    });
  });

  document.querySelectorAll('.info-banner').forEach((notice) => {
    notice.classList.add('dismissible-notice');
    let button = notice.querySelector('[data-hide-resident-notice], [data-hide-notice]');
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'notice-hide';
      button.textContent = 'Hide';
      const title = notice.querySelector('strong')?.textContent.trim() || 'information';
      button.setAttribute('aria-label', `Hide ${title} notice`);
      notice.appendChild(button);
    }
    button.setAttribute('data-hide-notice', '');
    button.addEventListener('click', () => {
      if (button.disabled) return;
      button.disabled = true;
      const finish = () => { notice.hidden = true; };
      if (!reducedMotion.matches && notice.animate) {
        const height = notice.getBoundingClientRect().height;
        const margin = getComputedStyle(notice).marginBottom;
        const paddingTop = getComputedStyle(notice).paddingTop;
        const paddingBottom = getComputedStyle(notice).paddingBottom;
        notice.style.overflow = 'hidden';
        const animation = notice.animate([
          {opacity: 1, height: `${height}px`, marginBottom: margin, paddingTop, paddingBottom},
          {opacity: 0, height: '0px', marginBottom: '0px', paddingTop: '0px', paddingBottom: '0px', borderWidth: '0px'}
        ], {duration: 220, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'forwards'});
        animation.onfinish = () => { finish(); animation.cancel(); };
        animation.oncancel = finish;
      } else finish();
      const nextControl = Array.from(document.querySelectorAll('button, a[href], input:not([type="hidden"]), select, textarea'))
        .find((control) => !notice.contains(control) && !control.disabled && control.getClientRects().length &&
          (notice.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_FOLLOWING));
      nextControl?.focus({preventScroll: true});
    });
  });

  document.querySelectorAll('[data-post-type]').forEach((select) => {
    const form = select.closest('form');
    const fields = form.querySelector('[data-event-fields]');
    const syncPostType = () => {
      const isEvent = select.value === 'event';
      fields.hidden = !isEvent;
      fields.querySelectorAll('input').forEach((input) => {
        input.disabled = !isEvent;
        input.required = isEvent;
      });
    };
    select.addEventListener('change', syncPostType);
    syncPostType();
  });

  const publishForm = document.getElementById('announcementForm');
  if (publishForm) {
    const schedule = publishForm.querySelector('[data-schedule-toggle]');
    const label = publishForm.querySelector('[data-publish-label]');
    const syncPublishLabel = () => { label.textContent = schedule.checked ? 'Schedule post' : 'Publish post'; };
    schedule.addEventListener('change', syncPublishLabel);
    syncPublishLabel();
  }

  let lastActivityPing = 0;
  function recordUserActivity() {
    if (Date.now() - lastActivityPing < 60000) return;
    const token = document.querySelector('#securitySettingsForm [name="csrf"]')?.value;
    if (!token) return;
    lastActivityPing = Date.now();
    fetch('session-activity.php', {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({csrf: token})}).catch(() => {});
  }
  ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(type => document.addEventListener(type, recordUserActivity, {passive: true}));

  const logoUpload = document.getElementById('logoUpload');
  if (logoUpload) {
    const preview = document.querySelector('.logo-preview img');
    let localLogoUrl = '';
    function resetLogoPreview() {
      if (localLogoUrl) URL.revokeObjectURL(localLogoUrl);
      localLogoUrl = '';
      if (preview) preview.src = 'branding-logo.php';
    }
    logoUpload.addEventListener('change', () => {
      resetLogoPreview();
      const file = logoUpload.files[0];
      if (file && preview && ['image/png', 'image/jpeg'].includes(file.type)) {
        localLogoUrl = URL.createObjectURL(file);
        preview.src = localLogoUrl;
      }
    });
    logoUpload.form.addEventListener('reset', resetLogoPreview);
  }

  const toastRegion = document.createElement('div');
  toastRegion.className = 'action-toasts';
  toastRegion.setAttribute('aria-live', 'polite');
  toastRegion.setAttribute('aria-relevant', 'additions');
  document.body.appendChild(toastRegion);
  function showActionToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `action-toast${type === 'error' || type === 'danger' ? ' action-toast--error' : ''}`;
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = '\u00d7';
    close.setAttribute('aria-label', 'Dismiss message');
    toast.append(text, close);
    toastRegion.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    let timer;
    const dismiss = () => {
      clearTimeout(timer);
      toast.classList.remove('is-visible');
      setTimeout(() => toast.remove(), 350);
    };
    const schedule = () => {
      clearTimeout(timer);
      timer = setTimeout(dismiss, type === 'error' || type === 'danger' ? 8000 : 4500);
    };
    close.addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', () => clearTimeout(timer));
    toast.addEventListener('mouseleave', schedule);
    toast.addEventListener('focusin', () => clearTimeout(timer));
    toast.addEventListener('focusout', schedule);
    schedule();
  }
  window.barangayToast = showActionToast;
  document.querySelectorAll('[data-action-toast]').forEach(source => {
    showActionToast(source.textContent.trim(), source.dataset.actionToast);
    source.remove();
  });
  // Redirect status messages are one-time confirmations, not reload reminders.
  const actionUrl = new URL(window.location.href);
  if (actionUrl.searchParams.has('status')) {
    actionUrl.searchParams.delete('status');
    history.replaceState(history.state, '', actionUrl);
  }

  // Refresh read-only overview sections without replacing forms, menus or dialogs.
  const overviewOwner = document.querySelector('[data-overview-owner]');
  if (overviewOwner) {
    let overviewRefreshing = false;
    const overviewUrl = new URL(window.location.href);
    overviewUrl.searchParams.delete('status');
    overviewUrl.hash = '';
    const overviewBusy = () => document.hidden ||
      !!document.querySelector('.modal.is-open, [data-post-options].is-open') ||
      !!window.getSelection()?.toString();
    async function refreshOverview() {
      if (overviewRefreshing || overviewBusy() || !navigator.onLine) return;
      overviewRefreshing = true;
      try {
        const response = await fetch(overviewUrl, {
          credentials: 'same-origin', cache: 'no-store',
          signal: AbortSignal.timeout(15000)
        });
        if (!response.ok || response.redirected) return;
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const owner = page.querySelector('[data-overview-owner]');
        if (overviewBusy() || owner?.dataset.overviewOwner !== overviewOwner.dataset.overviewOwner) return;
        document.querySelectorAll('[data-live-overview]').forEach(current => {
          const next = page.querySelector(`[data-live-overview="${current.dataset.liveOverview}"]`);
          const isFeedbackList = current.dataset.liveOverview === 'community-feedback';
          if (next && current.innerHTML !== next.innerHTML && (!current.contains(document.activeElement) || isFeedbackList)) {
            const focused = isFeedbackList && current.contains(document.activeElement) ? document.activeElement : null;
            const focusedCode = focused?.closest('[data-feedback-code]')?.dataset.feedbackCode;
            const action = focused?.matches('[data-view-feedback]') ? '[data-view-feedback]'
              : focused?.matches('[data-delete-feedback]') ? '[data-delete-feedback]' : '[data-feedback-unread] button';
            current.replaceChildren(...Array.from(next.childNodes));
            if (focusedCode) [...current.querySelectorAll('[data-feedback-code]')]
              .find(row => row.dataset.feedbackCode === focusedCode)?.querySelector(action)?.focus({preventScroll: true});
          }
        });
        document.dispatchEvent(new CustomEvent('overview-updated'));
      } catch (_) {
        // Keep the last usable overview during a temporary connection failure.
      } finally {
        overviewRefreshing = false;
      }
    }
    window.setInterval(refreshOverview, 5000);
    document.addEventListener('document-notifications-updated', refreshOverview);
    window.addEventListener('online', refreshOverview);
    window.addEventListener('focus', refreshOverview);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) refreshOverview();
    });
  }

  /* Sidebar mobile */
  document.querySelectorAll('[data-document-fee-form]').forEach(form => {
    const picker = form.querySelector('[data-fee-picker]');
    const options = [...form.querySelectorAll('[name="document_types[]"]')];
    const all = form.querySelector('[data-fee-select-all]');
    const label = form.querySelector('[data-fee-selection-label]');
    const error = form.querySelector('[data-fee-selection-error]');
    function syncFeeSelection() {
      const selected = options.filter(option => option.checked);
      label.textContent = selected.length === 1 ? selected[0].value : selected.length ? `${selected.length} document types selected` : 'Select document types';
      all.checked = selected.length === options.length;
      all.indeterminate = selected.length > 0 && selected.length < options.length;
      if (selected.length) error.hidden = true;
    }
    all.addEventListener('change', () => {
      options.forEach(option => { option.checked = all.checked; });
      syncFeeSelection();
    });
    options.forEach(option => option.addEventListener('change', syncFeeSelection));
    document.addEventListener('click', event => {
      if (!picker.contains(event.target)) picker.open = false;
    });
    picker.addEventListener('keydown', event => {
      if (event.key === 'Escape') { picker.open = false; picker.querySelector('summary').focus(); }
    });
    form.addEventListener('submit', event => {
      if (!options.some(option => option.checked)) {
        event.preventDefault(); error.hidden = false; picker.open = true;
        options[0]?.focus();
      }
    });
  });

  /* Sidebar mobile */
  const sidebar = document.getElementById('appSidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  let sidebarBackdrop = null;

  function ensureSidebarBackdrop() {
    if (sidebarBackdrop || !sidebar) return sidebarBackdrop;
    sidebarBackdrop = document.createElement('button');
    sidebarBackdrop.type = 'button';
    sidebarBackdrop.className = 'sidebar-backdrop';
    sidebarBackdrop.setAttribute('aria-label', 'Close menu');
    sidebar.insertAdjacentElement('afterend', sidebarBackdrop);
    sidebarBackdrop.addEventListener('click', closeSidebar);
    return sidebarBackdrop;
  }

  function openSidebar() {
    if (!sidebar) return;
    ensureSidebarBackdrop();
    sidebar.classList.add('is-open');
    document.body.classList.add('sidebar-is-open');
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    document.body.classList.remove('sidebar-is-open');
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', String(window.innerWidth > 900 && !sidebar.classList.contains('is-collapsed')));
  }

  if (sidebarToggle) {
    sidebarToggle.setAttribute('aria-expanded', 'false');
    sidebarToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      if (!sidebar) return;
      if (window.innerWidth > 900) {
        toggleSidebarWidth();
      } else {
        if (sidebar.classList.contains('is-open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      }
    });
  }
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeSidebar();
  });

  // Width and menu scroll are restored by sidebar-state.php before first paint.

  /* Header profile dropdown */
  const profileMenus = document.querySelectorAll('[data-profile-menu]');
  function closeProfileMenus(exceptMenu) {
    profileMenus.forEach((menu) => {
      if (exceptMenu && menu === exceptMenu) return;
      menu.classList.remove('is-open');
      const menuToggle = menu.querySelector('[data-profile-toggle]');
      if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    });
  }
  document.querySelectorAll('[data-profile-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = toggle.closest('[data-profile-menu]');
      if (!menu) return;
      const willOpen = !menu.classList.contains('is-open');
      closeProfileMenus(menu);
      menu.classList.toggle('is-open', willOpen);
      toggle.setAttribute('aria-expanded', String(willOpen));
    });
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-profile-menu]')) closeProfileMenus();
  });

  /* Header notification bell */
  const notificationMenus = document.querySelectorAll('[data-notification-menu]');
  const persistentMenu = document.querySelector('[data-notification-account]');
  const notificationIdentity = document.querySelector('[data-notification-owner]')?.dataset.notificationOwner || persistentMenu?.dataset.notificationAccount || '';
  const notificationAccountSuffix = notificationIdentity ? ':' + notificationIdentity : '';
  const notificationSeenKey = 'barangayNotificationSeen' + notificationAccountSuffix;
  const notificationReadKey = 'barangayNotificationRead' + notificationAccountSuffix;
  const notificationUnreadKey = 'barangayNotificationUnread' + notificationAccountSuffix;
  const notificationDismissedKey = 'barangayNotificationDismissed' + notificationAccountSuffix;
  const notificationKinds = {[notificationSeenKey]: 'seen', [notificationReadKey]: 'read', [notificationUnreadKey]: 'unread', [notificationDismissedKey]: 'dismissed'};
  const confirmedNotificationState = {seen: new Set(), read: new Set(), unread: new Set(), dismissed: new Set()};
  const memoryNotificationState = {};
  const notificationRefreshMs = 2000;
  let notificationRefreshPending = false;
  let notificationServerCounts = {};

  function mergeNotificationState(state) {
    if (!persistentMenu || !state) return;
    Object.entries(notificationKinds).forEach(([key, kind]) => {
      const values = readNotificationSet(key);
      (state[kind] || []).forEach(id => {
        const oppositeKey = kind === 'read' ? notificationUnreadKey : kind === 'unread' ? notificationReadKey : null;
        if (oppositeKey && readNotificationSet(oppositeKey).has(id)) return;
        confirmedNotificationState[kind].add(id);
        values.add(id);
      });
      saveNotificationSet(key, values);
    });
  }

  function readNotificationSet(key) {
    try {
      return new Set([...(memoryNotificationState[key] || []), ...JSON.parse(localStorage.getItem(key) || '[]')]);
    } catch (error) {
      return new Set(memoryNotificationState[key] || []);
    }
  }

  function saveNotificationSet(key, values) {
    memoryNotificationState[key] = [...values];
    try { localStorage.setItem(key, JSON.stringify([...values])); } catch (_) {}
    if (!persistentMenu) return;
    const kind = notificationKinds[key];
    const unsaved = [...values].filter(id => !confirmedNotificationState[kind].has(id));
    for (let offset = 0; offset < unsaved.length; offset += 300) {
      const ids = unsaved.slice(offset, offset + 300);
      fetch('notification-state.php', {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: persistentMenu.dataset.notificationToken, kind, ids})
      }).then(response => {
        if (response.ok) ids.forEach(id => confirmedNotificationState[kind].add(id));
      }).catch(() => {}); // Retry unconfirmed entries with the next feed refresh.
    }
  }
  if (persistentMenu) mergeNotificationState(JSON.parse(persistentMenu.dataset.notificationState || '{}'));

  function notificationViewFromHref(href) {
    try {
      const url = new URL(href, window.location.href);
      return url.searchParams.get('view') || '';
    } catch (error) {
      return '';
    }
  }

  function ensureNotificationBadge(menu) {
    let badge = menu.querySelector('.notification-count');
    if (badge) return badge;

    const button = menu.querySelector('[data-notification-toggle]');
    if (!button) return null;

    badge = document.createElement('span');
    badge.className = 'notification-count';
    badge.hidden = true;
    button.appendChild(badge);
    return badge;
  }

  function formatCount(count) {
    return count > 99 ? '99+' : String(count);
  }

  function setSideBadge(view, count) {
    if (!view) return;
    document.querySelectorAll(`[data-nav-view="${view}"], [data-notification-count-view="${view}"]`).forEach((navLink) => {
      let badge = navLink.querySelector('.nav-count');
      if (!badge && count > 0) {
        badge = document.createElement('span');
        badge.className = 'nav-count';
        badge.setAttribute('data-notification-side-count', '');
        navLink.appendChild(badge);
      }
      if (!badge) return;
      badge.textContent = formatCount(count);
      badge.hidden = count === 0;
    });
  }

  function setDashboardAnnouncementBadge(count) {
    const shell = document.querySelector('[data-announcement-dashboard-shell]');
    const badge = document.querySelector('[data-notification-dashboard-count]');
    if (!shell || !badge) return;

    badge.textContent = `${count} new announcement${count === 1 ? '' : 's'}`;
    shell.hidden = count === 0;
    badge.hidden = count === 0;
  }

  function setNotificationReadState(id, unread) {
    const removeKey = unread ? notificationReadKey : notificationUnreadKey;
    const addKey = unread ? notificationUnreadKey : notificationReadKey;
    const values = readNotificationSet(removeKey);
    values.delete(id);
    memoryNotificationState[removeKey] = [...values];
    try { localStorage.setItem(removeKey, JSON.stringify([...values])); } catch (_) {}
    confirmedNotificationState[notificationKinds[removeKey]].delete(id);
    const added = readNotificationSet(addKey);
    added.add(id);
    saveNotificationSet(addKey, added);
  }

  function bindNotificationItem(item) {
    if (item.dataset.notificationBound === 'true') return;
    item.dataset.notificationBound = 'true';
    item.addEventListener('click', () => {
      const id = item.getAttribute('data-notification-id');
      setNotificationReadState(id, false);
      const seen = readNotificationSet(notificationSeenKey);
      seen.add(id);
      saveNotificationSet(notificationSeenKey, seen);
      item.classList.remove('is-new');
      notificationMenus.forEach(syncNotificationMenu);
    });
  }

  function notificationRowFor(item) {
    return item.closest('[data-notification-row]') || item;
  }

  function ensureNotificationEmptyState(menu) {
    const list = menu.querySelector('.notification-list');
    if (!list) return;

    const hasItems = !!list.querySelector('[data-notification-id]');
    const existingEmpty = list.querySelector('.notification-empty');

    if (hasItems && existingEmpty) existingEmpty.remove();
    if (!hasItems && !existingEmpty) {
      const empty = document.createElement('div');
      empty.className = 'notification-empty';
      empty.textContent = 'No notifications yet.';
      list.appendChild(empty);
    }
  }

  function dismissNotification(id) {
    if (!id) return;
    const dismissed = readNotificationSet(notificationDismissedKey);
    dismissed.add(id);
    saveNotificationSet(notificationDismissedKey, dismissed);

    notificationMenus.forEach((menu) => {
      menu.querySelectorAll('[data-notification-id]').forEach((item) => {
        if (item.getAttribute('data-notification-id') === id) notificationRowFor(item).remove();
      });
      ensureNotificationEmptyState(menu);
      syncNotificationMenu(menu);
    });
  }

  function bindNotificationDismiss(button) {
    if (button.dataset.notificationDismissBound === 'true') return;
    button.dataset.notificationDismissBound = 'true';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      dismissNotification(button.getAttribute('data-notification-dismiss') || '');
    });
  }

  function closeNotificationOptions(except = null) {
    document.querySelectorAll('[data-notification-options-toggle]').forEach(button => {
      if (button === except) return;
      button.setAttribute('aria-expanded', 'false');
      const options = button.closest('[data-notification-row]')?.querySelector('[data-notification-options]');
      if (options) options.hidden = true;
    });
  }
  document.addEventListener('click', event => {
    const unreadButton = event.target.closest('[data-notification-unread]');
    if (unreadButton) {
      event.preventDefault();
      setNotificationReadState(unreadButton.getAttribute('data-notification-unread'), unreadButton.getAttribute('data-mark-unread') === 'true');
      closeNotificationOptions();
      notificationMenus.forEach(syncNotificationMenu);
      unreadButton.closest('[data-notification-row]')?.querySelector('[data-notification-options-toggle]')?.focus();
      return;
    }
    const button = event.target.closest('[data-notification-options-toggle]');
    if (button) {
      event.preventDefault();
      const open = button.getAttribute('aria-expanded') !== 'true';
      closeNotificationOptions(button);
      button.setAttribute('aria-expanded', String(open));
      const options = button.closest('[data-notification-row]')?.querySelector('[data-notification-options]');
      if (options) options.hidden = !open;
    } else if (!event.target.closest('[data-notification-options]')) closeNotificationOptions();
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    closeNotificationOptions();
  });

  function syncNotificationMenu(menu) {
    const seen = readNotificationSet(notificationSeenKey);
    const read = readNotificationSet(notificationReadKey);
    const dismissed = readNotificationSet(notificationDismissedKey);
    const items = menu.querySelectorAll('[data-notification-id]');
    let unseenCount = 0;
    const unseenByView = {};

    items.forEach((item) => {
      const id = item.getAttribute('data-notification-id');
      if (dismissed.has(id)) {
        notificationRowFor(item).remove();
        return;
      }

      const isServerNew = item.getAttribute('data-notification-new') === 'true' || readNotificationSet(notificationUnreadKey).has(id);
      const isRead = read.has(id);
      const isUnread = isServerNew && !isRead;
      item.classList.toggle('is-new', isUnread);
      const readAction = notificationRowFor(item).querySelector('[data-notification-unread]');
      if (readAction) {
        readAction.textContent = isUnread ? 'Mark as read' : 'Mark as unread';
        readAction.setAttribute('data-mark-unread', String(!isUnread));
      }
      if (isUnread && !seen.has(id)) {
        const view = item.getAttribute('data-notification-view') || notificationViewFromHref(item.getAttribute('href') || '');
        unseenCount += 1;
        if (view) unseenByView[view] = (unseenByView[view] || 0) + 1;
      }
    });

    const badge = ensureNotificationBadge(menu);
    if (badge) {
      badge.textContent = formatCount(unseenCount);
      badge.hidden = unseenCount === 0;
    }

    const summary = menu.querySelector('[data-notification-summary]');
    if (summary) {
      summary.textContent = unseenCount > 0 ? `${unseenCount} new` : 'No new alerts';
    }

    document.querySelectorAll('[data-nav-view]').forEach((navLink) => {
      const view = navLink.getAttribute('data-notification-count-view') || navLink.getAttribute('data-nav-view');
      if (view) setSideBadge(view, ['feedbacks', 'staff-feedbacks'].includes(view) && Object.prototype.hasOwnProperty.call(notificationServerCounts, view)
        ? Number(notificationServerCounts[view]) || 0 : unseenByView[view] || 0);
    });

    document.querySelectorAll('[data-notification-dashboard-count]').forEach((badge) => {
      const count = unseenByView['resident-announcements'] || 0;
      badge.hidden = count === 0;
      badge.textContent = `${count} new announcement${count === 1 ? '' : 's'}`;
    });

    const announcementShell = document.querySelector('[data-announcement-dashboard-shell]');
    if (announcementShell) announcementShell.hidden = !(unseenByView['resident-announcements'] || 0);

    ensureNotificationEmptyState(menu);
    menu.dataset.notificationReady = 'true';
  }

  function markNotificationsSeen(menu) {
    const seen = readNotificationSet(notificationSeenKey);
    menu.querySelectorAll('[data-notification-id]').forEach((item) => {
      seen.add(item.getAttribute('data-notification-id'));
    });
    saveNotificationSet(notificationSeenKey, seen);
    syncNotificationMenu(menu);
  }

  function markNotificationsSeenForView(view) {
    if (!view) return;
    const seen = readNotificationSet(notificationSeenKey);

    notificationMenus.forEach((menu) => {
      menu.querySelectorAll('[data-notification-id][data-notification-new="true"]').forEach((item) => {
        const itemView = item.getAttribute('data-notification-view') || notificationViewFromHref(item.getAttribute('href') || '');
        if (itemView === view) seen.add(item.getAttribute('data-notification-id'));
      });
    });

    saveNotificationSet(notificationSeenKey, seen);
    notificationMenus.forEach(syncNotificationMenu);
  }

  function renderNotificationItems(menu, items) {
    const list = menu.querySelector('.notification-list');
    if (!list) return;

    const dismissed = readNotificationSet(notificationDismissedKey);
    const visibleItems = items.filter((notification) => {
      const id = notification.id || `${notification.title || ''}|${notification.date || ''}`;
      return !dismissed.has(id);
    });
    const signature = JSON.stringify(visibleItems);
    if (menu.dataset.notificationItems === signature) return;
    menu.dataset.notificationItems = signature;
    const previousScroll = list.scrollTop;
    const focused = list.contains(document.activeElement) ? document.activeElement : null;
    const focusedAttribute = focused?.hasAttribute('data-notification-unread') ? 'data-notification-unread' : focused?.hasAttribute('data-notification-dismiss') ? 'data-notification-dismiss' : focused?.hasAttribute('data-notification-options-toggle') ? 'data-notification-options-toggle' : 'data-notification-id';
    const focusedId = focused?.getAttribute(focusedAttribute);
    const openOptions = new Set([...list.querySelectorAll('[data-notification-options-toggle]')].filter(button => button.getAttribute('aria-expanded') === 'true').map(button => button.getAttribute('data-notification-options-toggle')));

    list.innerHTML = '';
    if (!visibleItems.length) {
      const empty = document.createElement('div');
      empty.className = 'notification-empty';
      empty.textContent = 'No notifications yet.';
      list.appendChild(empty);
      return;
    }

    visibleItems.forEach((notification) => {
      const id = notification.id || `${notification.title || ''}|${notification.date || ''}`;
      const row = document.createElement('div');
      row.className = 'notification-row';
      row.setAttribute('data-notification-row', '');

      const item = document.createElement('a');
      item.className = 'notification-item';
      item.href = notification.href || '#';
      item.setAttribute('data-notification-id', id);
      item.setAttribute('data-notification-new', notification.status === 'Unread' ? 'true' : 'false');
      item.setAttribute('data-notification-view', notification.view || notificationViewFromHref(notification.href || ''));

      const title = document.createElement('strong');
      title.textContent = notification.title || 'Notification';
      const message = document.createElement('span');
      message.textContent = notification.message || '';
      const date = document.createElement('small');
      const rawDate = notification.date || notification.status || '';
      const timestamp = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/.test(rawDate) ? new Date(rawDate.replace(' ', 'T')) : null;
      date.textContent = timestamp && !Number.isNaN(timestamp.getTime())
        ? timestamp.toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit', hour12:true}) : rawDate;

      item.append(title, message, date);
      bindNotificationItem(item);

      const dismissButton = document.createElement('button');
      dismissButton.type = 'button';
      dismissButton.className = 'notification-remove-action';
      dismissButton.textContent = 'Remove notification';
      dismissButton.setAttribute('data-notification-dismiss', id);
      bindNotificationDismiss(dismissButton);

      const more = document.createElement('button');
      more.type = 'button'; more.className = 'notification-more'; more.textContent = '...';
      more.setAttribute('aria-label', 'Notification options'); more.setAttribute('aria-expanded', String(openOptions.has(id)));
      more.setAttribute('data-notification-options-toggle', id);
      const options = document.createElement('div'); options.className = 'notification-options'; options.setAttribute('data-notification-options', ''); options.hidden = !openOptions.has(id);
      const unreadButton = document.createElement('button');
      unreadButton.type = 'button'; unreadButton.className = 'notification-unread-action';
      const isUnread = (notification.status === 'Unread' || readNotificationSet(notificationUnreadKey).has(id)) && !readNotificationSet(notificationReadKey).has(id);
      unreadButton.textContent = isUnread ? 'Mark as read' : 'Mark as unread';
      unreadButton.setAttribute('data-mark-unread', String(!isUnread));
      unreadButton.setAttribute('data-notification-unread', id);
      options.append(unreadButton, dismissButton);
      row.append(item, more, options);
      list.appendChild(row);
    });
    list.scrollTop = previousScroll;
    if (focusedId) {
      [...list.querySelectorAll(`[${focusedAttribute}]`)].find(item => item.getAttribute(focusedAttribute) === focusedId)?.focus({preventScroll: true});
    }
  }

  async function refreshNotifications() {
    if (!notificationMenus.length || document.hidden || notificationRefreshPending) return;
    notificationRefreshPending = true;
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 10000);

    try {
      const response = await fetch('notification-feed.php', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: controller.signal,
      });
      if (!response.ok) return;

      const data = await response.json();
      if (data.owner !== notificationIdentity || document.hidden) return;
      mergeNotificationState(data.state);
      const items = Array.isArray(data.items) ? data.items : [];
      const sideCounts = data.sideCounts || {};
      notificationServerCounts = sideCounts;
      document.dispatchEvent(new CustomEvent('document-notifications-updated'));

      notificationMenus.forEach((menu) => {
        renderNotificationItems(menu, items);
      });

      Object.keys(sideCounts).forEach((view) => {
        setSideBadge(view, Number(sideCounts[view]) || 0);
      });
      if (Object.prototype.hasOwnProperty.call(sideCounts, 'resident-announcements')) {
        setDashboardAnnouncementBadge(Number(sideCounts['resident-announcements']) || 0);
      }

      notificationMenus.forEach(syncNotificationMenu);
      if (persistentMenu && !residentInitialViewSeen) {
        markNotificationsSeenForView(new URL(window.location.href).searchParams.get('view') || 'resident-dashboard');
        residentInitialViewSeen = true;
      }
    } catch (error) {
      // Keep the dashboard quiet if the connection briefly drops.
    } finally {
      window.clearTimeout(timeout);
      notificationRefreshPending = false;
    }
  }

  let residentInitialViewSeen = false;
  notificationMenus.forEach((menu) => {
    menu.querySelectorAll('[data-notification-id]').forEach(bindNotificationItem);
    menu.querySelectorAll('[data-notification-dismiss]').forEach(bindNotificationDismiss);
    syncNotificationMenu(menu);
  });

  function positionNotificationMenu(menu) {
    if (!menu.classList.contains('is-open')) return;
    const toggle = menu.querySelector('[data-notification-toggle]');
    const panel = menu.querySelector('[data-notification-dropdown]');
    if (!toggle || !panel) return;
    const rect = toggle.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const viewportHeight = window.visualViewport?.height || window.innerHeight;
    const width = Math.min(460, Math.max(0, viewportWidth - 24));
    const left = Math.max(12, Math.min(rect.right - width, viewportWidth - width - 12));
    const top = Math.max(12, rect.bottom + 10);
    Object.assign(panel.style, {top: `${top}px`, left: `${left}px`, right: 'auto', width: `${width}px`, maxHeight: `${Math.max(0, viewportHeight - top - 12)}px`});
  }
  let notificationPositionFrame = null;
  function scheduleNotificationPosition() {
    if (![...notificationMenus].some(menu => menu.classList.contains('is-open'))) return;
    if (notificationPositionFrame !== null) return;
    notificationPositionFrame = requestAnimationFrame(() => {
      notificationPositionFrame = null;
      notificationMenus.forEach(positionNotificationMenu);
    });
  }
  document.addEventListener('scroll', scheduleNotificationPosition, {capture: true, passive: true});
  window.addEventListener('resize', scheduleNotificationPosition);
  window.visualViewport?.addEventListener('resize', scheduleNotificationPosition);
  if (typeof ResizeObserver !== 'undefined') {
    const notificationHeaderObserver = new ResizeObserver(scheduleNotificationPosition);
    document.querySelectorAll('.top-header').forEach(header => notificationHeaderObserver.observe(header));
  }
  function closeNotificationMenus(exceptMenu) {
    closeNotificationOptions();
    notificationMenus.forEach((menu) => {
      if (exceptMenu && menu === exceptMenu) return;
      menu.classList.remove('is-open');
      const toggle = menu.querySelector('[data-notification-toggle]');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
  }
  document.querySelectorAll('[data-notification-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = toggle.closest('[data-notification-menu]');
      if (!menu) return;
      const willOpen = !menu.classList.contains('is-open');
      closeProfileMenus();
      closeNotificationMenus(menu);
      menu.classList.toggle('is-open', willOpen);
      toggle.setAttribute('aria-expanded', String(willOpen));
      if (willOpen) markNotificationsSeen(menu);
      if (willOpen) { positionNotificationMenu(menu); refreshNotifications(); }
    });
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-notification-menu]')) closeNotificationMenus();
  });
  // Close transient menus before a section navigation or a cached-page restore.
  function clearNavigationMenus() {
    closeSidebar();
    closeProfileMenus();
    closeNotificationMenus();
    document.dispatchEvent(new CustomEvent('portal-navigation-start'));
  }
  document.querySelectorAll('[data-nav-view]').forEach((link) => {
    link.addEventListener('click', () => {
      if (link.matches('a[href]')) clearNavigationMenus();
      markNotificationsSeenForView(link.getAttribute('data-nav-view') || '');
    });
  });
  window.addEventListener('pagehide', clearNavigationMenus);
  window.addEventListener('pageshow', event => {
    if (event.persisted) clearNavigationMenus();
  });
  if (notificationMenus.length) {
    document.addEventListener('husay-updated', refreshNotifications);
    refreshNotifications();
    window.setInterval(refreshNotifications, notificationRefreshMs);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) refreshNotifications();
    });
    window.addEventListener('focus', refreshNotifications);
    window.addEventListener('online', refreshNotifications);
  }

  /* Modals */
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-residency-review]');
    if (!button) return;
    const modal = document.getElementById('residentInactivityReviewModal');
    if (!modal) return;
    modal.querySelector('form').reset();
    modal.querySelector('[data-review-resident-code]').value = button.dataset.residentCode;
    modal.querySelector('[data-review-resident-name]').textContent = `${button.dataset.residentName} · ${button.dataset.residentCode}`;
    closeProfileMenus();
    openModal(modal.id);
  });
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-request-remarks]');
    if (!button) return;
    const modal = document.getElementById('requestRejectionRemarksModal');
    if (!modal) return;
    modal.querySelector('[data-rejection-request]').textContent = `${button.dataset.requestId} · ${button.dataset.requestType}`;
    modal.querySelector('[data-rejection-remarks]').textContent = button.dataset.remarks.trim() || 'No rejection remarks were provided. Please contact the barangay office for details.';
    closeProfileMenus();
    openModal(modal.id);
  });
  const modalTriggers = new WeakMap();
  // Other dashboard modules use this handler for automatic and QR dialogs too.
  window.barangayOpenModal = openModal;
  window.barangayCloseModal = closeModal;
  function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    if (!m.classList.contains('is-open')) modalTriggers.set(m, document.activeElement);
    // Every dialog must sit above the header, outside animated page containers.
    // Moving the existing node preserves its fields and attached event handlers.
    if (m.parentElement !== document.body) document.body.appendChild(m);
    m.classList.add('is-open');
    m.setAttribute('aria-hidden', 'false');
    m.setAttribute('aria-modal', 'true');
    document.body.style.overflow = 'hidden';
    m.querySelector('button[data-modal-close], .modal-close')?.focus({preventScroll: true});
  }
  function closeModal(m) {
    if (!m) return;
    if (m.id === 'issuancePrintModal' && m.dataset.generating === 'true') return;
    if (m.id === 'issuancePrintModal' && m.dataset.released === 'true') {
      window.location.reload();
      return;
    }
    if (m.id === 'profileSettingsModal') {
      m.dispatchEvent(new CustomEvent('profile-modal-reset'));
      m.querySelectorAll('form').forEach((form) => form.reset());
    }
    m.classList.remove('is-open');
    m.setAttribute('aria-hidden', 'true');
    m.removeAttribute('aria-modal');
    if (!document.querySelector('.modal.is-open')) document.body.style.overflow = '';
    const trigger = modalTriggers.get(m);
    if (trigger?.isConnected) trigger.focus({preventScroll: true});
    modalTriggers.delete(m);
    if (m.dataset.returnStaff === 'true') {
      delete m.dataset.returnStaff;
      openModal('viewStaffModal');
    }
    if (m.id === 'viewRecordModal' && m.dataset.returnRequests === 'true') {
      delete m.dataset.returnRequests;
      openModal('documentRequestsModal');
    }
  }
  function setPostOptionsOpen(options, open) {
    const toggle = options.querySelector('[data-post-toggle]');
    const panel = options.querySelector('[data-post-actions]');
    if (!toggle || !panel) return;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    options.classList.toggle('is-open', open);
    options.closest('.recent-post-card, [data-complaint-search]')?.classList.toggle('has-open-post-menu', open);
  }
  function closePostOptions(except) {
    document.querySelectorAll('[data-post-options].is-open').forEach(options => {
      if (options !== except) setPostOptionsOpen(options, false);
    });
  }
  document.addEventListener('click', event => {
    const options = event.target.closest('[data-post-options]');
    const toggle = event.target.closest('[data-post-toggle]');
    closePostOptions(options);
    if (toggle && options) {
      setPostOptionsOpen(options, toggle.getAttribute('aria-expanded') !== 'true');
    } else if (options && event.target.closest('[data-modal-open], [data-husay-schedule-complaint]')) {
      setPostOptionsOpen(options, false);
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      document.querySelectorAll('[data-post-options].is-open').forEach(options => {
        setPostOptionsOpen(options, false);
        options.querySelector('[data-post-toggle]')?.focus({preventScroll: true});
      });
    } else if (event.key === 'ArrowDown' && event.target.closest('[data-post-toggle]')) {
      const options = event.target.closest('[data-post-options]');
      if (!options) return;
      event.preventDefault();
      closePostOptions(options);
      setPostOptionsOpen(options, true);
      options.querySelector('[data-post-actions] button')?.focus({preventScroll: true});
    }
  });
  document.addEventListener('focusin', event => closePostOptions(event.target.closest('[data-post-options]')));
  document.addEventListener('portal-navigation-start', () => closePostOptions());
  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', () => {
      closeProfileMenus();
      if (btn.hasAttribute('data-modal-close')) closeModal(btn.closest('.modal'));
      openModal(btn.getAttribute('data-modal-open'));
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach((el) => {
    el.addEventListener('click', () => {
      if (el.hasAttribute('data-modal-open')) return;
      const m = el.closest('.modal');
      if (m) closeModal(m);
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeProfileMenus();
      closeNotificationMenus();
      document.querySelectorAll('.modal.is-open').forEach(closeModal);
    }
  });

  if (new URL(window.location.href).searchParams.get('requests') === '1' && document.getElementById('documentRequestsModal')) {
    openModal('documentRequestsModal');
    const requestButton = document.querySelector('[data-modal-open="documentRequestsModal"]');
    if (requestButton) markNotificationsSeenForView(requestButton.getAttribute('data-nav-view'));
  }
  if (new URL(window.location.href).searchParams.has('requirements_resident') && document.getElementById('registrationRequirements')) {
    openModal('registrationRequirements');
  }

  /* Profile photo crop preview */
  const profilePhotoInput = document.getElementById('profilePhotoUpload');
  const profilePhotoPreview = document.getElementById('profilePhotoPreview');
  const profilePhotoCropped = document.getElementById('profilePhotoCropped');
  const profileCropper = document.getElementById('profilePhotoCropper');
  const profileCropStage = document.getElementById('profileCropStage');
  const profileCropImage = document.getElementById('profileCropImage');
  const profilePhotoZoom = document.getElementById('profilePhotoZoom');
  const originalProfilePreview = profilePhotoPreview ? profilePhotoPreview.innerHTML : '';
  let profilePhotoLoading = false;
  let profileCropState = {
    image: null,
    url: '',
    zoom: 1,
    x: 0,
    y: 0,
    dragging: false,
    pointerId: null,
    startX: 0,
    startY: 0,
    startOffsetX: 0,
    startOffsetY: 0,
  };

  function profileCropBaseScale() {
    if (!profileCropImage || !profileCropStage || !profileCropState.image) return 1;
    const rect = profileCropStage.getBoundingClientRect();
    return Math.max(rect.width / profileCropState.image.naturalWidth, rect.height / profileCropState.image.naturalHeight);
  }

  function clampProfileCrop() {
    if (!profileCropStage || !profileCropState.image) return;
    const rect = profileCropStage.getBoundingClientRect();
    const scale = profileCropBaseScale() * profileCropState.zoom;
    const displayW = profileCropState.image.naturalWidth * scale;
    const displayH = profileCropState.image.naturalHeight * scale;
    const maxX = Math.max(0, (displayW - rect.width) / 2);
    const maxY = Math.max(0, (displayH - rect.height) / 2);
    profileCropState.x = Math.min(maxX, Math.max(-maxX, profileCropState.x));
    profileCropState.y = Math.min(maxY, Math.max(-maxY, profileCropState.y));
  }

  function renderProfileCrop() {
    if (!profileCropImage || !profileCropState.image) return;
    clampProfileCrop();
    const scale = profileCropBaseScale() * profileCropState.zoom;
    profileCropImage.style.width = `${profileCropState.image.naturalWidth}px`;
    profileCropImage.style.height = `${profileCropState.image.naturalHeight}px`;
    profileCropImage.style.transform = `translate(calc(-50% + ${profileCropState.x}px), calc(-50% + ${profileCropState.y}px)) scale(${scale})`;
  }

  function buildProfileCropDataUrl() {
    if (!profileCropStage || !profileCropState.image) return '';
    const outputSize = 512;
    const rect = profileCropStage.getBoundingClientRect();
    const scale = profileCropBaseScale() * profileCropState.zoom;
    const sourceSize = rect.width / scale;
    const sourceX = (profileCropState.image.naturalWidth / 2) - (profileCropState.x / scale) - (sourceSize / 2);
    const sourceY = (profileCropState.image.naturalHeight / 2) - (profileCropState.y / scale) - (sourceSize / 2);
    const canvas = document.createElement('canvas');
    canvas.width = outputSize;
    canvas.height = outputSize;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, outputSize, outputSize);
    ctx.drawImage(profileCropState.image, sourceX, sourceY, sourceSize, sourceSize, 0, 0, outputSize, outputSize);
    return canvas.toDataURL('image/jpeg', 0.88);
  }

  function updateProfilePhotoPreview() {
    const dataUrl = buildProfileCropDataUrl();
    if (!dataUrl) return;
    if (profilePhotoCropped) profilePhotoCropped.value = dataUrl;
    if (profilePhotoPreview) {
      profilePhotoPreview.innerHTML = `<img src="${dataUrl}" alt="Profile picture preview">`;
    }
  }

  if (profilePhotoInput && profileCropImage && profileCropper) {
    const profileModal = document.getElementById('profileSettingsModal');
    if (profileModal) {
      profileModal.addEventListener('profile-modal-reset', () => {
        profilePhotoLoading = false;
        if (profileCropState.url) URL.revokeObjectURL(profileCropState.url);
        profileCropState = { ...profileCropState, image: null, url: '', zoom: 1, x: 0, y: 0, dragging: false, pointerId: null };
        profileCropper.hidden = true;
        if (profileCropImage) profileCropImage.removeAttribute('src');
        if (profilePhotoCropped) profilePhotoCropped.value = '';
        if (profilePhotoPreview) profilePhotoPreview.innerHTML = originalProfilePreview;
      });
    }

    profilePhotoInput.addEventListener('change', () => {
      const file = profilePhotoInput.files && profilePhotoInput.files[0];
      if (!file) return;
      if (profileCropState.url) URL.revokeObjectURL(profileCropState.url);
      profilePhotoLoading = false;
      profileCropState.image = null;
      profileCropState.url = '';
      profileCropper.hidden = true;
      if (profilePhotoCropped) profilePhotoCropped.value = '';
      if (profilePhotoPreview) profilePhotoPreview.innerHTML = originalProfilePreview;
      if (!file.type.startsWith('image/')) {
        profilePhotoInput.value = '';
        showActionToast('Please select an image file.', 'error');
        return;
      }
      profileCropState.url = URL.createObjectURL(file);
      const selectionUrl = profileCropState.url;
      profilePhotoLoading = true;
      const image = new Image();
      image.onload = () => {
        if (profileCropState.url !== selectionUrl) return;
        profilePhotoLoading = false;
        profileCropState.image = image;
        profileCropState.zoom = 1;
        profileCropState.x = 0;
        profileCropState.y = 0;
        if (profilePhotoZoom) profilePhotoZoom.value = '1';
        profileCropImage.src = profileCropState.url;
        profileCropper.hidden = false;
        renderProfileCrop();
        updateProfilePhotoPreview();
      };
      image.onerror = () => {
        if (profileCropState.url !== selectionUrl) return;
        profilePhotoLoading = false;
        URL.revokeObjectURL(selectionUrl);
        profileCropState.url = '';
        profilePhotoInput.value = '';
        showActionToast('This photo could not be opened. Try a JPG or PNG image.', 'error');
      };
      image.src = selectionUrl;
    });

    if (profilePhotoZoom) {
      profilePhotoZoom.addEventListener('input', () => {
        profileCropState.zoom = Number(profilePhotoZoom.value) || 1;
        renderProfileCrop();
        updateProfilePhotoPreview();
      });
    }

    if (profileCropStage && typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(() => {
        if (!profileCropState.image || !profileCropStage.getClientRects().length) return;
        renderProfileCrop();
        updateProfilePhotoPreview();
      }).observe(profileCropStage);
    }

    if (profileCropStage) {
      profileCropStage.addEventListener('pointerdown', (event) => {
        if (!profileCropState.image) return;
        profileCropState.dragging = true;
        profileCropState.pointerId = event.pointerId;
        profileCropState.startX = event.clientX;
        profileCropState.startY = event.clientY;
        profileCropState.startOffsetX = profileCropState.x;
        profileCropState.startOffsetY = profileCropState.y;
        profileCropStage.setPointerCapture(event.pointerId);
      });
      profileCropStage.addEventListener('pointermove', (event) => {
        if (!profileCropState.dragging || profileCropState.pointerId !== event.pointerId) return;
        profileCropState.x = profileCropState.startOffsetX + (event.clientX - profileCropState.startX);
        profileCropState.y = profileCropState.startOffsetY + (event.clientY - profileCropState.startY);
        renderProfileCrop();
        updateProfilePhotoPreview();
      });
      ['pointerup', 'pointercancel'].forEach((eventName) => {
        profileCropStage.addEventListener(eventName, () => {
          profileCropState.dragging = false;
          profileCropState.pointerId = null;
        });
      });
    }

    document.querySelectorAll('[data-profile-settings-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (profilePhotoLoading) {
          event.preventDefault();
          showActionToast('Please wait for the photo preview to finish loading.', 'error');
          return;
        }
        if (profileCropState.image && profilePhotoCropped) {
          profilePhotoCropped.value = buildProfileCropDataUrl();
        }
      });
    });
  }

  /* Password visibility buttons */
  document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (input.closest('.password-field')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = '<svg viewBox="0 0 256 256" fill="currentColor" stroke="none" data-system-icon="eye" aria-hidden="true"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Zm0,112a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/><line class="password-toggle-slash" data-password-eye-slash x1="4" y1="4" x2="20" y2="20" transform="scale(10.6666667)" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
    wrapper.appendChild(button);

    button.addEventListener('click', () => {
      const shouldShow = input.type === 'password';
      const slash = button.querySelector('[data-password-eye-slash]');
      input.type = shouldShow ? 'text' : 'password';
      button.classList.toggle('is-visible', shouldShow);
      button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
      if (slash) {
        slash.style.opacity = shouldShow ? '1' : '0';
        slash.style.visibility = shouldShow ? 'visible' : 'hidden';
      }
    });
  });

  /* Security settings validation */
  document.querySelectorAll('[data-security-form]').forEach((form) => {
    const newPassword = form.querySelector('[name="new_password"]');
    const confirmPassword = form.querySelector('[name="confirm_password"]');
    const message = form.querySelector('[data-password-message]');

    function validatePasswordMatch() {
      if (!newPassword || !confirmPassword || !message) return true;
      const hasValues = newPassword.value.length > 0 || confirmPassword.value.length > 0;
      const matches = newPassword.value === confirmPassword.value;
      message.classList.toggle('is-error', hasValues && !matches);
      message.classList.toggle('is-success', hasValues && matches);
      message.textContent = hasValues ? (matches ? 'Passwords match.' : 'New password and confirm password must match.') : '';
      return matches;
    }

    if (newPassword) newPassword.addEventListener('input', validatePasswordMatch);
    if (confirmPassword) confirmPassword.addEventListener('input', validatePasswordMatch);
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!validatePasswordMatch() || form.dataset.saving === 'true') return;
      const submit = document.querySelector('[form="securitySettingsForm"][type="submit"]');
      form.dataset.saving = 'true';
      if (submit) submit.disabled = true;
      try {
        const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Could not change your password.');
        form.reset();
        validatePasswordMatch();
        closeModal(form.closest('.modal'));
        showActionToast(result.message);
      } catch (error) {
        showActionToast(error.message || 'Could not change your password.', 'error');
      } finally {
        delete form.dataset.saving;
        if (submit) submit.disabled = false;
      }
    });
  });

  /* Optional middle name fields */
  document.querySelectorAll('[data-no-middle-name]').forEach((checkbox) => {
    const field = checkbox.closest('.field');
    const input = field ? field.querySelector('[data-middle-name-input]') : null;
    if (!input) return;

    function syncMiddleNameField() {
      input.disabled = checkbox.checked;
      if (checkbox.checked) input.value = '';
      input.placeholder = checkbox.checked ? 'No middle name' : 'Middle name';
    }

    checkbox.addEventListener('change', syncMiddleNameField);
    syncMiddleNameField();
  });

  /* File upload labels */
  document.querySelectorAll('[data-file-input]').forEach((input) => {
    const wrapper = input.closest('.file-upload-field');
    const label = wrapper ? wrapper.querySelector('[data-file-name]') : null;
    if (!label) return;

    input.addEventListener('change', () => {
      label.textContent = input.files && input.files.length ? input.files[0].name : (input.dataset.emptyLabel || 'No file selected');
    });
  });

  /* Announcement form */
  document.querySelectorAll('[data-schedule-toggle]').forEach((toggle) => {
    const form = toggle.closest('form');
    if (!form) return;

    const dateInput = form.querySelector('[data-schedule-date]');
    const timeInput = form.querySelector('[data-schedule-time]');
    const schedulePanels = form.querySelectorAll('[data-schedule-panel]');

    function currentDateTimeParts() {
      const now = new Date();
      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, '0');
      const dd = String(now.getDate()).padStart(2, '0');
      const hh = String(now.getHours()).padStart(2, '0');
      const min = String(now.getMinutes()).padStart(2, '0');
      return { date: `${yyyy}-${mm}-${dd}`, time: `${hh}:${min}` };
    }

    function syncScheduleFields() {
      const enabled = toggle.checked;
      schedulePanels.forEach((panel) => {
        panel.hidden = !enabled;
      });
      if (dateInput) dateInput.readOnly = !enabled;
      if (timeInput) timeInput.readOnly = !enabled;

      if (!enabled && toggle.dataset.scheduleReset === 'true') {
        const current = currentDateTimeParts();
        if (dateInput) dateInput.value = current.date;
        if (timeInput) timeInput.value = current.time;
      }
    }

    toggle.addEventListener('change', syncScheduleFields);
    if (form && toggle.dataset.scheduleReset === 'true') {
      form.addEventListener('submit', syncScheduleFields);
    }
    syncScheduleFields();
  });

  function fillDetailList(containerSelector, data) {
    const container = document.querySelector(containerSelector);
    if (!container) return;
    container.querySelectorAll('[data-field]').forEach((field) => {
      const key = field.getAttribute('data-field');
      field.textContent = data[key] || (key === 'position' ? 'Barangay Staff' : '-');
    });
  }

  function setResidentCardText(selector, value) {
    const element = document.querySelector(selector);
    if (element) element.textContent = value || '-';
  }

  function residentInitials(name) {
    return String(name || '?')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('') || '?';
  }

  function renderResidentIdCard(data) {
    const card = document.querySelector('[data-resident-id-card]');
    if (!card) return;

    setResidentCardText('[data-resident-card-id]', data.id);
    setResidentCardText('[data-resident-card-name]', data.name);
    setResidentCardText('[data-resident-card-username]', data.username);
    setResidentCardText('[data-resident-card-mobile]', data.mobile);
    setResidentCardText('[data-resident-card-purok]', data.purok);
    setResidentCardText('[data-resident-card-household]', data.household);
    setResidentCardText('[data-resident-card-birthdate]', `${data.birthdate || '-'}${data.age ? ` (${data.age} yrs old)` : ''}`);
    setResidentCardText('[data-resident-card-gender]', data.gender);
    setResidentCardText('[data-resident-card-civil-status]', data.civil_status);
    setResidentCardText('[data-resident-card-verification]', data.verification_status);
    setResidentCardText('[data-resident-card-registered]', data.registered);
    setResidentCardText('[data-resident-card-address]', data.address);
    setResidentCardText('[data-resident-card-qr-code]', data.qr_code || 'No QR code yet');

    const status = document.querySelector('[data-resident-card-status]');
    if (status) {
      status.textContent = data.status || 'Pending';
      status.className = `badge ${data.status === 'Active' ? 'badge-active' : (data.status === 'Inactive' ? 'badge-inactive' : 'badge-pending')}`;
    }

    const photo = document.querySelector('[data-resident-card-photo]');
    if (photo) {
      photo.replaceChildren();
      if (data.profile_photo_path) {
        const img = document.createElement('img');
        img.src = data.profile_photo_path;
        img.alt = `${data.name || 'Resident'} profile photo`;
        photo.appendChild(img);
      } else {
        photo.textContent = residentInitials(data.name);
      }
    }

    const qr = document.querySelector('[data-resident-card-qr]');
    if (qr) {
      const qrCode = data.qr_code || '';
      qr.replaceChildren();
      if (qrCode) {
        const img = document.createElement('img');
        img.alt = 'Resident QR code';
        img.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrCode)}`;
        const label = document.createElement('span');
        label.textContent = qrCode;
        qr.append(img, label);
      } else {
        const empty = document.createElement('div');
        empty.className = 'resident-id-card__qr-empty';
        empty.textContent = 'QR';
        const label = document.createElement('span');
        label.textContent = 'No QR code yet';
        qr.append(empty, label);
      }
    }
  }

  document.querySelectorAll('[data-view-staff]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const data = JSON.parse(btn.getAttribute('data-json') || '{}');
      fillDetailList('[data-staff-view]', data);
      const actions = document.querySelector('[data-staff-view-actions]');
      if (actions) {
        actions.querySelector('[data-edit-staff]').setAttribute('data-json', JSON.stringify(data));
        const resetButton = actions.querySelector('[data-reset-password]');
        resetButton.setAttribute('data-id', data.id);
        resetButton.setAttribute('data-name', data.name);
        const statusForm = actions.querySelector('form');
        const isActive = data.status === 'Active';
        statusForm.querySelector('[name="id"]').value = data.id;
        statusForm.querySelector('[name="status"]').value = isActive ? 'Inactive' : 'Active';
        const statusButton = actions.querySelector('[data-staff-view-status]');
        statusButton.textContent = isActive ? 'Deactivate' : 'Activate';
        statusButton.className = `btn ${isActive ? 'btn-danger' : 'btn-primary'} portal-icon-action`;
      }
      openModal('viewStaffModal');
    });
  });

  document.addEventListener('click', event => {
      const btn = event.target.closest('[data-view-resident]');
      if (!btn) return;
      renderResidentIdCard(JSON.parse(btn.getAttribute('data-json') || '{}'));
      openModal('viewResidentModal');
  });

  document.addEventListener('click', event => {
      const btn = event.target.closest('[data-reset-password]');
      if (!btn) return;
      const form = document.getElementById('resetPasswordForm');
      if (!form) return;
      const idInput = form.querySelector('[name="id"]');
      const redirectInput = form.querySelector('[name="redirect"]');
      const passwordInput = form.querySelector('[name="password"]');
      if (idInput) idInput.value = btn.getAttribute('data-id') || '';
      if (redirectInput) redirectInput.value = btn.getAttribute('data-redirect') || 'user-management';
      if (passwordInput) {
        passwordInput.value = '';
        passwordInput.type = 'password';
      }
      const label = document.querySelector('[data-reset-password-name]');
      if (label) label.textContent = [btn.getAttribute('data-name') || 'this account', btn.getAttribute('data-id')].filter(Boolean).join(' — ');
      if (btn.closest('#viewStaffModal')) {
        closeModal(document.getElementById('viewStaffModal'));
        document.getElementById('resetPasswordModal').dataset.returnStaff = 'true';
      }
      openModal('resetPasswordModal');
  });

  document.addEventListener('click', event => {
      const btn = event.target.closest('[data-view-record]');
      if (!btn) return;
      const container = document.querySelector('[data-record-view]');
      const title = document.querySelector('[data-record-title]');
      const data = JSON.parse(btn.getAttribute('data-json') || '{}');
      if (!container) return;
      const recordBody = container.closest('.modal-body') || container;
      recordBody.querySelectorAll('[data-complaint-section]').forEach(section => section.remove());
      if (title) title.textContent = btn.getAttribute('data-title') || 'Record details';
      container.innerHTML = '';
      const requestFields = btn.getAttribute('data-title') === 'Document request' ? {
        id: 'Request ID', resident: 'Resident name', resident_code: 'Resident ID', type: 'Document',
        status: 'Status', date: 'Requested on', copies: 'Copies', fee_amount: 'Total fee', unit_fee: 'Fee per copy',
        purpose: 'Purpose', contact_mobile: 'Contact number', remarks: 'Staff remarks',
        beneficiary_name: 'Beneficiary name', beneficiary_relationship: 'Relationship to resident',
        residency_years: 'Years of residency', partner_name: 'Partner name',
        cohabitation_start_date: 'Living together since', resident_gender_override: 'Applicant gender',
      } : null;
      let extraDetails = {};
      try { extraDetails = JSON.parse(data.custom_values_json || '{}'); } catch (_) {}
      Object.entries(extraDetails).forEach(([key,value]) => {
        if (typeof value !== 'string') return;
        const field = 'custom_' + key;
        data[field] = value;
        if (requestFields) requestFields[field] = key.replace(/_/g,' ').replace(/\b\w/g, letter => letter.toUpperCase());
      });
      let keys = requestFields ? Object.keys(requestFields).filter(key => data[key] !== undefined) : Object.keys(data).filter(key => key !== 'custom_values_json');
      const demographics = ['age', 'gender', 'civil_status'];
      const compactDemographics = demographics.every(key => data[key] !== undefined && data[key] !== null && data[key] !== '');
      if (compactDemographics) {
        const first = keys.findIndex(key => demographics.includes(key));
        keys = keys.filter(key => !demographics.includes(key));
        keys.splice(first, 0, ...demographics);
      }
      keys.forEach((key) => {
        let value = data[key];
        if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/.test(value)) {
          const time = new Date(value.replace(' ', 'T'));
          if (!Number.isNaN(time.getTime())) value = time.toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit', hour12:true});
        }
        if (value === null || value === '') return;
        const row = document.createElement('div');
        if (compactDemographics && demographics.includes(key)) row.className = 'resident-detail-' + (key === 'civil_status' ? 'civil' : key);
        else if (/address|description|remarks|message|purpose|details|body/i.test(key)) row.className = 'resident-detail-full';
        const label = document.createElement('span');
        const content = document.createElement('strong');
        label.textContent = requestFields?.[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        content.textContent = requestFields && ['fee_amount', 'unit_fee'].includes(key)
          ? `PHP ${Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : value;
        content.style.whiteSpace = 'pre-wrap';
        row.append(label, content);
        container.appendChild(row);
      });
      if (btn.getAttribute('data-title') === 'Complaint') {
        const updateUrl = btn.getAttribute('data-complaint-update-url');
        if (['admin-actions.php', 'staff-actions.php'].includes(updateUrl)) {
          const editor = document.createElement('form');
          editor.className = 'complaint-action-form complaint-detail-editor';
          editor.setAttribute('data-complaint-section', '');
          editor.method = 'post';
          editor.setAttribute('action', updateUrl);
          const editorTitle = document.createElement('h3');
          editorTitle.textContent = 'Update complaint';
          editor.appendChild(editorTitle);
          const returnView = updateUrl === 'admin-actions.php' ? 'complaints' : 'staff-complaints';
          Object.entries({action: 'update_complaint_status', id: data.id, redirect: returnView, csrf: btn.getAttribute('data-complaint-token') || ''}).forEach(([name, value]) => {
            const field = document.createElement('input');
            field.type = 'hidden'; field.name = name; field.value = value;
            editor.appendChild(field);
          });
          const statusLabel = document.createElement('label');
          statusLabel.textContent = 'Status';
          const select = document.createElement('select');
          select.name = 'status';
          ['Open', 'In Progress', 'Resolved', 'Closed'].forEach(value => {
            const option = document.createElement('option');
            option.value = value; option.textContent = value; option.selected = value === data.status;
            select.appendChild(option);
          });
          statusLabel.appendChild(select);
          const remarksLabel = document.createElement('label');
          remarksLabel.textContent = 'Remarks';
          const remarks = document.createElement('textarea');
          remarks.name = 'remarks'; remarks.rows = 3; remarks.maxLength = 4000; remarks.value = data.remarks || '';
          remarksLabel.appendChild(remarks);
          const save = document.createElement('button');
          save.type = 'submit'; save.className = 'btn btn-primary'; save.textContent = 'Save changes';
          editor.append(statusLabel, remarksLabel, save);
          recordBody.appendChild(editor);
        }
        const evidence = document.createElement('section');
        evidence.className = 'complaint-evidence';
        evidence.setAttribute('data-complaint-section', '');
        const heading = document.createElement('h3');
        heading.textContent = 'Photos and attachments';
        evidence.appendChild(heading);
        const attachments = JSON.parse(btn.getAttribute('data-record-attachments') || '[]');
        if (!attachments.length) {
          const empty = document.createElement('p');
          empty.textContent = 'No photo or attachment was submitted.';
          evidence.appendChild(empty);
        }
        attachments.forEach(file => {
          const id = Number(file.id);
          if (!Number.isSafeInteger(id) || id <= 0) return;
          const figure = document.createElement('figure');
          const link = document.createElement('a');
          link.href = `record-attachment.php?id=${id}`;
          link.target = '_blank';
          link.rel = 'noopener';
          if (['image/jpeg', 'image/png'].includes(file.mime_type)) {
            const image = document.createElement('img');
            image.src = link.href;
            image.alt = file.filename || 'Complaint photo';
            image.loading = 'lazy';
            image.onerror = () => { image.hidden = true; caption.textContent = 'Photo could not load. Open the attachment to try again.'; };
            link.appendChild(image);
          }
          const caption = document.createElement('figcaption');
          caption.textContent = file.filename || 'Attachment';
          const open = document.createElement('span');
          open.textContent = file.mime_type === 'application/pdf' ? 'Open PDF' : 'Open attachment';
          link.appendChild(open);
          figure.append(link, caption);
          evidence.appendChild(figure);
        });
        recordBody.appendChild(evidence);
      }
      const requestsModal = btn.closest('#documentRequestsModal');
      if (requestsModal) {
        closeModal(requestsModal);
        document.getElementById('viewRecordModal').dataset.returnRequests = 'true';
      }
      openModal('viewRecordModal');
      if (btn.getAttribute('data-complaint-edit') === 'true') recordBody.querySelector('.complaint-detail-editor select')?.focus();
      document.dispatchEvent?.(new CustomEvent('record-details-opened', {detail: {title: btn.getAttribute('data-title'), data}}));
  });

  document.addEventListener('click', async event => {
      const btn = event.target.closest('[data-view-feedback]');
      if (!btn) return;
      const modal = document.getElementById('feedbackViewModal');
      if (!modal) return;
      const data = JSON.parse(btn.getAttribute('data-json') || '{}');
      const setText = (selector, value) => {
        const el = modal.querySelector(selector);
        if (el) el.textContent = value || '-';
      };
      setText('[data-feedback-id]', data.id || '');
      setText('[data-feedback-resident]', data.resident || 'Resident');
      setText('[data-feedback-category]', data.category || 'General feedback');
      setText('[data-feedback-rating]', data.rating ? `${data.rating}/5` : 'Not rated');
      setText('[data-feedback-date]', data.date || '');
      setText('[data-feedback-message]', data.message || '');

      const status = modal.querySelector('[data-feedback-status]');
      if (status) {
        status.textContent = data.status || 'Unread';
        status.className = `badge ${data.status === 'Read' ? 'badge-active' : 'badge-pending'}`;
      }

      const imageBox = modal.querySelector('[data-feedback-image-box]');
      const imageEmpty = modal.querySelector('[data-feedback-image-empty]');
      const imageLink = modal.querySelector('[data-feedback-image-link]');
      const image = modal.querySelector('[data-feedback-image]');
      const imagePath = data.image_path || '';
      if (imagePath && imageBox && imageLink && image) {
        image.src = imagePath;
        imageLink.href = imagePath;
        imageBox.hidden = false;
        if (imageEmpty) imageEmpty.hidden = true;
      } else {
        if (imageBox) imageBox.hidden = true;
        if (imageEmpty) imageEmpty.hidden = false;
        if (image) image.removeAttribute('src');
        if (imageLink) imageLink.removeAttribute('href');
      }

      openModal('feedbackViewModal');
      modal.dataset.activeFeedbackId = data.id;
      if (btn.dataset.readPending) return;
      btn.dataset.readPending = 'true';
      try {
        const response = await fetch('feedback-read.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: new URLSearchParams({id: data.id, csrf: modal.dataset.readToken}),
          signal: AbortSignal.timeout(15000)
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error('Feedback update failed');
        const wasUnread = data.status !== 'Read';
        data.status = 'Read';
        btn.setAttribute('data-json', JSON.stringify(data));
        const row = btn.closest('tr');
        const badge = row?.querySelector('.badge');
        if (badge) { badge.textContent = 'Read'; badge.className = 'badge badge-active'; }
        const unread = row?.querySelector('[data-feedback-unread]');
        if (unread) unread.hidden = false;
        if (modal.dataset.activeFeedbackId === data.id && status) {
          status.textContent = 'Read';
          status.className = 'badge badge-active';
        }
        if (wasUnread) showActionToast('Feedback marked as read.');
        refreshNotifications();
      } catch (_) {
        showActionToast('Could not mark feedback as read. Close and reopen it to try again.', 'error');
      } finally {
        delete btn.dataset.readPending;
      }
  });
  let requestedFeedbackOpened = false;
  function openRequestedFeedback() {
    if (requestedFeedbackOpened) return;
    const id = new URL(window.location.href).searchParams.get('feedback');
    if (!id) return;
    const button = [...document.querySelectorAll('[data-view-feedback]')].find(item => {
      try { return JSON.parse(item.getAttribute('data-json') || '{}').id === id; } catch (_) { return false; }
    });
    if (button) { requestedFeedbackOpened = true; button.click(); }
  }
  document.addEventListener('overview-updated', openRequestedFeedback);
  openRequestedFeedback();

  /* CRUD edit â€” prefill staff modal */
  document.querySelectorAll('[data-edit-staff]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const data = JSON.parse(btn.getAttribute('data-json') || '{}');
      const form = document.getElementById('editStaffForm');
      if (!form) return;
      Object.keys(data).forEach((key) => {
        const input = form.querySelector(`[name="${key}"]`);
        if (input) input.value = data[key];
      });
      if (btn.closest('#viewStaffModal')) {
        closeModal(document.getElementById('viewStaffModal'));
        document.getElementById('editStaffModal').dataset.returnStaff = 'true';
      }
      openModal('editStaffModal');
    });
  });

  document.addEventListener('click', event => {
      const btn = event.target.closest('[data-delete-feedback]');
      if (!btn) return;
      const form = document.getElementById('deleteFeedbackForm');
      if (!form) return;
      const idInput = form.querySelector('[name="id"]');
      if (idInput) idInput.value = btn.getAttribute('data-delete-feedback') || '';
      const label = document.querySelector('[data-delete-feedback-label]');
      if (label) label.textContent = btn.getAttribute('data-feedback-label') || btn.getAttribute('data-delete-feedback') || '';
      openModal('deleteFeedbackModal');
  });

  /* Global search â€” filter tables */
  document.querySelectorAll('[data-table-filter-panel]').forEach((panel) => {
    const table = document.getElementById(panel.getAttribute('data-table-filter-target') || '');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    function applyLocalFilters() {
      const textFilter = (panel.querySelector('[data-table-text-filter]')?.value || '').trim().toLowerCase();
      const columnSelects = Array.from(panel.querySelectorAll('[data-table-column-filter]'));
      const columnTextInputs = Array.from(panel.querySelectorAll('[data-table-column-text-filter]'));

      rows.forEach((row) => {
        if (row.querySelector('td[colspan]')) return;
        let visible = !textFilter || (row.textContent + ' ' + (row.dataset.searchText || '')).toLowerCase().includes(textFilter);

        columnSelects.forEach((select) => {
          if (!visible) return;
          const value = select.value.toLowerCase();
          if (value === 'all' || value === '') return;
          const index = Number(select.getAttribute('data-table-column-filter'));
          const cellText = (row.cells[index]?.textContent || '').trim().toLowerCase();
          visible = cellText.includes(value);
        });

        columnTextInputs.forEach((input) => {
          if (!visible) return;
          const value = input.value.trim().toLowerCase();
          if (!value) return;
          const index = Number(input.getAttribute('data-table-column-text-filter'));
          const cellText = (row.cells[index]?.textContent || '').trim().toLowerCase();
          visible = cellText.includes(value);
        });

        row.style.display = visible ? '' : 'none';
      });
    }

    panel.querySelectorAll('input, select').forEach((control) => {
      control.addEventListener('input', applyLocalFilters);
      control.addEventListener('change', applyLocalFilters);
    });
    if (panel.tagName === 'FORM') panel.addEventListener('submit', (event) => {
      event.preventDefault();
      applyLocalFilters();
    });
  });

  /* The header menu button controls the desktop icon rail and mobile drawer. */
  const COLLAPSED_KEY = 'barangaySidebarCollapsed';
  const desktopSidebar = window.matchMedia('(min-width: 901px)');
  let preferredCollapsed = false;
  try { preferredCollapsed = localStorage.getItem(COLLAPSED_KEY) === '1'; } catch (_) {}

  function applySidebarCollapsed(collapsed, animate = true) {
    if (!sidebar) return;
    collapsed = desktopSidebar.matches && collapsed;
    const mainArea = document.querySelector('.main-area');
    if (!animate) {
      sidebar.style.transition = 'none';
      if (mainArea) mainArea.style.transition = 'none';
    }
    sidebar.classList.toggle('is-collapsed', collapsed);
    document.body.classList.toggle('sidebar-is-collapsed', collapsed);
    const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-label', desktopSidebar.matches ? label : 'Open menu');
      sidebarToggle.setAttribute('title', desktopSidebar.matches ? label : 'Open menu');
      sidebarToggle.setAttribute('aria-expanded', String(desktopSidebar.matches ? !collapsed : sidebar.classList.contains('is-open')));
      sidebarToggle.setAttribute('aria-controls', 'appSidebar');
    }
    if (!animate) {
      sidebar.getBoundingClientRect();
      sidebar.style.transition = '';
      if (mainArea) mainArea.style.transition = '';
    }
  }
  function toggleSidebarWidth() {
    preferredCollapsed = !sidebar.classList.contains('is-collapsed');
    applySidebarCollapsed(preferredCollapsed);
    try { localStorage.setItem(COLLAPSED_KEY, preferredCollapsed ? '1' : '0'); } catch (_) {}
  }
  sidebar?.querySelectorAll('.nav-link').forEach(link => {
    const label = link.querySelector('.nav-label, .nav-link-label')?.textContent.trim();
    if (label) {
      link.setAttribute('title', label);
      link.setAttribute('aria-label', label);
    }
  });
  applySidebarCollapsed(preferredCollapsed, false);
  desktopSidebar.addEventListener('change', () => {
    closeSidebar();
    applySidebarCollapsed(preferredCollapsed, false);
  });

  /* Cancel from the current height so rapid toggles never leave stale timers. */
  document.querySelectorAll('.settings-nav-group').forEach(group => {
    const summary = group.querySelector('summary');
    const content = group.querySelector('.settings-nav-children');
    if (!summary || !content) return;
    let targetOpen = group.open;
    let animation = null;
    summary.setAttribute('aria-expanded', String(targetOpen));
    summary.addEventListener('click', event => {
      event.preventDefault();
      const fromHeight = group.open ? content.getBoundingClientRect().height : 0;
      const fromOpacity = group.open ? getComputedStyle(content).opacity : '0';
      targetOpen = !targetOpen;
      animation?.cancel();
      group.open = true;
      content.inert = !targetOpen;
      summary.setAttribute('aria-expanded', String(targetOpen));
      const finish = () => {
        group.open = targetOpen;
        content.inert = !targetOpen;
        animation = null;
      };
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { finish(); return; }
      animation = content.animate([
        {height: fromHeight + 'px', opacity: fromOpacity},
        {height: (targetOpen ? content.scrollHeight : 0) + 'px', opacity: targetOpen ? 1 : 0}
      ], {duration: 500, easing: 'cubic-bezier(.2,.8,.2,1)'});
      animation.onfinish = finish;
    });
  });

})();

// Scale the landscape ID as one unit; reserve its scaled height in the modal.
(() => {
  const selector = '.resident-id-card, .qr-id-preview';
  const initialized = new WeakSet();
  function initialize(card) {
    if (initialized.has(card)) return;
    initialized.add(card);
    if (card.classList.contains('resident-id-card')) card.classList.add('resident-id-card--admin');
    const wrapper = document.createElement('div');
    wrapper.className = 'resident-id-fit';
    card.before(wrapper); wrapper.append(card);
    let frame;
    const fit = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const width = wrapper.clientWidth;
        if (!width) return;
        const compact = window.innerWidth <= 1024;
        card.classList.toggle('id-landscape', compact);
        if (compact) {
          const scale = width / 840;
          card.style.transform = `scale(${scale})`;
          wrapper.style.height = `${Math.ceil(card.offsetHeight * scale)}px`;
        } else {
          card.style.removeProperty('transform'); wrapper.style.removeProperty('height');
        }
      });
    };
    const observer = new ResizeObserver(fit);
    observer.observe(wrapper); observer.observe(card);
    window.addEventListener('resize', fit);
    const modal = card.closest('.modal');
    if (modal) new MutationObserver(fit).observe(modal, {attributes: true, attributeFilter: ['aria-hidden', 'class']});
    card.addEventListener('load', fit, true);
    fit();
  }
  document.querySelectorAll(selector).forEach(initialize);
  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType !== 1) return;
      if (node.matches(selector)) initialize(node);
      node.querySelectorAll(selector).forEach(initialize);
    }));
  }).observe(document.body, {childList: true, subtree: true});
})();
