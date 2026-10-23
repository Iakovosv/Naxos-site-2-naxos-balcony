<?php
/* ============================================================================
 * Naxos Balcony Apartments — occupancy calendar
 * ============================================================================
 * Two views over the same bookings:
 *
 *   - month grid: rows are days, columns are the four rooms. Each cell shows who
 *     is sleeping there that night, coloured by whether they arrive, stay or leave.
 *   - day list: pick any date and see, room by room, who arrives and who leaves.
 *
 * A guest "occupies" a night when stayFrom <= night < stayTo — the standard
 * hotel convention, so the departure day is free for the next arrival.
 *
 * Deploy: upload to  <public_html>/admin/calendar.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('admin');

$guests = guests_load();

/* ---------------------------------------------------------------- date maths */

$ym = (string)($_GET['m'] ?? date('Y-m'));
if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $mm)) $ym = date('Y-m');
$year = (int)$mm[1]; $month = (int)$mm[2];
if ($month < 1 || $month > 12) { $year = (int)date('Y'); $month = (int)date('n'); $ym = date('Y-m'); }

$first   = sprintf('%04d-%02d-01', $year, $month);
$daysIn  = (int)date('t', strtotime($first));
$prevYm  = date('Y-m', strtotime($first . ' -1 month'));
$nextYm  = date('Y-m', strtotime($first . ' +1 month'));
$today   = date('Y-m-d');

$day = (string)($_GET['d'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = $today;

$monthNames = [1=>'Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
               'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];
$dowShort   = [1=>'Δε','2'=>'Τρ','3'=>'Τε','4'=>'Πε','5'=>'Πα','6'=>'Σα','7'=>'Κυ'];

/** Ο επισκέπτης κοιμάται το βράδυ της $night; */
function occupies(array $g, string $night): bool {
    $f = (string)($g['stayFrom'] ?? '');
    $t = (string)($g['stayTo'] ?? '');
    return $f !== '' && $t !== '' && $night >= $f && $night < $t;
}

/** Ποιος μένει σε κάθε δωμάτιο το βράδυ $night. */
function occupancy_for(array $guests, string $night): array {
    $out = [];
    foreach ($guests as $g) {
        if (!occupies($g, $night)) continue;
        $room = (string)($g['room'] ?? '');
        if ($room === '') continue;
        $out[$room][] = $g;
    }
    return $out;
}

/** Ποιος φεύγει το πρωί της $night — δεν κοιμάται πια, αλλά το δωμάτιο
 *  ελευθερώνεται εκείνη την ημέρα και πρέπει να φαίνεται. */
function departures_for(array $guests, string $night): array {
    $out = [];
    foreach ($guests as $g) {
        if ((string)($g['stayTo'] ?? '') !== $night) continue;
        $room = (string)($g['room'] ?? '');
        if ($room === '') continue;
        $out[$room][] = $g;
    }
    return $out;
}

/** Πόσες διανυκτερεύσεις έχει μια κράτηση. */
function nights(array $g): int {
    $f = strtotime((string)($g['stayFrom'] ?? ''));
    $t = strtotime((string)($g['stayTo'] ?? ''));
    if (!$f || !$t) return 0;
    return max(0, (int)round(($t - $f) / 86400));
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ημερολόγιο — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#16232e;--muted:#5b6b7a;--accent:#a85c30;--accent-2:#2c5282;--soft:#f6f8fb;--line:rgba(16,32,48,.12);
      --arrive:#1f7a45;--arrivebg:#dff3e6;--stay:#2c5282;--staybg:#e4edf8;--leave:#8a6100;--leavebg:#fdf0d5;
      --empty:#fbfcfd;--todaybg:#fff6e0}
*{box-sizing:border-box}
body{margin:0;font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--soft);line-height:1.55}
.wrap{max-width:1180px;margin:0 auto;padding:24px 16px 80px}
h1{font-size:1.4rem;margin:0 0 4px}
h2{font-size:1.05rem;margin:0 0 14px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;margin:16px 0;box-shadow:0 2px 10px rgba(16,32,48,.04)}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;min-height:44px;line-height:22px}
a.btn.pri{background:var(--accent);border-color:var(--accent);color:#fff}
.acts{display:flex;gap:8px;flex-wrap:wrap}
.mnav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 14px;flex-wrap:wrap}
.mnav .m{font-size:1.15rem;font-weight:700}
.jump{display:inline-flex;gap:8px;align-items:center}
.jump select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;
             color:var(--ink);font-weight:600;font-size:.9rem;min-height:44px}
.hint{color:var(--muted);font-size:.85rem}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:.8rem;color:var(--muted);margin:0 0 12px}
.legend span{display:inline-flex;align-items:center;gap:6px}
.sw{width:14px;height:14px;border-radius:4px;border:1px solid var(--line);display:inline-block}
table.cal{width:100%;border-collapse:separate;border-spacing:0;font-size:.82rem;table-layout:fixed}
table.cal th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);padding:6px 4px;font-weight:700;text-align:center}
table.cal th.dayh{width:74px;text-align:left;padding-left:8px}
table.cal td{border-top:1px solid var(--line);padding:0;vertical-align:top;height:42px}
td.daycell{padding:5px 8px;font-weight:700;white-space:nowrap}
td.daycell .dn{font-size:.95rem}
td.daycell .dw{font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase}
tr.today td{background:var(--todaybg)}
tr.today td.daycell{box-shadow:inset 3px 0 0 var(--accent)}
tr.we td.daycell .dw{color:var(--accent)}
.cell{padding:4px;min-height:42px}
.occ{display:block;padding:3px 6px;border-radius:6px;font-size:.74rem;font-weight:600;line-height:1.25;
     overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin:1px 0}
.o-arrive{background:var(--arrivebg);color:var(--arrive);border-left:3px solid var(--arrive)}
.o-stay{background:var(--staybg);color:var(--stay);border-left:3px solid var(--stay)}
.o-leave{background:var(--leavebg);color:var(--leave);border-left:3px solid var(--leave)}
.o-free{color:#c3ccd4;font-size:.72rem;padding:3px 6px;display:block}
.daylist{display:grid;gap:10px}
.drow{display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:start;padding:11px 0;border-top:1px solid var(--line)}
.drow:first-child{border-top:0}
.droom{font-weight:700;font-size:.88rem}
.dinfo{font-size:.9rem}
.dinfo .arr{color:var(--arrive);font-weight:600}
.dinfo .dep{color:var(--leave);font-weight:600}
.dinfo .none{color:var(--muted)}
input[type=date]{padding:9px 11px;border:1px solid var(--line);border-radius:10px;font:inherit;min-height:44px}
button{font:inherit;font-weight:600;border:0;border-radius:10px;padding:11px 18px;cursor:pointer;background:var(--accent);color:#fff;min-height:44px}
.flash{padding:12px 16px;border-radius:10px;margin:12px 0;font-size:.92rem;background:#e8f6ec;border:1px solid #bfe3c9}
@media(max-width:760px){
  table.cal{font-size:.72rem}
  table.cal th.dayh{width:56px}
  td.daycell{padding:4px 5px}
  .occ{font-size:.68rem;padding:2px 4px}
  .drow{grid-template-columns:1fr;gap:2px}
}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1>Ημερολόγιο</h1>
      <p class="hint" style="margin:0">Ποιος μένει πού, ανά ημέρα.</p>
    </div>
    <div class="acts">
      <a class="btn" href="guests.php">Links</a>
      <a class="btn" href="index.php">Φωτογραφίες</a>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash"><?= h($msg) ?></div>
  <?php endforeach; ?>

  <div class="card">
    <div class="mnav">
      <div class="acts">
        <a class="btn" href="?m=<?= h($prevYm) ?>&amp;d=<?= h($day) ?>">←</a>
        <form method="get" class="jump">
          <input type="hidden" name="d" value="<?= h($day) ?>">
          <select name="m" onchange="this.form.submit()" aria-label="Μήνας και έτος">
            <?php
              // Δύο χρόνια πίσω, δύο μπροστά — αρκετά για κρατήσεις και ιστορικό.
              for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 2; $y++) {
                  foreach ($monthNames as $mn => $mname) {
                      $val = sprintf('%04d-%02d', $y, $mn);
                      printf('<option value="%s"%s>%s %d</option>',
                          h($val), $val === $ym ? ' selected' : '', h($mname), $y);
                  }
              }
            ?>
          </select>
          <noscript><button class="btn" type="submit">Πήγαινε</button></noscript>
        </form>
        <a class="btn" href="?m=<?= h(date('Y-m')) ?>&amp;d=<?= h($today) ?>">Σήμερα</a>
        <a class="btn" href="?m=<?= h($nextYm) ?>&amp;d=<?= h($day) ?>">→</a>
      </div>
      <div class="m"><?= h($monthNames[$month]) ?> <?= $year ?></div>
    </div>

    <div class="legend">
      <span><i class="sw" style="background:var(--arrivebg);border-left:3px solid var(--arrive)"></i> Άφιξη</span>
      <span><i class="sw" style="background:var(--staybg);border-left:3px solid var(--stay)"></i> Διανυκτέρευση</span>
      <span><i class="sw" style="background:var(--leavebg);border-left:3px solid var(--leave)"></i> Αναχώρηση</span>
      <span><i class="sw" style="background:#fff4d6;border-left:3px solid #d9a94a"></i> Καθαριότητα</span>
      <span><i class="sw" style="background:var(--empty)"></i> Ελεύθερο</span>
    </div>

    <div style="overflow-x:auto">
    <table class="cal">
      <thead>
        <tr>
          <th class="dayh">Ημέρα</th>
          <?php foreach (ROOMS_G as $k => $r): ?>
            <th><?= h($r['el']) ?></th>
          <?php endforeach; ?>
          <th style="width:64px">Άφ.</th>
          <th style="width:64px">Αναχ.</th>
          <th style="width:78px">Καθαρ.</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $monthArrivals = 0; $monthDepartures = 0; $monthNights = 0;
      for ($d = 1; $d <= $daysIn; $d++):
        $night = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow   = (int)date('N', strtotime($night));
        $occ   = occupancy_for($guests, $night);

        // Αφίξεις/αναχωρήσεις αυτής της ημέρας, από όλα τα δωμάτια.
        $arr = 0; $dep = 0;
        foreach ($guests as $g) {
            if ((string)($g['stayFrom'] ?? '') === $night) $arr++;
            if ((string)($g['stayTo']   ?? '') === $night) $dep++;
        }
        $monthArrivals += $arr; $monthDepartures += $dep;
        foreach ($occ as $list) $monthNights += count($list);
      ?>
        <tr class="<?= $night === $today ? 'today' : '' ?> <?= $dow >= 6 ? 'we' : '' ?>">
          <td class="daycell">
            <span class="dn"><?= $d ?></span>
            <span class="dw"><?= h($dowShort[$dow]) ?></span>
          </td>
          <?php foreach (ROOMS_G as $k => $r): ?>
            <td>
              <div class="cell">
                <?php
                $cellRows = [];
                foreach ($occ[$k] ?? [] as $g) {
                    $isArr = (string)($g['stayFrom'] ?? '') === $night;
                    $cellRows[] = ['o-' . ($isArr ? 'arrive' : 'stay'), (string)($g['surname'] ?? '')];
                }
                // Όποιος φεύγει σήμερα δεν κοιμάται, αλλά το κρατάμε ορατό:
                // αλλιώς το δωμάτιο δείχνει ελεύθερο ενώ καθαρίζεται ακόμη.
                foreach (departures_for($guests, $night)[$k] ?? [] as $g) {
                    $cellRows[] = ['o-leave', (string)($g['surname'] ?? '') . ' ↩'];
                }
                ?>
                <?php if (!$cellRows): ?>
                  <span class="o-free">—</span>
                <?php else: foreach ($cellRows as [$cls, $name]): ?>
                  <span class="occ <?= $cls ?>" title="<?= h($name) ?>"><?= h($name) ?></span>
                <?php endforeach; endif; ?>
              </div>
            </td>
          <?php endforeach; ?>
          <td class="cell" style="text-align:center;font-weight:700;color:<?= $arr ? 'var(--arrive)' : '#c3ccd4' ?>">
            <?= $arr ?: '·' ?>
          </td>
          <td class="cell" style="text-align:center;font-weight:700;color:<?= $dep ? 'var(--leave)' : '#c3ccd4' ?>">
            <?= $dep ?: '·' ?>
          </td>
          <td class="cell" style="text-align:center">
            <?php
              // Ποιες καθαριότητες πέφτουν σήμερα. Δείχνουμε δωμάτιο, όχι όνομα:
              // το ημερολόγιο το βλέπει μόνο ο διαχειριστής, αλλά κρατάμε τη
              // στήλη στενή.
              $clToday = [];
              foreach ($guests as $g) {
                  if (in_array($night, cleaning_dates($g), true)) {
                      $clToday[] = ROOMS_G[(string)$g['room']]['el'] ?? '';
                  }
              }
              $clToday = array_filter($clToday);
            ?>
            <?php if ($clToday): ?>
              <span class="occ" style="background:#fff4d6;color:#7a5a12;border-left:3px solid #d9a94a"
                    title="Καθαριότητα: <?= h(implode(', ', $clToday)) ?>">🧹 <?= count($clToday) ?></span>
            <?php else: ?>
              <span class="o-free">·</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
    </div>

    <p class="hint" style="margin:12px 0 0">
      Σύνολο μήνα: <strong><?= $monthArrivals ?></strong> αφίξεις,
      <strong><?= $monthDepartures ?></strong> αναχωρήσεις,
      <strong><?= $monthNights ?></strong> διανυκτερεύσεις.
      Την ημέρα αναχώρησης το δωμάτιο εμφανίζεται ελεύθερο — ο επισκέπτης φεύγει το πρωί.
    </p>
  </div>

  <div class="card">
    <h2>Μία ημέρα</h2>
    <form method="get" class="acts" style="align-items:end">
      <div>
        <label for="d" style="display:block;font-weight:600;font-size:.88rem;margin-bottom:6px">Διάλεξε ημερομηνία</label>
        <input id="d" type="date" name="d" value="<?= h($day) ?>">
        <input type="hidden" name="m" value="<?= h($ym) ?>">
      </div>
      <div><button type="submit">Δες την ημέρα</button></div>
    </form>

    <div style="margin-top:16px">
      <?php
      $occDay = occupancy_for($guests, $day);
      $arrDay = []; $depDay = [];
      foreach ($guests as $g) {
          if ((string)($g['stayFrom'] ?? '') === $day) $arrDay[] = $g;
          if ((string)($g['stayTo']   ?? '') === $day) $depDay[] = $g;
      }
      $fmt = function (string $iso): string {
          $dt = DateTime::createFromFormat('Y-m-d', $iso);
          if (!$dt) return $iso;
          $m = [1=>'Ιαν','Φεβ','Μάρ','Απρ','Μάι','Ιούν','Ιούλ','Αύγ','Σεπ','Οκτ','Νοέ','Δεκ'];
          return $dt->format('j') . ' ' . $m[(int)$dt->format('n')] . ' ' . $dt->format('Y');
      };
      ?>
      <p class="hint" style="margin:0 0 12px">
        <strong><?= h($fmt($day)) ?></strong> —
        <?= count($arrDay) ?> αφίξεις, <?= count($depDay) ?> αναχωρήσεις,
        <?= array_sum(array_map('count', $occDay)) ?> διανυκτερεύσεις.
      </p>
      <div class="daylist">
        <?php foreach (ROOMS_G as $k => $r): ?>
          <div class="drow">
            <div class="droom"><?= h($r['el']) ?></div>
            <div class="dinfo">
              <?php
              $lines = [];
              foreach ($occDay[$k] ?? [] as $g) {
                  $n = nights($g);
                  $tag = (string)($g['stayFrom'] ?? '') === $day
                       ? '<span class="arr">φτάνει σήμερα</span>'
                       : 'διανυκτερεύει (' . ($n > 0 ? $n . ' νύχτες' : '?') . ')';
                  $lines[] = '<strong>' . h((string)($g['surname'] ?? '')) . '</strong> — ' . $tag
                           . ' <span class="hint">έως ' . h($fmt((string)($g['stayTo'] ?? ''))) . '</span>';
              }
              foreach ($depDay as $g) {
                  if ((string)($g['room'] ?? '') !== $k) continue;
                  $lines[] = '<strong>' . h((string)($g['surname'] ?? '')) . '</strong> — '
                           . '<span class="dep">αναχωρεί σήμερα</span>';
              }
              echo $lines ? implode('<br>', $lines) : '<span class="none">Κανείς</span>';
              ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>
