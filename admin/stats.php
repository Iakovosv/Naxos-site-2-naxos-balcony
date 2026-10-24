<?php
/* ============================================================================
 *  Στατιστικά — ανά μήνα και δωμάτιο
 *
 *  Διανυκτέρευση = ένα βράδυ που κάποιος κοιμάται στο δωμάτιο. Η νύχτα της
 *  αναχώρησης ΔΕΝ μετράει: ο επισκέπτης φεύγει το πρωί.
 *
 *  Deploy: upload to  <public_html>/admin/stats.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('admin');

$guests = guests_load();

/* --------------------------------------------------------------- επιλογή έτους */

$years = [];
foreach ($guests as $g) {
    foreach (['stayFrom', 'stayTo'] as $k) {
        $v = (string)($g[$k] ?? '');
        if (preg_match('/^(\d{4})/', $v, $m)) $years[(int)$m[1]] = true;
    }
}
$years = array_keys($years);
$thisYear = (int)date('Y');
if (!$years) $years = [$thisYear];
sort($years);

$year = (int)($_GET['y'] ?? $thisYear);
if (!in_array($year, $years, true)) $year = max($years);

$monthNames = [1=>'Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
               'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

/* --------------------------------------------------------------- υπολογισμοί */

// Ένας πίνακας ανά μήνα, ώστε να μη ξαναϋπολογίζουμε.
$byMonth = [];
for ($m = 1; $m <= 12; $m++) $byMonth[$m] = month_stats($guests, $year, $m);

$yearTotal = ['nights' => 0, 'arrivals' => 0, 'departures' => 0, 'bookings' => 0,
              'capacity' => 0];
foreach ($byMonth as $st) {
    foreach (['nights','arrivals','departures','bookings','capacity'] as $k) {
        $yearTotal[$k] += $st['total'][$k];
    }
}
$yearTotal['occupancy'] = $yearTotal['capacity'] > 0
    ? round($yearTotal['nights'] / $yearTotal['capacity'] * 100, 1) : 0.0;

// Ανά δωμάτιο για όλο το έτος
$yearRooms = [];
foreach (array_keys(ROOMS_G) as $k) {
    $yearRooms[$k] = ['nights' => 0, 'arrivals' => 0, 'departures' => 0, 'bookings' => 0];
}
foreach ($byMonth as $st) {
    foreach ($st['rooms'] as $k => $r) {
        foreach ($r as $kk => $vv) $yearRooms[$k][$kk] += $vv;
    }
}

// Κατάσταση ανά πηγή κράτησης (χρήσιμο για τον λογιστή)
$bySource = [];
foreach ($guests as $g) {
    $f = (string)($g['stayFrom'] ?? '');
    if (!preg_match('/^(\d{4})/', $f, $mm) || (int)$mm[1] !== $year) continue;
    $s = (string)($g['source'] ?? 'direct');
    $bySource[$s] = ($bySource[$s] ?? 0) + guest_nights($g);
}
arsort($bySource);

// Έσοδα. Μπαίνουν μόνο οι κρατήσεις με καταχωρημένο ποσό· οι υπόλοιπες μετριούνται
// χωριστά, αλλιώς ο τζίρος θα φαινόταν μικρότερος χωρίς εξήγηση.
$rates = fee_rates();
$revMonth = array_fill(1, 12, ['total' => 0.0, 'net' => 0.0, 'vat' => 0.0,
                               'takk' => 0.0, 'municipal' => 0.0]);
$revYear  = ['total' => 0.0, 'net' => 0.0, 'vat' => 0.0, 'takk' => 0.0, 'municipal' => 0.0];
$revMissing = 0;
foreach ($guests as $g) {
    $f = (string)($g['stayFrom'] ?? '');
    if (!preg_match('/^(\d{4})-(\d{2})/', $f, $mm) || (int)$mm[1] !== $year) continue;
    $c = booking_calc($g, $rates);
    if (!$c['has']) { $revMissing++; continue; }
    $m = (int)$mm[2];
    foreach (['total', 'net', 'vat', 'takk', 'municipal'] as $k) {
        $revMonth[$m][$k] += $c[$k];
        $revYear[$k]      += $c[$k];
    }
}

function pct(float $n): string { return number_format($n, 1, ',', '.') . '%'; }
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Στατιστικά — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--soft:#f6f8fa;
      --accent:#c2703f;--accent-dark:#a85c30;--good:#1d7a4d;--arrive:#1d7a4d;--leave:#b4341f}
*{box-sizing:border-box}
body{margin:0;background:#f2f5f8;color:var(--ink);
     font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1100px;margin:0 auto;padding:24px 18px 64px}
.top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
     flex-wrap:wrap;margin:0 0 20px}
h1{margin:0 0 4px;font-size:1.5rem}
h2{margin:0 0 14px;font-size:1.1rem}
.hint{color:var(--muted);font-size:.85rem;margin:0}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;
      background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;
      min-height:44px;line-height:22px}
.acts{display:flex;gap:8px;flex-wrap:wrap}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;
      margin:0 0 18px;box-shadow:0 1px 3px rgba(12,24,34,.05)}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:0 0 18px}
.kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.kpi .n{font-size:1.7rem;font-weight:800;line-height:1.1}
.kpi .l{color:var(--muted);font-size:.82rem;margin-top:2px}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left}
th{font-size:.78rem;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);
   background:var(--soft);font-weight:700;white-space:nowrap}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr.total td{font-weight:800;background:#fafcfd;border-top:2px solid var(--line)}
tr.zero td{color:#b6c0c9}
.bar{height:8px;border-radius:99px;background:var(--soft);overflow:hidden;min-width:60px}
.bar i{display:block;height:100%;background:var(--accent);border-radius:99px}
.badge{display:inline-block;padding:3px 9px;border-radius:99px;font-size:.75rem;font-weight:700}
.tbl-scroll{overflow-x:auto}
@media(max-width:620px){
  .wrap{padding:18px 12px 48px}
  table{font-size:.84rem}
  th,td{padding:7px 7px}
  .kpi .n{font-size:1.4rem}
}
@media print{
  body{background:#fff}.acts,.noprint{display:none!important}
  .card{break-inside:avoid;box-shadow:none}
}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1>Στατιστικά</h1>
      <p class="hint">Διανυκτερεύσεις ανά μήνα και δωμάτιο.</p>
    </div>
    <div class="acts">
      <a class="btn" href="guests.php">Links</a>
      <a class="btn" href="calendar.php">Ημερολόγιο</a>
      <a class="btn" href="staff.php">Κωδικοί</a>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="card" style="border-left:4px solid <?= $kind === 'err' ? 'var(--leave)' : 'var(--good)' ?>">
      <?= h($msg) ?>
    </div>
  <?php endforeach; ?>

  <div class="card noprint">
    <form method="get" class="acts" style="align-items:center">
      <label for="y" style="font-weight:600;font-size:.9rem">Έτος</label>
      <select id="y" name="y" onchange="this.form.submit()"
              style="padding:10px 12px;border:1px solid var(--line);border-radius:10px;
                     font-weight:600;font-size:.9rem;min-height:44px">
        <?php foreach ($years as $yy): ?>
          <option value="<?= $yy ?>"<?= $yy === $year ? ' selected' : '' ?>><?= $yy ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn" type="submit">Δες</button></noscript>
      <button class="btn" type="button" onclick="window.print()">Εκτύπωση</button>
    </form>
  </div>

  <div class="kpis">
    <div class="kpi"><div class="n"><?= $yearTotal['nights'] ?></div><div class="l">Διανυκτερεύσεις <?= $year ?></div></div>
    <div class="kpi"><div class="n"><?= $yearTotal['bookings'] ?></div><div class="l">Κρατήσεις</div></div>
    <div class="kpi"><div class="n"><?= $yearTotal['arrivals'] ?></div><div class="l">Αφίξεις</div></div>
    <div class="kpi"><div class="n"><?= pct($yearTotal['occupancy']) ?></div><div class="l">Πληρότητα</div></div>
    <div class="kpi"><div class="n"><?= h(money($revYear['total'])) ?></div><div class="l">Έσοδα <?= $year ?></div></div>
    <div class="kpi"><div class="n"><?= h(money($revYear['net'])) ?></div><div class="l">Καθαρή αξία</div></div>
    <div class="kpi"><div class="n"><?= h(money($revYear['vat'])) ?></div><div class="l">ΦΠΑ</div></div>
  </div>

  <?php if ($revMissing > 0): ?>
    <p class="hint" style="margin:0 0 16px">
      <strong><?= $revMissing ?></strong>
      <?= $revMissing === 1 ? 'κράτηση δεν έχει' : 'κρατήσεις δεν έχουν' ?>
      καταχωρημένο ποσό, γι' αυτό μένουν έξω από τα έσοδα.
    </p>
  <?php endif; ?>

  <div class="card">
    <h2>Ανά μήνα — όλα τα δωμάτια</h2>
    <div class="tbl-scroll">
    <table>
      <thead>
        <tr>
          <th>Μήνας</th>
          <th class="num">Διανυκτερεύσεις</th>
          <th class="num">Αφίξεις</th>
          <th class="num">Αναχωρήσεις</th>
          <th class="num">Κρατήσεις</th>
          <th class="num">Πληρότητα</th>
          <th class="num">Έσοδα</th>
          <th style="width:120px">Ποσοστό</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($m = 1; $m <= 12; $m++): $st = $byMonth[$m]; ?>
          <tr class="<?= $st['total']['nights'] === 0 ? 'zero' : '' ?>">
            <td><?= h($monthNames[$m]) ?></td>
            <td class="num"><strong><?= $st['total']['nights'] ?></strong></td>
            <td class="num"><?= $st['total']['arrivals'] ?></td>
            <td class="num"><?= $st['total']['departures'] ?></td>
            <td class="num"><?= $st['total']['bookings'] ?></td>
            <td class="num"><?= pct($st['total']['occupancy']) ?></td>
            <td class="num"><?= $revMonth[$m]['total'] > 0 ? h(money($revMonth[$m]['total'])) : '' ?></td>
            <td>
              <div class="bar"><i style="width:<?= min(100, $st['total']['occupancy']) ?>%"></i></div>
            </td>
          </tr>
        <?php endfor; ?>
        <tr class="total">
          <td>Σύνολο <?= $year ?></td>
          <td class="num"><?= $yearTotal['nights'] ?></td>
          <td class="num"><?= $yearTotal['arrivals'] ?></td>
          <td class="num"><?= $yearTotal['departures'] ?></td>
          <td class="num"><?= $yearTotal['bookings'] ?></td>
          <td class="num"><?= pct($yearTotal['occupancy']) ?></td>
          <td class="num"><?= h(money($revYear['total'])) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

  <div class="card">
    <h2>Ανά δωμάτιο — <?= $year ?></h2>
    <div class="tbl-scroll">
    <table>
      <thead>
        <tr>
          <th>Δωμάτιο</th>
          <th class="num">Διανυκτερεύσεις</th>
          <th class="num">Αφίξεις</th>
          <th class="num">Αναχωρήσεις</th>
          <th class="num">Κρατήσεις</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (ROOMS_G as $k => $r): $y = $yearRooms[$k]; ?>
          <tr class="<?= $y['nights'] === 0 ? 'zero' : '' ?>">
            <td><strong><?= h($r['el']) ?></strong></td>
            <td class="num"><?= $y['nights'] ?></td>
            <td class="num"><?= $y['arrivals'] ?></td>
            <td class="num"><?= $y['departures'] ?></td>
            <td class="num"><?= $y['bookings'] ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total">
          <td>Σύνολο</td>
          <td class="num"><?= $yearTotal['nights'] ?></td>
          <td class="num"><?= $yearTotal['arrivals'] ?></td>
          <td class="num"><?= $yearTotal['departures'] ?></td>
          <td class="num"><?= $yearTotal['bookings'] ?></td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

  <div class="card">
    <h2>Ανά μήνα και δωμάτιο — <?= $year ?></h2>
    <p class="hint" style="margin:0 0 12px">Διανυκτερεύσεις. Η παύλα σημαίνει καμία διαμονή.</p>
    <div class="tbl-scroll">
    <table>
      <thead>
        <tr>
          <th>Μήνας</th>
          <?php foreach (ROOMS_G as $k => $r): ?>
            <th class="num"><?= h($r['el']) ?></th>
          <?php endforeach; ?>
          <th class="num">Σύνολο</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($m = 1; $m <= 12; $m++): $st = $byMonth[$m]; ?>
          <tr class="<?= $st['total']['nights'] === 0 ? 'zero' : '' ?>">
            <td><?= h($monthNames[$m]) ?></td>
            <?php foreach (ROOMS_G as $k => $r): $n = $st['rooms'][$k]['nights']; ?>
              <td class="num"><?= $n ?: '—' ?></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= $st['total']['nights'] ?: '—' ?></strong></td>
          </tr>
        <?php endfor; ?>
        <tr class="total">
          <td>Σύνολο</td>
          <?php foreach (ROOMS_G as $k => $r): ?>
            <td class="num"><?= $yearRooms[$k]['nights'] ?></td>
          <?php endforeach; ?>
          <td class="num"><?= $yearTotal['nights'] ?></td>
        </tr>
      </tbody>
    </table>
    </div>
  </div>

  <?php if ($bySource): ?>
    <div class="card">
      <h2>Από πού ήρθαν — <?= $year ?></h2>
      <p class="hint" style="margin:0 0 12px">
        Διανυκτερεύσεις ανά κανάλι κράτησης. Χρήσιμο για να ξέρεις τι σου φέρνει δουλειά.
      </p>
      <div class="tbl-scroll">
      <table>
        <thead>
          <tr><th>Πηγή</th><th class="num">Διανυκτερεύσεις</th><th class="num">Μερίδιο</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bySource as $s => $n): ?>
            <tr>
              <td><?= h(source_label((string)$s)) ?></td>
              <td class="num"><?= $n ?></td>
              <td class="num"><?= pct($yearTotal['nights'] > 0 ? $n / $yearTotal['nights'] * 100 : 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
