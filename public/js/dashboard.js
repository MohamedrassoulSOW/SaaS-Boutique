/**
 * NdamStore — interactions communes des tableaux de bord
 */
(() => {
  'use strict';

  const money = (n) =>
    `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(n) || 0)} FCFA`;

  const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

  const animateCount = (el, target, { money: asMoney = false, duration = 900 } = {}) => {
    const from = 0;
    const start = performance.now();
    const tick = (now) => {
      const p = Math.min(1, (now - start) / duration);
      const value = from + (target - from) * easeOutCubic(p);
      el.textContent = asMoney ? money(Math.round(value)) : String(Math.round(value));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };

  const bindCounters = () => {
    document.querySelectorAll('[data-count]').forEach((el, i) => {
      const target = Number(el.getAttribute('data-count')) || 0;
      const asMoney = el.hasAttribute('data-money');
      const delay = Math.min(i * 80, 320);
      setTimeout(() => animateCount(el, target, { money: asMoney, duration: 950 }), delay);
    });
  };

  const bindRankBars = () => {
    document.querySelectorAll('.dash-rank-bar > span[data-width]').forEach((bar, i) => {
      const w = Number(bar.getAttribute('data-width')) || 0;
      bar.style.width = '0%';
      setTimeout(() => {
        bar.style.width = `${Math.max(0, Math.min(100, w))}%`;
      }, 180 + i * 70);
    });
  };

  const bindClock = () => {
    const el = document.querySelector('[data-dash-clock]');
    if (!el) return;
    const fmt = new Intl.DateTimeFormat('fr-FR', {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
    const paint = () => {
      el.textContent = fmt.format(new Date());
    };
    paint();
    setInterval(paint, 30_000);
  };

  const bindOnboardingRing = () => {
    const ring = document.querySelector('[data-onboarding-ring]');
    if (!ring) return;
    const done = Number(ring.getAttribute('data-done')) || 0;
    const total = Number(ring.getAttribute('data-total')) || 1;
    const pct = Math.round((done / total) * 100);
    const circle = ring.querySelector('.dash-ring-progress');
    if (circle) {
      const radius = Number(circle.getAttribute('r')) || 34;
      const circ = 2 * Math.PI * radius;
      circle.style.strokeDasharray = String(circ);
      circle.style.strokeDashoffset = String(circ);
      requestAnimationFrame(() => {
        circle.style.strokeDashoffset = String(circ * (1 - pct / 100));
      });
    }
    const label = ring.querySelector('[data-ring-pct]');
    if (label) label.textContent = `${pct}%`;
  };

  const bindChartTheme = () => {
    // Expose helper for inline chart scripts
    window.NdamDash = window.NdamDash || {};
    window.NdamDash.theme = () => {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      return {
        isDark,
        brand: isDark ? '#3bb39e' : '#0c5c50',
        mid: isDark ? '#2a9b88' : '#147a6a',
        muted: isDark ? '#9db0a8' : '#5f726c',
        grid: isDark ? 'rgba(230,240,236,.08)' : 'rgba(19,32,28,.06)',
        soft: isDark ? 'rgba(59,179,158,.28)' : 'rgba(20,122,106,.28)',
      };
    };
  };

  bindChartTheme();
  bindCounters();
  bindRankBars();
  bindClock();
  bindOnboardingRing();
})();
