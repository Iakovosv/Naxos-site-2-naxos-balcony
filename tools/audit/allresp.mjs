// Responsive sweep: every page at phone / tablet / desktop width.
//
// Two traps this script exists to avoid:
//   * waiting a fixed 2.5 s and then reading the DOM. The preview tunnel is slow
//     and shared, so a page that is merely still loading reports the logo as 0px
//     wide and the hamburger as missing, i.e. two false failures. We now wait for
//     document.readyState === 'complete' plus the logo image, with retries.
//   * a closed mobile menu is collapsed with max-height:0, so measuring the panel
//     says nothing about whether the button itself is shown. We check the button's
//     own display and width instead.
//
// Requires: a server on 12000 and headless Chrome with --remote-debugging-port=9222.
const PORT = 9222;
const BASE = 'http://127.0.0.1:12000/';
const pages = ['index', 'gallery', 'amenities', 'weather', 'contact', 'forecast-pro'];
const widths = [375, 768, 1280];

async function open(url, width) {
  const t = await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' })).json();
  const ws = new WebSocket(t.webSocketDebuggerUrl);
  let id = 0;
  const pending = new Map();
  const send = (m, q = {}) => new Promise((res, rej) => {
    const i = ++id; pending.set(i, { res, rej });
    ws.send(JSON.stringify({ id: i, method: m, params: q }));
  });
  ws.onmessage = e => {
    const m = JSON.parse(e.data);
    if (m.id && pending.has(m.id)) {
      const q = pending.get(m.id); pending.delete(m.id);
      m.error ? q.rej(new Error(JSON.stringify(m.error))) : q.res(m.result);
    }
  };
  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 1024 });
  await send('Page.navigate', { url });
  return { send, close: () => ws.close() };
}

async function probe(url, width, tries = 3) {
  for (let attempt = 1; attempt <= tries; attempt++) {
    const cdp = await open(url, width);
    // Wait for the document and the logo, rather than for a fixed delay.
    await cdp.send('Runtime.evaluate', {
      expression: `(async () => {
        for (let i = 0; i < 120 && document.readyState !== 'complete'; i++) await new Promise(r => setTimeout(r, 100));
        const img = document.querySelector('.logo img');
        if (img && !img.complete) await img.decode().catch(() => {});
      })()`,
      awaitPromise: true, returnByValue: true,
    });
    const { result } = await cdp.send('Runtime.evaluate', {
      expression: `(() => {
        const img = document.querySelector('.logo img');
        const tog = document.getElementById('navToggle');
        if (!img || !tog) return { err: 'missing DOM' };
        return {
          overflow: document.documentElement.scrollWidth > innerWidth + 1,
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth,
          toggle: getComputedStyle(tog).display,
          toggleW: Math.round(tog.getBoundingClientRect().width),
          logoW: img.naturalWidth,
          logoComplete: img.complete,
        };
      })()`, returnByValue: true,
    });
    cdp.close();
    if (result.value && !result.value.err) return result.value;
  }
  return { err: 'all attempts failed', overflow: false, toggle: '?', toggleW: 0, logoW: 0 };
}

const out = [];
for (const pg of pages) for (const w of widths) {
  out.push({ pg, w, ...(await probe(BASE + pg + '.html', w)) });
}

const bad = out.filter(r => r.overflow || r.err);
console.log('checked:', out.length, '| overflow:', bad.length ? JSON.stringify(bad) : 'NONE ✅');

const at = w => out.filter(r => r.w === w);
console.log('hamburger shown at 375:', at(375).every(r => r.toggle === 'block' && r.toggleW > 0) ? '✅' : '❌');
console.log('hamburger shown at 768 (below 900):', at(768).every(r => r.toggle === 'block' && r.toggleW > 0) ? '✅' : '❌');
console.log('hamburger hidden at 1280:', at(1280).every(r => r.toggle === 'none' && r.toggleW === 0) ? '✅' : '❌');
console.log('logo loaded everywhere:', out.every(r => r.logoW > 0 && r.logoComplete) ? '✅' : '❌');

const stalled = out.filter(r => r.err);
if (stalled.length) console.log('pages that never finished loading:', JSON.stringify(stalled));
process.exit(0);
