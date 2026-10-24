<?php
/* ============================================================================
 *  Κωδικοί προσωπικού — λογιστής και καθαρίστριες
 *
 *  Μόνο ο διαχειριστής. Οι κωδικοί αποθηκεύονται κατακερματισμένοι και δεν
 *  εμφανίζονται ποτέ ξανά: αν ξεχαστεί ένας, βάζεις νέο.
 *
 *  Deploy: upload to  <public_html>/admin/staff.php
 * ========================================================================== */

declare(strict_types=1);

require __DIR__ . '/_lib.php';
require_login('admin');

/* Πρόσθετοι ρόλοι που μπορεί να έχει κάποιος: καθαρίστρια 1/2, λογιστής. */
$assignable = ['accountant', 'cleaner1', 'cleaner2'];

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$fresh = [];   // Κωδικοί που μόλις δημιουργήθηκαν — φαίνονται ΜΙΑ φορά.

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'set') {
        $role = (string)($_POST['role'] ?? '');
        $pw   = (string)($_POST['password'] ?? '');
        if (!in_array($role, $assignable, true)) {
            fail('Λάθος ρόλος.', 'staff.php');
        } elseif (strlen($pw) < 8) {
            fail('Ο κωδικός θέλει τουλάχιστον 8 χαρακτήρες.', 'staff.php');
        } else {
            staff_set($role, $pw)
                ? ok('Ο κωδικός για ' . role_label($role) . ' ορίστηκε.')
                : fail('Δεν μπόρεσα να αποθηκεύσω.', 'staff.php');
        }
        header('Location: staff.php'); exit;
    }

    if ($action === 'generate') {
        $role = (string)($_POST['role'] ?? '');
        if (!in_array($role, $assignable, true)) {
            fail('Λάθος ρόλος.', 'staff.php');
        } else {
            $pw = staff_gen_password();
            if (staff_set($role, $pw)) {
                $_SESSION['fresh'][$role] = $pw;
                ok('Νέος κωδικός για ' . role_label($role) . '. Αντέγραψέ τον τώρα — δεν ξαναδείχνεται.');
            } else {
                fail('Δεν μπόρεσα να αποθηκεύσω.', 'staff.php');
            }
        }
        header('Location: staff.php'); exit;
    }

    if ($action === 'revoke') {
        $role = (string)($_POST['role'] ?? '');
        if (!in_array($role, $assignable, true)) {
            fail('Λάθος ρόλος.', 'staff.php');
        } else {
            staff_set($role, '')
                ? ok('Ο κωδικός για ' . role_label($role) . ' αφαιρέθηκε.')
                : fail('Δεν μπόρεσα να αποθηκεύσω.', 'staff.php');
        }
        header('Location: staff.php'); exit;
    }

    /* Διόρθωση ωρών καθαριότητας (απαίτηση 3: ο διαχειριστής έχει δικαίωμα
       τροποποίησης). Οι καθαρίστριες γράφουν τις ώρες τους στη σελίδα τους· εδώ
       ο διαχειριστής διορθώνει, για οποιαδήποτε ημέρα και οποιοδήποτε δωμάτιο. */
    if ($action === 'hours_edit') {
        $hRole = (string)($_POST['hRole'] ?? '');
        $hDate = (string)($_POST['hDate'] ?? '');
        $hRoom = (string)($_POST['hRoom'] ?? '');
        $hVal  = trim((string)($_POST['hVal'] ?? ''));

        if (!in_array($hRole, ['cleaner1', 'cleaner2'], true)) {
            fail('Λάθος ρόλος.', 'staff.php');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hDate)) {
            fail('Λάθος ημερομηνία.', 'staff.php');
        }
        if (!in_array($hRoom, CLEANER_ROOMS[$hRole] ?? [], true)) {
            fail('Το δωμάτιο δεν ανήκει σε αυτή την καθαρίστρια.', 'staff.php');
        }

        if ($hVal === '') {
            hours_save(hours_set(hours_load(), $hDate, $hRole, $hRoom, 0.0));
            ok('Η καταχώρηση διαγράφηκε.');
        } else {
            $h = hours_norm($hVal);
            if ($h === null) {
                fail('Οι ώρες θέλουν αριθμό, π.χ. 2 ή 2,5 ή 2:30 (έως ' . hours_fmt(HOURS_MAX) . ').', 'staff.php');
            }
            hours_save(hours_set(hours_load(), $hDate, $hRole, $hRoom, $h))
                ? ok('Οι ώρες αποθηκεύτηκαν: ' . hours_fmt($h) . ' ώρες, ' . $hDate . '.')
                : fail('Δεν μπόρεσα να αποθηκεύσω.', 'staff.php');
        }
        header('Location: staff.php?mo=' . urlencode(substr($hDate, 0, 7))); exit;
    }
}

$fresh = $_SESSION['fresh'] ?? [];
unset($_SESSION['fresh']);

/* ------------------------------------------------ ώρες καθαριότητας: ο μήνας
 *
 * Ο διαχειριστής βλέπει όλες τις καταχωρήσεις των καθαριστριών για έναν μήνα και
 * διορθώνει ό,τι χρειάζεται. Ο μήνας έρχεται ως «YYYY-MM»· προεπιλογή ο τρέχων. */

$hMonth = (string)($_GET['mo'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $hMonth)) $hMonth = date('Y-m');
$hYear  = (int)substr($hMonth, 0, 4);
$hMon   = (int)substr($hMonth, 5, 2);
if ($hMon < 1 || $hMon > 12) { $hYear = (int)date('Y'); $hMon = (int)date('n'); $hMonth = date('Y-m'); }

$hoursAll = hours_load();

/* Όλες οι ημέρες του μήνα που έχουν καταχώρηση, από όποια καθαρίστρια. */
$hoursRows = [];
foreach ($hoursAll as $e) {
    if (!is_array($e)) continue;
    $d = (string)($e['date'] ?? '');
    if (!str_starts_with($d, $hMonth . '-')) continue;
    $r = (string)($e['role'] ?? '');
    if (!isset(CLEANER_ROOMS[$r])) continue;
    $hoursRows[$d][$r][(string)($e['room'] ?? '')] = (float)($e['hours'] ?? 0);
}
ksort($hoursRows);

$hoursMonthTotal = hours_month_total($hoursAll, $hYear, $hMon);
$hoursPrev = date('Y-m', mktime(0, 0, 0, $hMon - 1, 1, $hYear));
$hoursNext = date('Y-m', mktime(0, 0, 0, $hMon + 1, 1, $hYear));

$store = store_active();
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Κωδικοί προσωπικού — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--soft:#f6f8fa;
      --accent:#c2703f;--accent-dark:#a85c30;--good:#1d7a4d;--leave:#b4341f}
*{box-sizing:border-box}
body{margin:0;background:#f2f5f8;color:var(--ink);
     font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:860px;margin:0 auto;padding:24px 18px 64px}
.top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
     flex-wrap:wrap;margin:0 0 20px}
h1{margin:0 0 4px;font-size:1.5rem}
h2{margin:0 0 14px;font-size:1.1rem}
h3{margin:0 0 4px;font-size:1rem}
.hint{color:var(--muted);font-size:.85rem;margin:0}
a.btn{text-decoration:none;padding:10px 16px;border:1px solid var(--line);border-radius:10px;
      background:#fff;color:var(--ink);font-weight:600;font-size:.9rem;display:inline-block;
      min-height:44px;line-height:22px}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;
      margin:0 0 18px;box-shadow:0 1px 3px rgba(12,24,34,.05)}
input[type=text],input[type=password]{padding:10px 12px;border:1px solid var(--line);
      border-radius:10px;font-size:.95rem;min-height:44px;font-family:inherit;width:100%}
button{padding:10px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;
       color:var(--ink);font-weight:600;font-size:.9rem;min-height:44px;cursor:pointer;
       font-family:inherit}
button.pri{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
button.danger{color:var(--leave);border-color:#e8c4bc}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.row>div{flex:1 1 200px}
label{display:block;font-weight:600;font-size:.85rem;margin:0 0 6px}
.person{border:1px solid var(--line);border-radius:12px;padding:14px;margin:0 0 12px;background:#fff}
.person.on{border-left:5px solid var(--good)}
.person.off{border-left:5px solid #ccd6de;background:#fbfcfd}
.state{font-size:.8rem;font-weight:700;padding:3px 10px;border-radius:99px;display:inline-block}
.state.on{background:#e6f5ec;color:var(--good)}
.state.off{background:var(--soft);color:var(--muted)}
.fresh{background:#e6f5ec;border:1px solid #b6e0c8;border-radius:12px;padding:14px;
       margin:0 0 16px;font-size:.9rem}
.fresh code{background:#fff;border:1px solid #b6e0c8;border-radius:8px;padding:8px 12px;
            font-size:1.05rem;font-weight:700;letter-spacing:.5px;display:inline-block;
            margin:6px 0;font-family:ui-monospace,Menlo,Consolas,monospace}
.flash{border-left:4px solid var(--good);background:#fff;border:1px solid var(--line);
       border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:.9rem}
/* Τα μεγάλα URLs σπάνε· αλλιώς σπρώχνουν τη σελίδα πλάγια στο κινητό. */
code{word-break:break-all;overflow-wrap:anywhere}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
th{font-size:.74rem;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);
   background:var(--soft);font-weight:700}
tfoot th{background:#fff;border-top:2px solid var(--line);font-size:.8rem;text-transform:none;
         letter-spacing:0}
@media(max-width:560px){.wrap{padding:18px 12px 48px}.row>div{flex:1 1 100%}}
</style>
</head>
<body>
<div class="wrap">
<?php render_notice(); ?>

  <div class="top">
    <div>
      <h1>Κωδικοί προσωπικού</h1>
      <p class="hint">Ξεχωριστός κωδικός για τον λογιστή και για κάθε καθαρίστρια.</p>
    </div>
    <div class="acts">
      <a class="btn" href="guests.php">Links</a>
      <a class="btn" href="calendar.php">Ημερολόγιο</a>
      <a class="btn" href="stats.php">Στατιστικά</a>
      <a class="btn" href="?logout=1">Αποσύνδεση</a>
    </div>
  </div>

  <?php /* Ο διαχειριστής βλέπει τη σελίδα όπως τη βλέπει ο καθένας (απαίτηση 5).
         Ανοίγει σε νέα καρτέλα, ώστε να μη χαθεί η δουλειά του εδώ. */ ?>
  <div class="card">
    <h2>Δες όπως βλέπει ο καθένας</h2>
    <p class="hint" style="margin:0 0 12px">
      Ανοίγει τη σελίδα του ρόλου με τα δικά του δικαιώματα, για να ελέγξεις τι
      βλέπει. Μέσα από εκεί δεν αλλάζεις τίποτα — για αλλαγή, γύρνα εδώ.
    </p>
    <div class="acts">
      <?php foreach ($assignable as $r): ?>
        <a class="btn" target="_blank" rel="noopener"
           href="?as=<?= h($r) ?>"><?= h(role_label($r)) ?> →</a>
      <?php endforeach; ?>
      <a class="btn" target="_blank" rel="noopener" href="?as=admin">Διαχειριστής →</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash" style="border-left-color:<?= $kind === 'err' ? 'var(--leave)' : 'var(--good)' ?>">
      <?= h($msg) ?>
    </div>
  <?php endforeach; ?>

  <?php if ($fresh): ?>
    <div class="fresh">
      <strong>Γράψε αυτούς τους κωδικούς τώρα.</strong>
      Δεν ξαναδείχνονται — ούτε εσύ δεν μπορείς να τους δεις μετά.
      Δώσε στον καθένα τον δικό του.
      <?php foreach ($fresh as $role => $pw): ?>
        <div style="margin-top:10px">
          <div style="font-weight:700"><?= h(role_label((string)$role)) ?></div>
          <code><?= h($pw) ?></code>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Ποιος βλέπει τι</h2>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
      <thead>
        <tr>
          <th style="text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);
                     font-size:.74rem;text-transform:uppercase;color:var(--muted)">Ρόλος</th>
          <th style="text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);
                     font-size:.74rem;text-transform:uppercase;color:var(--muted)">Βλέπει</th>
          <th style="text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);
                     font-size:.74rem;text-transform:uppercase;color:var(--muted)">Αλλάζει</th>
        </tr>
      </thead>
      <tbody style="font-size:.88rem">
        <tr>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)"><strong>Διαχειριστής</strong><br><span class="hint">εσύ</span></td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Τα πάντα</td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Τα πάντα</td>
        </tr>
        <tr>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)"><strong>Λογιστής</strong></td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Επώνυμο, δωμάτιο, ημερομηνίες, διανυκτερεύσεις, ΑΦΜ, διεύθυνση, ταυτότητα, τηλέφωνο, πηγή. Λήψη CSV.</td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Τίποτα</td>
        </tr>
        <tr>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)"><strong>Καθαρίστρια 1</strong><br><span class="hint">Δωμάτια 3 και 4</span></td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Δωμάτιο, ημερομηνίες, άτομα, ημέρες καθαριότητας</td>
          <td style="padding:9px 10px;border-bottom:1px solid var(--line)">Τίποτα</td>
        </tr>
        <tr>
          <td style="padding:9px 10px"><strong>Καθαρίστρια 2</strong><br><span class="hint">Δωμάτια 5 και 6</span></td>
          <td style="padding:9px 10px">Δωμάτιο, ημερομηνίες, άτομα, ημέρες καθαριότητας</td>
          <td style="padding:9px 10px">Τίποτα</td>
        </tr>
      </tbody>
    </table>
    </div>
    <p class="hint" style="margin:14px 0 0">
      Οι καθαρίστριες <strong>δεν βλέπουν κανένα προσωπικό στοιχείο</strong> —
      ούτε όνομα, ούτε τηλέφωνο, ούτε ΑΦΜ.
    </p>
  </div>

  <div class="card">
    <h2>Κωδικοί</h2>
    <?php foreach ($assignable as $role):
        $has = staff_has($role);
        $pages = $role === 'accountant'
            ? 'accountant.php'
            : 'cleaning.php';
        $rooms = CLEANER_ROOMS[$role] ?? [];
    ?>
      <div class="person <?= $has ? 'on' : 'off' ?>">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
          <div>
            <h3><?= h(role_label($role)) ?></h3>
            <p class="hint" style="margin:0">
              Σελίδα: <code><?= h($pages) ?></code>
              <?php if ($rooms): ?>
                · Δωμάτια <?= h(implode(', ', array_map(fn($r) => (string)(ROOMS_G[$r]['el'] ?? $r), $rooms))) ?>
              <?php endif; ?>
            </p>
          </div>
          <span class="state <?= $has ? 'on' : 'off' ?>">
            <?= $has ? 'Έχει κωδικό' : 'Χωρίς κωδικό' ?>
          </span>
        </div>

        <div class="row" style="margin-top:14px">
          <form method="post" class="row" style="flex:1 1 auto;align-items:flex-end">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="role" value="<?= h($role) ?>">
            <div>
              <label for="pw-<?= h($role) ?>"><?= $has ? 'Νέος κωδικός' : 'Βάλε κωδικό' ?></label>
              <input id="pw-<?= h($role) ?>" type="text" name="password"
                     minlength="8" maxlength="72" autocomplete="new-password"
                     placeholder="τουλάχιστον 8 χαρακτήρες">
            </div>
            <div style="flex:0 0 auto">
              <button class="pri" type="submit" name="action" value="set">Ορισμός</button>
            </div>
          </form>

          <form method="post" style="flex:0 0 auto">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="role" value="<?= h($role) ?>">
            <button type="submit" name="action" value="generate">Τυχαίος</button>
          </form>

          <?php if ($has): ?>
            <form method="post" style="flex:0 0 auto"
                  onsubmit="return confirm('Αφαίρεση κωδικού για <?= h(role_label($role)) ?>; Δεν θα μπορεί να μπει.')">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="role" value="<?= h($role) ?>">
              <button class="danger" type="submit" name="action" value="revoke">Αφαίρεση</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p class="hint" style="margin:14px 0 0">
      Ο κωδικός του διαχειριστή (εσύ) αλλάζει από τη σελίδα
      <a href="index.php">Φωτογραφίες</a>, όπως πριν.
    </p>
  </div>

  <div class="card">
    <h2>Πώς το δίνεις στο προσωπικό</h2>
    <ol style="margin:0;padding-left:20px;font-size:.92rem">
      <li>Πάτησε <strong>Τυχαίος</strong> για έναν κωδικό που διαβάζεται εύκολα στο τηλέφωνο.</li>
      <li>Αντέγραψέ τον <em>αμέσως</em> — δεν ξαναδείχνεται.</li>
      <li>Στείλε τον με μήνυμα, μαζί με τη διεύθυνση:
          <code>www.naxosbalcony.gr/admin/accountant.php</code> ή
          <code>.../admin/cleaning.php</code>.</li>
      <li>Όταν κάποιος φύγει από τη δουλειά, πάτησε <strong>Αφαίρεση</strong>.</li>
    </ol>
    <p class="hint" style="margin:12px 0 0">
      Οι κωδικοί φυλάσσονται κατακερματισμένοι στο
      <code><?= h($store['kind'] === 'outside' ? 'naxos-data/staff.json' : 'admin/staff.json') ?></code>,
      που δεν είναι στο GitHub.
    </p>
  </div>

  <?php /* ------------------------------------------- ώρες καθαριότητας, ανά μήνα
     Ο διαχειριστής βλέπει τι έχει γράψει κάθε καθαρίστρια και διορθώνει. Η αλλαγή
     γίνεται πάνω στην ίδια εγγραφή που βλέπει η καθαρίστρια στη σελίδα της. */ ?>
  <div class="card">
    <h2>Ώρες καθαριότητας</h2>
    <p class="hint" style="margin:0 0 14px">
      Ό,τι έχει καταχωρήσει κάθε καθαρίστρια. Διόρθωσε ένα ποσό και πάτησε
      <strong>✓</strong>. Κενό πεδίο σβήνει την καταχώρηση.
    </p>

    <div class="acts" style="margin:0 0 14px">
      <a class="btn" href="?mo=<?= h($hoursPrev) ?>">← <?= h($hoursPrev) ?></a>
      <a class="btn" href="?mo=<?= h(date('Y-m')) ?>">Τρέχων μήνας</a>
      <a class="btn" href="?mo=<?= h($hoursNext) ?>"><?= h($hoursNext) ?> →</a>
      <span class="state on" style="margin-left:auto">
        Σύνολο μήνα: <?= h(hours_fmt($hoursMonthTotal)) ?> ώρες
      </span>
    </div>

    <?php if (!$hoursRows): ?>
      <p class="hint" style="text-align:center;padding:18px;margin:0">
        Δεν υπάρχει καμία καταχώρηση ώρων για τον μήνα <?= h($hMonth) ?>.
      </p>
    <?php else: ?>
      <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Ημέρα</th>
            <?php foreach (['cleaner1', 'cleaner2'] as $cr): ?>
              <?php foreach (CLEANER_ROOMS[$cr] ?? [] as $rm): ?>
                <th><?= h(role_label($cr)) ?><br>
                    <span style="font-weight:400"><?= h((string)(ROOMS_G[$rm]['el'] ?? $rm)) ?></span></th>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <th>Σύνολο ημέρας</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hoursRows as $d => $byRole): $rowTotal = 0.0; ?>
            <tr>
              <td><strong><?= h($d) ?></strong></td>
              <?php foreach (['cleaner1', 'cleaner2'] as $cr): ?>
                <?php foreach (CLEANER_ROOMS[$cr] ?? [] as $rm):
                    $v = (float)($byRole[$cr][$rm] ?? 0);
                    $rowTotal += $v;
                ?>
                  <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                      <input type="hidden" name="action" value="hours_edit">
                      <input type="hidden" name="hRole" value="<?= h($cr) ?>">
                      <input type="hidden" name="hDate" value="<?= h($d) ?>">
                      <input type="hidden" name="hRoom" value="<?= h($rm) ?>">
                      <input type="text" name="hVal" inputmode="decimal" style="width:74px;min-height:38px"
                             value="<?= $v > 0 ? h(hours_fmt($v)) : '' ?>">
                      <button type="submit" class="pri" style="min-height:38px;padding:6px 12px">✓</button>
                    </form>
                  </td>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <td><strong><?= h(hours_fmt($rowTotal)) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Σύνολο μήνα</th>
            <?php foreach (['cleaner1', 'cleaner2'] as $cr): ?>
              <?php foreach (CLEANER_ROOMS[$cr] ?? [] as $rm): ?>
                <th><?= h(hours_fmt(hours_month_total($hoursAll, $hYear, $hMon, $cr, $rm))) ?></th>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <th><?= h(hours_fmt($hoursMonthTotal)) ?></th>
          </tr>
          <tr>
            <th>Ανά άτομο</th>
            <?php foreach (['cleaner1', 'cleaner2'] as $cr): ?>
              <th colspan="<?= max(1, count(CLEANER_ROOMS[$cr] ?? [])) ?>">
                <?= h(role_label($cr)) ?>: <?= h(hours_fmt(hours_month_total($hoursAll, $hYear, $hMon, $cr))) ?>
              </th>
            <?php endforeach; ?>
            <th></th>
          </tr>
        </tfoot>
      </table>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
