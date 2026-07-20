(function () {
  var drawer = document.getElementById('gi-drawer');
  if (!drawer) return;
  var openers = document.querySelectorAll('[data-drawer-open]');
  var closers = drawer.querySelectorAll('[data-drawer-close]');

  function open() {
    drawer.classList.add('is-open');
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'true'); });
    document.body.style.overflow = 'hidden';
  }
  function close() {
    drawer.classList.remove('is-open');
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'false'); });
    document.body.style.overflow = '';
  }

  openers.forEach(function (btn) { btn.addEventListener('click', open); });
  closers.forEach(function (el) { el.addEventListener('click', close); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
