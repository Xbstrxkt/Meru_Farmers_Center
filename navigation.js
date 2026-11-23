const sidebarToggle = document.querySelector('.sidebar-toggle');
const sidebar = document.querySelector('.global-sidebar');
const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
const sidebarClose = document.querySelector('.sidebar-close');

if (sidebarToggle && sidebar && sidebarBackdrop && sidebarClose) {
  const setSidebarOpen = (isOpen) => {
    document.body.classList.toggle('sidebar-open', isOpen);
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    sidebar.setAttribute('aria-hidden', String(!isOpen));
    sidebar.inert = !isOpen;

    if (isOpen) {
      sidebar.querySelector('a, summary, button')?.focus();
    } else {
      sidebarToggle.focus();
    }
  };

  sidebarToggle.addEventListener('click', () => {
    setSidebarOpen(sidebarToggle.getAttribute('aria-expanded') !== 'true');
  });
  sidebarClose.addEventListener('click', () => setSidebarOpen(false));
  sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
  sidebar.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setSidebarOpen(false));
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      setSidebarOpen(false);
    }
  });
}