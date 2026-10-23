/* Full functional + responsive audit of the built site, via CDP.
 * Usage: node fullcheck.mjs [baseUrl]
 */
const PORT = 9222;
const BASE = process.argv[2] || 'http://127.0.0.1:12000/';

async function open(url, width, height) {
  const t = await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' })).json();
  const ws = new WebSocket(t.webSocketDebuggerUrl);
  let id = 0; const p = new Map(); const logs = [];
  const send = (m, q = {}) => new Promise((res, rej) => {
    const i = ++id; p.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method: m, params: q }));
  });
  ws.onmessage = e => {
    const m = JSON.parse(e.data);
    if (m.method === 'Runtime.consoleAPICalled') logs.push(m.params.type + ': ' + m.params.args.map(a => a.value || a.description || '').join(' '));
    if (m.method === 'Log.entryAdded') logs.push('log: ' + m.params.entry.text);
    if (m.id && p.has(m.id)) { const q = p.get(m.id); p.delete(m.id); m.error ? q.rej(new Error(JSON.stringify(m.error))) : q.res(m.result); }
  };
  await new Promise(r => ws.onopen = r);
  await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 900 });
  await send('Page.navigate', { url });
  await new Promise(r => setTimeout(r, 2200));
  const ev = async e => (await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result.value;
  return { ev, send, logs, close: () => ws.close() };
}

const PAGES = ['index.html', 'gallery.html', 'amenities.html', 'weather.html', 'contact.html',
  'forecast-pro.html', 'privacy.html', 'cookies.html', '404.html'];
const SIZES = [[375, 812, 'mobile'], [768, 1024, 'tablet'], [1280, 900, 'desktop']];

const problems = [];
const report = [];

/* ---------- 1. responsive + broken assets + a11y ---------- */
for (const [w, h, name] of SIZES) {
  for (const p of PAGES) {
    const { ev, logs, close } = await open(BASE + p, w, h);
    const r = await ev(`(() => {
      const imgs = [...document.querySelectorAll('img')];
      return {
        sw: document.documentElement.scrollWidth, iw: window.innerWidth,
        wide: [...document.querySelectorAll('*')].filter(e => e.getBoundingClientRect().right > window.innerWidth + 3)
              .map(e => e.tagName + '.' + (e.className||'').toString().split(' ')[0]).slice(0,4),
        noAlt: imgs.filter(i => !i.hasAttribute('alt')).length,
        broken: imgs.filter(i => i.getAttribute('src') && i.complete && i.naturalWidth === 0).map(i => i.getAttribute('src')).slice(0,4),
        h1: (document.querySelector('h1')||{}).textContent ? document.querySelector('h1').textContent.trim().slice(0,50) : null,
        title: document.title.slice(0, 60),
        banner: !!document.querySelector('#cookieBanner:not([hidden])'),
        lang: document.documentElement.lang
      };
    })()`);
    const jerr = logs.filter(l => /error/i.test(l)).slice(0, 2);
    if (r.sw > r.iw + 3) problems.push(`${name}/${p}: οριζόντιο overflow ${r.sw}>${r.iw} :: ${JSON.stringify(r.wide)}`);
    if (r.noAlt) problems.push(`${name}/${p}: ${r.noAlt} img χωρίς alt`);
    if (r.broken.length) problems.push(`${name}/${p}: σπασμένες εικόνες ${JSON.stringify(r.broken)}`);
    if (jerr.length) problems.push(`${name}/${p}: JS ${jerr.join(' | ').slice(0, 150)}`);
    if (name === 'desktop') report.push(`  ${p.padEnd(18)} lang=${r.lang} banner=${r.banner ? 'Y' : 'n'} h1="${(r.h1 || '-').slice(0, 34)}"`);
    close();
  }
}
console.log('=== DESKTOP ===');
console.log(report.join('\n'));

/* ---------- 2. gallery: filter, lightbox, no double render ---------- */
{
  const { ev, close } = await open(BASE + 'gallery.html', 1280, 900);
  const g = await ev(`(() => {
    const tiles = [...document.querySelectorAll('.tile')];
    return { total: tiles.length, cats: [...new Set(tiles.map(t => t.dataset.cat))].sort(),
             w100: tiles.every(t => { const i = t.querySelector('img'); return i.getAttribute('width') && i.getAttribute('height'); }),
             allSrc: [...new Set(tiles.map(t => t.querySelector('img').getAttribute('src')))].length };
  })()`);
  await ev(`document.querySelector('[data-filter="room5"]').click()`);
  await new Promise(r => setTimeout(r, 250));
  const f = await ev(`({ visible: [...document.querySelectorAll('.tile')].filter(t => t.style.display !== 'none').length,
                            info: document.getElementById('room-info').textContent.slice(0, 45) })`);
  await ev(`document.querySelectorAll('.tile')[0].querySelector('a').click()`);
  await new Promise(r => setTimeout(r, 250));
  const lb = await ev(`({ open: document.getElementById('lightbox').classList.contains('open'),
                           img: document.getElementById('lbImg').src.split('/').pop(),
                           bodyOverflow: document.body.style.overflow })`);
  await ev(`document.getElementById('lbNext').click()`);
  const nx = await ev(`document.getElementById('lbImg').src.split('/').pop()`);
  await ev(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}))`);
  const cl = await ev(`document.getElementById('lightbox').classList.contains('open')`);
  console.log('\n=== GALLERY ===');
  console.log(JSON.stringify({ ...g, filter: f, lightbox: lb, next: nx, closedAfterEsc: !cl }, null, 0));
  if (g.total !== 83) problems.push(`gallery: ${g.total} tiles αντί 83`);
  if (lb.img === nx) problems.push('lightbox: το Next δεν άλλαξε εικόνα');
  if (cl) problems.push('lightbox: δεν έκλεισε με Escape');
  close();
}

/* ---------- 3. consent ---------- */
{
  const { ev, close } = await open(BASE + 'index.html', 1280, 900);
  // simulate a first-time visitor: forget any earlier choice, then reload
  await ev(`localStorage.removeItem('cookieConsent')`);
  await ev(`location.reload()`);
  await new Promise(r => setTimeout(r, 1800));
  const before = await ev(`({ banner: !!document.querySelector('#cookieBanner:not([hidden])'),
                              stored: localStorage.getItem('cookieConsent') })`);
  await ev(`document.getElementById('cookieAccept').click()`);
  await new Promise(r => setTimeout(r, 400));
  const after = await ev(`({ banner: !!document.querySelector('#cookieBanner:not([hidden])'),
                             stored: localStorage.getItem('cookieConsent'),
                             consent: (window.dataLayer||[]).map(a => Array.from(a)).filter(a => a[0] === 'consent').slice(-1)[0] })`);
  await ev(`document.getElementById('cookieSettings2').click()`);
  const reopened = await ev(`!!document.querySelector('#cookieBanner:not([hidden])')`);
  console.log('\n=== CONSENT ===');
  console.log(JSON.stringify({ before, after, reopened }, null, 0));
  if (!before.banner) problems.push('consent: το banner δεν εμφανίστηκε σε νέο επισκέπτη');
  if (after.banner) problems.push('consent: το banner δεν έκλεισε μετά την Αποδοχή');
  if (after.stored !== 'granted') problems.push('consent: δεν αποθηκεύτηκε η επιλογή');
  if (!reopened) problems.push('consent: ο σύνδεσμος "Ρυθμίσεις Cookies" δεν ανοίγει ξανά το banner');
  close();
}

/* ---------- 4. language links ---------- */
{
  const { ev, close } = await open(BASE + 'index.html', 1280, 900);
  const el = await ev(`({ el: document.getElementById('langEl').getAttribute('href'),
                           en: document.getElementById('langEn').getAttribute('href'),
                           elTag: document.getElementById('langEl').tagName })`);
  close();
  const { ev: ev2, close: c2 } = await open(BASE + 'en/gallery.html', 1280, 900);
  const en = await ev2(`({ el: document.getElementById('langEl').getAttribute('href'),
                            en: document.getElementById('langEn').getAttribute('href'),
                            h1: document.querySelector('h1').textContent.trim(),
                            tabs: [...document.querySelectorAll('.tab-btn')].map(b => b.textContent.trim()).slice(0, 3) })`);
  c2();
  console.log('\n=== LANGUAGE LINKS ===');
  console.log(JSON.stringify({ greekPage: el, englishPage: en }, null, 0));
  if (el.en !== 'en/index.html') problems.push('lang: το EN κουμπί στην αρχική δεν δείχνει στο en/index.html');
  if (en.el !== '../gallery.html') problems.push('lang: to EL link on en/ does not point back');
  if (en.tabs.join() !== 'All,Room 3,Room 4') problems.push('lang: τα φίλτρα της αγγλικής gallery δεν είναι αγγλικά: ' + en.tabs.join());
}

/* ---------- 5. mobile menu ---------- */
{
  const { ev, close } = await open(BASE + 'index.html', 375, 812);
  const before = await ev(`({ open: document.getElementById('nav').classList.contains('open'),
                              aria: document.getElementById('navToggle').getAttribute('aria-expanded') })`);
  await ev(`document.getElementById('navToggle').click()`);
  await new Promise(r => setTimeout(r, 350));
  const after = await ev(`({ open: document.getElementById('nav').classList.contains('open'),
                             aria: document.getElementById('navToggle').getAttribute('aria-expanded'),
                             visible: document.querySelector('.nav-links').getBoundingClientRect().height > 0 })`);
  console.log('\n=== MOBILE MENU ===');
  console.log(JSON.stringify({ before, after }, null, 0));
  if (!after.open) problems.push('mobile: το μενού δεν ανοίγει');
  if (after.aria !== 'true') problems.push('mobile: aria-expanded δεν ενημερώθηκε');
  close();
}

/* ---------- 6. skip link + focus ---------- */
{
  const { ev, close } = await open(BASE + 'index.html', 1280, 900);
  const s = await ev(`(() => { const a = document.querySelector('.skip-link');
    return { present: !!a, href: a && a.getAttribute('href'), target: !!document.getElementById('main') }; })()`);
  console.log('\n=== SKIP LINK ===');
  console.log(JSON.stringify(s));
  if (!s.present || !s.target) problems.push('a11y: skip link ή #main λείπει');
  close();
}

console.log('\n================ ΠΡΟΒΛΗΜΑΤΑ: ' + problems.length + ' ================');
problems.forEach(p => console.log('  ❌ ' + p));
if (!problems.length) console.log('  ✅ ΚΑΘΑΡΟ');
