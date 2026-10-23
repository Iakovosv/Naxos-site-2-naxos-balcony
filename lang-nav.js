/* Language navigation.
 *
 * lang-system.js is reused completely unmodified: it still holds every EL/EN
 * string and still swaps [data-i18n] elements in place. But because that swap
 * happens in JavaScript, search engines only ever saw the Greek text.
 *
 * So the site now ships a real English tree under /en/ with the text already in
 * the HTML, and EL/EN are ordinary links between the two trees. This file only
 * has to stop lang-system.js from fighting those links: the EL/EN elements are
 * now <a>, and we intercept the click before lang-system.js' own listener runs,
 * letting the browser follow the link.
 */
(function () {
  'use strict';

  function intercept(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function (e) {
      // Let the browser navigate; stop the in-page swap from also running.
      e.stopImmediatePropagation();
    }, true);   // capture phase: runs before lang-system.js' bubble-phase listener
  }

  intercept('langEl');
  intercept('langEn');
})();
