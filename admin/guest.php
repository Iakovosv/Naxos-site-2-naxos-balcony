<?php
/* ============================================================================
 *  Στοιχεία επισκέπτη — επεξεργασία από τον διαχειριστή
 *
 *  Εδώ μπαίνουν τα στοιχεία για την απόδειξη (ΑΦΜ, διεύθυνση, ταυτότητα,
 *  τηλέφωνο) και εδώ αλλάζει ο διαχειριστής την ημέρα καθαριότητας.
 *  Οι διανυκτερεύσεις βγαίνουν αυτόματα από τις ημερομηνίες.
 *
 *  Deploy: upload to  <public_html>/admin/guest.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('admin');

$id = (string)($_GET['id'] ?? '');
$guests = guests_load();
$i = guests_index($guests, $id);

if ($i === null) {
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="el"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Δεν βρέθηκε — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>body{margin:0;background:#f2f5f8;color:#0c1822;
  font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  display:grid;place-items:center;min-height:100vh;text-align:center}
a{color:#a85c30}</style></head>
<body><div><h1>Δεν βρέθηκε</h1>
<p>Η εγγραφή δεν υπάρχει — μπορεί να διαγράφηκε.</p>
<p><a href="guests.php">← Πίσω στα links</a></p></div></body></html><?php
    exit;
}

$g = $guests[$i];
$nights = guest_nights($g);
$auto   = default_cleaning_days($nights);
$dates  = cleaning_dates($g);
$hasCustom = array_key_exists('cleaningDays', $g) && is_array($g['cleaningDays']);

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$store = store_active();
$fname = guest_file_name($g);
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Στοιχεία <?= h((string)($g['surname'] ?? '')) ?> — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--soft:#f6f8fa;
      --accent:#c2703f;--accent-dark:#a85c30;--good:#1d7a4d;--leave:#b4341f;
      --clean:#7a5a12;--cleanbg:#fff4d6}
*{box-sizing:border-box}
body{margin:0;background:#f2f5f8;color:var(--ink);
     font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:820px;margin:0 auto;padding:24px 18px 64px}
.top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
     flex-wrap:wrap;margin:0 0 18px}
h1{margin:0 0 4px;font-size:1.45rem}
h2{margin:0 0 14px;font-size:1.08rem}
.hint{color:var(--muted);font-size:.85rem;margin:0}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;
      background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;
      min-height:44px;line-height:22px}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;
      margin:0 0 18px;box-shadow:0 1px 3px rgba(12,24,34,.05)}
input[type=text],input[type=tel],input[type=email],input[type=number],input[type=date],select{
  padding:10px 12px;border:1px solid var(--line);border-radius:10px;font-size:.95rem;
  min-height:44px;font-family:inherit;width:100%;background:#fff;color:var(--ink)}
button{padding:10px 18px;border:1px solid var(--line);border-radius:10px;background:#fff;
       color:var(--ink);font-weight:600;font-size:.9rem;min-height:44px;cursor:pointer;
       font-family:inherit}
button.pri{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
button.danger{color:var(--leave);border-color:#e8c4bc}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>div{flex:1 1 200px}
label{display:block;font-weight:600;font-size:.85rem;margin:0 0 6px}
.flash{border-left:4px solid var(--good);background:#fff;border:1px solid var(--line);
       border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:.9rem}
.kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:0 0 16px}
.kpi div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px}
.kpi .n{font-size:1.5rem;font-weight:800;line-height:1.1}
.kpi .l{font-size:.78rem;color:var(--muted);margin-top:2px}
.cleanbox{background:var(--cleanbg);border:1px solid #e8d5a8;border-radius:12px;padding:14px}
.cleanbox .d{font-weight:700;color:var(--clean)}
code.lnk{background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:6px 10px;
         font-size:.86rem;font-family:ui-monospace,Menlo,Consolas,monospace;word-break:break-all}
@media(max-width:560px){.wrap{padding:18px 12px 48px}.row>div{flex:1 1 100%}}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1><?= h((string)($g['surname'] ?? '')) ?></h1>
      <p class="hint">
        <?= h(ROOMS_G[(string)($g['room'] ?? '')]['el'] ?? '') ?>
        · <?= h(date('d/m/Y', strtotime((string)$g['stayFrom']))) ?>
        → <?= h(date('d/m/Y', strtotime((string)$g['stayTo']))) ?>
        · <span class="badge"><?= h(status_label(guest_status($g))) ?></span>
      </p>
    </div>
    <div class="acts">
      <a class="btn" href="guests.php">← Links</a>
      <a class="btn" href="calendar.php">Ημερολόγιο</a>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash" style="border-left-color:<?= $kind === 'err' ? 'var(--leave)' : 'var(--good)' ?>">
      <?= h($msg) ?>
    </div>
  <?php endforeach; ?>

  <div class="kpi">
    <div><div class="n"><?= $nights ?></div><div class="l">Διανυκτερεύσεις</div></div>
    <div><div class="n"><?= (int)($g['adults'] ?? 1) ?></div><div class="l">Άτομα</div></div>
    <div><div class="n" style="font-size:1.05rem;padding-top:5px"><?= h(source_label((string)($g['source'] ?? 'direct'))) ?></div><div class="l">Κράτηση από</div></div>
    <div><div class="n"><?= count($dates) ?></div><div class="l">Καθαριότητες</div></div>
  </div>

  <form method="post" action="guests.php">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h((string)$g['id']) ?>">

    <div class="card">
      <h2>Διαμονή</h2>
      <div class="row">
        <div style="flex:2 1 220px">
          <label for="surname">Επίθετο</label>
          <input id="surname" type="text" name="surname" maxlength="60"
                 value="<?= h((string)($g['surname'] ?? '')) ?>" required>
        </div>
        <div>
          <label for="room">Δωμάτιο</label>
          <select id="room" name="room">
            <?php foreach (ROOMS_G as $k => $r): ?>
              <option value="<?= h($k) ?>"<?= $k === ($g['room'] ?? '') ? ' selected' : '' ?>>
                <?= h($r['el']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="lang">Γλώσσα σελίδας</label>
          <select id="lang" name="lang">
            <option value="el"<?= ($g['lang'] ?? 'el') === 'el' ? ' selected' : '' ?>>Ελληνικά</option>
            <option value="en"<?= ($g['lang'] ?? 'el') === 'en' ? ' selected' : '' ?>>English</option>
          </select>
        </div>
      </div>
      <div class="row" style="margin-top:12px">
        <div>
          <label for="stayFrom">Άφιξη</label>
          <input id="stayFrom" type="date" name="stayFrom" required value="<?= h((string)$g['stayFrom']) ?>">
        </div>
        <div>
          <label for="stayTo">Αναχώρηση</label>
          <input id="stayTo" type="date" name="stayTo" required value="<?= h((string)$g['stayTo']) ?>">
        </div>
        <div>
          <label for="source">Κράτηση από</label>
          <select id="source" name="source">
            <?php foreach (BOOKING_SOURCES as $k => $lbl): ?>
              <option value="<?= h($k) ?>"<?= $k === ($g['source'] ?? 'direct') ? ' selected' : '' ?>>
                <?= h($lbl) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="adults">Άτομα</label>
          <input id="adults" type="number" name="adults" min="1" max="12"
                 value="<?= (int)($g['adults'] ?? 1) ?>">
        </div>
      </div>
      <p class="hint" style="margin:12px 0 0">
        Οι διανυκτερεύσεις υπολογίζονται μόλις αλλάξεις τις ημερομηνίες — δεν τις γράφεις εσύ.
      </p>
    </div>

    <div class="card">
      <h2>Στοιχεία για απόδειξη</h2>
      <p class="hint" style="margin:0 0 14px">
        Τα βλέπεις εσύ και ο λογιστής. Οι καθαρίστριες δεν τα βλέπουν καθόλου.
      </p>
      <div class="row">
        <div style="flex:2 1 260px">
          <label for="address">Διεύθυνση</label>
          <input id="address" type="text" name="address" maxlength="120"
                 value="<?= h((string)($g['address'] ?? '')) ?>">
        </div>
        <div>
          <label for="vat">ΑΦΜ</label>
          <input id="vat" type="text" name="vat" maxlength="9" inputmode="numeric"
                 pattern="\d{9}" placeholder="9 ψηφία"
                 value="<?= h((string)($g['vat'] ?? '')) ?>">
        </div>
      </div>
      <div class="row" style="margin-top:12px">
        <div>
          <label for="idDoc">Ταυτότητα / Διαβατήριο</label>
          <input id="idDoc" type="text" name="idDoc" maxlength="30"
                 value="<?= h((string)($g['idDoc'] ?? '')) ?>">
        </div>
        <div>
          <label for="phone">Τηλέφωνο</label>
          <input id="phone" type="tel" name="phone" maxlength="30"
                 value="<?= h((string)($g['phone'] ?? '')) ?>">
        </div>
        <div>
          <label for="email">Email</label>
          <input id="email" type="email" name="email" maxlength="80"
                 value="<?= h((string)($g['email'] ?? '')) ?>">
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Καθαριότητα</h2>
      <div class="cleanbox">
        <p style="margin:0 0 10px;font-size:.9rem">
          Με βάση τις <?= $nights ?> διανυκτερεύσεις, το πρόγραμμα βγαίνει αυτόματα:
          <span class="d">
            <?= $auto ? implode(', ', array_map(fn($d) => $d . 'η ημέρα', $auto)) : 'καμία καθαριότητα (σύντομη διαμονή)' ?>
          </span>
        </p>
        <?php if ($dates): ?>
          <p style="margin:0 0 10px;font-size:.9rem">
            Δηλαδή στις:
            <strong><?= h(implode(', ', array_map(fn($c) => date('d/m/Y', strtotime($c)), $dates))) ?></strong>
          </p>
        <?php endif; ?>
        <div>
          <label for="cleaningDays">Αλλαγή ημερών (μόνο εσύ)</label>
          <input id="cleaningDays" type="text" name="cleaningDays" maxlength="40"
                 placeholder="π.χ. 2 ή 2,5  —  κενό = αυτόματο"
                 value="<?= h($hasCustom ? implode(',', array_map('intval', $g['cleaningDays'])) : '') ?>">
          <p class="hint" style="margin:8px 0 0">
            Γράψε ποια <strong>ημέρα της διαμονής</strong> θέλεις καθάρισμα (1 = ημέρα άφιξης).
            Άφησέ το κενό για να αποφασίζει το σύστημα: 3-4 διανυκτερεύσεις → 2η ημέρα,
            και μετά κάθε 3 ημέρες.
          </p>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Πρόσβαση επισκέπτη</h2>
      <p class="hint" style="margin:0 0 14px">
        Φαίνονται στη σελίδα που ανοίγει ο επισκέπτης με το link του.
      </p>
      <div class="row">
        <div>
          <label for="wifi">Κωδικός WiFi</label>
          <input id="wifi" type="text" name="wifi" maxlength="40" autocomplete="off"
                 placeholder="<?= h(wifi_default()) ?> (ο προεπιλεγμένος)"
                         value="<?= h((string)($g['wifi'] ?? '')) ?>">
        </div>
        <div>
          <label for="doorCode">Κωδικός εισόδου διαμερίσματος</label>
          <input id="doorCode" type="text" name="doorCode" maxlength="20" autocomplete="off"
                 placeholder="π.χ. 4821" value="<?= h((string)($g['doorCode'] ?? '')) ?>">
        </div>
      </div>
      <div class="warn" style="margin-top:14px">
        <strong>⚠️ Τα βλέπει όποιος έχει το link.</strong>
        Αν το link προωθηθεί, τα βλέπει κι άλλος. Άλλαξέ τα μόλις τελειώσει η διαμονή,
        ή κράτα τον κωδικό εισόδου κενό και στείλ' τον με μήνυμα.
      </div>
    </div>

    <div class="card">
      <h2>Σημειώσεις</h2>
      <input type="text" name="notes" maxlength="200" placeholder="π.χ. ζήτησε κούνια"
             value="<?= h((string)($g['notes'] ?? '')) ?>">
    </div>

    <div class="acts" style="margin-bottom:18px">
      <button class="pri" type="submit">Αποθήκευση</button>
      <a class="btn" href="guests.php">Άκυρο</a>
    </div>
  </form>

  <div class="card">
    <h2>Το link του επισκέπτη</h2>
    <p style="margin:0 0 10px">
      <code class="lnk">/welcome/<?= h($fname) ?>.php</code>
    </p>
    <div class="acts">
      <button type="button" onclick="copyL(this,'<?= h(guest_link($g, 'https://www.naxosbalcony.gr')) ?>')">
        Αντιγραφή
      </button>
      <a class="btn" href="../welcome/<?= h($fname) ?>.php" target="_blank" rel="noopener">Άνοιγμα</a>
    </div>
    <p class="hint" style="margin:12px 0 0">
      Αλλαγή κωδικού ή διαγραφή, από τη σελίδα <a href="guests.php">Links</a>.
    </p>
  </div>

  <div class="card" style="border-left:4px solid var(--accent)">
    <p class="hint" style="margin:0">
      Οι κωδικοί πρόσβασης του προσωπικού ορίζονται στη σελίδα
      <a href="staff.php">Κωδικοί προσωπικού</a>.
      Τα δεδομένα φυλάσσονται
      <?= $store['kind'] === 'outside'
            ? 'έξω από το site, εκεί που δεν τα σερβίρει ποτέ ο server.'
            : 'σε αρχείο <code>admin/guests-data.php</code> που δεν τυπώνει τίποτα αν το ζητήσεις απευθείας.' ?>
    </p>
  </div>

</div>
<script>
function copyL(btn, url) {
  var done = function () {
    var t = btn.textContent; btn.textContent = 'Αντιγράφηκε ✓';
    setTimeout(function () { btn.textContent = t; }, 1600);
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(done, function () { window.prompt('Αντίγραψε το link:', url); });
  } else {
    window.prompt('Αντίγραψε το link:', url);
  }
}
</script>
</body>
</html>
