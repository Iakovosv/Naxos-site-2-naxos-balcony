<?php
/* ============================================================================
 *  Καθαρίστρια — πότε έχει άφιξη, αναχώρηση και καθαριότητα
 *
 *  ΔΕΝ ΒΛΕΠΕΙ ΚΑΝΕΝΑ ΠΡΟΣΩΠΙΚΟ ΣΤΟΙΧΕΙΟ. Ούτε επίθετο, ούτε ΑΦΜ, ούτε
 *  τηλέφωνο, ούτε διεύθυνση, ούτε ταυτότητα. Βλέπει μόνο:
 *      δωμάτιο · ημερομηνίες · πόσα άτομα · ποιες ημέρες καθαρίζει
 *
 *  Αυτό δεν είναι θέμα εμφάνισης: η σελίδα δεν φορτώνει ποτέ τα πεδία αυτά
 *  στη μνήμη. Ό,τι δεν διαβάζεται δεν μπορεί να διαρρεύσει.
 *
 *  Η κάθε καθαρίστρια βλέπει μόνο τα δικά της δωμάτια.
 *
 *  Deploy: upload to  <public_html>/admin/cleaning.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
// Ξεχωριστό κλείδωμα: οι αποτυχίες του λογιστή δεν πρέπει να κλειδώνουν εδώ.
require_login(['cleaner1', 'cleaner2'], 'cleaner');

// view_role(): αν ο διαχειριστής βλέπει «ως» καθαρίστρια, παίρνει τα δωμάτιά της.
$role  = view_role();
$rooms = CLEANER_ROOMS[$role] ?? [];

/* ------------------------------------------------------------ καταγραφή ωρών
 *
 * Η καθαρίστρια γράφει πόσες ώρες έκανε. Ο διαχειριστής που βλέπει «ως» αυτή
 * (viewing_as) ΔΕΝ γράφει — βλέπει ό,τι βλέπει κι εκείνη, χωρίς να αλλάζει τίποτα.
 * Η ίδια η καθαρίστρια δεν έχει κανένα άλλο δικαίωμα αλλαγής. */

$hours_all = hours_load();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'hours') {
    check_csrf();
    if (viewing_as()) {
        fail('Βλέπεις τη σελίδα ως διαχειριστής — δεν αλλάζεις στοιχεία.', 'cleaning.php');
    }
    $hDay = (string)($_POST['hDay'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hDay)) {
        fail('Λάθος ημερομηνία.', 'cleaning.php');
    }

    // Ένα πεδίο ανά δωμάτιο. Το κενό σημαίνει «σβήσε την καταχώρηση».
    $vals  = is_array($_POST['hVal'] ?? null) ? $_POST['hVal'] : [];
    $saved = 0; $cleared = 0; $bad = [];

    foreach ($rooms as $r) {
        if (!array_key_exists($r, $vals)) continue;          // δεν ανήκει στη φόρμα
        $raw = trim((string)$vals[$r]);
        if ($raw === '') {
            $before = hours_get($hours_all, $hDay, $role, $r);
            if ($before > 0) { $hours_all = hours_set($hours_all, $hDay, $role, $r, 0.0); $cleared++; }
            continue;
        }
        $h = hours_norm($raw);
        if ($h === null) { $bad[] = (string)(ROOMS_G[$r]['el'] ?? $r); continue; }
        $hours_all = hours_set($hours_all, $hDay, $role, $r, $h);
        $saved++;
    }

    if ($bad) {
        fail('Λάθος ώρες για: ' . implode(', ', $bad)
             . '. Γράψε π.χ. 2 ή 2,5 ή 2:30 (έως ' . hours_fmt(HOURS_MAX) . ' ώρες).', 'cleaning.php');
    }
    if (!hours_save($hours_all)) {
        fail('Δεν μπόρεσα να αποθηκεύσω. Πες το στον διαχειριστή.', 'cleaning.php');
    }
    ok($saved > 0
        ? 'Οι ώρες καταχωρήθηκαν.'
        : ($cleared > 0 ? 'Οι ώρες διαγράφηκαν.' : 'Δεν άλλαξε κάτι.'));
    header('Location: cleaning.php?d=' . urlencode($hDay)); exit;
}

/* Κρατάμε ΜΟΝΟ τα πεδία που επιτρέπεται να δει. Ό,τι δεν περνάει από εδώ δεν
   φτάνει ποτέ στο HTML, οπότε δεν μπορεί να διαρρεύσει ούτε κατά λάθος. */
$tasks = [];
foreach (guests_load() as $g) {
    $room = (string)($g['room'] ?? '');
    if (!in_array($room, $rooms, true)) continue;
    $from = (string)($g['stayFrom'] ?? '');
    $to   = (string)($g['stayTo'] ?? '');
    if ($from === '' || $to === '') continue;
    $tasks[] = [
        'room'     => $room,
        'roomName' => (string)(ROOMS_G[$room]['el'] ?? $room),
        'from'     => $from,
        'to'       => $to,
        'nights'   => nights_between($from, $to),
        'adults'   => max(1, (int)($g['adults'] ?? 1)),
        'cleaning' => cleaning_dates($g),
    ];
}
usort($tasks, fn($a, $b) => strcmp($a['from'], $b['from']));

$today = date('Y-m-d');

/* ------------------------------------------------------- ποια ημέρα δείχνουμε */

$day = (string)($_GET['d'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = $today;

// Τι έχει αυτή την ημέρα: αφίξεις, αναχωρήσεις, καθαριότητες.
$arrivals = []; $departures = []; $cleaning = []; $staying = [];
foreach ($tasks as $t) {
    if ($t['from'] === $day) $arrivals[] = $t;
    if ($t['to']   === $day) $departures[] = $t;
    if (in_array($day, $t['cleaning'], true)) $cleaning[] = $t;
    if ($t['from'] < $day && $t['to'] > $day) $staying[] = $t;
}

/* Επόμενες καθαριότητες από σήμερα, για να μπορεί να προγραμματίσει. */
$upcoming = [];
foreach ($tasks as $t) {
    foreach ($t['cleaning'] as $cd) {
        if ($cd >= $today) $upcoming[$cd][] = $t;
    }
}
ksort($upcoming);
$upcoming = array_slice($upcoming, 0, 14, true);

$dayNames = [1=>'Δευτέρα',2=>'Τρίτη',3=>'Τετάρτη',4=>'Πέμπτη',5=>'Παρασκευή',
             6=>'Σάββατο',7=>'Κυριακή'];

$monthNames = [1=>'Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
               'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];

/* ------------------------------------------------- ώρες: ημέρα και μήνας */

$dayHours  = hours_day($hours_all, $day, $role);
$dayTotal  = array_sum($dayHours);

// Ο μήνας που δείχνουμε: αυτός της ημέρας, ώστε «άλλη ημέρα» να αλλάζει και το σύνολο.
$mYear  = (int)date('Y', strtotime($day));
$mMonth = (int)date('n', strtotime($day));

$monthTotal = hours_month_total($hours_all, $mYear, $mMonth, $role);
$monthByRoom = [];
foreach ($rooms as $r) {
    $monthByRoom[$r] = hours_month_total($hours_all, $mYear, $mMonth, $role, $r);
}
// Πόσες ημέρες δούλεψε μέσα στον μήνα — για να μη φαίνεται μόνο ένα νούμερο.
$monthDays = [];
foreach ($hours_all as $e) {
    if (!is_array($e)) continue;
    if ((string)($e['role'] ?? '') !== $role) continue;
    $d = (string)($e['date'] ?? '');
    if (!str_starts_with($d, sprintf('%04d-%02d-', $mYear, $mMonth))) continue;
    if ((float)($e['hours'] ?? 0) > 0) $monthDays[$d] = true;
}

function d_el2(string $iso): string {
    $ts = strtotime($iso);
    if (!$ts) return $iso;
    $dn = [1=>'Δευτέρα',2=>'Τρίτη',3=>'Τετάρτη',4=>'Πέμπτη',5=>'Παρασκευή',
           6=>'Σάββατο',7=>'Κυριακή'];
    return $dn[(int)date('N', $ts)] . ' ' . date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Καθαριότητα — Naxos Balcony Apartments</title>
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
.wrap{max-width:820px;margin:0 auto;padding:20px 16px 64px}
.top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;
     flex-wrap:wrap;margin:0 0 18px}
h1{margin:0 0 4px;font-size:1.4rem}
h2{margin:0 0 12px;font-size:1.05rem}
.hint{color:var(--muted);font-size:.85rem;margin:0}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;
      background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;
      min-height:44px;line-height:22px}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;
      margin:0 0 16px;box-shadow:0 1px 3px rgba(12,24,34,.05)}
.role{display:inline-block;background:var(--soft);border:1px solid var(--line);
      border-radius:99px;padding:4px 12px;font-size:.8rem;font-weight:700;color:var(--muted)}
input[type=date]{padding:10px 12px;border:1px solid var(--line);border-radius:10px;
                 font-weight:600;font-size:.95rem;min-height:44px;font-family:inherit}
/* Κάρτα εργασίας: μεγάλη, για χέρια που δουλεύουν. */
.job{display:flex;align-items:center;gap:14px;padding:14px;border:1px solid var(--line);
     border-left:5px solid var(--accent-dark);border-radius:12px;background:#fff;
     margin:0 0 10px}
.job.clean{border-left-color:#d9a94a;background:var(--cleanbg)}
.job.arrive{border-left-color:var(--good)}
.job.leave{border-left-color:var(--leave)}
.job .room{font-size:1.3rem;font-weight:800;min-width:96px}
.job .meta{flex:1;font-size:.9rem;color:var(--muted)}
.job .meta strong{color:var(--ink);font-size:1rem}
.job .tag{font-size:.75rem;font-weight:700;padding:4px 10px;border-radius:99px;
          background:var(--soft);color:var(--muted);white-space:nowrap}
.empty{color:var(--muted);text-align:center;padding:22px;font-size:.95rem}
.row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
.row>div{flex:1 1 170px}
input[type=text]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;
                 font:inherit;background:#fff;min-height:44px}
button{font:inherit;font-weight:600;border:0;border-radius:10px;padding:11px 20px;cursor:pointer;
       background:var(--accent);color:#fff;min-height:44px}
.flash{padding:12px 16px;border-radius:10px;margin:0 0 14px;font-size:.92rem}
.flash.ok{background:#e8f6ec;border:1px solid #bfe3c9}
.flash.err{background:#fdecec;border:1px solid #f2c7c7}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left}
th{font-size:.74rem;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);
   background:var(--soft);font-weight:700;white-space:nowrap}
tr.today-row td{background:var(--cleanbg);font-weight:700}
.big{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin:0 0 14px}
.big div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px;text-align:center}
.big .n{font-size:1.6rem;font-weight:800;line-height:1.1}
.big .l{font-size:.76rem;color:var(--muted);margin-top:2px}
@media(max-width:560px){
  .wrap{padding:16px 11px 48px}
  .job{flex-wrap:wrap;gap:8px}
  .job .room{min-width:auto;font-size:1.15rem}
  table{font-size:.83rem}
  th,td{padding:7px 6px}
}
@media print{body{background:#fff}.acts,.noprint{display:none!important}}
</style>
</head>
<body>
<div class="wrap">

  <?php $flash = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); ?>
  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash <?= h($kind) ?>"><?= h($msg) ?></div>
  <?php endforeach; ?>

  <?php if (viewing_as()): ?>
    <div class="card noprint" style="border-left:4px solid var(--accent);margin:0 0 16px">
      <strong>Βλέπεις τη σελίδα ως <?= h(role_label($role)) ?>.</strong>
      Έτσι τη βλέπει η ίδια. Δεν μπορείς να αλλάξεις τίποτα από εδώ —
      <a href="?as=admin">γύρνα στον διαχειριστή</a>.
    </div>
  <?php endif; ?>

  <div class="top">
    <div>
      <h1>Καθαριότητα</h1>
      <p class="hint">
        Τα δωμάτιά σου: <?= h(implode(', ', array_map(fn($r) => (string)(ROOMS_G[$r]['el'] ?? $r), $rooms))) ?>
      </p>
    </div>
    <div class="acts">
      <span class="role"><?= h(role_label($role)) ?></span>
      <?php if (current_role() === 'admin'): ?>
        <a class="btn" href="guests.php">Πίνακας</a>
      <?php endif; ?>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <div class="card noprint">
    <form method="get" class="acts">
      <label for="d" style="font-weight:600;font-size:.9rem">Δες άλλη ημέρα</label>
      <input id="d" type="date" name="d" value="<?= h($day) ?>">
      <button class="btn" type="submit">Δες</button>
      <a class="btn" href="?d=<?= h($today) ?>">Σήμερα</a>
      <button class="btn" type="button" onclick="window.print()">Εκτύπωση</button>
    </form>
  </div>

  <div class="big">
    <div><div class="n" style="color:var(--clean)"><?= count($cleaning) ?></div><div class="l">Καθαριότητες</div></div>
    <div><div class="n" style="color:var(--good)"><?= count($arrivals) ?></div><div class="l">Αφίξεις</div></div>
    <div><div class="n" style="color:var(--leave)"><?= count($departures) ?></div><div class="l">Αναχωρήσεις</div></div>
    <div><div class="n"><?= count($staying) ?></div><div class="l">Μένουν</div></div>
  </div>

  <?php /* ---------------------------------------------------- ώρες καθαριότητας
     Η καθαρίστρια γράφει πόσες ώρες έκανε. Ένα πεδίο ανά δωμάτιο, για την ημέρα
     που βλέπει. Από κάτω το σύνολο του μήνα, που ζήτησε ο ιδιοκτήτης. */ ?>
  <div class="card noprint" style="border-left:5px solid var(--accent)">
    <h2>Οι ώρες μου — <?= h(d_el2($day)) ?></h2>

    <?php if (viewing_as()): ?>
      <p class="hint" style="margin:0">
        Οι ώρες που έχει καταχωρήσει η <?= h(role_label($role)) ?> αυτή την ημέρα:
        <?php if ($dayTotal > 0): ?>
          <strong><?= h(hours_fmt($dayTotal)) ?> ώρες</strong>
          <?php foreach ($dayHours as $r => $hv): ?>
            <?php if ($hv > 0): ?>
              (<?= h((string)(ROOMS_G[$r]['el'] ?? $r)) ?>: <?= h(hours_fmt($hv)) ?>)
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          καμία ακόμη.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="hint" style="margin:0 0 12px">
        Γράψε πόσες ώρες σου πήρε. Δέχεται <strong>2</strong>, <strong>2,5</strong> ή
        <strong>2:30</strong>. Άφησέ το κενό για να σβήσεις μια καταχώρηση.
      </p>
      <form method="post" class="row">
        <input type="hidden" name="action" value="hours">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="hDay" value="<?= h($day) ?>">
        <?php foreach ($rooms as $r): ?>
          <div>
            <label for="h_<?= h($r) ?>"><?= h((string)(ROOMS_G[$r]['el'] ?? $r)) ?></label>
            <input id="h_<?= h($r) ?>" type="text" name="hVal[<?= h($r) ?>]" inputmode="decimal"
                   placeholder="π.χ. 2,5"
                   value="<?= isset($dayHours[$r]) && $dayHours[$r] > 0 ? h(hours_fmt($dayHours[$r])) : '' ?>">
          </div>
        <?php endforeach; ?>
        <div><button type="submit">Καταχώρηση</button></div>
      </form>
    <?php endif; ?>

    <div class="big" style="margin:14px 0 0">
      <div>
        <div class="n" style="color:var(--accent-dark)"><?= h(hours_fmt($monthTotal)) ?></div>
        <div class="l">Ώρες τον μήνα</div>
      </div>
      <div>
        <div class="n"><?= count($monthDays) ?></div>
        <div class="l">Ημέρες δουλειάς</div>
      </div>
      <?php foreach ($monthByRoom as $r => $hv): ?>
        <div>
          <div class="n" style="font-size:1.25rem"><?= h(hours_fmt($hv)) ?></div>
          <div class="l"><?= h((string)(ROOMS_G[$r]['el'] ?? $r)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:12px 0 0">
      Σύνολο <strong><?= h($monthNames[$mMonth] ?? '') ?> <?= $mYear ?></strong>.
      <?php if ($monthTotal <= 0): ?>
        Δεν έχεις καταχωρήσει ώρες για αυτόν τον μήνα.
      <?php endif; ?>
    </p>
  </div>

  <div class="card">
    <h2><?= h(d_el2($day)) ?><?= $day === $today ? ' — σήμερα' : '' ?></h2>

    <?php if ($cleaning): ?>
      <h2 style="font-size:.95rem;color:var(--clean);margin:14px 0 8px">🧹 Καθαριότητα</h2>
      <?php foreach ($cleaning as $t): ?>
        <div class="job clean">
          <div class="room"><?= h($t['roomName']) ?></div>
          <div class="meta">
            <strong>Καθάρισμα δωματίου</strong><br>
            Διαμονή <?= h(date('d/m', strtotime($t['from']))) ?> → <?= h(date('d/m', strtotime($t['to']))) ?>
            · <?= $t['nights'] ?> διανυκτερεύσεις
          </div>
          <span class="tag"><?= $t['adults'] ?> <?= $t['adults'] === 1 ? 'άτομο' : 'άτομα' ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($arrivals): ?>
      <h2 style="font-size:.95rem;color:var(--good);margin:16px 0 8px">🔑 Αφίξεις</h2>
      <?php foreach ($arrivals as $t): ?>
        <div class="job arrive">
          <div class="room"><?= h($t['roomName']) ?></div>
          <div class="meta">
            <strong>Φτάνει σήμερα</strong><br>
            Μένει έως <?= h(date('d/m/Y', strtotime($t['to']))) ?>
            · <?= $t['nights'] ?> διανυκτερεύσεις
          </div>
          <span class="tag"><?= $t['adults'] ?> <?= $t['adults'] === 1 ? 'άτομο' : 'άτομα' ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($departures): ?>
      <h2 style="font-size:.95rem;color:var(--leave);margin:16px 0 8px">🧳 Αναχωρήσεις</h2>
      <?php foreach ($departures as $t): ?>
        <div class="job leave">
          <div class="room"><?= h($t['roomName']) ?></div>
          <div class="meta">
            <strong>Φεύγει σήμερα</strong><br>
            Έμεινε <?= h(date('d/m', strtotime($t['from']))) ?> → <?= h(date('d/m', strtotime($t['to']))) ?>
            · <?= $t['nights'] ?> διανυκτερεύσεις
          </div>
          <span class="tag"><?= $t['adults'] ?> <?= $t['adults'] === 1 ? 'άτομο' : 'άτομα' ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($staying): ?>
      <h2 style="font-size:.95rem;margin:16px 0 8px">🛏 Μένουν στο δωμάτιο</h2>
      <?php foreach ($staying as $t): ?>
        <div class="job">
          <div class="room"><?= h($t['roomName']) ?></div>
          <div class="meta">
            Διαμονή <?= h(date('d/m', strtotime($t['from']))) ?> → <?= h(date('d/m', strtotime($t['to']))) ?>
            · <?= $t['nights'] ?> διανυκτερεύσεις
            <?php if ($t['cleaning']): ?>
              <br><span style="color:var(--clean)">Καθαριότητα:
                <?= h(implode(', ', array_map(fn($c) => date('d/m', strtotime($c)), $t['cleaning']))) ?></span>
            <?php endif; ?>
          </div>
          <span class="tag"><?= $t['adults'] ?> <?= $t['adults'] === 1 ? 'άτομο' : 'άτομα' ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$cleaning && !$arrivals && !$departures && !$staying): ?>
      <p class="empty">Δεν έχεις κάτι αυτή την ημέρα. 🎉</p>
    <?php endif; ?>
  </div>

  <?php if ($upcoming): ?>
    <div class="card">
      <h2>Οι επόμενες καθαριότητες</h2>
      <div style="overflow-x:auto">
      <table>
        <thead>
          <tr><th>Ημέρα</th><th>Δωμάτιο</th><th>Διαμονή</th><th>Άτομα</th></tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $cd => $list): ?>
            <?php foreach ($list as $i => $t): ?>
              <tr class="<?= $cd === $today ? 'today-row' : '' ?>">
                <?php if ($i === 0): ?>
                  <td rowspan="<?= count($list) ?>">
                    <?= h(d_el2($cd)) ?><?= $cd === $today ? ' (σήμερα)' : '' ?>
                  </td>
                <?php endif; ?>
                <td><strong><?= h($t['roomName']) ?></strong></td>
                <td><?= h(date('d/m', strtotime($t['from']))) ?> → <?= h(date('d/m', strtotime($t['to']))) ?></td>
                <td><?= $t['adults'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p class="hint" style="margin:12px 0 0">
        Η καθαριότητα μπαίνει αυτόματα στη μέση της διαμονής, και μετά κάθε 3 ημέρες.
        Αν χρειαστεί αλλαγή, πες το στον διαχειριστή.
      </p>
    </div>
  <?php endif; ?>

  <div class="card noprint" style="border-left:4px solid var(--accent)">
    <p class="hint" style="margin:0">
      Σε αυτή τη σελίδα <strong>δεν φαίνονται ονόματα, τηλέφωνα ή άλλα στοιχεία
      επισκεπτών</strong> — μόνο δωμάτιο, ημερομηνίες και άτομα.
      Δεν μπορείς να αλλάξεις τίποτα από εδώ.
    </p>
  </div>

</div>
</body>
</html>
