/* Κοινό JS: mobile menu, gallery (render + φίλτρα + lightbox), καιρός. Καμία αλλαγή σε κείμενα. */

/* ---------- Mobile menu + sticky navbar ---------- */
(function () {
  const toggle = document.getElementById('navToggle');
  const nav = document.getElementById('nav');
  const navbar = document.getElementById('navbar');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      const open = nav.classList.toggle('open');
      toggle.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', String(open));
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        nav.classList.remove('open');
        toggle.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  if (navbar) {
    const onScroll = function () {
      navbar.classList.toggle('scrolled', window.scrollY > 12);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();

/* ---------- Gallery ---------- */
(function () {
  const grid = document.getElementById('grid');
  if (!grid) return;

  const tiles = window.GALLERY_TILES || [];
  const info = document.getElementById('room-info');

  // Τα κείμενα των φίλτρων. Στις αγγλικές σελίδες έρχονται έτοιμα από το HTML
  // (data-room-info), ώστε να μη χρειάζεται το lang-system.js.
  const details = JSON.parse(grid.getAttribute('data-room-info') || 'null') || {
    all: '',
    room3: 'Room 3: Αυτόνομο δωμάτιο 26 τ.μ. με πλήρη κουζίνα.',
    room4: 'Room 4: Ευρύχωρο διαμέρισμα 26 τ.μ. με πλήρη κουζίνα.',
    room5: 'Room 5: Studio 23 τ.μ. με πλήρη κουζίνα και την καλύτερη θέα στο Αιγαίο.',
    room6: 'Room 6: Αυτόνομο διαμέρισμα 26 τ.μ. με πλήρη κουζίνα και πανοραμική θέα.',
    exterior: 'Εξωτερικοί χώροι του συγκροτήματος.',
    sunset: 'Το μαγευτικό ηλιοβασίλεμα από το μπαλκόνι μας.'
  };

  // Τα tiles είναι προ-σχεδιασμένα στο HTML (τα βλέπει η Google χωρίς JS).
  // Αν για οποιονδήποτε λόγο λείπει το HTML, τα φτιάχνουμε από το gallery-data.js.
  if (!grid.querySelector('.tile')) {
    const frag = document.createDocumentFragment();
    tiles.forEach(function (t) {
      const div = document.createElement('div');
      div.className = 'tile';
      div.dataset.cat = t.cat;
      const a = document.createElement('a');
      a.href = t.src;
      const img = document.createElement('img');
      img.loading = 'lazy';
      img.decoding = 'async';
      // Prefer the 700px thumbnail in the grid; fall back to the original.
      img.src = t.thumb || t.src;
      img.alt = t.alt;
      a.appendChild(img);
      div.appendChild(a);
      frag.appendChild(div);
    });
    grid.appendChild(frag);
  }

  const allTiles = Array.from(grid.querySelectorAll('.tile'));
  const tabs = document.getElementById('tabs');

  function applyFilter(filter, btn) {
    if (info) info.textContent = details[filter] || '';
    if (tabs) {
      tabs.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
      if (btn) btn.classList.add('active');
    }
    allTiles.forEach(function (t) {
      t.style.display = (filter === 'all' || t.dataset.cat === filter) ? '' : 'none';
    });
  }

  if (tabs) {
    tabs.addEventListener('click', function (e) {
      const btn = e.target.closest('.tab-btn');
      if (!btn) return;
      applyFilter(btn.getAttribute('data-filter'), btn);
    });
  }
  if (info) info.textContent = details.all;

  // The room cards on the home page link here as gallery.html?room=room3#tabs.
  // Only accept a key that the tabs already know, so a stray query string can
  // never produce an empty gallery.
  const wanted = new URLSearchParams(location.search).get('room');
  if (wanted && tabs && Object.prototype.hasOwnProperty.call(details, wanted)) {
    const btn = tabs.querySelector('.tab-btn[data-filter="' + wanted + '"]');
    if (btn) applyFilter(wanted, btn);
  }

  // Lightbox
  const lb = document.getElementById('lightbox');
  const lbImg = document.getElementById('lbImg');
  if (!lb) return;
  let current = 0;

  function visible() {
    return allTiles.filter(function (t) { return t.style.display !== 'none'; });
  }
  function show(i) {
    const list = visible();
    if (!list.length) return;
    current = (i + list.length) % list.length;
    const tile = list[current];
    const img = tile.querySelector('img');
    // The tile shows a 700px thumbnail; the anchor holds the full-size file.
    const link = tile.querySelector('a');
    lbImg.src = (link && link.getAttribute('href')) || img.src;
    lbImg.alt = img.alt;
  }
  function open(tile) {
    const list = visible();
    current = list.indexOf(tile);
    show(current);
    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  grid.addEventListener('click', function (e) {
    const a = e.target.closest('a');
    if (!a) return;
    e.preventDefault();
    open(a.closest('.tile'));
  });
  document.getElementById('lbClose').addEventListener('click', close);
  document.getElementById('lbPrev').addEventListener('click', function () { show(current - 1); });
  document.getElementById('lbNext').addEventListener('click', function () { show(current + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
})();

/* ---------- Weather (live δεδομένα από τον σταθμό του καταλύματος) ---------- */
(function () {
  const tempEl = document.getElementById('w_temp');
  if (!tempEl) return;

  const setTxt = function (id, v) { const el = document.getElementById(id); if (el) el.textContent = v; };
  const setI18n = function (id, key) { const el = document.getElementById(id); if (el) el.setAttribute('data-i18n', key); };
  const dot = document.getElementById('w_dot');

  function kmhToBeaufort(kmh) {
    const s = parseFloat(kmh);
    if (isNaN(s) || s < 1) return 0;
    if (s < 6) return 1;
    if (s < 12) return 2;
    if (s < 20) return 3;
    if (s < 29) return 4;
    if (s < 39) return 5;
    if (s < 50) return 6;
    if (s < 62) return 7;
    if (s < 75) return 8;
    if (s < 89) return 9;
    if (s < 103) return 10;
    return 11;
  }

  // Στο production το api είναι στο ίδιο domain → σχετικό path.
  // Στο τοπικό preview (localhost) δεν υπάρχει το PHP api, οπότε δείχνουμε δείγμα.
  const isPreview = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
  const DEMO = {
    temperature: 26.6, humidity: 38, wind_speed: 6.1, wind_dir: 'ΝΔ', wind_deg: '223',
    pressure: 1016.8, uv_index: 2, timestamp: new Date().toISOString()
  };
  const request = isPreview
    ? Promise.resolve(DEMO)
    : fetch('api/weather-latest.php', { cache: 'no-store' }).then(function (r) {
        if (!r.ok) throw new Error('no data');
        return r.json();
      });

  request
    .then(function (d) {
      const tempVal = parseFloat(d.temperature);
      tempEl.textContent = Math.round(tempVal);
      tempEl.className = 'value ' + (tempVal <= 16 ? 'temp-cold' : (tempVal >= 28 ? 'temp-hot' : 'temp-normal'));

      setTxt('w_hum', d.humidity);

      const speed = parseFloat(d.wind_speed);
      const bft = kmhToBeaufort(speed);
      setTxt('w_wind', !isNaN(speed) ? speed + ' (' + bft + ' Bf)' : '—');

      const dirContainer = document.getElementById('w_dir');
      if (dirContainer) {
        dirContainer.textContent = d.wind_dir || '—';
        let arrow = document.getElementById('wind_arrow');
        if (!arrow) {
          arrow = document.createElement('span');
          arrow.id = 'wind_arrow';
          arrow.innerHTML = '↑';
          dirContainer.after(arrow);
        }
        if (d.wind_deg !== undefined) arrow.style.transform = 'rotate(' + d.wind_deg + 'deg)';
      }

      setTxt('w_press', d.pressure);
      setTxt('w_uv', d.uv_index);
      setTxt('w_updated', d.timestamp ? new Date(d.timestamp).toLocaleString('el-GR') : '—');

      setI18n('w_status', 'weatherStatusOn');
      if (dot) dot.classList.add('ok');
    })
    .catch(function () {
      setI18n('w_status', 'weatherStatusOff');
      if (dot) dot.classList.add('bad');
    });
})();
