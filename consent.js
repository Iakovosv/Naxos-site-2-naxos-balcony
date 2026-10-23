/* Consent Mode v2 front-end.
 *
 * Nothing is measured until the visitor chooses. The Google tag loads with
 * analytics_storage denied (set in the page <head>), so no cookies are written
 * and no data is sent until consent is granted here.
 */
(function () {
  'use strict';

  var KEY = 'cookieConsent';           // 'granted' | 'denied'
  var banner = document.getElementById('cookieBanner');
  var accept = document.getElementById('cookieAccept');
  var reject = document.getElementById('cookieReject');
  var reopen = document.getElementById('cookieSettings2');

  function read() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  function write(v) {
    try { localStorage.setItem(KEY, v); } catch (e) { /* private mode */ }
  }
  function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
  }
  function apply(state) {
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage: state === 'granted' ? 'granted' : 'denied'
      });
    } else {
      gtag('consent', 'update', {
        analytics_storage: state === 'granted' ? 'granted' : 'denied'
      });
    }
  }
  function hide() { if (banner) banner.hidden = true; placeFab(); }
  function show() { if (banner) banner.hidden = false; placeFab(); }

  var saved = read();
  if (saved === 'granted' || saved === 'denied') {
    apply(saved);
    hide();
  } else {
    show();
  }

  if (accept) {
    accept.addEventListener('click', function () {
      write('granted'); apply('granted'); hide();
    });
  }
  if (reject) {
    reject.addEventListener('click', function () {
      write('denied'); apply('denied'); hide();
    });
  }
  if (reopen) {
    reopen.addEventListener('click', function () { show(); });
  }

  /* The floating booking button sits in the same bottom-right corner as the
     cookie banner. While the banner is on screen we lift the button above it
     by the banner's measured height, so the two never cover each other. When
     the banner is gone the button returns to its normal offset. */
  var fab = document.getElementById('bookFab');
  function placeFab() {
    if (!fab) return;
    if (banner && !banner.hidden) {
      fab.style.setProperty('--fab-bottom', (banner.offsetHeight + 16) + 'px');
    } else {
      fab.style.removeProperty('--fab-bottom');
    }
  }
  if (fab) {
    if (window.ResizeObserver && banner) new ResizeObserver(placeFab).observe(banner);
    else window.addEventListener('resize', placeFab);
    placeFab();
  }
})();
