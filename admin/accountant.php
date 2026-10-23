<?php
/* ============================================================================
 *  Λογιστής — ημέρες διαμονής ανά άτομο, με στοιχεία για αποδείξεις
 *
 *  Βλέπει: επίθετο, δωμάτιο, ημερομηνίες, διανυκτερεύσεις, ΑΦΜ, διεύθυνση,
 *  ταυτότητα, τηλέφωνο, πηγή κράτησης, άτομα.
 *  Μπορεί: να δει, να φιλτράρει ανά μήνα, και να κατεβάσει CSV για το
 *  λογιστικό πρόγραμμα.
 *  ΔΕΝ μπορεί: να αλλάξει, να διαγράψει, ή να δει κωδικούς πρόσβασης.
 *
 *  Deploy: upload to  <public_html>/admin/accountant.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('accountant');

$guests = guests_load();

$monthNames = [1=>'Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
               'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];

$years = [];
foreach ($guests as $g) {
    if (preg_match('/^(\d{4})/', (string)($g['stayFrom'] ?? ''), $m)) $years[(int)$m[1]] = true;
}
$years = array_keys($years) ?: [(int)date('Y')];
sort($years);

$selYear  = (int)($_GET['y'] ?? max($years));
$selMonth = (int)($_GET['mo'] ?? 0);   // 0 = όλο το έτος

/* Φίλτρο: κρατήσεις που αγγίζουν τον μήνα (ή το έτος). */
$rows = [];
foreach ($guests as $g) {
    $f = (string)($g['stayFrom'] ?? '');
    $t = (string)($g['stayTo'] ?? '');
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $mf)) continue;
    if ((int)$mf[1] !== $selYear) continue;

    if ($selMonth > 0) {
        $daysIn = (int)date('t', mktime(0, 0, 0, $selMonth, 1, $selYear));
        $ms = sprintf('%04d-%02d-01', $selYear, $selMonth);
        $me = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $daysIn);
        // Μετράει αν κοιμήθηκε έστω μία νύχτα μέσα στον μήνα.
        if (!($f <= $me && $t > $ms)) continue;
    }
    $rows[] = $g;
}
usort($rows, fn($a, $b) => strcmp((string)$a['stayFrom'], (string)$b['stayFrom']));

/* ---------------------------------------------------------------- CSV export */

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="dianykterefseis-'
        . $selYear . ($selMonth ? '-' . sprintf('%02d', $selMonth) : '') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM: χωρίς αυτό το Excel δεν καταλαβαίνει τα ελληνικά.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Επώνυμο','Δωμάτιο','Άφιξη','Αναχώρηση','Διανυκτερεύσεις',
                   'Άτομα','ΑΦΜ','Διεύθυνση','Ταυτότητα/Διαβατήριο','Τηλέφωνο',
                   'Email','Κράτηση από','Σημειώσεις'], ';');
    foreach ($rows as $g) {
        fputcsv($out, [
            (string)($g['surname'] ?? ''),
            ROOMS_G[(string)($g['room'] ?? '')]['el'] ?? '',
            (string)($g['stayFrom'] ?? ''),
            (string)($g['stayTo'] ?? ''),
            guest_nights($g),
            (int)($g['adults'] ?? 1),
            (string)($g['vat'] ?? ''),
            (string)($g['address'] ?? ''),
            (string)($g['idDoc'] ?? ''),
            (string)($g['phone'] ?? ''),
            (string)($g['email'] ?? ''),
            source_label((string)($g['source'] ?? 'direct')),
            (string)($g['notes'] ?? ''),
        ], ';');
    }
    fclose($out);
    exit;
}

$totalNights = 0; $totalGuests = 0;
foreach ($rows as $g) { $totalNights += guest_nights($g); $totalGuests += (int)($g['adults'] ?? 1); }

function d_el(string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : $iso;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Λογιστήριο — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--soft:#f6f8fa;
      --accent:#c2703f;--accent-dark:#a85c30;--good:#1d7a4d;--leave:#b4341f}
*{box-sizing:border-box}
body{margin:0;background:#f2f5f8;color:var(--ink);
     font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1240px;margin:0 auto;padding:24px 18px 64px}
.top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
     flex-wrap:wrap;margin:0 0 20px}
h1{margin:0 0 4px;font-size:1.5rem}
h2{margin:0 0 14px;font-size:1.1rem}
.hint{color:var(--muted);font-size:.85rem;margin:0}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;
      background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;
      min-height:44px;line-height:22px}
.btn-pri{background:var(--accent-dark)!important;border-color:var(--accent-dark)!important;color:#fff!important}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;
      margin:0 0 18px;box-shadow:0 1px 3px rgba(12,24,34,.05)}
select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;font-weight:600;
       font-size:.9rem;min-height:44px;background:#fff;color:var(--ink)}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{font-size:.74rem;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);
   background:var(--soft);font-weight:700;white-space:nowrap}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
tr.total td{font-weight:800;background:#fafcfd;border-top:2px solid var(--line)}
.tbl-scroll{overflow-x:auto}
.role{display:inline-block;background:var(--soft);border:1px solid var(--line);
      border-radius:99px;padding:4px 12px;font-size:.8rem;font-weight:700;color:var(--muted)}
@media(max-width:700px){
  .wrap{padding:18px 12px 48px}
  table{font-size:.82rem}
  th,td{padding:7px 7px}
}
@media print{
  body{background:#fff}.acts,.noprint{display:none!important}
  .card{break-inside:avoid;box-shadow:none}
  th{background:#eee}
}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1>Λογιστήριο</h1>
      <p class="hint">Διαμονές και στοιχεία για έκδοση αποδείξεων.</p>
    </div>
    <div class="acts">
      <span class="role">Λογιστής</span>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <div class="card noprint">
    <form method="get" class="acts">
      <label for="y" style="font-weight:600;font-size:.9rem">Έτος</label>
      <select id="y" name="y" onchange="this.form.submit()">
        <?php foreach ($years as $yy): ?>
          <option value="<?= $yy ?>"<?= $yy === $selYear ? ' selected' : '' ?>><?= $yy ?></option>
        <?php endforeach; ?>
      </select>
      <label for="mo" style="font-weight:600;font-size:.9rem">Μήνας</label>
      <select id="mo" name="mo" onchange="this.form.submit()">
        <option value="0">Όλο το έτος</option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>"<?= $m === $selMonth ? ' selected' : '' ?>><?= h($monthNames[$m]) ?></option>
        <?php endfor; ?>
      </select>
      <noscript><button class="btn" type="submit">Δες</button></noscript>
      <a class="btn btn-pri" href="?y=<?= $selYear ?>&amp;mo=<?= $selMonth ?>&amp;csv=1">⬇ Λήψη CSV</a>
      <button class="btn" type="button" onclick="window.print()">Εκτύπωση</button>
    </form>
  </div>

  <div class="card">
    <h2>
      <?= $selMonth > 0 ? h($monthNames[$selMonth]) . ' ' : 'Έτος ' ?><?= $selYear ?>
      — <?= count($rows) ?> διαμονές
    </h2>
    <p class="hint" style="margin:0 0 14px">
      Σύνολο <strong><?= $totalNights ?></strong> διανυκτερεύσεις,
      <strong><?= $totalGuests ?></strong> άτομα.
      Οι διανυκτερεύσεις βγαίνουν αυτόματα από τις ημερομηνίες.
    </p>
    <div class="tbl-scroll">
    <table>
      <thead>
        <tr>
          <th>Επώνυμο</th>
          <th>Δωμάτιο</th>
          <th>Άφιξη</th>
          <th>Αναχώρηση</th>
          <th class="num">Διαν/σεις</th>
          <th class="num">Άτομα</th>
          <th>ΑΦΜ</th>
          <th>Διεύθυνση</th>
          <th>Ταυτ./Διαβ.</th>
          <th>Τηλέφωνο</th>
          <th>Πηγή</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11" style="text-align:center;color:var(--muted);padding:26px">
            Καμία διαμονή σε αυτή την περίοδο.
          </td></tr>
        <?php else: foreach ($rows as $g): ?>
          <tr>
            <td><strong><?= h((string)($g['surname'] ?? '')) ?></strong></td>
            <td><?= h(ROOMS_G[(string)($g['room'] ?? '')]['el'] ?? '') ?></td>
            <td><?= h(d_el((string)($g['stayFrom'] ?? ''))) ?></td>
            <td><?= h(d_el((string)($g['stayTo'] ?? ''))) ?></td>
            <td class="num"><strong><?= guest_nights($g) ?></strong></td>
            <td class="num"><?= (int)($g['adults'] ?? 1) ?></td>
            <td><?= h((string)($g['vat'] ?? '')) ?: '<span style="color:#b6c0c9">—</span>' ?></td>
            <td><?= h((string)($g['address'] ?? '')) ?: '<span style="color:#b6c0c9">—</span>' ?></td>
            <td><?= h((string)($g['idDoc'] ?? '')) ?: '<span style="color:#b6c0c9">—</span>' ?></td>
            <td><?= h((string)($g['phone'] ?? '')) ?: '<span style="color:#b6c0c9">—</span>' ?></td>
            <td><?= h(source_label((string)($g['source'] ?? 'direct'))) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <?php if ($rows): ?>
          <tr class="total">
            <td colspan="4">Σύνολο</td>
            <td class="num"><?= $totalNights ?></td>
            <td class="num"><?= $totalGuests ?></td>
            <td colspan="5"></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="card noprint" style="border-left:4px solid var(--accent)">
    <p class="hint" style="margin:0">
      <strong>Δεν μπορείς να αλλάξεις τίποτα από εδώ.</strong>
      Αυτή η σελίδα είναι μόνο για ανάγνωση και λήψη. Αν κάτι λείπει ή είναι λάθος,
      πες το στον διαχειριστή να το διορθώσει.
    </p>
  </div>

</div>
</body>
</html>
