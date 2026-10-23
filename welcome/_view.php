<?php
/* ============================================================================
 * Naxos Balcony Apartments — guest welcome page
 * ============================================================================
 * Rendered by the small stub files in this folder:
 *
 *     /welcome/papadopoulos-a7f3.php  ->  $GUEST_SLUG, $GUEST_CODE, require _view.php
 *
 * The stubs exist because the site runs on nginx, which does not read .htaccess,
 * so we cannot rely on a rewrite rule to route /welcome/<name> to a handler.
 * A real file per guest is served directly by nginx and needs nothing from the
 * host. All the logic lives here, so changing the page never means rewriting
 * the stubs.
 *
 * ⚠️  THIS PAGE IS REACHED BY ANYONE WHO HAS THE LINK, so anything printed here is
 *     only as private as the link. The Wi-Fi password and the door code ARE printed
 *     here at the owner's request. Treat both as semi-public: change them when a stay
 *     ends, and keep the stub filename and code hard to guess (see guest_code()).
 *     Never print anything here that would be costly to leak to a forwarded link.
 * ========================================================================== */

declare(strict_types=1);

$GUEST_SLUG = $GUEST_SLUG ?? '';
$GUEST_CODE = $GUEST_CODE ?? '';

require dirname(__DIR__) . '/admin/_lib.php';

/* ------------------------------------------------------------------- lookup */

$guest = guests_find(guests_load(), (string)$GUEST_SLUG);

// Ο κωδικός στο URL πρέπει να ταιριάζει: αν ο διαχειριστής ζήτησε νέο κωδικό,
// το παλιό link σταματά να δουλεύει.
if ($guest !== null && (string)($guest['code'] ?? '') !== (string)$GUEST_CODE) $guest = null;

$status = $guest !== null ? guest_status($guest) : 'missing';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');

$lang = ($guest['lang'] ?? 'el') === 'en' ? 'en' : 'el';

/* ------------------------------------------------------------------- strings
 *
 * ⚠️ ΠΡΟΣΩΡΙΝΑ ΚΕΙΜΕΝΑ. Περιμένουν τα δικά σου, στα ελληνικά και στα αγγλικά. */

$T = [
    'el' => [
        'title'       => 'Το δωμάτιό σας — Naxos Balcony Apartments',
        'welcome'     => 'Καλώς ήρθατε!',
        'yourRoom'    => 'Το δωμάτιό σας είναι το',
        'wifiLabel'   => 'Κωδικός WiFi',
        'doorLabel'   => 'Κωδικός πρόσβασης',
        'stay'        => 'Η διαμονή σας',
        'checkin'     => 'Άφιξη από',
        'checkout'    => 'Αναχώρηση έως',
        'address'     => 'Διεύθυνση',
        'addressVal'  => 'Γλινάδο, Νάξος 84300',
        'map'         => 'Άνοιγμα στους χάρτες',
        'needHelp'    => 'Χρειάζεστε κάτι;',
        'whatsapp'    => 'WhatsApp',
        'call'        => 'Κλήση',
        'email'       => 'Email',
        'arrivalNote' => 'Θα λάβετε τις οδηγίες πρόσβασης και τον κωδικό για την κάρτα-κλειδί σας λίγο πριν την άφιξή σας.',
        'expiredT'    => 'Ο σύνδεσμος έληξε',
        'expiredB'    => 'Ο σύνδεσμος για τη διαμονή σας δεν είναι πλέον ενεργός. Αν χρειάζεστε τις πληροφορίες, επικοινωνήστε μαζί μας και θα σας τις στείλουμε.',
        'scheduledT'  => 'Ο σύνδεσμος δεν είναι ακόμη ενεργός',
        'scheduledB'  => 'Ο σύνδεσμος για τη διαμονή σας θα ενεργοποιηθεί στις',
        'missingT'    => 'Ο σύνδεσμος δεν βρέθηκε',
        'missingB'    => 'Ο σύνδεσμος που ανοίξατε δεν αντιστοιχεί σε κάποια κράτηση. Ελέγξτε το μήνυμα που λάβατε, ή επικοινωνήστε μαζί μας.',
        'footer'      => 'Naxos Balcony Apartments — Γλινάδο, Νάξος',
        'langOther'   => 'English',
        'goSite'      => 'Πηγαίνετε στο site',
        'draft'       => 'ΠΡΟΧΕΙΡΟ ΚΕΙΜΕΝΟ',
    ],
    'en' => [
        'title'       => 'Your room — Naxos Balcony Apartments',
        'welcome'     => 'Welcome!',
        'yourRoom'    => 'Your room is',
        'wifiLabel'   => 'Wi-Fi password',
        'doorLabel'   => 'Access code',
        'stay'        => 'Your stay',
        'checkin'     => 'Check-in from',
        'checkout'    => 'Check-out by',
        'address'     => 'Address',
        'addressVal'  => 'Glinado, Naxos 84300, Greece',
        'map'         => 'Open in maps',
        'needHelp'    => 'Need anything?',
        'whatsapp'    => 'WhatsApp',
        'call'        => 'Call',
        'email'       => 'Email',
        'arrivalNote' => 'You will receive the access instructions and the key-card code shortly before your arrival.',
        'expiredT'    => 'This link has expired',
        'expiredB'    => 'The link for your stay is no longer active. If you still need the information, get in touch and we will send it to you.',
        'scheduledT'  => 'This link is not active yet',
        'scheduledB'  => 'The link for your stay becomes active on',
        'missingT'    => 'Link not found',
        'missingB'    => 'The link you opened does not match a booking. Check the message you received, or get in touch with us.',
        'footer'      => 'Naxos Balcony Apartments — Glinado, Naxos',
        'langOther'   => 'Ελληνικά',
        'goSite'      => 'Go to the website',
        'draft'       => 'DRAFT TEXT',
    ],
];

// Η γλώσσα ορίζεται από τη ρύθμιση του επισκέπτη. Το ?lang= είναι προαιρετικό.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'en'], true)) {
    $lang = (string)$_GET['lang'];
}
$t = $T[$lang];

/* --------------------------------------------------------- stay formatting */

function fmt_date(string $iso, string $lang): string {
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$d) return $iso;
    if ($lang === 'en') return $d->format('j F Y');
    $m = [1=>'Ιανουαρίου','Φεβρουαρίου','Μαρτίου','Απριλίου','Μαΐου','Ιουνίου',
          'Ιουλίου','Αυγούστου','Σεπτεμβρίου','Οκτωβρίου','Νοεμβρίου','Δεκεμβρίου'];
    return $d->format('j') . ' ' . $m[(int)$d->format('n')] . ' ' . $d->format('Y');
}

$roomLabel = $guest !== null ? (ROOMS_G[$guest['room'] ?? ''][$lang] ?? '') : '';

// Στοιχεία πρόσβασης. Ο κωδικός WiFi έχει προεπιλογή· ο κωδικός εισόδου
// εμφανίζεται μόνο αν τον έχει δώσει ο διαχειριστής.
$wifiPass = $guest !== null ? guest_wifi($guest) : '';
$doorCode = $guest !== null
    ? trim((string)($guest['doorCode'] ?? '')) : '';

// Φωτογραφία του δωματίου από τη λίστα της γκαλερί, αν υπάρχει.
$photo = null;
if ($guest !== null) {
    $tilesFile = dirname(__DIR__) . '/gallery-tiles.json';
    if (is_file($tilesFile)) {
        $tiles = json_decode((string)file_get_contents($tilesFile), true);
        if (is_array($tiles)) {
            foreach ($tiles as $tl) {
                if (($tl['cat'] ?? '') !== ($guest['room'] ?? '')) continue;
                $rel = ltrim((string)($tl['thumb'] ?? ''), '/');
                if ($rel !== '' && is_file(dirname(__DIR__) . '/' . $rel)) { $photo = $rel; break; }
            }
        }
    }
}

$otherLang = ($guest !== null && $status === 'active')
    ? '?lang=' . ($lang === 'el' ? 'en' : 'el')
    : null;
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($t['title']) ?></title>
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="../favicon-32.png">
<link rel="apple-touch-icon" href="../apple-touch-icon.png">
<link rel="stylesheet" href="../fonts.css">
<link rel="stylesheet" href="../styles.css">
<style>
  /* Σελίδα επισκέπτη — ίδια χρώματα, γραμματοσειρές και ακμές με το site. */
  .w-hero{
    background:linear-gradient(180deg,rgba(12,24,34,.92),rgba(12,24,34,.74)),
               url("../naxos-balcony-apartments-glinado-view-1280.jpg") center/cover no-repeat;
    color:#fff;padding:40px 0 34px;position:relative;
  }
  .w-hero .logo img{height:42px;width:auto;display:block}
  .w-hero h1{font-size:clamp(1.6rem,5vw,2.25rem);margin:20px 0 4px;line-height:1.2}
  .w-sub{opacity:.9;font-size:.98rem;margin:0}
  .w-wrap{max-width:var(--maxw);margin:0 auto;padding:0 20px}
  .w-main{max-width:720px;margin:0 auto;padding:26px 20px 56px}
  .w-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);
          padding:24px;box-shadow:var(--shadow);margin:0 0 18px}
  .w-room{display:inline-block;font-weight:700;color:#fff;background:var(--accent-dark);
          padding:8px 20px;border-radius:999px;font-size:1.3rem;margin-top:4px}
  .w-photo{width:100%;height:auto;border-radius:var(--radius);display:block;margin:0 0 18px}
  .w-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}
  .w-item .k{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700}
  .w-item .v{font-size:1.03rem;margin-top:3px;color:var(--ink)}
  .w-access-k{margin:16px 0 6px;color:var(--muted);font-size:.94rem}
  .w-code{font-size:1.05rem;letter-spacing:.08em}
  .w-note{background:var(--soft);border-left:4px solid var(--accent-2);border-radius:10px;
          padding:14px 16px;font-size:.94rem;margin:0 0 18px}
  .w-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:4px}
  .w-actions a{flex:1 1 140px;min-height:48px;display:flex;align-items:center;justify-content:center;
               gap:8px;text-decoration:none;font-weight:600;border-radius:12px;
               border:1px solid var(--line);color:var(--ink);background:#fff}
  .w-actions a.primary{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
  .w-state{text-align:center;padding:32px 22px}
  .w-state .ico{font-size:2.3rem;line-height:1;margin-bottom:10px}
  .w-state h2{margin:0 0 10px;font-size:1.25rem}
  .w-state p{color:var(--muted);margin:0 0 20px}
  .w-foot{text-align:center;color:var(--muted);font-size:.85rem;padding:0 20px 36px}
  .w-lang{position:absolute;top:16px;right:20px;color:#fff;font-size:.88rem;text-decoration:none;
          border:1px solid rgba(255,255,255,.55);padding:7px 14px;border-radius:999px}
  /* Πρόχειρο σήμα: φεύγει μόλις μπουν τα πραγματικά κείμενα. */
  .w-draft{background:#fff8e6;border:1px dashed #d9a94a;color:#7a5a12;border-radius:10px;
           padding:10px 14px;font-size:.82rem;margin:0 0 16px}
  @media(max-width:520px){
    .w-card{padding:19px}
    .w-lang{top:12px;right:14px}
    .w-hero .logo img{height:36px}
  }
</style>
</head>
<body>

<header class="w-hero">
  <div class="w-wrap">
    <a class="logo" href="../<?= $lang === 'en' ? 'en/' : '' ?>index.html">
      <picture>
        <source type="image/webp" srcset="../logo-naxos-balcony-240.webp 240w, ../logo-naxos-balcony-480.webp 480w" sizes="140px">
        <img src="../logo-naxos-balcony-240.png" width="240" height="128" alt="Naxos Balcony Apartments logo">
      </picture>
    </a>
    <?php if ($otherLang !== null): ?>
      <a class="w-lang" href="<?= h($otherLang) ?>" hreflang="<?= $lang === 'el' ? 'en' : 'el' ?>"><?= h($t['langOther']) ?></a>
    <?php endif; ?>
    <h1><?= h($t['welcome']) ?></h1>
    <p class="w-sub"><?= h($t['footer']) ?></p>
  </div>
</header>

<main class="w-main">

<?php /* Υπενθύμιση για τον ιδιοκτήτη, όχι για τον επισκέπτη. */ ?>
<?php if (is_admin()): ?>
<div class="w-draft">
  <strong><?= h($t['draft']) ?></strong> —
  <?= $lang === 'en'
      ? 'the wording below is a placeholder and will be replaced with your own text.'
      : 'τα κείμενα παρακάτω είναι προσωρινά και θα αντικατασταθούν με τα δικά σου.' ?>
</div>
<?php endif; ?>

<?php if ($status === 'active' && $guest !== null): ?>

  <?php if ($photo !== null): ?>
    <img class="w-photo" src="../<?= h($photo) ?>" alt="<?= h($roomLabel) ?>" width="700" height="467">
  <?php endif; ?>

  <div class="w-card">
    <p style="margin:0 0 6px;color:var(--muted);font-size:.94rem"><?= h($t['yourRoom']) ?></p>
    <span class="w-room"><?= h($roomLabel) ?></span>

    <?php if ($wifiPass !== ''): ?>
      <p class="w-access-k"><?= h($t['wifiLabel']) ?></p>
      <span class="w-room w-code"><?= h($wifiPass) ?></span>
    <?php endif; ?>

    <?php if ($doorCode !== ''): ?>
      <p class="w-access-k"><?= h($t['doorLabel']) ?></p>
      <span class="w-room w-code"><?= h($doorCode) ?></span>
    <?php endif; ?>
  </div>

  <div class="w-card">
    <h2 style="margin:0 0 16px;font-size:1.08rem"><?= h($t['stay']) ?></h2>
    <div class="w-grid">
      <div class="w-item">
        <div class="k"><?= h($t['checkin']) ?></div>
        <div class="v"><?= h(fmt_date((string)($guest['stayFrom'] ?? ''), $lang)) ?> — 15:00</div>
      </div>
      <div class="w-item">
        <div class="k"><?= h($t['checkout']) ?></div>
        <div class="v"><?= h(fmt_date((string)($guest['stayTo'] ?? ''), $lang)) ?> — 11:00</div>
      </div>
      <div class="w-item">
        <div class="k"><?= h($t['address']) ?></div>
        <div class="v">
          <?= h($t['addressVal']) ?><br>
          <a href="https://www.google.com/maps/search/?api=1&amp;query=Naxos+Balcony+Apartments+Glinado+Naxos"
             target="_blank" rel="noopener" style="color:var(--accent-2)"><?= h($t['map']) ?></a>
        </div>
      </div>
    </div>
  </div>

  <?php /* Όταν ο κωδικός εισόδου είναι ήδη εδώ, το «θα λάβετε τον κωδικό πριν την
           άφιξη» θα ήταν αναληθές — το κρύβουμε χωρίς να αλλάξουμε το κείμενο. */ ?>
  <?php if ($doorCode === ''): ?>
    <div class="w-note"><?= h($t['arrivalNote']) ?></div>
  <?php endif; ?>

  <div class="w-card">
    <h2 style="margin:0 0 14px;font-size:1.08rem"><?= h($t['needHelp']) ?></h2>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
      <a href="mailto:naxosbalcony@gmail.com"><span aria-hidden="true">✉️</span> <?= h($t['email']) ?></a>
    </div>
  </div>

<?php elseif ($status === 'scheduled' && $guest !== null): ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">⏳</div>
    <h2><?= h($t['scheduledT']) ?></h2>
    <p><?= h($t['scheduledB']) ?> <strong><?= h(fmt_date((string)($guest['activeFrom'] ?? ''), $lang)) ?></strong>.</p>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
    </div>
  </div>

<?php elseif ($status === 'expired' || $status === 'disabled'): ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">🔒</div>
    <h2><?= h($t['expiredT']) ?></h2>
    <p><?= h($t['expiredB']) ?></p>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="mailto:naxosbalcony@gmail.com"><span aria-hidden="true">✉️</span> <?= h($t['email']) ?></a>
    </div>
  </div>

<?php else: ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">❓</div>
    <h2><?= h($t['missingT']) ?></h2>
    <p><?= h($t['missingB']) ?></p>
    <div class="w-actions">
      <a class="primary" href="../<?= $lang === 'en' ? 'en/' : '' ?>index.html"><?= h($t['goSite']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
    </div>
  </div>

<?php endif; ?>

</main>

<p class="w-foot"><?= h($t['footer']) ?></p>

</body>
</html>
