<script>
/* Restore navigation before the main content is painted. */
(() => {
  const sidebar = document.getElementById('appSidebar');
  const nav = document.getElementById('sidebarNav');
  if (!sidebar) return;
  let collapsed = false;
  try { collapsed = localStorage.getItem('barangaySidebarCollapsed') === '1'; } catch (_) {}
  collapsed = window.matchMedia('(min-width: 901px)').matches && collapsed;
  sidebar.style.transition = 'none';
  sidebar.classList.toggle('is-collapsed', collapsed);
  document.body.classList.toggle('sidebar-is-collapsed', collapsed);
  sidebar.getBoundingClientRect();
  sidebar.style.transition = '';
  if (!nav) return;
  const scrollKey = 'barangaySidebarScroll:' + window.location.pathname;
  let saved = null;
  try { saved = JSON.parse(sessionStorage.getItem(scrollKey)); } catch (_) {}
  if (saved && Number.isFinite(saved.top) && Number.isFinite(saved.left)) {
    nav.scrollTop = Math.max(0, saved.top);
    nav.scrollLeft = Math.max(0, saved.left);
  }
  const active = nav.querySelector('.nav-link.is-active');
  if (active) {
    const item = active.getBoundingClientRect();
    const viewport = nav.getBoundingClientRect();
    // Move only enough to reveal the active item, keeping other links steady.
    if (item.top < viewport.top) nav.scrollTop -= viewport.top - item.top;
    else if (item.bottom > viewport.bottom) nav.scrollTop += item.bottom - viewport.bottom;
    if (item.left < viewport.left) nav.scrollLeft -= viewport.left - item.left;
    else if (item.right > viewport.right) nav.scrollLeft += item.right - viewport.right;
  }
  const remember = () => {
    try { sessionStorage.setItem(scrollKey, JSON.stringify({top: nav.scrollTop, left: nav.scrollLeft})); } catch (_) {}
  };
  nav.addEventListener('scroll', remember, {passive: true});
  nav.addEventListener('click', remember);
  window.addEventListener('pagehide', remember);
})();
</script>
