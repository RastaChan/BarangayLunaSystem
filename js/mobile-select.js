(function () {
  'use strict';
  const media = window.matchMedia('(min-width: 0px)');
  const entries = new Map();
  let active = null;
  let sequence = 0;
  let typed = '';
  let typedAt = 0;
  let animation = null;
  const menu = document.createElement('div');
  menu.className = 'mobile-select-menu';
  menu.id = 'mobile-select-options';
  menu.setAttribute('role', 'listbox');
  menu.setAttribute('popover', 'manual');
  menu.hidden = true;
  document.body.appendChild(menu);

  function close(restoreFocus = false, immediate = false) {
    const previous = active;
    active = null;
    animation?.cancel();
    animation = null;
    const finish = () => {
      if (typeof menu.hidePopover === 'function' && menu.matches(':popover-open')) menu.hidePopover();
      menu.hidden = true;
      menu.style.pointerEvents = '';
      animation = null;
    };
    if (!immediate && !menu.hidden && menu.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      menu.style.pointerEvents = 'none';
      animation = menu.animate([{opacity: 1, transform: 'translateY(0)'}, {opacity: 0, transform: 'translateY(-6px)'}], {duration: 180, easing: 'ease-out'});
      animation.onfinish = finish;
    } else finish();
    if (previous) {
      previous.button.setAttribute('aria-expanded', 'false');
      if (restoreFocus) previous.button.focus({ preventScroll: true });
    }
  }

  function sync(entry) {
    const { select, button, label } = entry;
    const enabled = media.matches;
    select.classList.toggle('mobile-select-native', enabled);
    if (enabled) {
      select.tabIndex = -1;
      select.setAttribute('aria-hidden', 'true');
    } else {
      if (entry.tabIndex === null) select.removeAttribute('tabindex');
      else select.setAttribute('tabindex', entry.tabIndex);
      if (entry.ariaHidden === null) select.removeAttribute('aria-hidden');
      else select.setAttribute('aria-hidden', entry.ariaHidden);
    }
    button.hidden = !enabled || select.hidden || getComputedStyle(select).display === 'none';
    button.disabled = select.matches(':disabled');
    const text = select.selectedOptions[0]?.textContent || 'Select an option';
    if (label.textContent !== text) label.textContent = text;
    const labelText = item => {
      const copy = item.cloneNode(true);
      copy.querySelectorAll('select, button, input, textarea').forEach(control => control.remove());
      return copy.textContent.trim();
    };
    const fieldLabel = select.closest('.field')?.querySelector('label');
    const name = select.getAttribute('aria-label') || Array.from(select.labels || []).map(labelText).join(' ') || (fieldLabel ? labelText(fieldLabel) : '') || select.name || 'Select an option';
    button.setAttribute('aria-label', `${name}: ${text}`);
    entry.name = name;
    button.setAttribute('aria-required', String(select.required));
    if (select.validity.valid) button.removeAttribute('aria-invalid');
    if (active === entry && (button.hidden || button.disabled || !button.getClientRects().length)) close();
  }

  function position() {
    if (!active) return;
    const rect = active.button.getBoundingClientRect();
    const viewport = window.visualViewport;
    const leftEdge = viewport?.offsetLeft || 0;
    const topEdge = viewport?.offsetTop || 0;
    const width = viewport?.width || window.innerWidth;
    const height = viewport?.height || window.innerHeight;
    if (rect.bottom < topEdge || rect.top > topEdge + height || !active.button.getClientRects().length) { close(); return; }
    const below = topEdge + height - rect.bottom - 12;
    const above = rect.top - topEdge - 12;
    const useAbove = below < 180 && above > below;
    const limit = Math.max(44, Math.min(300, useAbove ? above : below));
    const menuWidth = Math.min(Math.max(rect.width, 200), width - 24);
    menu.style.width = `${menuWidth}px`;
    menu.style.maxHeight = `${limit}px`;
    menu.style.left = `${Math.max(leftEdge + 12, Math.min(rect.left, leftEdge + width - menuWidth - 12))}px`;
    menu.style.top = `${useAbove ? Math.max(topEdge + 12, rect.top - Math.min(menu.scrollHeight, limit) - 6) : rect.bottom + 6}px`;
  }

  function renderOptions() {
    if (!active) return;
    const entry = active;
    menu.replaceChildren();
    menu.setAttribute('aria-label', entry.name);
    let lastGroup = null;
    Array.from(entry.select.options).forEach((option, index) => {
      const group = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
      if (option.hidden || group?.hidden) return;
      if (group && group !== lastGroup) {
        const heading = document.createElement('div');
        heading.className = 'mobile-select-group';
        heading.textContent = group.label;
        menu.appendChild(heading);
      }
      lastGroup = group;
      const choice = document.createElement('button');
      choice.type = 'button';
      choice.className = 'mobile-select-option';
      choice.setAttribute('role', 'option');
      choice.setAttribute('aria-selected', String(option.selected));
      choice.tabIndex = -1;
      choice.disabled = option.disabled || Boolean(group?.disabled);
      const text = document.createElement('span');
      text.textContent = option.textContent;
      choice.appendChild(text);
      choice.addEventListener('click', () => {
        if (entry.select.matches(':disabled')) { close(); return; }
        entry.select.selectedIndex = index;
        entry.select.dispatchEvent(new Event('input', { bubbles: true }));
        entry.select.dispatchEvent(new Event('change', { bubbles: true }));
        sync(entry);
        close(true);
      });
      menu.appendChild(choice);
    });
  }

  function open(entry) {
    close(false, true);
    sync(entry);
    if (entry.button.hidden || entry.button.disabled) return;
    // Modal dialogs make nodes outside them inert, regardless of z-index.
    const host = entry.select.closest('dialog[open]') || document.body;
    if (menu.parentElement !== host) host.appendChild(menu);
    active = entry;
    entry.button.setAttribute('aria-expanded', 'true');
    renderOptions();
    menu.hidden = false;
    if (typeof menu.showPopover === 'function') {
      try { menu.showPopover(); } catch (_) { /* The in-dialog menu also supports older browsers. */ }
    }
    position();
    if (menu.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      animation = menu.animate([{opacity: 0, transform: 'translateY(-6px)'}, {opacity: 1, transform: 'translateY(0)'}], {duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)'});
      animation.onfinish = () => { animation = null; };
    }
    const option = menu.querySelector('[aria-selected="true"]:not(:disabled)') || menu.querySelector('button:not(:disabled)');
    option?.focus({ preventScroll: true });
    if (option) menu.scrollTop = Math.max(0, option.offsetTop - menu.clientHeight / 2);
  }

  function enhance(select) {
    if (entries.has(select) || select.multiple || select.size > 1) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-select-trigger';
    button.id = `mobile-select-trigger-${++sequence}`;
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-controls', menu.id);
    button.setAttribute('aria-expanded', 'false');
    const label = document.createElement('span');
    button.appendChild(label);
    const entry = { select, button, label, tabIndex: select.getAttribute('tabindex'), ariaHidden: select.getAttribute('aria-hidden') };
    entries.set(select, entry);
    select.after(button);
    button.addEventListener('click', () => active === entry ? close(true) : open(entry));
    button.addEventListener('keydown', event => {
      if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(entry); }
    });
    select.addEventListener('input', () => sync(entry));
    select.addEventListener('change', () => sync(entry));
    select.addEventListener('invalid', event => {
      if (media.matches && !button.hidden) {
        event.preventDefault();
        button.setAttribute('aria-invalid', 'true');
        if (!active) open(entry);
      }
    });
    select.addEventListener('focus', () => { if (media.matches) button.focus(); });
    Array.from(select.labels || []).forEach(item => item.addEventListener('click', event => {
      if (media.matches && event.target !== button && !button.contains(event.target)) { event.preventDefault(); button.focus(); }
    }));
    new MutationObserver(() => {
      sync(entry);
      if (active === entry) { renderOptions(); position(); }
    }).observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'selected', 'label', 'value', 'required', 'style'] });
    sync(entry);
  }

  document.querySelectorAll('select').forEach(enhance);
  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType !== 1) return;
      if (node.matches('select')) enhance(node);
      node.querySelectorAll('select').forEach(enhance);
    }));
  }).observe(document.body, { childList: true, subtree: true });

  document.addEventListener('pointerdown', event => {
    if (active && !menu.contains(event.target) && !active.button.contains(event.target)) close();
  }, true);
  document.addEventListener('close', event => {
    if (active && event.target.contains(active.select)) close();
  }, true);
  document.addEventListener('keydown', event => {
    if (!active) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); close(true); return; }
    if (event.key === 'Tab') { active.button.focus({ preventScroll: true }); close(); return; }
    const choices = Array.from(menu.querySelectorAll('button:not(:disabled)'));
    if (!choices.length) return;
    const current = choices.indexOf(document.activeElement);
    let next = -1;
    if (event.key === 'ArrowDown') next = (current + 1) % choices.length;
    if (event.key === 'ArrowUp') next = (current - 1 + choices.length) % choices.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = choices.length - 1;
    if (event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey) {
      typed = Date.now() - typedAt > 700 ? event.key : typed + event.key;
      typedAt = Date.now();
      next = choices.findIndex(choice => choice.textContent.trim().toLowerCase().startsWith(typed.toLowerCase()));
    }
    if (next >= 0) { event.preventDefault(); choices[next].focus({ preventScroll: true }); menu.scrollTop = Math.max(0, choices[next].offsetTop - menu.clientHeight / 2); }
  }, true);
  document.addEventListener('scroll', event => { if (!menu.contains(event.target)) position(); }, true);
  window.addEventListener('resize', position);
  document.addEventListener('portal-navigation-start', () => close(false, true));
  window.addEventListener('pagehide', () => close(false, true));
  window.addEventListener('pageshow', event => {
    if (event.persisted) close(false, true);
  });
  window.visualViewport?.addEventListener('resize', position);
  window.visualViewport?.addEventListener('scroll', position);
  media.addEventListener('change', () => { close(); entries.forEach(sync); });
  document.addEventListener('reset', () => { close(); setTimeout(() => entries.forEach(sync), 0); }, true);
  // Existing forms also assign select.value directly when prefilling a modal.
  window.setInterval(() => {
    if (document.hidden) return;
    entries.forEach((entry, select) => {
      if (!select.isConnected) { if (active === entry) close(); entry.button.remove(); entries.delete(select); }
      else sync(entry);
    });
  }, 300);
})();
