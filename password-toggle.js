/**
 * Toggle password visibility when .password-toggle-btn is clicked.
 * Expects: .password-toggle-wrap (use with .input-group) > input + button.password-toggle-btn > i.bi
 */
(function () {
  function syncButtonState(btn, input, icon, visible) {
    btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    btn.setAttribute('title', visible ? 'Hide password' : 'Show password');
    if (icon) {
      icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
  }

  function initPasswordToggles(root) {
    root = root || document;
    root.querySelectorAll('.password-toggle-wrap').forEach(function (wrap) {
      if (wrap.dataset.ptInit) return;
      wrap.dataset.ptInit = '1';
      var input = wrap.querySelector('input');
      var btn = wrap.querySelector('.password-toggle-btn');
      if (!input || !btn) return;
      var icon = btn.querySelector('i');
      btn.type = 'button';
      syncButtonState(btn, input, icon, input.getAttribute('type') === 'text');
      btn.addEventListener('click', function () {
        var show = input.getAttribute('type') === 'password';
        input.setAttribute('type', show ? 'text' : 'password');
        syncButtonState(btn, input, icon, show);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initPasswordToggles();
    });
  } else {
    initPasswordToggles();
  }
})();
