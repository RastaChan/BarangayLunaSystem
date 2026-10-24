/**
 * Barangay Luna MIS — Login interactions
 */

(function () {
  'use strict';

  /* ---------- Modals ---------- */
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const focusable = modal.querySelector('[data-modal-close], button.modal-close');
    if (focusable) setTimeout(() => focusable.focus(), 180);
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal.is-open')) {
      document.body.style.overflow = '';
    }
  }

  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.getAttribute('data-modal-open')));
  });

  document.querySelectorAll('[data-modal-close]').forEach((el) => {
    el.addEventListener('click', () => {
      const modal = el.closest('.modal');
      if (modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.is-open').forEach(closeModal);
    }
  });

  /* ---------- Password visibility ---------- */
  const togglePw = document.getElementById('togglePassword');
  const pwInput = document.getElementById('loginPassword');
  const loginForm = document.getElementById('loginForm');
  if (togglePw && pwInput) {
    const eyeSlash = togglePw.querySelector('[data-password-eye-slash]');
    const setPasswordVisible = (visible) => {
      pwInput.type = visible ? 'text' : 'password';
      togglePw.classList.toggle('is-visible', visible);
      togglePw.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      togglePw.setAttribute('aria-pressed', visible ? 'true' : 'false');
      if (eyeSlash) {
        eyeSlash.hidden = false;
        eyeSlash.style.opacity = visible ? '1' : '0';
        eyeSlash.style.visibility = visible ? 'visible' : 'hidden';
      }
    };

    setPasswordVisible(false);
    togglePw.addEventListener('click', () => setPasswordVisible(pwInput.type === 'password'));
    if (loginForm) {
      loginForm.addEventListener('submit', () => setPasswordVisible(false));
    }
    window.addEventListener('pageshow', () => setPasswordVisible(false));
  }

  /* ---------- Keep failed login beside the sign-in form ---------- */
  const loginCard = document.getElementById('loginAltCard');
  const loginMessage = loginCard ? loginCard.querySelector('.auth-message-error') : null;
  if (loginCard && loginMessage && window.location.hash === '#loginAltCard') {
    window.setTimeout(() => {
      loginCard.scrollIntoView({ block: 'center', behavior: 'auto' });
      const username = document.getElementById('loginUsername');
      const password = document.getElementById('loginPassword');
      if (username && username.value.trim() === '') {
        username.focus({ preventScroll: true });
      } else if (password) {
        password.focus({ preventScroll: true });
      }
    }, 0);
  }

})();
