// Drives the flow the owner reported as broken: switch to EN, then click
// through the site, and assert the language survives every navigation.
const BASE = process.argv[2] || 'http://127.0.0.1:12000';
const PORT = 9222;

async function open(url) {
  const t = await (await fetch(`http://127.0.0.1:${PORT}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' })).json();
  const ws = new WebSocket(t.webSocketDebuggerUrl); let id = 0; const p = new Map();
  const send = (m, q = {}) => new Promise((res, rej) => { const i = ++id; p.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method: m, params: q })); });
  ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && p.has(m.id)) { const q = p.get(m.id); p.delete(m.id); m.error ? q.rej(new Error(JSON.stringify(m.error))) : q.res(m.result); } };
  await new Promise(r => ws.onopen = r);
  await send('Page.enable'); await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
  const ev = async e => (await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result.value;
  await send('Page.navigate', { url }); await new Promise(r => setTimeout(r, 2500));
  return { ev, close: () => ws.close() };
}

const { ev, close } = await open(BASE + '/');
const click = async (sel, label) => {
  await ev(sel);
  await new Promise(r => setTimeout(r, 2800));
  const path = await ev('location.pathname');
  const lang = await ev('document.documentElement.lang');
  const h1 = await ev(`document.querySelector('h1')?.textContent`);
  return { label, path, lang, h1 };
};

const steps = [];
steps.push(await click(`document.getElementById('langEn').click()`, 'EL home -> EN'));
steps.push(await click(`[...document.querySelectorAll('.nav-links a')].find(a=>a.getAttribute('href')==='contact.html').click()`, 'nav Contact'));
steps.push(await click(`[...document.querySelectorAll('.nav-links a')].find(a=>a.getAttribute('href')==='gallery.html').click()`, 'nav Gallery'));
steps.push(await click(`document.querySelector('.footer-legal a').click()`, 'footer Privacy'));
steps.push(await click(`document.getElementById('langEl').click()`, 'EN -> EL'));

let bad = 0;
for (const s of steps) {
  const want = s.label === 'EN -> EL' ? 'el' : 'en';
  const ok = s.lang === want;
  if (!ok) bad++;
  console.log(`  ${ok ? '✅' : '❌'} ${s.label.padEnd(18)} ${s.path.padEnd(22)} lang=${s.lang}  h1="${s.h1}"`);
}
console.log(bad === 0 ? '\n✅ Η ΓΛΩΣΣΑ ΔΙΑΤΗΡΕΙΤΑΙ ΣΕ ΚΑΘΕ ΠΛΟΗΓΗΣΗ' : `\n❌ ${bad} αποτυχίες`);
close();
process.exit(bad === 0 ? 0 : 1);
