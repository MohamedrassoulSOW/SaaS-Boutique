(() => {
  const THEME_KEY = 'ndamstore-theme';
  const root = document.documentElement;
  const nav = document.getElementById('mktNav');
  const toggle = document.getElementById('mktNavToggle');
  const links = document.getElementById('mktNavLinks');

  const getTheme = () => (root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

  const syncThemeButton = () => {
    const dark = getTheme() === 'dark';
    document.querySelectorAll('[data-theme-toggle], #mktThemeToggle').forEach((btn) => {
      btn.setAttribute('aria-label', dark ? 'Activer le mode clair' : 'Activer le mode sombre');
      btn.title = dark ? 'Mode clair' : 'Mode sombre';
    });
  };

  const setTheme = (theme) => {
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch (_) {}
    syncThemeButton();
  };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-theme-toggle], #mktThemeToggle');
    if (!btn) return;
    e.preventDefault();
    setTheme(getTheme() === 'dark' ? 'light' : 'dark');
  });
  syncThemeButton();

  const onScroll = () => {
    if (!nav) return;
    nav.classList.toggle('is-solid', window.scrollY > 24);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  if (toggle && links) {
    toggle.addEventListener('click', () => {
      const open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    links.querySelectorAll('a').forEach((a) => {
      a.addEventListener('click', () => {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  const reveals = document.querySelectorAll('.reveal-up');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-in'));
  }

  document.querySelectorAll('.mkt-hero .reveal-up').forEach((el) => el.classList.add('is-in'));

  const flashNode = document.getElementById('mkt-flash-data');
  const stack = document.getElementById('mktToasts');
  if (flashNode && stack) {
    try {
      const flashes = JSON.parse(flashNode.textContent || '[]');
      flashes.forEach((f) => {
        const toast = document.createElement('div');
        toast.className = `mkt-toast is-${f.type || 'success'}`;
        toast.textContent = f.message;
        stack.appendChild(toast);
        setTimeout(() => toast.remove(), 5500);
      });
    } catch (_) {}
  }
})();
