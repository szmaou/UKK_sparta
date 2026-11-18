const drawer = document.querySelector('#app-drawer');
const openDrawer = document.querySelector('[data-drawer-open]');
const closeDrawer = document.querySelector('[data-drawer-close]');

if (drawer && openDrawer && closeDrawer) {
  const setDrawer = (open) => {
    drawer.checked = open;
    openDrawer.setAttribute('aria-expanded', String(open));
    (open ? closeDrawer : openDrawer).focus();
  };

  openDrawer.addEventListener('click', () => setDrawer(true));
  closeDrawer.addEventListener('click', () => setDrawer(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer.checked) setDrawer(false);
  });
}
