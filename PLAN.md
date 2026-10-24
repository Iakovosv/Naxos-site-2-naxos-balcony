# MASTER PLAN — Naxos Balcony Apartments

> The complete blueprint. Read with `AGENTS.md`. Goal: professional-grade site,
> EL + EN, nothing basic missing, no rework later.

## 0. Corrections to earlier assumptions (verified 2026-10-22)

Earlier notes were wrong on three points. Corrected:

1. **The 3 weather backgrounds EXIST.** `naxos-balcony-exterior-night.jpg`,
   `-rain.jpg`, `-night-rain.jpg` all return 200 under `images/gallery/exterior/`.
   The earlier 404 was a wrong path. All 4 backgrounds are now local and the
   4-state switching is verified working.
2. **`naxos-balcony-apartments-logo.png` exists** on the live site (2.1MB, the
   uncropped logo). Our cropped `logo-naxos-balcony.png` is a deliberate
   improvement, not a fix for a missing file.
3. **Duplicate images are worse than reported.** 83 files, only 60 unique,
   **22 duplicate groups**. Critically: **all of room3 == room5**, and
   **all of room4 == room6**. So a guest viewing "Room 3" sees exactly the same
   photos as "Room 5". Needs an owner decision (see §6).

## 1. Verified facts about the current site

| Item | Value |
|---|---|
| Domain | `https://www.naxosbalcony.gr` (HTTPS + www) |
| Pages | `index`, `gallery`, `amenities`, `weather`, `contact`, `forecast-pro` |
| Business | Naxos Balcony Apartments, Glinado, Naxos |
| Phone | +30 694 227 9665 |
| Email | naxosbalcony@gmail.com |
| Licence no. | 1174Κ13000166800 (Greek EOT/registry number) |
| Facebook | `facebook.com/naxosbalconyapartments` |
| Instagram | `instagram.com/naxos_balcony_apartments/` |
| Google Reviews | linked but **no rating value available** |
| Analytics | GA4 `G-XVJ1VN5JLF` on 5 live pages (forecast-pro has none) |
| PHP on host | **yes** (`api/*.php` → 200) |
| Directory listing | disabled (403) — good |
| `.htaccess` | present (403 on direct fetch) |
| `robots.txt` | **404 — missing** |
| `sitemap.xml` | exists, but lists BOTH `/` and `/index.html` (duplicate) |
| Canonical / OG / JSON-LD / hreflang | **none anywhere** |
| Cookie consent banner | **none** |
| Privacy / cookie policy pages | **none** |
| Contact form | none (tel/WhatsApp/Viber/email links only) |
| Booking engine | none — CTA links to `index.html` |
| Search Console verification | none found |
| Images | 83 files, 60 unique, 22 dup groups; 13 files >250KB; hero 740KB |

### Language architecture (critical)
- `lang-system.js` (503 lines, must stay unmodified) holds `translations.el` /
  `translations.en` and swaps `[data-i18n]` elements via JS.
- Language chosen from `localStorage.preferredLang`, else `'el'`. **No URL
  involvement at all.**
- Consequence: **Google only ever indexes the Greek text.** The English content
  is invisible to crawlers. The site cannot rank for any English query.
- Coverage check (visible text elements not carrying `data-i18n`):
  - `index` 3 uncovered, `gallery` 3, `amenities` 1 — mostly phone/email/Instagram
  - `weather` 8 uncovered — including **"Προσανατολισμός"** (untranslated label)
  - `contact` 8 uncovered — phone/email/Google Reviews
  - **`forecast-pro` 19 of 19 uncovered — the whole page is Greek-only.**
    ⚠️ This includes the visible `<h1>` and the meta title/description.

## 2. Foundation checklist — what MUST be in from the start

Ordered so nothing gets discovered late. Grouped by who can unblock it.

### Group A — I can do now, no input needed
1. **Restore GA4** `G-XVJ1VN5JLF` on all 6 pages (currently 0). *Blocked by §3.1 GDPR.*
2. **Canonical URL** on every page (self-referencing, `https://www.naxosbalcony.gr/...`).
3. **`robots.txt`** — allow all, declare sitemap, block nothing sensitive.
4. **Fix `sitemap.xml`** — drop the duplicate `/index.html`; add `<lastmod>`;
   later add `xhtml:link` hreflang alternates.
5. **Open Graph + Twitter Cards** — og:type/title/description/url/image/locale,
   `og:locale:alternate`, `twitter:card=summary_large_image`. Use the hero photo.
6. **Structured data (JSON-LD)** — `LodgingBusiness` with `name`, `address`
   (locality Glinado, region South Aegean, country GR, postal 84300),
   `geo`, `telephone`, `email`, `url`, `image[]`, `sameAs[]` (FB/IG),
   `identifier` (licence), `amenityFeature`, `checkinTime`/`checkoutTime`,
   `priceRange`, `numberOfRooms` (4). Plus `BreadcrumbList` per page and
   `WebSite`. `aggregateRating` ONLY if real review data is supplied.
7. **Pre-render gallery tiles** into `gallery.html` (regression fix — the live
   site had 84 `<img>` in raw HTML; we moved them to JS).
8. **Accessibility pass** — skip link, `aria-label`s on icon buttons, visible
   focus rings, `prefers-reduced-motion`, contrast check, `lang` attributes.
9. **Image optimisation** — WebP/AVIF + `srcset`/`sizes`, explicit
   `width`/`height` (fixes CLS), lazy-load below the fold, **preload the hero**
   (currently a CSS background → not preloaded → slow LCP).
10. **Custom 404 page.**
11. **Favicon set** — `favicon.ico`, `apple-touch-icon.png` (180px),
    `site.webmanifest` + theme colour. Currently a single 128px PNG.
12. **`.htaccess` hardening** — HTTPS + non-www→www (or reverse, pick one),
    HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`,
    `X-Frame-Options`/CSP frame-ancestors (allow the Google Maps iframe),
    gzip/brotli, long cache headers for `images/`/css/js.
13. **Search Console + Bing Webmaster** verification meta tags (needs the
    owner to create the properties and hand over the codes).

### Group B — needs owner input / approval (see §3)
14. **English site with real URLs** (`/en/...`) + `hreflang` — the big one.
15. **GDPR: cookie consent banner** + privacy policy + cookie policy.
16. **`forecast-pro` i18n** — needs EN labels (new text).
17. **Fix "Προσανατολισμός"** — untranslated label (text addition).
18. **FAQ section** — new text, but strong SEO win (featured snippets).
19. **Real booking CTA** — link to Booking.com / Airbnb / direct enquiry.
20. **`geo` coordinates** — must be exact; get from the owner or Google Business.
21. **`priceRange`**, `checkinTime`, `checkoutTime`, `numberOfRooms`.

### Group C — nice to have, decide later
22. Google Business Profile consistency (NAP) + reviews widget.
23. Guest reviews section (real, with permission).
24. Availability/booking calendar.
25. Analytics events (tel clicks, WhatsApp clicks, gallery opens).
26. Multi-page OG images (one per page rather than the hero everywhere).

## 3. Decisions required BEFORE coding

### 3.1 GDPR + Analytics — the immediate blocker
Greece is in the EU. GA4 sets cookies and sends IPs to Google. Running it
without consent is a compliance problem, and the site currently has **no banner
and no privacy policy**.

Options:
- **(a) Consent banner + privacy/cookie pages** — correct and safe. Needs new
  legal text (owner or lawyer supplies). Blocks item 1 until pages exist.
- **(b) Consent-mode GA4** — load GA with `denied` defaults, enable after
  consent. Good middle ground; still needs a banner.
- **(c) Server-side / cookieless analytics** (e.g. self-hosted, or GA4 with
  `anonymize_ip` and no ad features) — lighter, fewer legal duties.
- **(d) Restore GA as-is, add banner later** — NOT recommended, that is the
  "we'll fix it later" path the owner explicitly wants to avoid.

**Recommendation: (b) + minimal banner, with privacy/cookie pages.** GA keeps
working for consented users; no compliance gap; nothing to redo later.

### 3.2 English architecture — the biggest decision
Goal: rank in English ("Naxos apartments sea view", "Glinado accommodation").

Chosen approach: **static `/en/` tree with English text baked into the HTML.**
- `https://www.naxosbalcony.gr/` (Greek) and `/en/` (English)
- `hreflang`: `el-GR` → Greek URL, `en` → English URL, `x-default` → Greek
- Self-referencing canonical in each tree
- Language buttons become **links** to the counterpart URL

Constraint handling: the text is *copied*, never reworded — so the
"no text changes" rule holds. This does create duplicate content across the two
trees, which `hreflang` + canonicals resolve.

Implementation without touching `lang-system.js`: add a small `lang-nav.js`
registered in the capture phase that intercepts the `#langEl`/`#langEn` clicks
and navigates to the counterpart URL, calling `stopImmediatePropagation()` so
`lang-system.js` never runs its in-page swap. The library file stays byte-identical.

⚠️ Still needs: **English page titles and meta descriptions** (new text — these
cannot be copied, they do not exist in English today). Owner approval required.

### 3.3 Photo upload workflow
See `AGENTS.md` §8 item 8 — options A/B/C, decision still open.
Recommended: PHP auto-scan + optional `captions.json`, static list as fallback.

## 4. Execution order (no going back)

- **Phase 0 — unblock.** Get decisions §3.1, §3.2, §3.3 + the data in §3 items
  20/21. Without these we would code twice.
- **Phase 1 — technical SEO, Greek only.** Items 2-13. No text changes, no
  approvals needed. Delivers the whole foundation.
- **Phase 2 — GDPR + analytics.** Items 1, 15. Depends on the legal text.
- **Phase 3 — English tree.** Item 14 + 16 + 17. Biggest win, needs EN meta text.
- **Phase 4 — content & conversion.** Items 18, 19, 22-26.
- **Phase 5 — photo workflow.** §3.3.
- **Phase 6 — image performance.** Item 9 (do last so it optimises final assets).

## 5. Verification gates (every push)

- Text identity check (`AGENTS.md` §7) → must print ✅
- `allresp.mjs` — 6 pages × 375/768/1280, zero overflow
- `func.mjs` — gallery/weather/EL-EN intact
- `bgtest2.mjs` — all 4 weather backgrounds
- Rich-results / structured-data validation
- Lighthouse on mobile + desktop for the 6 pages
- Link check (no 404s, no absolute links pointing at the live site)

## 5b. Open: duplicate gallery photos (needs an owner decision)

The gallery shows 83 tiles covering 56 unique images — 26 groups repeat, exactly
as the live site does today. Nothing was changed: deleting or swapping a tile is
a content change, which the hard constraint forbids. Ask the owner whether to
(a) keep as is, or (b) show each image once. If (b), drop the repeated entries
from `gallery-tiles.json` and keep one appearance per image.

## 5c. OPEN: the licence label is the only visible text change

Comparing rendered (post-JS) text across 5 pages, the new build differs from the
live site in exactly one string:

| | live | new |
|---|---|---|
| Greek | `Αρ. Γνωστοποίησης: …` | `Αριθμός Γνωστοποίησης: …` |
| English | `License Number: …` | `Notification Number: …` |

This was a deliberate legal correction (ν. 4442/2016 art. 40 as amended by
ν. 5073/2023), but it violates the owner's zero-text-change rule. **Ask first.**
Everything else that looks new is a feature absent from the live site (skip link,
cookie banner, privacy/cookies links, booking/WhatsApp/call/email buttons,
lightbox captions), not a rewrite of existing copy.

## 6. Open questions for the owner

1. **Duplicate photos:** see §5b for the measured numbers (26 groups, 56 unique
   images of 83 tiles; room4↔room6 share 17, room3↔room5 share 5, each room's
   last tile is also in `exterior`; Room 4 lists `room4_12.jpg` twice). All 26
   groups already exist on the live site. Keep as-is, or show each image once?
2. **`geo` coordinates** — exact latitude/longitude of the property?
3. **`priceRange`** — e.g. "€70–€150" per night in high season?
4. **Check-in / check-out times?**
5. **Number of rooms = 4?** (Room 3, 4, 5, 6 → 4 units.)
6. **Booking:** link to Booking.com/Airbnb, or a direct enquiry form?
7. **Privacy/cookie policy text** — do you have it, or should I draft a
   template for your lawyer to approve?
8. **English page titles/descriptions** — approve me drafting them?
9. **FAQ section** — approve adding one? (New text, strong SEO benefit.)
10. **Photo workflow** — option A, B or C (`AGENTS.md` §8)?
11. **New GitHub token** for the next push.
12. **Search Console / Bing** — do you have access to create the properties?
