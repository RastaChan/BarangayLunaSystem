(() => {
  const root = document.body;
  if (!root) return;
  const running = new WeakMap();
  root.addEventListener('click', event => {
    const summary = event.target.closest('summary');
    const group = summary?.parentElement;
    if (!group || group.tagName !== 'DETAILS' || !root.contains(group)) return;
    if (group.classList.contains('settings-nav-group')) return;
    if (event.target.closest('a, button, input, select, textarea')) return;
    event.preventDefault();
    const previous = running.get(group);
    const targetOpen = previous ? !previous.targetOpen : !group.open;
    const start = group.getBoundingClientRect().height;
    previous?.animation.cancel();
    const cleanup = () => {
      group.open = targetOpen;
      group.style.overflow = '';
      group.style.boxSizing = '';
      delete group.dataset.disclosureClosing;
      running.delete(group);
    };
    if (!group.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      cleanup();
      return;
    }
    // Measure the natural destination before keeping content visible during collapse.
    group.open = targetOpen;
    const end = group.getBoundingClientRect().height;
    group.open = true;
    group.dataset.disclosureClosing = String(!targetOpen);
    group.style.overflow = 'hidden';
    group.style.boxSizing = 'border-box';
    const animation = group.animate([{height: `${start}px`}, {height: `${end}px`}], {
      duration: 420, easing: 'cubic-bezier(.2,.8,.2,1)'
    });
    running.set(group, {animation, targetOpen});
    animation.onfinish = cleanup;
  });
})();
