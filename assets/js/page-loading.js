(function () {
  var overlay = document.getElementById('jn-page-loading');
  if (!overlay) return;

  var bailout;

  function show() {
    overlay.style.display = 'flex';
    // ponytail: last-resort backstop. A handler can still cancel the navigation
    // without calling preventDefault, and a stuck overlay locks the user out of
    // the page entirely — 8s of a spinner that outstayed its welcome beats that.
    clearTimeout(bailout);
    bailout = setTimeout(function () { overlay.style.display = 'none'; }, 8000);
  }

  // Full-page navigations only — buttons / Alpine @click handlers are untouched.
  // Bubble phase, NOT capture: handlers that cancel the click are delegated on
  // body (ModernCart binds '.ast-site-header-cart-li a' to open its drawer, Alpine
  // uses @click.prevent, WooCommerce hijacks ajax_add_to_cart), and body sits
  // before document on the bubble path, so defaultPrevented is already set by the
  // time this runs. In capture phase it is always false and the check is dead code.
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    var link = e.target.closest('a[href]');
    if (!link) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.classList.contains('ajax_add_to_cart')) return;

    // href="#" is a JS hook, not a navigation. It needs its own check: new URL('#', ...)
    // reports an EMPTY hash, so the same-page test below never catches it.
    var href = link.getAttribute('href');
    if (!href || href.charAt(0) === '#') return;

    try {
      // non-http schemes (mailto:, tel:, javascript:) resolve to a "null" origin, so the
      // origin check below covers them too
      var url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;
      // same-page hash jump — not a real navigation
      if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
    } catch (err) {
      return;
    }

    show();
  }, false);

  // Restoring from bfcache (back/forward) — hide any overlay left over from before.
  window.addEventListener('pageshow', function () {
    clearTimeout(bailout);
    overlay.style.display = 'none';
  });
})();
