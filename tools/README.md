# Project tools (backup)

`/tmp` is wiped between sessions, so these live here.

- `gen/build.py` + `gen/*_body.html` — static site generator. Writes all 6 HTML pages and
  `gallery-data.js`. Run `python3 tools/gen/build.py`; it writes into the preview dir.
  Edit `*_body.html` for page content, `build.py` for the shell (nav/footer/head).
- `gen/thumbs.py` — makes the 700px `*-thumb.webp`/`*-thumb.jpg` pair for every gallery
  photo and records both paths in `gallery-tiles.json`. Run before `build.py` when photos
  change. Idempotent; `--force` rebuilds. Without these the gallery grid would serve the
  full-size originals (~15 MB per visit).
- `gen/fonts.py` — downloads the four Inter subsets (greek, greek-ext, latin, latin-ext)
  into `preview/fonts/` and regenerates `preview/fonts.css`. Self-hosting avoids a
  render-blocking third-party stylesheet and keeps visitor IPs away from Google.
- `audit/*.mjs` — Chrome DevTools Protocol checks against `http://127.0.0.1:12000`
  (start with `python3 -m http.server 12000` in the preview dir, and a headless Chrome
  with `--remote-debugging-port=9222`). Key ones: `allresp.mjs` (6 pages × 3 widths,
  overflow/hamburger/logo), `func.mjs` (gallery, weather, EL/EN), `procheck.mjs`
  (forecast-pro), `scrolltest.mjs` (lazy images — scroll before judging "broken"),
  `gallerychk.mjs` (tile thumbnails + lightbox shows the full-size file),
  `netchk.mjs` (real transfer weight per page).

Ports are hardcoded; adjust with `sed -i 's/12000/<port>/g' *.mjs` if needed.
