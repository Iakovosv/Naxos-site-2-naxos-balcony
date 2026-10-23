/**
 * Touch-target audit.
 *
 * On touch screens the minimum comfortable hit area is 44x44 CSS pixels (WCAG
 * 2.5.5 AAA, and the Android / iOS guidelines). A mouse pointer is far more
 * precise, so on desktop the bar is WCAG 2.2 AA's 24x24 (2.5.8).
 *
 * Measuring getBoundingClientRect() alone gives false positives: a link can be
 * small yet sit inside a much larger clickable surface, either through a
 * stretched ::after overlay (the room cards) or by being wrapped in a bigger
 * anchor. So for each element this grows a MIN-sized box around its centre and
 * asks elementFromPoint() at the four corners whether each point still resolves
 * to that element or to a link pointing at the same destination.
 *
 * Run:  node touch.mjs        (needs the PHP preview server and Chrome on :9222)
 */
const PORT = 9222;
const BASE = 'http://127.0.0.1:12000/';
const TOUCH_MIN = 44;
const POINTER_MIN = 24;

const PAGES = [
  'index.html', 'gallery.html', 'amenities.html', 'contact.html', 'weather.html',
  'forecast-pro.html', '404.html', 'privacy.html', 'cookies.html',
  'en/index.html', 'en/gallery.html', 'en/amenities.html', 'en/contact.html',
  'en/weather.html', 'en/forecast-pro.html', 'en/404.html', 'en/privacy.html',
  'en/cookies.html',
];

async function open(url, width, height) {
  const t = await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' })).json();
  const ws = new WebSocket(t.webSocketDebuggerUrl);
  let id = 0;
  const pending = new Map();
  const send = (method, params = {}) => new Promise((res, rej) => {
    const i = ++id;
    pending.set(i, { res, rej });
    ws.send(JSON.stringify({ id: i, method, params }));
  });
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    if (m.id && pending.has(m.id)) {
      const q = pending.get(m.id);
      pending.delete(m.id);
      m.error ? q.rej(new Error(JSON.stringify(m.error))) : q.res(m.result);
    }
  };
  await new Promise((r) => { ws.onopen = r; });
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', {
    width, height, deviceScaleFactor: 2, mobile: width < 900,
  });
  await send('Page.navigate', { url });
  for (let i = 0; i < 150; i++) {
    const r = await send('Runtime.evaluate', { expression: 'document.readyState', returnByValue: true });
    if (r.result.value === 'complete') break;
    await new Promise((r) => setTimeout(r, 100));
  }
  await new Promise((r) => setTimeout(r, 1000));
  const ev = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
    return r.result.value;
  };
  return { ev, close: () => ws.close() };
}

// Collect the candidate controls that are smaller than the minimum in either
// axis. Elements outside the viewport are included; each is scrolled into view
// before it is measured.
const collect = (min) => `(() => {
  const MIN = ${min};
  const out = [];
  document.querySelectorAll('a,button,summary,input,select,textarea,[role="button"]').forEach((el, i) => {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return;
    const b = el.getBoundingClientRect();
    if (b.width === 0 || b.height === 0) return;
    if (b.width >= MIN && b.height >= MIN) return;
    el.dataset.touchProbe = String(i);
    out.push(i);
  });
  return out;
})()`;

// Measure one control: grow a MIN-sized box around its centre and ask
// elementFromPoint() at the four corners whether the point still hits it.
const measure = (min, idx) => `(() => {
  const MIN = ${min};
  const el = document.querySelector('[data-touch-probe="' + ${idx} + '"]');
  if (!el) return null;
  // The pages scroll smoothly, so scrolling then measuring in the same tick
  // would read a stale rect. Jump instantly instead.
  const prev = document.documentElement.style.scrollBehavior;
  document.documentElement.style.scrollBehavior = 'auto';
  el.scrollIntoView({block: 'center'});
  document.documentElement.style.scrollBehavior = prev;
  const hitTarget = (x, y) => {
    const top = document.elementFromPoint(x, y);
    if (!top) return false;
    if (top === el || el.contains(top) || top.contains(el)) return true;
    // A stretched ::after overlay, or a wrapper anchor, still counts as a hit
    // when it navigates to the same place.
    const a = top.closest('a');
    const b = el.closest('a') || (el.tagName === 'A' ? el : null);
    return !!(a && b && a.getAttribute('href') === b.getAttribute('href'));
  };
  const b = el.getBoundingClientRect();
  const cx = b.left + b.width / 2, cy = b.top + b.height / 2;
  const h = MIN / 2;
  const corners = [[cx - h, cy - h], [cx + h, cy - h], [cx - h, cy + h], [cx + h, cy + h]];
  const covered = corners.filter(([x, y]) =>
    x >= 0 && y >= 0 && x <= innerWidth && y <= innerHeight && hitTarget(x, y)).length;
  return {
    tag: el.tagName,
    cls: String(el.className || '').split(' ').slice(0, 2).join('.'),
    text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 30),
    w: Math.round(b.width), h: Math.round(b.height), covered, corners: corners.length,
  };
})()`;

let problems = 0;
for (const pg of PAGES) {
  for (const w of [390, 1280]) {
    const min = w < 900 ? TOUCH_MIN : POINTER_MIN;
    const c = await open(BASE + pg, w, w < 900 ? 844 : 900);
    const idxs = await c.ev(collect(min));
    for (const i of idxs) {
      const it = await c.ev(measure(min, i));
      if (!it) continue;
      if (it.covered === it.corners) continue;   // effectively big enough
      problems++;
      const note = it.covered ? `hit area covers only ${it.covered}/${it.corners} corners` : 'hit area not covered';
      console.log(`  ❌ ${(pg + ' @' + w).padEnd(24)} ${it.tag}${it.cls ? '.' + it.cls : ''} ` +
                  `${it.w}x${it.h} (min ${min}) "${it.text}" ${note}`);
    }
    c.close();
  }
}

console.log(problems
  ? `\n  ➜ ${problems} hit areas below the minimum`
  : `\n  ➜ every interactive element meets the ${TOUCH_MIN}px touch / ${POINTER_MIN}px pointer minimum  ✅`);
process.exit(problems ? 1 : 0);
