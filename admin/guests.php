<?php
/* ============================================================================
 * Naxos Balcony Apartments — guest links admin
 * ============================================================================
 * Create a private welcome page per booking:
 *
 *   surname + room + language + stay dates + how long the link stays live
 *        ->  /welcome/<surname>-<code>.php
 *
 * The guest opens the link and sees their room, their dates, the address and a
 * way to reach you — in the language you chose. When the active window passes,
 * the link stops showing the details by itself.
 *
 * Deploy: upload to  <public_html>/admin/guests.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('admin');

/* ------------------------------------------------------------------- actions */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $guests = guests_load();

    if ($action === 'add') {
        $surname  = trim((string)($_POST['surname'] ?? ''));
        $room     = (string)($_POST['room'] ?? '');
        $lang     = ($_POST['lang'] ?? 'el') === 'en' ? 'en' : 'el';
        $from     = (string)($_POST['stayFrom'] ?? '');
        $to       = (string)($_POST['stayTo'] ?? '');
        $af       = (string)($_POST['activeFrom'] ?? '');
        $at       = (string)($_POST['activeTo'] ?? '');
        $codeMode = ($_POST['codeMode'] ?? 'auto') === 'none' ? 'none' : 'auto';

        // Στοιχεία για αποδείξεις. Προαιρετικά — μπορείς να τα βάλεις αργότερα.
        $source   = array_key_exists((string)($_POST['source'] ?? ''), BOOKING_SOURCES)
                    ? (string)$_POST['source'] : 'direct';
        $address  = trim((string)($_POST['address'] ?? ''));
        $vat      = trim((string)($_POST['vat'] ?? ''));
        $idDoc    = trim((string)($_POST['idDoc'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $adults   = max(1, (int)($_POST['adults'] ?? 2));
        $notes    = trim((string)($_POST['notes'] ?? ''));
        // Ο κωδικός WiFi έχει έτοιμη τιμή· ο κωδικός εισόδου μένει κενός μέχρι
        // να τον δώσει ο διαχειριστής, γιατί αλλάζει σε κάθε κράτηση.
        // Κενό σημαίνει «ακολούθησε τον προεπιλεγμένο κωδικό». Δεν αντιγράφουμε
                // την τιμή, ώστε αλλαγή του κωδικού του ρούτερ να φτάνει σε όλους.
                $wifi     = trim((string)($_POST['wifi'] ?? ''));
        $door     = trim((string)($_POST['doorCode'] ?? ''));

        $err = null;
        if ($surname === '')                                 $err = 'Γράψε το επίθετο.';
        elseif (!isset(ROOMS_G[$room]))                      $err = 'Διάλεξε δωμάτιο.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $err = 'Λείπει η ημερομηνία άφιξης.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $err = 'Λείπει η ημερομηνία αναχώρησης.';
        elseif ($to <= $from)                                $err = 'Η αναχώρηση πρέπει να είναι μετά την άφιξη.';
        elseif ($af !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $af)) $err = 'Λάθος ημερομηνία ενεργοποίησης.';
        elseif ($at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $at)) $err = 'Λάθος ημερομηνία λήξης.';
        elseif ($af !== '' && $at !== '' && $at < $af)       $err = 'Η λήξη του link είναι πριν την ενεργοποίηση.';
        elseif ($vat !== '' && !preg_match('/^\d{9}$/', $vat)) $err = 'Το ΑΦΜ έχει 9 ψηφία.';

        if ($err !== null) {
            fail($err);
        } else {
            // Δύο «Παπαδόπουλος» δεν μπορούν να δείχνουν στο ίδιο αρχείο.
            $slug = guest_slug($surname);
            $base = $slug; $n = 2;
            while (guests_find($guests, $slug) !== null) { $slug = $base . '-' . $n; $n++; }

            $g = [
                'id'         => bin2hex(random_bytes(6)),
                'surname'    => $surname,
                'slug'       => $slug,
                'code'       => $codeMode === 'auto' ? guest_code() : '',
                'room'       => $room,
                'lang'       => $lang,
                'stayFrom'   => $from,
                'stayTo'     => $to,
                'activeFrom' => $af,
                'activeTo'   => $at,
                'enabled'    => true,
                'created'    => date('c'),
                'source'     => $source,
                'address'    => $address,
                'vat'        => $vat,
                'idDoc'      => $idDoc,
                'phone'      => $phone,
                'email'      => $email,
                'adults'     => $adults,
                'notes'      => $notes,
                'wifi'       => $wifi,
                'doorCode'   => $door,
            ];

            if (!welcome_write_stub($g)) {
                fail('Δεν μπόρεσα να γράψω τον φάκελο welcome/. Έλεγξε τα δικαιώματα (755).');
            } else {
                $guests[] = $g;
                if (guests_save($guests)) {
                    ok('Δημιουργήθηκε το link για ' . $surname . '.');
                } else {
                    welcome_remove_stub($g);
                    fail('Δεν μπόρεσα να αποθηκεύσω τη λίστα. Έλεγξε τα δικαιώματα του φακέλου.');
                }
            }
        }
    }

    elseif ($action === 'edit') {
        $id = (string)($_POST['id'] ?? '');
        $i  = guests_index($guests, $id);
        if ($i === null) { fail('Δεν βρέθηκε η εγγραφή.'); }

        $g = $guests[$i];
        $from  = (string)($_POST['stayFrom'] ?? $g['stayFrom']);
        $to    = (string)($_POST['stayTo'] ?? $g['stayTo']);
        $room  = (string)($_POST['room'] ?? $g['room']);

        $err = null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $err = 'Λάθος ημερομηνία άφιξης.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $err = 'Λάθος ημερομηνία αναχώρησης.';
        elseif ($to <= $from) $err = 'Η αναχώρηση πρέπει να είναι μετά την άφιξη.';
        elseif (!isset(ROOMS_G[$room])) $err = 'Λάθος δωμάτιο.';
        elseif (($v = trim((string)($_POST['vat'] ?? ''))) !== '' && !preg_match('/^\d{9}$/', $v)) $err = 'Το ΑΦΜ έχει 9 ψηφία.';

        if ($err !== null) { fail($err, 'guest.php?id=' . urlencode($id)); }

        $g['surname'] = trim((string)($_POST['surname'] ?? $g['surname']));
        $g['room']    = $room;
        $g['lang']    = ($_POST['lang'] ?? $g['lang']) === 'en' ? 'en' : 'el';
        $g['stayFrom'] = $from;
        $g['stayTo']   = $to;
        $g['source']  = array_key_exists((string)($_POST['source'] ?? ''), BOOKING_SOURCES)
                        ? (string)$_POST['source'] : ($g['source'] ?? 'direct');
        $g['address'] = trim((string)($_POST['address'] ?? ''));
        $g['vat']     = trim((string)($_POST['vat'] ?? ''));
        $g['idDoc']   = trim((string)($_POST['idDoc'] ?? ''));
        $g['phone']   = trim((string)($_POST['phone'] ?? ''));
        $g['email']   = trim((string)($_POST['email'] ?? ''));
        $g['adults']  = max(1, (int)($_POST['adults'] ?? 2));
        $g['notes']   = trim((string)($_POST['notes'] ?? ''));
        // Κενό = ακολούθησε τον προεπιλεγμένο (δες guest_wifi() στο _lib.php).
                $g['wifi']     = trim((string)($_POST['wifi'] ?? ''));
        $g['doorCode'] = trim((string)($_POST['doorCode'] ?? ''));

        // Ημέρες καθαριότητας: το κενό πεδίο σημαίνει «αυτόματο».
        $cd = trim((string)($_POST['cleaningDays'] ?? ''));
        if ($cd === '') {
            unset($g['cleaningDays']);
        } else {
            $days = array_values(array_filter(
                array_map('intval', preg_split('/[,\s]+/', $cd) ?: []),
                fn($d) => $d >= 1 && $d <= nights_between($from, $to)
            ));
            sort($days);
            $g['cleaningDays'] = $days;
        }

        $guests[$i] = $g;
        if (guests_save($guests)) ok('Αποθηκεύτηκε.');
        else fail('Δεν μπόρεσα να αποθηκεύσω.', 'guest.php?id=' . urlencode($id));

        header('Location: guest.php?id=' . urlencode($id)); exit;
    }

    elseif ($action === 'wifi_default') {
        $v   = trim((string)($_POST['wifiDefault'] ?? ''));
        $old = wifi_default();
        $st  = settings_load();
        if (strlen($v) > 40) {   // strlen αρκεί: 40 bytes = 40 χαρακτήρες εδώ
            fail('Ο κωδικός WiFi δεν μπορεί να ξεπερνά τους 40 χαρακτήρες.');
        } else {
            // Κενό = «γύρνα στην αρχική τιμή του κώδικα».
            if ($v === '') unset($st['wifiDefault']); else $st['wifiDefault'] = $v;
            settings_save($st);

            // Οι παλιές κρατήσεις έχουν τον κωδικό αντιγραμμένο. Όσες έχουν ακριβώς
            // τον προηγούμενο προεπιλεγμένο δεν είχαν ποτέ δικό τους κωδικό — τις
            // αδειάζουμε, ώστε να ακολουθήσουν τον νέο. Όποιος είχε βάλει άλλον
            // κωδικό επίτηδες δεν τον χάνει.
            $moved = 0;
            foreach ($guests as $i => $g) {
                if (trim((string)($g['wifi'] ?? '')) === $old) { $guests[$i]['wifi'] = ''; $moved++; }
            }
            if ($moved > 0) guests_save($guests);

            $now = wifi_default();
            ok($now === $old
                ? 'Ο προεπιλεγμένος κωδικός WiFi έμεινε ' . $now . '.'
                : 'Ο προεπιλεγμένος κωδικός WiFi έγινε ' . $now . '.'
                  . ($moved > 0 ? ' Ενημερώθηκαν ' . $moved . ' κρατήσεις που ακολουθούσαν τον παλιό.' : ''));
        }
    }
    elseif ($action === 'toggle' || $action === 'delete' || $action === 'regen') {
        $id = (string)($_POST['id'] ?? '');
        $i  = guests_index($guests, $id);
        if ($i === null) {
            fail('Δεν βρέθηκε η εγγραφή.');
        } elseif ($action === 'delete') {
            welcome_remove_stub($guests[$i]);
            array_splice($guests, $i, 1);
            guests_save($guests);
            ok('Διαγράφηκε.');
        } elseif ($action === 'toggle') {
            $was = !empty($guests[$i]['enabled']);
            welcome_remove_stub($guests[$i]);
            $guests[$i]['enabled'] = !$was;
            welcome_write_stub($guests[$i]);
            guests_save($guests);
            ok($was ? 'Απενεργοποιήθηκε.' : 'Ενεργοποιήθηκε.');
        } else { // regen — ο παλιός κωδικός στο URL παύει να ισχύει
            welcome_remove_stub($guests[$i]);
            $guests[$i]['code'] = guest_code();
            welcome_write_stub($guests[$i]);
            guests_save($guests);
            ok('Νέος κωδικός. Το παλιό link δεν δουλεύει πια.');
        }
    }

    header('Location: guests.php'); exit;
}

if (isset($_GET['setup_outside'])) {
    if (store_try_create_outside()) {
        $g = guests_load();
        if ($g !== [] && guests_save($g)) ok('Τα δεδομένα μεταφέρθηκαν στον ασφαλή φάκελο.');
        else ok('Ο ασφαλής φάκελος είναι έτοιμος.');
    } else {
        fail('Δεν μπόρεσα να δημιουργήσω τον φάκελο. Δες τις οδηγίες πιο κάτω.');
    }
    header('Location: guests.php'); exit;
}

$guests = guests_load();
$store  = store_active();
$flash  = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$base = 'https://www.naxosbalcony.gr';
if (($_SERVER['HTTP_HOST'] ?? '') !== '' && str_contains((string)$_SERVER['HTTP_HOST'], 'all-hands.dev')) {
    $base = 'https://' . $_SERVER['HTTP_HOST'];
}

// Πρώτα τα ενεργά, μετά τα προγραμματισμένα, τα απενεργοποιημένα, τα ληγμένα.
$order = ['active' => 0, 'scheduled' => 1, 'disabled' => 2, 'expired' => 3];
usort($guests, function ($a, $b) use ($order) {
    $d = ($order[guest_status($a)] ?? 9) <=> ($order[guest_status($b)] ?? 9);
    return $d !== 0 ? $d : strcmp((string)($a['stayFrom'] ?? ''), (string)($b['stayFrom'] ?? ''));
});

$today   = date('Y-m-d');
$defFrom = date('Y-m-d', strtotime('+1 day'));
$defTo   = date('Y-m-d', strtotime('+4 days'));
$defEnd  = date('Y-m-d', strtotime('+5 days'));

function date_el(string $iso): string {
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$d) return $iso;
    $m = [1=>'Ιαν','Φεβ','Μάρ','Απρ','Μάι','Ιούν','Ιούλ','Αύγ','Σεπ','Οκτ','Νοέ','Δεκ'];
    return $d->format('j') . ' ' . $m[(int)$d->format('n')];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Links επισκεπτών — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#16232e;--muted:#5b6b7a;--accent:#a85c30;--accent-2:#2c5282;--soft:#f6f8fb;--line:rgba(16,32,48,.12);
      --ok:#1f7a45;--okbg:#e8f6ec;--off:#6b7683;--offbg:#eef1f4;--soon:#8a6100;--soonbg:#fff6e0;--exp:#8a3030;--expbg:#fdecec}
*{box-sizing:border-box}
body{margin:0;font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--soft);line-height:1.6}
.wrap{max-width:1100px;margin:0 auto;padding:24px 18px 80px}
h1{font-size:1.4rem;margin:0 0 4px}
h2{font-size:1.05rem;margin:0 0 14px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin:16px 0;box-shadow:0 2px 10px rgba(16,32,48,.04)}
label{display:block;font-weight:600;font-size:.88rem;margin:0 0 6px}
input[type=text],input[type=password],input[type=date],select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff;min-height:44px}
button{font:inherit;font-weight:600;border:0;border-radius:10px;padding:11px 20px;cursor:pointer;background:var(--accent);color:#fff;min-height:44px}
button.ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}
button.danger{background:#b23b3b}
button.tiny{padding:8px 13px;font-size:.83rem;min-height:38px}
.row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
.row>div{flex:1 1 170px}
.flash{padding:12px 16px;border-radius:10px;margin:12px 0;font-size:.92rem}
.flash.ok{background:var(--okbg);border:1px solid #bfe3c9}
.flash.err{background:var(--expbg);border:1px solid #f2c7c7}
.hint{color:var(--muted);font-size:.85rem}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px}
a.signout{text-decoration:none;padding:10px 18px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-weight:600;font-size:.9rem}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th{text-align:left;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:0 10px 8px 0;font-weight:700}
td{padding:12px 10px 12px 0;border-top:1px solid var(--line);vertical-align:top}
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:700;white-space:nowrap}
.b-active{background:var(--okbg);color:var(--ok);border:1px solid #bfe3c9}
.b-disabled{background:var(--offbg);color:var(--off);border:1px solid #d7dde3}
.b-scheduled{background:var(--soonbg);color:var(--soon);border:1px solid #e8d08a}
.b-expired{background:var(--expbg);color:var(--exp);border:1px solid #f2c7c7}
.link-cell{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
code.lnk{background:var(--soft);border:1px solid var(--line);border-radius:7px;padding:5px 9px;font-size:.8rem;word-break:break-all;display:inline-block;max-width:100%}
.acts{display:flex;gap:6px;flex-wrap:wrap}
.room-tag{font-weight:700;font-size:.82rem;background:var(--soft);border:1px solid var(--line);padding:2px 9px;border-radius:7px;white-space:nowrap}
.warn{background:var(--soonbg);border:1px solid #e8d08a;border-radius:12px;padding:16px;font-size:.9rem;margin:16px 0}
.warn strong{color:#7a5a12}
.good{background:var(--okbg);border:1px solid #bfe3c9;border-radius:12px;padding:14px 16px;font-size:.9rem;margin:16px 0}
.empty{text-align:center;color:var(--muted);padding:36px 20px}
details.help{margin:16px 0}
details.help summary{cursor:pointer;font-weight:600;font-size:.9rem}
details.help div{margin-top:10px;font-size:.88rem;color:var(--muted)}
@media(max-width:820px){
  thead{display:none}
  table,tbody,tr,td{display:block;width:100%}
  tr{border:1px solid var(--line);border-radius:12px;padding:12px;margin:0 0 12px;background:#fff}
  td{border:0;padding:5px 0}
  td::before{content:attr(data-l);display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;margin-bottom:2px}
}
</style>
</head>
<body>
<div class="wrap">

<?php if (!is_file(CRED_FILE)): ?>

  <h1>Links επισκεπτών</h1>
  <div class="warn">
    <strong>Πρώτα φτιάξε κωδικό στο gallery admin.</strong><br>
    Το <code>admin/credentials.php</code> δεν υπάρχει ακόμη. Άνοιξε το
    <a href="index.php">gallery admin</a> και όρισε κωδικό — ο ίδιος κωδικός
    προστατεύει και αυτή τη σελίδα.
  </div>

<?php elseif (!logged_in()): ?>

  <h1>Links επισκεπτών</h1>
  <?php if (!empty($_SESSION['login_error'])): ?>
    <div class="flash err"><?= h((string)$_SESSION['login_error']) ?></div>
    <?php unset($_SESSION['login_error']); ?>
  <?php endif; ?>
  <div class="card" style="max-width:420px">
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label for="p">Κωδικός</label>
      <input id="p" type="password" name="password" required autocomplete="current-password" autofocus>
      <p style="margin-top:16px"><button type="submit">Σύνδεση</button></p>
    </form>
  </div>

<?php else: ?>

  <div class="top">
    <div>
      <h1>Links επισκεπτών</h1>
      <p class="hint" style="margin:0">Φτιάξε ένα προσωπικό link για κάθε κράτηση.</p>
    </div>
    <div class="acts">
      <a class="signout" href="calendar.php">Ημερολόγιο</a>
      <a class="signout" href="stats.php">Στατιστικά</a>
      <a class="signout" href="staff.php">Κωδικοί προσωπικού</a>
      <a class="signout" href="index.php">Φωτογραφίες</a>
      <a class="signout" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash <?= h($kind) ?>"><?= h($msg) ?></div>
  <?php endforeach; ?>

  <?php if ($store['kind'] === 'inside'): ?>
    <div class="warn">
      <strong>⚠️ Τα δεδομένα φυλάσσονται μέσα στο site.</strong><br>
      Ο server είναι nginx, οπότε το <code>.htaccess</code> δεν ισχύει και δεν
      μπορώ να απαγορεύσω την πρόσβαση σε αρχείο μέσα στο site. Το
      <code>admin/guests-data.php</code> δεν τυπώνει τίποτα αν το ζητήσεις
      απευθείας (η PHP το εκτελεί), αλλά η πιο ασφαλής θέση είναι <em>έξω</em>
      από το <code>public_html</code>.<br><br>
      <a href="?setup_outside=1"><button type="button" class="tiny">Δημιούργησε τον ασφαλή φάκελο</button></a>
      <p class="hint" style="margin:10px 0 0">Αν αποτύχει, δες τις οδηγίες στο κάτω μέρος της σελίδας.</p>
    </div>
  <?php else: ?>
    <div class="good">✅ Τα δεδομένα φυλάσσονται <strong>έξω από το site</strong>,
      εκεί που δεν τα σερβίρει ποτέ ο server.</div>
  <?php endif; ?>

  <div class="card">
    <h2>Προεπιλεγμένος κωδικός WiFi</h2>
    <p class="hint" style="margin:0 0 12px">
      Ο κωδικός του ρούτερ. Τον βλέπουν <strong>όλοι οι επισκέπτες</strong> που δεν
      έχουν δικό τους κωδικό στη σελίδα τους. Όταν αλλάξεις τον κωδικό του ρούτερ,
      άλλαξέ τον κι εδώ — μία φορά, και ισχύει για όλους.
    </p>
    <form method="post" class="row">
      <input type="hidden" name="action" value="wifi_default">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <div>
        <label for="wifiDefault">Κωδικός WiFi</label>
        <input id="wifiDefault" type="text" name="wifiDefault" maxlength="40"
               value="<?= h(wifi_default()) ?>" autocomplete="off">
      </div>
      <div style="display:flex;align-items:flex-end">
        <button type="submit">Αποθήκευση</button>
      </div>
    </form>
    <p class="hint" style="margin:10px 0 0">
      Άφησέ το κενό για να επιστρέψει στο <code><?= h(DEFAULT_WIFI_PASSWORD) ?></code>.
    </p>
  </div>

  <div class="card">
    <h2>Νέο link</h2>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <div class="row">
        <div style="flex:2 1 220px">
          <label for="surname">Επίθετο επισκέπτη</label>
          <input id="surname" type="text" name="surname" required maxlength="60"
                 placeholder="π.χ. Παπαδόπουλος" autocomplete="off">
        </div>
        <div>
          <label for="room">Δωμάτιο</label>
          <select id="room" name="room" required>
            <?php foreach (ROOMS_G as $k => $r): ?>
              <option value="<?= h($k) ?>"><?= h($r['el']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="lang">Γλώσσα σελίδας</label>
          <select id="lang" name="lang">
            <option value="el">Ελληνικά</option>
            <option value="en">English</option>
          </select>
        </div>
        <div>
          <label for="codeMode">Κωδικός στο link</label>
          <select id="codeMode" name="codeMode">
            <option value="auto">Ναι (4 χαρακτήρες)</option>
            <option value="none">Όχι</option>
          </select>
        </div>
      </div>
      <div class="row" style="margin-top:12px">
        <div>
          <label for="stayFrom">Άφιξη</label>
          <input id="stayFrom" type="date" name="stayFrom" required value="<?= h($defFrom) ?>">
        </div>
        <div>
          <label for="stayTo">Αναχώρηση</label>
          <input id="stayTo" type="date" name="stayTo" required value="<?= h($defTo) ?>">
        </div>
        <div>
          <label for="activeFrom">Το link ενεργό από</label>
          <input id="activeFrom" type="date" name="activeFrom" value="<?= h($today) ?>">
        </div>
        <div>
          <label for="activeTo">Έως</label>
          <input id="activeTo" type="date" name="activeTo" value="<?= h($defEnd) ?>">
        </div>
        <div style="flex:0 0 auto">
          <button type="submit">Δημιουργία</button>
        </div>
      </div>

      <details style="margin-top:16px">
        <summary style="cursor:pointer;font-weight:600;font-size:.92rem">
          Στοιχεία για απόδειξη (προαιρετικά — μπορείς να τα βάλεις και αργότερα)
        </summary>
        <div class="row" style="margin-top:12px">
          <div>
            <label for="source">Κράτηση από</label>
            <select id="source" name="source">
              <?php foreach (BOOKING_SOURCES as $k => $lbl): ?>
                <option value="<?= h($k) ?>"><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="adults">Άτομα</label>
            <input id="adults" type="number" name="adults" min="1" max="12" value="2">
          </div>
          <div>
            <label for="phone">Τηλέφωνο</label>
            <input id="phone" type="tel" name="phone" maxlength="30" autocomplete="off">
          </div>
          <div>
            <label for="email">Email</label>
            <input id="email" type="email" name="email" maxlength="80" autocomplete="off">
          </div>
        </div>
        <div class="row" style="margin-top:12px">
          <div style="flex:2 1 260px">
            <label for="address">Διεύθυνση</label>
            <input id="address" type="text" name="address" maxlength="120" autocomplete="off">
          </div>
          <div>
            <label for="vat">ΑΦΜ</label>
            <input id="vat" type="text" name="vat" maxlength="9" inputmode="numeric"
                   pattern="\d{9}" placeholder="9 ψηφία" autocomplete="off">
          </div>
          <div>
            <label for="idDoc">Ταυτότητα / Διαβατήριο</label>
            <input id="idDoc" type="text" name="idDoc" maxlength="30" autocomplete="off">
          </div>
        </div>
        <p class="hint" style="margin:10px 0 0">
          Οι διανυκτερεύσεις υπολογίζονται αυτόματα από τις ημερομηνίες.
          Τα στοιχεία αυτά τα βλέπεις εσύ και ο λογιστής — <strong>όχι</strong> οι καθαρίστριες.
        </p>
      </details>

      <details style="margin-top:14px">
        <summary style="cursor:pointer;font-weight:600;font-size:.92rem">
          Πρόσβαση επισκέπτη — WiFi και κωδικός εισόδου
        </summary>
        <div class="row" style="margin-top:12px">
          <div>
            <label for="wifi">Κωδικός WiFi</label>
            <input id="wifi" type="text" name="wifi" maxlength="40"
                   placeholder="<?= h(wifi_default()) ?> (ο προεπιλεγμένος)" autocomplete="off">
          </div>
          <div>
            <label for="doorCode">Κωδικός εισόδου διαμερίσματος</label>
            <input id="doorCode" type="text" name="doorCode" maxlength="20"
                   placeholder="π.χ. 4821" autocomplete="off">
          </div>
        </div>
        <div class="warn" style="margin-top:12px">
          <strong>⚠️ Φαίνονται στη σελίδα του επισκέπτη.</strong>
          Τα βλέπει <em>όποιος έχει το link</em>. Αν το link προωθηθεί, τα βλέπει κι άλλος.
          Άλλαξέ τα <strong>μόλις τελειώσει η διαμονή</strong>, ή άφησε τον κωδικό εισόδου
          κενό και στείλ' τον με μήνυμα.
        </div>
      </details>

      <p class="hint" style="margin:12px 0 0">
        Μετά την ημερομηνία «Έως», το link δεν δείχνει πια τα στοιχεία της διαμονής.
        Άφησε κενά τα δύο τελευταία πεδία για link που δεν λήγει ποτέ.
      </p>
    </form>
  </div>

  <div class="card">
    <h2>Όλα τα links (<?= count($guests) ?>)</h2>

    <?php if ($guests === []): ?>
      <div class="empty">Δεν έχεις φτιάξει ακόμη κανένα link.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Επισκέπτης</th><th>Δωμάτιο</th><th>Διαμονή</th>
            <th>Κατάσταση</th><th>Link</th><th>Ενέργειες</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($guests as $g):
          $st    = guest_status($g);
          $url   = guest_link($g, $base);
          $fname = guest_file_name($g);
        ?>
          <tr>
            <td data-l="Επισκέπτης">
              <strong><?= h((string)($g['surname'] ?? '')) ?></strong><br>
              <span class="hint" style="font-size:.78rem">
                <?= ($g['lang'] ?? 'el') === 'en' ? 'Αγγλικά' : 'Ελληνικά' ?>
                <?= !empty($g['code']) ? ' · κωδ. ' . h((string)$g['code']) : '' ?>
              </span>
            </td>
            <td data-l="Δωμάτιο">
              <span class="room-tag"><?= h(ROOMS_G[$g['room'] ?? '']['el'] ?? (string)($g['room'] ?? '')) ?></span>
            </td>
            <td data-l="Διαμονή">
              <?= h(date_el((string)($g['stayFrom'] ?? ''))) ?> → <?= h(date_el((string)($g['stayTo'] ?? ''))) ?>
              <br><span class="hint" style="font-size:.78rem">
                <strong><?= guest_nights($g) ?></strong> διανυκτερεύσεις
                · <?= h(source_label((string)($g['source'] ?? 'direct'))) ?>
              </span>
              <?php if (!empty($g['activeFrom']) || !empty($g['activeTo'])): ?>
                <br><span class="hint" style="font-size:.76rem">
                  link: <?= h(!empty($g['activeFrom']) ? date_el((string)$g['activeFrom']) : 'πάντα') ?>
                  → <?= h(!empty($g['activeTo']) ? date_el((string)$g['activeTo']) : 'πάντα') ?>
                </span>
              <?php endif; ?>
            </td>
            <td data-l="Κατάσταση">
              <span class="badge b-<?= h($st) ?>"><?= h(status_label($st)) ?></span>
              <?php if (!empty($g['vat'])): ?>
                <br><span class="hint" style="font-size:.76rem">ΑΦΜ <?= h((string)$g['vat']) ?></span>
              <?php endif; ?>
            </td>
            <td data-l="Link">
              <div class="link-cell">
                <code class="lnk">/welcome/<?= h($fname) ?>.php</code>
                <button type="button" class="tiny ghost" onclick="copyLink(this,'<?= h($url) ?>')">Αντιγραφή</button>
              </div>
            </td>
            <td data-l="Ενέργειες">
              <div class="acts">
                <a class="tiny" href="guest.php?id=<?= h(urlencode((string)$g['id'])) ?>"
                   style="text-decoration:none;display:inline-flex;align-items:center">Στοιχεία</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= h((string)$g['id']) ?>">
                  <button class="tiny ghost" type="submit"><?= !empty($g['enabled']) ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?></button>
                </form>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Νέος κωδικός: το link που έχεις στείλει θα πάψει να δουλεύει. Συνέχεια;')">
                  <input type="hidden" name="action" value="regen">
                  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= h((string)$g['id']) ?>">
                  <button class="tiny ghost" type="submit">Νέος κωδικός</button>
                </form>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Διαγραφή του link για <?= h((string)($g['surname'] ?? '')) ?>; Συνέχεια;')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= h((string)$g['id']) ?>">
                  <button class="tiny danger" type="submit">Διαγραφή</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <details class="help">
    <summary>Πώς να βάλεις τα δεδομένα έξω από το site (αν απέτυχε το κουμπί)</summary>
    <div>
      <ol style="margin:0;padding-left:20px">
        <li>Άνοιξε τον <strong>Διαχειριστή Αρχείων</strong> του cPanel.</li>
        <li>Πήγαινε έναν φάκελο <em>πάνω</em> από το <code>public_html</code>.</li>
        <li>Φτιάξε φάκελο με όνομα <code>naxos-data</code>.</li>
        <li>Βάλε δικαιώματα <strong>755</strong>.</li>
        <li>Ξαναπάτησε «Δημιούργησε τον ασφαλή φάκελο».</li>
      </ol>
      <p style="margin:10px 0 0">Έτσι τα επώνυμα και οι ημερομηνίες μένουν εκτός
      του φακέλου που σερβίρει ο server.</p>
    </div>
  </details>

  <details class="help">
    <summary>Τι βλέπει ο επισκέπτης</summary>
    <div>
      Το δωμάτιό του, τις ημερομηνίες διαμονής, τη διεύθυνση, χάρτη και τρόπους
      επικοινωνίας — στη γλώσσα που διάλεξες. Όταν περάσει η ημερομηνία λήξης,
      η σελίδα δεν δείχνει πια τα στοιχεία.
      <br><br>
      <strong>Δεν</strong> βάζουμε κωδικό κλειδαριάς ή WiFi: το link το ανοίγει
      όποιος το έχει. Τις οδηγίες πρόσβασης τις στέλνεις προσωπικά, όπως λέει
      ήδη το site.
    </div>
  </details>

<?php endif; ?>
</div>

<script>
function copyLink(btn, url) {
  var done = function () { var o = btn.textContent; btn.textContent = 'Αντιγράφηκε!';
    setTimeout(function(){ btn.textContent = o; }, 1600); };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(done, function(){ window.prompt('Αντίγραψε το link:', url); });
  } else {
    window.prompt('Αντίγραψε το link:', url);
  }
}
</script>
</body>
</html>
