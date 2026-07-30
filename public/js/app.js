/**
 * NdamStore — UI dynamique (toasts, popups, interactions)
 */
(() => {
  'use strict';

  const TYPE_MAP = {
    success: { icon: 'bi-check-circle-fill', title: 'Succès' },
    danger: { icon: 'bi-exclamation-triangle-fill', title: 'Erreur' },
    warning: { icon: 'bi-exclamation-circle-fill', title: 'Attention' },
    info: { icon: 'bi-info-circle-fill', title: 'Info' },
    error: { icon: 'bi-exclamation-triangle-fill', title: 'Erreur' },
  };

  const AppUI = {
    toastContainer: null,
    confirmModal: null,
    confirmResolve: null,

    init() {
      this.toastContainer = document.getElementById('toastStack');
      this.confirmModal = document.getElementById('appConfirmModal');
      this.bindConfirm();
      this.bindDataConfirms();
      this.bindFlashMessages();
      this.bindFormLoading();
      this.bindReveal();
      this.bindTables();
      this.bindLogoutConfirm();
      this.bindLiveClock();
    },

    toast(message, type = 'info', options = {}) {
      if (!this.toastContainer) return;
      const meta = TYPE_MAP[type] || TYPE_MAP.info;
      const delay = options.delay ?? (type === 'danger' || type === 'error' ? 7000 : 4200);
      const el = document.createElement('div');
      el.className = `app-toast app-toast-${type}`;
      el.setAttribute('role', 'status');
      el.innerHTML = `
        <div class="app-toast-icon"><i class="bi ${meta.icon}"></i></div>
        <div class="app-toast-body">
          <div class="app-toast-title">${meta.title}</div>
          <div class="app-toast-msg"></div>
        </div>
        <button type="button" class="app-toast-close" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
        <div class="app-toast-progress" style="animation-duration:${delay}ms"></div>
      `;
      el.querySelector('.app-toast-msg').textContent = message;
      this.toastContainer.appendChild(el);
      requestAnimationFrame(() => el.classList.add('show'));

      const remove = () => {
        el.classList.remove('show');
        el.classList.add('hiding');
        setTimeout(() => el.remove(), 280);
      };
      el.querySelector('.app-toast-close').addEventListener('click', remove);
      setTimeout(remove, delay);
    },

    bindFlashMessages() {
      const node = document.getElementById('flash-data');
      if (!node) return;
      try {
        const flashes = JSON.parse(node.textContent || '[]');
        flashes.forEach((item, i) => {
          const type = item.type || 'info';
          const isSalePopup = type === 'sale_success' || type === 'sale_error' || item.popup;
          if (isSalePopup) {
            setTimeout(() => {
              const ok = type === 'sale_success' || type === 'success';
              this.result({
                type: ok ? 'success' : 'danger',
                title: ok ? 'Encaissement validé' : 'Encaissement refusé',
                message: item.message,
                primaryLabel: ok ? (item.primaryLabel || 'Nouvelle vente') : 'Réessayer',
                primaryHref: item.primaryHref || (ok ? (window.AppRoutes?.saleNew || null) : null),
                secondaryLabel: ok ? (item.secondaryLabel || null) : null,
                secondaryHref: item.secondaryHref || null,
              });
            }, 120);
            return;
          }
          setTimeout(() => this.toast(item.message, type), 80 + i * 120);
        });
      } catch (_) { /* ignore */ }
    },

    confirm(message, options = {}) {
      return new Promise((resolve) => {
        if (!this.confirmModal || typeof bootstrap === 'undefined') {
          resolve(window.confirm(message));
          return;
        }
        this.confirmResolve = resolve;
        const title = options.title || 'Confirmation';
        const confirmLabel = options.confirmLabel || 'Confirmer';
        const danger = options.danger !== false;
        document.getElementById('appConfirmTitle').textContent = title;
        document.getElementById('appConfirmMessage').textContent = message;
        const btn = document.getElementById('appConfirmOk');
        btn.textContent = confirmLabel;
        btn.className = danger ? 'btn btn-danger' : 'btn btn-brand';
        bootstrap.Modal.getOrCreateInstance(this.confirmModal).show();
      });
    },

    bindConfirm() {
      if (!this.confirmModal) return;
      const ok = document.getElementById('appConfirmOk');
      const cancel = document.getElementById('appConfirmCancel');
      const finish = (value) => {
        if (this.confirmResolve) {
          this.confirmResolve(value);
          this.confirmResolve = null;
        }
        if (typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(this.confirmModal).hide();
        }
      };
      ok?.addEventListener('click', () => finish(true));
      cancel?.addEventListener('click', () => finish(false));
      this.confirmModal.addEventListener('hidden.bs.modal', () => {
        if (this.confirmResolve) {
          this.confirmResolve(false);
          this.confirmResolve = null;
        }
      });
    },

    bindDataConfirms() {
      document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const msg = form.getAttribute('data-confirm');
        if (!msg) return;
        if (form.dataset.confirmed === '1') {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        const ok = await this.confirm(msg, {
          title: form.getAttribute('data-confirm-title') || 'Confirmer l\'action',
          confirmLabel: form.getAttribute('data-confirm-label') || 'Oui, continuer',
          danger: form.getAttribute('data-confirm-danger') !== '0',
        });
        if (ok) {
          form.dataset.confirmed = '1';
          form.requestSubmit();
          // Remettre le flag après le cycle de submit pour le spinner
          setTimeout(() => { delete form.dataset.confirmed; }, 0);
        }
      }, true);

      document.addEventListener('click', async (e) => {
        const link = e.target.closest('[data-confirm-link]');
        if (!link) return;
        e.preventDefault();
        const ok = await this.confirm(link.getAttribute('data-confirm-link'), {
          title: link.getAttribute('data-confirm-title') || 'Confirmation',
          confirmLabel: link.getAttribute('data-confirm-label') || 'Continuer',
        });
        if (ok) {
          if (link.tagName === 'A' && link.href) {
            window.location.href = link.href;
          } else if (link.dataset.href) {
            window.location.href = link.dataset.href;
          }
        }
      });
    },

    bindFormLoading() {
      document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
        if (form.getAttribute('data-confirm') && form.dataset.confirmed !== '1') return;
        const btn = form.querySelector('[type="submit"], button:not([type]), .btn-brand');
        if (!btn || btn.disabled) return;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>Patientez…`;
      });
    },

    bindReveal() {
      const items = document.querySelectorAll('.panel, .stat-card, .table tbody tr, .auth-card');
      if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
      }
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
      items.forEach((el, i) => {
        el.classList.add('ui-rise');
        el.style.setProperty('--rise-delay', `${Math.min(i * 35, 280)}ms`);
        io.observe(el);
      });
    },

    bindTables() {
      document.querySelectorAll('.table tbody tr').forEach((row) => {
        row.addEventListener('mouseenter', () => row.classList.add('row-hot'));
        row.addEventListener('mouseleave', () => row.classList.remove('row-hot'));
      });
    },

    bindLogoutConfirm() {
      document.querySelectorAll('a[href*="logout"]').forEach((link) => {
        if (link.hasAttribute('data-confirm-link')) return;
        link.setAttribute('data-confirm-link', 'Voulez-vous vraiment vous déconnecter ?');
        link.setAttribute('data-confirm-title', 'Déconnexion');
        link.setAttribute('data-confirm-label', 'Se déconnecter');
        link.setAttribute('data-confirm-danger', '0');
      });
    },

    bindLiveClock() {
      const el = document.getElementById('liveClock');
      if (!el) return;
      const tick = () => {
        const now = new Date();
        el.textContent = now.toLocaleString('fr-FR', {
          weekday: 'short', day: '2-digit', month: 'short',
          hour: '2-digit', minute: '2-digit',
        });
      };
      tick();
      setInterval(tick, 30000);
    },

    result(options = {}) {
      const modal = document.getElementById('appResultModal');
      if (!modal || typeof bootstrap === 'undefined') {
        window.alert(options.message || '');
        return;
      }

      const type = options.type === 'danger' || options.type === 'error' || options.type === 'sale_error'
        ? 'danger'
        : 'success';
      const isOk = type === 'success';
      const icon = isOk ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
      const title = options.title || (isOk ? 'Encaissement validé' : 'Encaissement refusé');
      const message = options.message || '';
      const primaryLabel = options.primaryLabel || 'OK';
      const secondaryLabel = options.secondaryLabel || null;
      const primaryHref = options.primaryHref || null;
      const secondaryHref = options.secondaryHref || null;

      modal.dataset.resultType = type;
      modal.classList.toggle('is-success', isOk);
      modal.classList.toggle('is-danger', !isOk);

      document.getElementById('appResultIcon').className = `bi ${icon}`;
      document.getElementById('appResultTitle').textContent = title;
      document.getElementById('appResultMessage').textContent = message;

      const instance = bootstrap.Modal.getOrCreateInstance(modal, {
        backdrop: true,
        keyboard: true,
        focus: true,
      });

      let done = false;
      const cleanupAndRun = (action) => {
        if (done) return;
        done = true;
        let finished = false;
        const finish = () => {
          if (finished) return;
          finished = true;
          document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
          if (typeof action === 'function') action();
        };
        modal.addEventListener('hidden.bs.modal', finish, { once: true });
        instance.hide();
        setTimeout(finish, 350);
      };

      const bindButton = (id, label, className, visible, action) => {
        const oldBtn = document.getElementById(id);
        if (!oldBtn) return;
        const btn = oldBtn.cloneNode(true);
        oldBtn.replaceWith(btn);
        btn.id = id;
        btn.type = 'button';
        btn.textContent = label;
        btn.className = className;
        btn.classList.toggle('d-none', !visible);
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          cleanupAndRun(action);
        });
      };

      bindButton('appResultPrimary', primaryLabel, isOk ? 'btn btn-brand' : 'btn btn-danger', true, () => {
        if (primaryHref) {
          window.location.href = primaryHref;
          return;
        }
        if (typeof options.onPrimary === 'function') {
          options.onPrimary();
          return;
        }
        document.dispatchEvent(new CustomEvent('app:checkout-retry', { detail: { type } }));
      });

      bindButton(
        'appResultSecondary',
        secondaryLabel || 'Fermer',
        'btn btn-outline-secondary',
        Boolean(secondaryLabel),
        () => {
          if (secondaryHref) window.location.href = secondaryHref;
          else if (typeof options.onSecondary === 'function') options.onSecondary();
        }
      );

      instance.show();
    },
  };

  window.AppUI = AppUI;
  document.addEventListener('DOMContentLoaded', () => AppUI.init());

  // —— PWA: service worker + install prompt ——
  const PWA = {
    deferredPrompt: null,
    dismissKey: 'ndamstore-pwa-dismiss',

    init() {
      this.registerSW();
      this.bindInstall();
    },

    registerSW() {
      if (!('serviceWorker' in navigator)) return;
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
      });
    },

    isStandalone() {
      return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    },

    bindInstall() {
      const banner = document.getElementById('pwaInstallBanner');
      const btn = document.getElementById('pwaInstallBtn');
      const dismiss = document.getElementById('pwaInstallDismiss');
      if (!banner || !btn) return;

      try {
        if (localStorage.getItem(this.dismissKey) === '1' || this.isStandalone()) return;
      } catch (_) {
        if (this.isStandalone()) return;
      }

      const showIosTip = () => {
        const ua = window.navigator.userAgent || '';
        const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (!isIOS) return;
        const copy = banner.querySelector('.pwa-install-copy span');
        if (copy) {
          copy.textContent = 'Sur iPhone : Partager → Sur l’écran d’accueil.';
        }
        btn.hidden = true;
        banner.hidden = false;
      };

      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        this.deferredPrompt = e;
        btn.hidden = false;
        banner.hidden = false;
      });

      // iOS n’émet pas beforeinstallprompt — tip après un court délai
      window.setTimeout(showIosTip, 1800);

      btn.addEventListener('click', async () => {
        if (!this.deferredPrompt) return;
        this.deferredPrompt.prompt();
        try {
          await this.deferredPrompt.userChoice;
        } catch (_) {}
        this.deferredPrompt = null;
        banner.hidden = true;
      });

      if (dismiss) {
        dismiss.addEventListener('click', () => {
          banner.hidden = true;
          try { localStorage.setItem(this.dismissKey, '1'); } catch (_) {}
        });
      }

      window.addEventListener('appinstalled', () => {
        banner.hidden = true;
        this.deferredPrompt = null;
      });
    },
  };

  PWA.init();
})();
