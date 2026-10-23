// Link check: every href/src on every page must resolve.
//
// PLAN.md §5 gate 7 was specified but never written, so nothing caught a broken
// link between the two language trees. Two traps it exists to avoid:
//   * anchors (#tabs) and query strings (gallery.html?room=room3#tabs) are page
//     links, not files. Fetching them literally gives a false 404.
//   * the Greek tree links with plain names ("gallery.html") and the English
//     tree with "../" prefixes, so each page needs resolving against its own
//     directory.
//
// Usage: node tools/audit/linkcheck.mjs [baseUrl] [root]
//   baseUrl  default http://127.0.0.1:12000/
//   root     default preview  (the directory baseUrl serves)

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, dirname, normalize, resolve } from 'node:path';

const BASE = process.argv[2] || 'http://127.0.0.1:12000/';
const ROOT = resolve(process.argv[3] || 'preview');

// Pages to check. Both trees, because a link can be valid in one and broken in
// the other: the English pages sit one level deeper.
function collect(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) {
      if (name === 'admin' || name === 'welcome' || name === 'images' || name === 'fonts') continue;
      collect(p, out);
    } else if (name.endsWith('.html')) {
      out.push(p);
    }
  }
  return out;
}

const PAGES = collect(ROOT).sort();

const ATTR = /\b(?:href|src)\s*=\s*"([^"]+)"/g;

function isExternal(u) {
  return /^(?:[a-z][a-z0-9+.-]*:|\/\/|#|mailto:|tel:|data:|javascript:)/i.test(u);
}

// Map a page-relative reference onto a filesystem path inside ROOT.
function toPath(page, url) {
  const clean = url.split('#')[0].split('?')[0];
  if (!clean) return null;
  const from = dirname(page);
  const abs = clean.startsWith('/')
    ? join(ROOT, clean)
    : normalize(join(from, clean));
  return abs;
}

async function fetchStatus(url) {
  try {
    const r = await fetch(url, { method: 'HEAD', redirect: 'manual' });
    return r.status;
  } catch (e) {
    return 0;
  }
}

const problems = [];
let checked = 0;
let skipped = 0;

for (const page of PAGES) {
  const html = readFileSync(page, 'utf8');
  const rel = page.slice(ROOT.length + 1);
  const seen = new Set();

  for (const m of html.matchAll(ATTR)) {
    const url = m[1].trim();
    if (!url || isExternal(url)) { skipped++; continue; }
    if (seen.has(url)) continue;
    seen.add(url);

    const target = toPath(page, url);
    if (!target) { skipped++; continue; }

    checked++;
    if (!existsSync(target)) {
      // Not a local file: could be a live-only route (e.g. /api/). Report the
      // ones that look like our own pages, stay quiet about the rest.
      const looksLocal = /\.(?:html|css|js|json|webp|jpg|jpeg|png|ico|svg|woff2?|xml|txt|webmanifest)$/i.test(url);
      if (looksLocal) problems.push({ page: rel, url, why: 'missing file' });
      else skipped++;
    }
  }
}

// Also confirm the pages themselves are served, since the sweep above only
// reads them from disk.
let served = 0, unserved = 0;
for (const page of PAGES) {
  const rel = page.slice(ROOT.length + 1).split('\\').join('/');
  const status = await fetchStatus(BASE + rel);
  if (status === 200) served++;
  else { unserved++; problems.push({ page: rel, url: BASE + rel, why: 'HTTP ' + status }); }
}

console.log('  pages on disk:      ' + PAGES.length);
console.log('  pages served:       ' + served + ' (' + unserved + ' failed)');
console.log('  local refs checked: ' + checked + ' (' + skipped + ' external/anchors skipped)');
if (problems.length === 0) {
  console.log('  ✅ every local link resolves');
  process.exit(0);
}
console.log('  ❌ ' + problems.length + ' broken:');
for (const p of problems) console.log('     ' + p.why + '  ' + p.page + '  ->  ' + p.url);
process.exit(1);
