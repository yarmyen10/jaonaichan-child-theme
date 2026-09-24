(function () {
  var overlay = document.getElementById('jn-page-loading');
  if (!overlay) return;

  function show() {
    overlay.style.display = 'flex';
  }

  // Full-page navigations only — buttons / Alpine @click handlers are untouched.
  // ponytail: AJAX-hijacked links (WooCommerce's ajax_add_to_cart) never navigate,
  // so they're excluded or the overlay would show and never go away.
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    var link = e.target.closest('a[href]');
    if (!link) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.classList.contains('ajax_add_to_cart')) return;

    try {
      // non-http schemes (mailto:, tel:, javascript:) resolve to a "null" origin, so the
      // origin check below covers them too
      var url = new URL(link.getAttribute('href'), window.location.href);
      if (url.origin !== window.location.origin) return;
      // same-page hash jump — not a real navigation
      if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
    } catch (err) {
      return;
    }

    show();
  }, true);

  // Restoring from bfcache (back/forward) — hide any overlay left over from before.
  window.addEventListener('pageshow', function () {
    overlay.style.display = 'none';
  });
})();
