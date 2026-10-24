<?php
/* ============================================================================
 * Naxos Balcony Apartments — shared admin library
 * ============================================================================
 * Authentication, CSRF, login throttling and the guest-link store.
 *
 * Used by guests.php. index.php (the gallery admin) still carries its own copy
 * of the auth code; that is deliberate, so a change here cannot break a tool
 * that is already working. Both share credentials.php, and the admin lockout
 * file, so a failed login on either counts against the other. Staff logins have
 * their own lockout file per role: the accountant running out of attempts must
 * not lock the cleaners out.
 *
 * Deploy: upload to  <public_html>/admin/_lib.php
 * ========================================================================== */

declare(strict_types=1);

// Πρώτα το _captcha.php: ορίζει το request_is_https(), που χρειάζεται παρακάτω.
require_once __DIR__ . '/_captcha.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Πίσω από nginx το $_SERVER['HTTPS'] μπορεί να λείπει· το request_is_https()
        // κοιτάζει και το X-Forwarded-Proto (δες _captcha.php).
        'secure'   => request_is_https(),
    ]);
    session_start();
}

const ADMIN_DIR   = __DIR__;
const CRED_FILE   = ADMIN_DIR . '/credentials.php';
const THROTTLE_F  = ADMIN_DIR . '/.login-attempts.json';
const MAX_ATTEMPTS = 5;
const WINDOW       = 900;   // 15 λεπτά
const LOCKOUT      = 900;   // 15 λεπτά αρχικό κλείδωμα

const ROOMS_G = [
    'room3' => ['el' => 'Room 3', 'en' => 'Room 3'],
    'room4' => ['el' => 'Room 4', 'en' => 'Room 4'],
    'room5' => ['el' => 'Room 5', 'en' => 'Room 5'],
    'room6' => ['el' => 'Room 6', 'en' => 'Room 6'],
];

/* ------------------------------------------------------------------ helpers */

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    // Το κενό token απορρίπτεται ρητά: χωρίς αυτόν τον έλεγχο το hash_equals('','')
    // επιστρέφει true, οπότε ένα POST χωρίς session ταίριαζε με άδειο session token.
    $sent = (string)($_POST['csrf'] ?? '');
    $have = (string)($_SESSION['csrf'] ?? '');
    if ($sent === '' || $have === '' || !hash_equals($have, $sent)) {
        http_response_code(400);
        exit('Bad request.');
    }
}

function logged_in(): bool { return !empty($_SESSION['ok']); }

function current_role(): string { return (string)($_SESSION['role'] ?? ''); }
function is_admin(): bool { return current_role() === 'admin'; }

/** Οι ρόλοι που ορίζει ο διαχειριστής από τη σελίδα staff.php. */
const STAFF_ROLES = ['accountant', 'cleaner1', 'cleaner2'];

/** Ο ρόλος που βλέπει ο χρήστης τώρα — ίσως «δανεικός» από τον διαχειριστή.
 *
 *  Ο διαχειριστής μπορεί να δει μια σελίδα όπως τη βλέπει ένας άλλος ρόλος
 *  (απαίτηση 5). Δεν αγγίζουμε το $_SESSION['role']: κρατάμε τον πραγματικό ρόλο
 *  και ένα ξεχωριστό πεδίο, ώστε ο πραγματικός ρόλος να μένει πάντα 'admin'.
 *  Έτσι μια πειραγμένη τιμή στο URL δεν μπορεί να δώσει δικαιώματα, και ο
 *  διαχειριστής μπορεί πάντα να γυρίσει πίσω. */
function view_role(): string {
    if (current_role() !== 'admin') return current_role();
    $as = (string)($_SESSION['view_as'] ?? '');
    return in_array($as, STAFF_ROLES, true) ? $as : 'admin';
}

/** Είμαστε σε λειτουργία «βλέπω ως»; */
function viewing_as(): bool {
    return current_role() === 'admin' && view_role() !== 'admin';
}

/** Πού πάει ο καθένας μετά τη σύνδεση. */
function home_for_role(string $role): string {
    return match ($role) {
        'accountant'          => 'accountant.php',
        'cleaner1','cleaner2' => 'cleaning.php',
        default               => 'guests.php',
    };
}

function role_label(string $role): string {
    return [
        'admin'      => 'Διαχειριστής',
        'accountant' => 'Λογιστής',
        'cleaner1'   => 'Καθαρίστρια 1',
        'cleaner2'   => 'Καθαρίστρια 2',
    ][$role] ?? $role;
}

function fail(string $m, string $to = 'guests.php'): void {
    $_SESSION['flash'][] = ['err', $m]; header('Location: ' . $to); exit;
}
function ok(string $m): void { $_SESSION['flash'][] = ['ok', $m]; }

/* ---------------------------------------------------------------- throttling
 *
 * Ίδια λογική με το index.php: μετά από MAX_ATTEMPTS αποτυχίες μέσα σε WINDOW
 * δευτερόλεπτα, το login κλειδώνει για LOCKOUT δευτερόλεπτα, και το διάστημα
 * διπλασιάζεται σε κάθε νέο κύκλο.
 *
 * ΤΟ ΚΛΕΙΔΩΜΑ ΕΙΝΑΙ ΞΕΧΩΡΙΣΤΟ ΑΝΑ ΡΟΛΟ. Το αρχείο του διαχειριστή μένει στο
 * .login-attempts.json, που το μοιράζεται με το gallery admin (index.php) όπως
 * πριν. Κάθε ρόλος προσωπικού παίρνει δικό του αρχείο: ο λογιστής που ξόδεψε τις
 * προσπάθειές του δεν πρέπει να κλειδώσει την καθαρίστρια. */

function throttle_file(?string $role = null): string {
    if ($role === null || $role === '' || $role === 'admin') return THROTTLE_F;
    $safe = preg_replace('/[^a-z0-9]/', '', strtolower($role));
    return ADMIN_DIR . '/.login-attempts-' . $safe . '.json';
}

function throttle_state(?string $role = null): array {
    $f = throttle_file($role);
    if (!is_file($f)) return ['fails' => [], 'until' => 0, 'cycles' => 0];
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d + ['fails' => [], 'until' => 0, 'cycles' => 0]
                        : ['fails' => [], 'until' => 0, 'cycles' => 0];
}

function throttle_save(array $d, ?string $role = null): void {
    @file_put_contents(throttle_file($role), json_encode($d), LOCK_EX);
    @chmod(throttle_file($role), 0600);
}

function throttle_wait(?string $role = null): int {
    $d = throttle_state($role);
    $left = (int)$d['until'] - time();
    return $left > 0 ? $left : 0;
}

function throttle_fail(?string $role = null): void {
    $now = time();
    $d = throttle_state($role);
    $d['fails'] = array_values(array_filter((array)$d['fails'], fn($t) => $now - (int)$t < WINDOW));
    $d['fails'][] = $now;
    if (count($d['fails']) >= MAX_ATTEMPTS) {
        $d['cycles'] = (int)($d['cycles'] ?? 0) + 1;
        $d['until']  = $now + LOCKOUT * (2 ** min($d['cycles'] - 1, 4));
        $d['fails']  = [];
    }
    throttle_save($d, $role);
}

function throttle_clear(?string $role = null): void { @unlink(throttle_file($role)); }

/** Έλεγχος κωδικού και, αν ταιριάζει, επιστροφή του ρόλου.
 *
 *  Σειρά ελέγχου:
 *   1. Ο κωδικός του διαχειριστή (credentials.php) — πάντα ισχύει.
 *   2. Οι πρόσθετοι κωδικοί από το staff.json (λογιστής, καθαρίστριες).
 *  Το staff.json φυλάσσεται δίπλα στο credentials.php και ΔΕΝ είναι στο repo.
 *
 *  Η σύγκριση γίνεται πάντα με password_verify, ακόμη κι αν ο χρήστης δεν
 *  υπάρχει, ώστε ο χρόνος απάντησης να μη δείχνει ποιοι κωδικοί ισχύουν. */
function cred_hash(): string {
    if (!is_file(CRED_FILE)) return '';
    $creds = @include CRED_FILE;
    if (!is_array($creds)) return '';
    // Δεκτό και το παλιό κλειδί 'pass': αρχεία από παλιότερες εκδόσεις το χρησιμοποιούν.
    foreach (['hash', 'pass'] as $k) {
        $h = (string)($creds[$k] ?? '');
        if ($h !== '') return $h;
    }
    return '';
}

/** Το admin έχει στηθεί; Κρίνουμε από το περιεχόμενο, όχι από την ύπαρξη του
 *  αρχείου: ένα αρχείο χωρίς έγκυρο κλειδί θα έκλεινε την σελίδα ρύθμισης και θα
 *  άφηνε τον ιδιοκτήτη χωρίς κανέναν τρόπο να το διορθώσει. */
function admin_configured(): bool { return cred_hash() !== ''; }

function verify_login(string $pw): ?string {
    $adminHash = cred_hash();
    $staff = staff_load();

    $hit = $adminHash !== '' && password_verify($pw, $adminHash) ? 'admin' : null;
    foreach ($staff as $s) {
        $h = (string)($s['hash'] ?? '');
        if ($h === '') continue;
        // Χωρίς βραχυκύκλωμα: ελέγχονται όλοι, ώστε ο χρόνος να μην προδίδει.
        if (password_verify($pw, $h) && $hit === null) {
            $hit = (string)($s['role'] ?? '');
        }
    }
    return $hit !== '' ? $hit : null;
}

/** Σελίδα σύνδεσης, κοινή για κάθε σελίδα που τη χρειάζεται.
 *
 *  Ζωγραφίζει μια αυτόνομη σελίδα — δεν εξαρτάται από το CSS της σελίδας που τη
 *  κάλεσε, ώστε ο λογιστής και η καθαρίστρια να βλέπουν δική τους φόρμα χωρίς να
 *  ξαναγραφτεί το κάθε περιβάλλον. Δημιουργεί νέα πράξη κάθε φορά που τυπώνεται. */
function render_login_page(string|array|null $need = null): void {
    $c = captcha_new();

    $who = 'Διαχειριστής';
    if (is_array($need) && $need !== []) {
        $who = implode(' / ', array_map('role_label', $need));
    } elseif (is_string($need) && $need !== '') {
        $who = role_label($need);
    }

    /* Ένα χρώμα ανά ρόλο. Οι σελίδες σύνδεσης ήταν πανομοιότυπες — ίδιος τίτλος,
       ίδια εμφάνιση — οπότε δεν φαινόταν σε ποιανού τη σελίδα βρίσκεσαι. */
    $tone = str_starts_with($who, 'Λογιστ') ? '#2f6f4f'
          : (str_starts_with($who, 'Καθαρίστρ') ? '#2b5f8f' : '#c2703f');

    header('X-Robots-Tag: noindex, nofollow, noarchive');
    ?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Σύνδεση <?= h($who) ?> — Naxos Balcony</title>
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--accent:#c2703f;--expbg:#fdecec}
*{box-sizing:border-box}
body{margin:0;background:#f2f5f8;color:var(--ink);
     font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:420px;margin:0 auto;padding:48px 16px}
h1{margin:0 0 4px;font-size:1.35rem}
.hint{color:var(--muted);font-size:.86rem;margin:0 0 18px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;
      box-shadow:0 1px 3px rgba(12,24,34,.05)}
label{display:block;font-weight:600;font-size:.86rem;margin:0 0 6px}
input[type=password],input[type=text]{width:100%;padding:10px 12px;border:1px solid var(--line);
      border-radius:10px;font:inherit;background:#fff;min-height:44px}
button{font:inherit;font-weight:600;border:0;border-radius:10px;padding:11px 20px;
       cursor:pointer;background:var(--accent);color:#fff;min-height:44px;width:100%;margin-top:14px}
.flash{padding:12px 16px;border-radius:10px;margin:0 0 14px;font-size:.9rem;
       background:var(--expbg);border:1px solid #f2c7c7}
.q{font-weight:700;font-size:1rem;background:#f6f8fa;border:1px solid var(--line);
   border-radius:10px;padding:10px 12px;margin:0 0 8px}
.back{display:inline-block;margin-top:14px;color:var(--muted);font-size:.86rem}
.brand{margin:0 0 6px;font-size:.76rem;font-weight:700;letter-spacing:.09em;
       text-transform:uppercase;color:var(--muted)}
h1{color:var(--tone)}
</style>
</head>
<body style="--tone:<?= h($tone) ?>">
<div class="wrap">
  <p class="brand">Naxos Balcony Apartments</p>
  <h1><?= h($who) ?></h1>
  <p class="hint">Σύνδεση προσωπικού</p>
  <div class="card" style="border-top:5px solid var(--tone)">
    <?php if (!empty($_SESSION['login_error'])): ?>
      <div class="flash"><?= h((string)$_SESSION['login_error']) ?></div>
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>
    <?php if (!admin_configured()): ?>
      <div class="flash">
        Δεν έχει οριστεί ακόμη κωδικός διαχειριστή. Άνοιξε το
        <a href="index.php">gallery admin</a> και όρισε έναν.
      </div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="captcha_id" value="<?= h($c['id']) ?>">
        <label for="p">Κωδικός</label>
        <input id="p" type="password" name="password" required
               autocomplete="current-password" autofocus>
        <label for="cap" style="margin-top:14px">Πόσο κάνει;</label>
        <div class="q"><?= h($c['q']) ?> = ?</div>
        <input id="cap" type="text" name="captcha_answer" required inputmode="numeric"
               autocomplete="off" placeholder="Γράψε τον αριθμό">
        <button type="submit">Σύνδεση</button>
      </form>
    <?php endif; ?>
  </div>
  <a class="back" href="../index.html">← Πίσω στο site</a>
</div>
</body>
</html><?php
}

/** Μήνυμα που ταξιδεύει με τη συνεδρία ως την επόμενη σελίδα.
 *
 *  Χρησιμοποιείται όταν ένας συνδεδεμένος χρήστης ανοίξει σελίδα άλλου ρόλου:
 *  αντί να πεταχτεί σιωπηλά πίσω στη δική του (και να νομίζει ότι «όλα τα link
 *  δείχνουν το ίδιο»), φτάνει εκεί με εξήγηση. */
function notice_set(string $m): void { $_SESSION['notice'] = $m; }

function render_notice(): void {
    if (empty($_SESSION['notice'])) return;
    $m = (string)$_SESSION['notice'];
    unset($_SESSION['notice']);
    echo '<div class="card noprint" style="border-left:4px solid var(--accent);'
       . 'margin:0 0 16px">' . h($m) . '</div>';
}

/** Πρέπει να κληθεί μετά το session_start() και πριν τυπωθεί οποιοδήποτε HTML.
 *
 *  $need    : null = οποιοσδήποτε συνδεδεμένος· αλλιώς ένας ρόλος ή λίστα ρόλων.
 *  $throttle: ποιο αρχείο κλειδώματος μετράει τις αποτυχίες αυτής της φόρμας.
 *             Ξεχωριστό ανά ομάδα σελίδων, ώστε οι αποτυχίες του λογιστή να μη
 *             κλειδώνουν την καθαρίστρια. Οι σελίδες του διαχειριστή κρατούν το
 *             κοινό αρχείο με το gallery admin, όπως πριν. */
function require_login(string|array|null $need = null, string $throttle = 'admin'): void {
    $self = (string)($_SERVER['PHP_SELF'] ?? '/admin/guests.php');

    /* Καμία σελίδα προσωπικού δεν ανήκει στις μηχανές αναζήτησης (απαίτηση 6).
       Η κεφαλίδα μπαίνει εδώ, οπότε ισχύει για κάθε σελίδα που καλεί τη
       συνάρτηση — και για τη φόρμα σύνδεσης, που απαντά στον ασύνδετο. */
    header('X-Robots-Tag: noindex, nofollow, noarchive');

    /* «Βλέπω ως…» — μόνο ο διαχειριστής. Ο πραγματικός ρόλος δεν αλλάζει ποτέ. */
    if (isset($_GET['as'])) {
        if (is_admin()) {
            $as = (string)$_GET['as'];
            if (in_array($as, STAFF_ROLES, true)) {
                $_SESSION['view_as'] = $as;
                header('Location: ' . home_for_role($as)); exit;
            }
            // «?as=admin» ή άκυρη τιμή: γύρνα στον διαχειριστή.
            unset($_SESSION['view_as']);
            header('Location: ' . home_for_role('admin')); exit;
        }
        header('Location: ' . $self); exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $wait = throttle_wait($throttle);
        if ($wait > 0) {
            $_SESSION['login_error'] = 'Πολλές προσπάθειες. Δοκίμασε σε ' . (int)ceil($wait / 60) . ' λεπτά.';
        } elseif (!captcha_check((string)($_POST['captcha_answer'] ?? ''), (string)($_POST['captcha_id'] ?? ''))) {
            // Λάθος πράξη: δεν μετράει ως αποτυχημένος κωδικός — δεν ξέρουμε καν
            // ποιος ρόλος είναι. Απλώς δεν προχωράμε σε έλεγχο κωδικού.
            $_SESSION['login_error'] = 'Λάθος απάντηση στην πράξη. Δοκίμασε ξανά.';
        } else {
            $role = verify_login((string)($_POST['password'] ?? ''));
            if ($role !== null) {
                throttle_clear($throttle);
                if ($role !== 'admin') throttle_clear($role);
                session_regenerate_id(true);
                $_SESSION['ok']   = true;
                $_SESSION['role'] = $role;
                unset($_SESSION['view_as'], $_SESSION['login_error']);
                header('Location: ' . home_for_role($role)); exit;
            }
            throttle_fail($throttle);
            $_SESSION['login_error'] = 'Λάθος κωδικός.';
        }
        header('Location: ' . $self); exit;
    }

    if (isset($_GET['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: guests.php'); exit;
    }

    /* Ο ΑΣΥΝΔΕΤΟΣ ΔΕΝ ΠΡΟΧΩΡΑΕΙ ΠΟΤΕ.
       Εδώ ήταν το κενό: η παλιά έκδοση ήλεγχε μόνο μήπως ένας ΣΥΝΔΕΔΕΜΕΝΟΣ μπήκε
       σε σελίδα άλλου ρόλου, και άφηνε τον ασύνδετο να δει τη σελίδα κανονικά.
       Οι σελίδες που δεν τύπωναν φόρμα σύνδεσης (λογιστής, ημερολόγιο, στατιστικά)
       έδειχναν τα δεδομένα των επισκεπτών σε οποιονδήποτε ήξερε τη διεύθυνση. */
    if (!logged_in()) {
        render_login_page($need);
        exit;
    }

    // Ένας συνδεδεμένος χρήστης δεν έχει δουλειά σε σελίδα άλλου ρόλου: τον
    // στέλνουμε εκεί που ανήκει, αντί να του δείξουμε σελίδα που δεν επιτρέπεται.
    if ($need !== null) {
        $role = view_role();
        $allowed = is_array($need) ? $need : [$need];
        // ΕΞΑΙΡΕΣΗ — Ο ΔΙΑΧΕΙΡΙΣΤΗΣ ΣΕ «ΒΛΕΠΩ ΩΣ». Χωρίς αυτό, ο διαχειριστής
        // παγιδεύεται: ανοίγει το ?as=cleaner1 και μετά κάθε σελίδα του διαχειριστή
        // τον πετάει πίσω στη σελίδα της καθαρίστριας, χωρίς τρόπο να βγει. Μόλις
        // βρεθεί σε σελίδα που δεν είναι του ρόλου που βλέπει, βγαίνει από τη
        // λειτουργία και ξαναγίνεται διαχειριστής.
        if (!in_array($role, $allowed, true) && is_admin()) {
            unset($_SESSION['view_as']);
            $role = 'admin';
        }
        if (!in_array($role, $allowed, true)) {
            /* Ο χρήστης άνοιξε σελίδα άλλου ρόλου. Του το λέμε, αντί να τον
               πετάξουμε σιωπηλά πίσω — αλλιώς μοιάζει με «όλα τα link δείχνουν
               το ίδιο». */
            $for = implode(' / ', array_map('role_label', $allowed));
            notice_set('Είσαι συνδεδεμένος ως ' . role_label($role) . '. '
                     . 'Η σελίδα που άνοιξες προορίζεται για: ' . $for . '. '
                     . 'Σε πήγα στη δική σου σελίδα.');
            header('Location: ' . home_for_role($role)); exit;
        }
    }
}

/* ============================================================== staff logins
 *
 * Ξεχωριστοί κωδικοί για λογιστή και καθαρίστριες, δίπλα στο credentials.php.
 * Κάθε κωδικός είναι κατακερματισμένος· το αρχείο δεν είναι στο repo.
 *
 * Οι ρόλοι:
 *   accountant  βλέπει στοιχεία επισκεπτών και διανυκτερεύσεις (για αποδείξεις)
 *   cleaner1/2  βλέπουν ΜΟΝΟ δωμάτιο, ημερομηνίες και καθαριότητα — κανένα
 *               προσωπικό στοιχείο. Η διαφορά είναι στον κώδικα της σελίδας
 *               τους, που δεν φορτώνει ποτέ τα πεδία αυτά.
 *
 * Επεξεργασία: μόνο από τη σελίδα staff.php, μόνο από τον διαχειριστή. */

const STAFF_FILE = ADMIN_DIR . '/staff.json';   // παλιά θέση — μόνο για μετάβαση

/* Οι κωδικοί προσωπικού ΔΕΝ πρέπει να σερβίρονται ως αρχείο. Ένα .json μέσα στο
 * site το δίνει ο nginx αυτούσιο σε όποιον ξέρει το όνομα, κατακερματισμένους
 * κωδικούς συμπεριλαμβανομένων. Χρησιμοποιούμε την ίδια λογική με τους
 * επισκέπτες: έξω από το public_html, αλλιώς ένα .php που δεν τυπώνει τίποτα. */

function staff_paths(): array {
    $outside = dirname(dirname(ADMIN_DIR)) . '/naxos-data';
    return [
        'outside_dir'  => $outside,
        'outside_file' => $outside . '/staff.json',
        'inside_file'  => ADMIN_DIR . '/staff-data.php',
        'legacy_file'  => STAFF_FILE,
    ];
}

function staff_store_active(): array {
    $p = staff_paths();
    if (is_file($p['outside_file'])) return ['file' => $p['outside_file'], 'kind' => 'outside'];
    if (is_dir($p['outside_dir']) && is_writable($p['outside_dir'])) {
        return ['file' => $p['outside_file'], 'kind' => 'outside'];
    }
    return ['file' => $p['inside_file'], 'kind' => 'inside'];
}

function staff_load(): array {
    $a = staff_paths();
    $src = null;

    $active = staff_store_active();
    if (is_file($active['file'])) {
        $src = $active['file'];
    } elseif (is_file($a['legacy_file'])) {
        // Μετάβαση από την παλιά θέση: τη διαβάζουμε μία φορά και τη σβήνουμε,
        // αλλιώς ο server συνεχίζει να τη σερβίρει.
        $src = $a['legacy_file'];
    }
    if ($src === null) return [];

    $raw = (string)@file_get_contents($src);
    if (str_ends_with($src, '.php')) {
        $i = strpos($raw, STORE_OPEN);
        $j = strrpos($raw, STORE_CLOSE);
        if ($i === false || $j === false || $j < $i) return [];
        $raw = substr($raw, $i + strlen(STORE_OPEN), $j - $i - strlen(STORE_OPEN));
    }
    $d = json_decode((string)$raw, true);
    $staff = is_array($d['staff'] ?? null) ? $d['staff'] : [];

    if ($src === $a['legacy_file']) {
        // Ξαναγράφουμε στη σωστή θέση πριν διαγράψουμε το εκτεθειμένο αρχείο.
        if ($staff !== []) staff_save($staff);
        @unlink($a['legacy_file']);
    }
    return $staff;
}

function staff_save(array $staff): bool {
    $active = staff_store_active();
    $json = json_encode(['staff' => array_values($staff)],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = $active['kind'] === 'inside'
        ? "<?php\n/* Κωδικοί προσωπικού, κατακερματισμένοι. Η PHP το εκτελεί και δεν\n"
        . "   τυπώνει τίποτα. Μην το επεξεργαστείς με το χέρι: το διαχειρίζεται\n"
        . "   το admin/staff.php */\n"
        . "return " . STORE_OPEN . $json . STORE_CLOSE . "\n"
        : (string)$json;

    $tmp = $active['file'] . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $active['file'])) { @unlink($tmp); return false; }
    @chmod($active['file'], 0600);
    return true;
}

function staff_set(string $role, string $password): bool {
    $staff = array_values(array_filter(staff_load(), fn($s) => ($s['role'] ?? '') !== $role));
    if ($password !== '') {
        $staff[] = ['role' => $role, 'hash' => password_hash($password, PASSWORD_DEFAULT)];
    }
    return staff_save($staff);
}

function staff_has(string $role): bool {
    foreach (staff_load() as $s) if (($s['role'] ?? '') === $role) return true;
    return false;
}

/** Τυχαίος κωδικός που διαβάζεται εύκολα στο τηλέφωνο. */
function staff_gen_password(int $len = 10): string {
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $abc[random_int(0, strlen($abc) - 1)];
    return $out;
}

/* ======================================================== booking sources */

/* Αρχικός προεπιλεγμένος κωδικός WiFi — μόνο εφεδρική τιμή. Ο πραγματικός
 * προεπιλεγμένος είναι ρύθμιση (δες wifi_default() παρακάτω), γιατί ο κωδικός
 * του ρούτερ αλλάζει κατά διαστήματα και δεν πρέπει να απαιτεί επεξεργασία
 * κώδικα. Αυτή η σταθερά ισχύει μέχρι να αποθηκευτεί ρύθμιση. */
const DEFAULT_WIFI_PASSWORD = 'NAXOS2027';

const BOOKING_SOURCES = [
    'direct'  => 'Απευθείας',
    'booking' => 'Booking.com',
    'airbnb'  => 'Airbnb',
    'other'   => 'Άλλο',
];

function source_label(string $s): string {
    return BOOKING_SOURCES[$s] ?? BOOKING_SOURCES['other'];
}

/* ======================================================== cleaning schedule
 *
 * Κανόνας: καθαριότητα κάθε 3 ημέρες. Για διαμονή 4 ημερών καθαρίζουμε τη 2η
 * ημέρα — δηλαδή στο μέσο της διαμονής, ώστε ο επισκέπτης να μη βρει βρώμικο
 * δωμάτιο στο τέλος.
 *
 * Πίνακας ημερών διαμονής -> ημέρα καθαριότητας (1 = ημέρα άφιξης):
 *   1-2 διανυκτερεύσεις : καμία (πολύ σύντομη διαμονή)
 *   3-4                 : 2η ημέρα
 *   5-7                 : 3η ημέρα
 *   8-10                : 3η και 6η
 *   11+                 : κάθε 3 ημέρες από την 3η
 *
 * Ο διαχειριστής μπορεί να αλλάξει τις ημέρες ανά κράτηση (cleaningDays). */

function nights_between(string $from, string $to): int {
    $f = strtotime($from); $t = strtotime($to);
    if (!$f || !$t || $t <= $f) return 0;
    return (int)round(($t - $f) / 86400);
}

/** Οι ημέρες καθαριότητας, ως αριθμός ημέρας από την άφιξη (1 = άφιξη). */
function default_cleaning_days(int $nights): array {
    if ($nights <= 2) return [];
    if ($nights <= 4) return [2];
    $days = [];
    for ($d = 3; $d < $nights; $d += 3) $days[] = $d;
    return $days;
}

/** Οι πραγματικές ημερομηνίες καθαριότητας μιας κράτησης.
 *  Αν ο διαχειριστής έχει ορίσει ημέρες, αυτές υπερισχύουν. */
function cleaning_dates(array $g): array {
    $from = (string)($g['stayFrom'] ?? '');
    $to   = (string)($g['stayTo'] ?? '');
    if ($from === '' || $to === '') return [];
    $nights = nights_between($from, $to);
    if ($nights <= 0) return [];

    // cleaningDays: λίστα με αριθμούς ημέρας. null/λείπει = αυτόματο.
    if (array_key_exists('cleaningDays', $g) && is_array($g['cleaningDays'])) {
        $days = array_values(array_filter(array_map('intval', $g['cleaningDays']), fn($d) => $d >= 1 && $d <= $nights));
        sort($days);
    } else {
        $days = default_cleaning_days($nights);
    }

    $base = strtotime($from);
    $out = [];
    foreach ($days as $d) $out[] = date('Y-m-d', $base + ($d - 1) * 86400);
    return $out;
}

/** Σε ποια καθαρίστρια ανήκει ένα δωμάτιο. */
const CLEANER_ROOMS = [
    'cleaner1' => ['room3', 'room4'],
    'cleaner2' => ['room5', 'room6'],
];

/* ========================================================== cleaning hours log
 *
 * Πόσες ώρες έκανε η καθαρίστρια κάθε φορά που πήγε (απαίτηση 3). Η ίδια γράφει
 * τις ώρες της· ο διαχειριστής μπορεί να τις διορθώσει. Κανείς άλλος δεν γράφει.
 *
 * ΤΟ ΚΛΕΙΔΙ ΕΙΝΑΙ ΗΜΕΡΑ + ΔΩΜΑΤΙΟ. Έτσι μια καθαρίστρια με δύο δωμάτια γράφει
 * χωριστά για το καθένα την ίδια μέρα, και το σύνολο του μήνα βγαίνει σωστά
 * χωρίς να χρειάζεται να θυμάται τι είχε γράψει πριν.
 *
 * Φυλάσσεται με τον ίδιο μηχανισμό με τα άλλα δεδομένα: έξω από το site αν
 * μπορεί να δημιουργηθεί ο φάκελος, αλλιώς σε αρχείο .php που η PHP εκτελεί και
 * δεν τυπώνει τίποτα. Δεν είναι ευαίσθητο ως προς τα πρόσωπα, αλλά είναι
 * εργατικό δεδομένο: δεν έχει λόγο να σερβίρεται. */

const HOURS_OPEN  = "<<<'NAXOS_HOURS'\n";
const HOURS_CLOSE = "\nNAXOS_HOURS;";

/** Όριο ασφαλείας: μια βάρδια δεν είναι 99 ώρες. */
const HOURS_MAX = 16.0;

function hours_paths(): array {
    $outside = dirname(dirname(ADMIN_DIR)) . '/naxos-data';
    return [
        'outside_dir'  => $outside,
        'outside_file' => $outside . '/cleaning-hours.json',
        'inside_file'  => ADMIN_DIR . '/hours-data.php',
    ];
}

function hours_store_active(): array {
    $p = hours_paths();
    if (is_file($p['outside_file'])) return ['file' => $p['outside_file'], 'kind' => 'outside'];
    if (is_dir($p['outside_dir']) && is_writable($p['outside_dir'])) {
        return ['file' => $p['outside_file'], 'kind' => 'outside'];
    }
    return ['file' => $p['inside_file'], 'kind' => 'inside'];
}

/** Όλες οι εγγραφές ωρών. Κλειδί: «ημερομηνία|ρόλος|δωμάτιο». */
function hours_load(): array {
    $a = hours_store_active();
    if (!is_file($a['file'])) return [];
    $raw = (string)@file_get_contents($a['file']);
    if ($a['kind'] === 'inside') {
        $i = strpos($raw, HOURS_OPEN);
        $j = strrpos($raw, HOURS_CLOSE);
        if ($i === false || $j === false || $j < $i) return [];
        $raw = substr($raw, $i + strlen(HOURS_OPEN), $j - $i - strlen(HOURS_OPEN));
    }
    $d = json_decode((string)$raw, true);
    return is_array($d['hours'] ?? null) ? $d['hours'] : [];
}

function hours_save(array $hours): bool {
    $a = hours_store_active();
    $json = json_encode(['hours' => $hours],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = $a['kind'] === 'inside'
        ? "<?php\n/* Ώρες καθαριότητας. Η PHP το εκτελεί και δεν τυπώνει τίποτα.\n"
        . "   Μην το επεξεργαστείς με το χέρι: το διαχειρίζεται το admin/cleaning.php */\n"
        . "return " . HOURS_OPEN . $json . HOURS_CLOSE . "\n"
        : (string)$json;
    $tmp = $a['file'] . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $a['file'])) { @unlink($tmp); return false; }
    @chmod($a['file'], 0600);
    return true;
}

function hours_key(string $date, string $role, string $room): string {
    return $date . '|' . $role . '|' . $room;
}

/** Οι ώρες μιας εγγραφής, ή 0 αν δεν έχει καταχωρηθεί. */
function hours_get(array $hours, string $date, string $role, string $room): float {
    $e = $hours[hours_key($date, $role, $room)] ?? null;
    return is_array($e) ? (float)($e['hours'] ?? 0) : 0.0;
}

/** Γράφει ή σβήνει τις ώρες. Ποσό 0 ή κενό σημαίνει «δεν δούλεψα» — η εγγραφή
 *  φεύγει, ώστε να μη γεμίζει το αρχείο με μηδενικά. */
function hours_set(array $hours, string $date, string $role, string $room, float $h): array {
    $k = hours_key($date, $role, $room);
    if ($h <= 0) unset($hours[$k]);
    else $hours[$k] = ['date' => $date, 'role' => $role, 'room' => $room, 'hours' => round($h, 2)];
    return $hours;
}

/** Δέχεται «2», «2,5», «2.5» ή «2:30». Επιστρέφει ώρες ως float, ή null αν είναι άκυρο. */
function hours_norm($v): ?float {
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = str_replace([' ', "\u{00A0}"], '', $v);
    if (str_contains($v, ':')) {
        // «2:30» = 2 ώρες και 30 λεπτά
        if (!preg_match('/^(\d{1,2}):([0-5]?\d)$/', $v, $m)) return null;
        $h = (int)$m[1] + ((int)$m[2]) / 60;
    } else {
        $v = str_replace(',', '.', $v);
        if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $v)) return null;
        $h = (float)$v;
    }
    if ($h < 0 || $h > HOURS_MAX) return null;
    return round($h, 2);
}

/** Σύνολο ωρών ενός μήνα, προαιρετικά για έναν ρόλο ή ένα δωμάτιο. */
function hours_month_total(array $hours, int $year, int $month, ?string $role = null, ?string $room = null): float {
    $sum = 0.0;
    $prefix = sprintf('%04d-%02d-', $year, $month);
    foreach ($hours as $e) {
        if (!is_array($e)) continue;
        if (!str_starts_with((string)($e['date'] ?? ''), $prefix)) continue;
        if ($role !== null && (string)($e['role'] ?? '') !== $role) continue;
        if ($room !== null && (string)($e['room'] ?? '') !== $room) continue;
        $sum += (float)($e['hours'] ?? 0);
    }
    return round($sum, 2);
}

/** Οι ώρες μιας ημέρας για έναν ρόλο, ως [δωμάτιο => ώρες]. */
function hours_day(array $hours, string $date, string $role): array {
    $out = [];
    foreach ($hours as $e) {
        if (!is_array($e)) continue;
        if ((string)($e['date'] ?? '') !== $date) continue;
        if ((string)($e['role'] ?? '') !== $role) continue;
        $out[(string)($e['room'] ?? '')] = (float)($e['hours'] ?? 0);
    }
    return $out;
}

/** Στρογγυλοποίηση σε μία ώρα για εμφάνιση: 7.5 -> «7,5». */
function hours_fmt(float $h): string {
    $s = number_format($h, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}

/* ============================================================== statistics */

/** Στατιστικά ενός μήνα: ανά δωμάτιο και συνολικά.
 *  Διανυκτέρευση = ένα βράδυ που κάποιος κοιμάται στο δωμάτιο. */
function month_stats(array $guests, int $year, int $month): array {
    $daysIn = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $start  = sprintf('%04d-%02d-01', $year, $month);
    $end    = sprintf('%04d-%02d-%02d', $year, $month, $daysIn);

    $rooms = [];
    foreach (array_keys(ROOMS_G) as $k) {
        $rooms[$k] = ['nights' => 0, 'arrivals' => 0, 'departures' => 0, 'bookings' => 0];
    }

    foreach ($guests as $g) {
        $room = (string)($g['room'] ?? '');
        if (!isset($rooms[$room])) continue;
        $f = (string)($g['stayFrom'] ?? '');
        $t = (string)($g['stayTo'] ?? '');
        if ($f === '' || $t === '') continue;

        // Διανυκτερεύσεις μέσα στον μήνα
        $n = 0;
        for ($d = 1; $d <= $daysIn; $d++) {
            $night = sprintf('%04d-%02d-%02d', $year, $month, $d);
            if ($night >= $f && $night < $t) $n++;
        }
        $rooms[$room]['nights'] += $n;
        if ($f >= $start && $f <= $end) $rooms[$room]['arrivals']++;
        if ($t >= $start && $t <= $end) $rooms[$room]['departures']++;
        // Κράτηση που αγγίζει τον μήνα έστω και μία νύχτα
        if ($n > 0) $rooms[$room]['bookings']++;
    }

    $total = ['nights' => 0, 'arrivals' => 0, 'departures' => 0, 'bookings' => 0];
    foreach ($rooms as $r) foreach ($r as $k => $v) $total[$k] += $v;
    $total['capacity'] = $daysIn * count(ROOMS_G);
    $total['occupancy'] = $total['capacity'] > 0
        ? round($total['nights'] / $total['capacity'] * 100, 1) : 0.0;

    return ['rooms' => $rooms, 'total' => $total, 'daysIn' => $daysIn];
}

/** Σύνολο διανυκτερεύσεων μιας κράτησης — αυτόματα από τις ημερομηνίες. */
function guest_nights(array $g): int {
    return nights_between((string)($g['stayFrom'] ?? ''), (string)($g['stayTo'] ?? ''));
}

/* ============================================================ guest storage
 *
 * ΠΡΟΣΩΠΙΚΑ ΔΕΔΟΜΕΝΑ. Το αρχείο κρατά επώνυμα επισκεπτών και ημερομηνίες
 * διαμονής. Ο server είναι nginx, οπότε το .htaccess ΔΕΝ ισχύει και δεν
 * μπορούμε να απαγορεύσουμε την πρόσβαση σε ένα αρχείο μέσα στο site.
 *
 * Γι' αυτό προτιμάμε φάκελο ΕΞΩ από το public_html: εκεί δεν τον σερβίρει
 * ποτέ ο server, ό,τι κι αν συμβεί. Αν ο φάκελος δεν υπάρχει ή δεν είναι
 * εγγράψιμος, πέφτουμε σε ένα αρχείο .php μέσα στο admin/ — η PHP το εκτελεί
 * και δεν τυπώνει τίποτα, όπως το credentials.php. */

/* ------------------------------------------------------------ store layout */

const STORE_OPEN  = "<<<'NAXOS_GUESTS'\n";
const STORE_CLOSE = "\nNAXOS_GUESTS;";

function store_paths(): array {
    $outside = dirname(dirname(ADMIN_DIR)) . '/naxos-data';
    return [
        'outside_dir'  => $outside,
        'outside_file' => $outside . '/guests.json',
        'inside_file'  => ADMIN_DIR . '/guests-data.php',
    ];
}

/** Ποιο αρχείο χρησιμοποιούμε, και είναι ασφαλές; */
function store_active(): array {
    $p = store_paths();
    if (is_file($p['outside_file'])) return ['file' => $p['outside_file'], 'kind' => 'outside'];
    if (is_dir($p['outside_dir']) && is_writable($p['outside_dir'])) {
        return ['file' => $p['outside_file'], 'kind' => 'outside'];
    }
    return ['file' => $p['inside_file'], 'kind' => 'inside'];
}

/** Δοκιμάζει να δημιουργήσει τον φάκελο έξω από το site. Επιστρέφει true αν τα κατάφερε. */
function store_try_create_outside(): bool {
    $p = store_paths();
    if (is_dir($p['outside_dir'])) return is_writable($p['outside_dir']);
    if (@mkdir($p['outside_dir'], 0755)) { @chmod($p['outside_dir'], 0755); return true; }
    return false;
}

function guests_load(): array {
    $a = store_active();
    if (!is_file($a['file'])) return [];
    $raw = (string)@file_get_contents($a['file']);
    if ($a['kind'] === 'inside') {
        // Το αρχείο είναι  <?php return <<<'NAXOS_GUESTS'  …  NAXOS_GUESTS;
        // Δεν το κάνουμε include: κόβουμε το περιτύλιγμα και αποκωδικοποιούμε το
        // JSON, ώστε να μην εκτελεστεί ποτέ κώδικας από αυτό το αρχείο.
        $i = strpos($raw, STORE_OPEN);
        $j = strrpos($raw, STORE_CLOSE);
        if ($i === false || $j === false || $j < $i) return [];
        $raw = substr($raw, $i + strlen(STORE_OPEN), $j - $i - strlen(STORE_OPEN));
    }
    $d = json_decode((string)$raw, true);
    return is_array($d['guests'] ?? null) ? $d['guests'] : [];
}

function guests_save(array $guests): bool {
    $a = store_active();
    $json = json_encode(['guests' => array_values($guests)],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = $a['kind'] === 'inside'
        ? "<?php\n/* Λίστα επισκεπτών. Η PHP το εκτελεί και δεν τυπώνει τίποτα.\n"
        . "   Μην το επεξεργαστείς με το χέρι: το διαχειρίζεται το admin/guests.php */\n"
        . "return " . STORE_OPEN . $json . STORE_CLOSE . "\n"
        : (string)$json;
    $tmp = $a['file'] . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $a['file'])) { @unlink($tmp); return false; }
    @chmod($a['file'], 0600);
    return true;
}

/* ============================================================== site settings
 *
 * Ρυθμίσεις που τις αλλάζει ο ιδιοκτήτης μόνος του από το admin — προς το παρόν
 * ο προεπιλεγμένος κωδικός WiFi. Δεν είναι μυστικό, αλλά δεν έχει λόγο να
 * σερβίρεται, οπότε φυλάσσεται με τον ίδιο μηχανισμό με τα δεδομένα επισκεπτών:
 * έξω από το site αν ο φάκελος μπορεί να δημιουργηθεί, αλλιώς σε αρχείο .php
 * που η PHP εκτελεί και δεν τυπώνει τίποτα. */

const SETTINGS_OPEN  = "<<<'NAXOS_SETTINGS'\n";
const SETTINGS_CLOSE = "\nNAXOS_SETTINGS;";

function settings_paths(): array {
    $outside = dirname(dirname(ADMIN_DIR)) . '/naxos-data';
    return [
        'outside_dir'  => $outside,
        'outside_file' => $outside . '/settings.json',
        'inside_file'  => ADMIN_DIR . '/settings-data.php',
    ];
}

function settings_store_active(): array {
    $p = settings_paths();
    if (is_file($p['outside_file'])) return ['file' => $p['outside_file'], 'kind' => 'outside'];
    if (is_dir($p['outside_dir']) && is_writable($p['outside_dir'])) {
        return ['file' => $p['outside_file'], 'kind' => 'outside'];
    }
    return ['file' => $p['inside_file'], 'kind' => 'inside'];
}

function settings_load(): array {
    $a = settings_store_active();
    if (!is_file($a['file'])) return [];
    $raw = (string)@file_get_contents($a['file']);
    if ($a['kind'] === 'inside') {
        $i = strpos($raw, SETTINGS_OPEN);
        $j = strrpos($raw, SETTINGS_CLOSE);
        if ($i === false || $j === false || $j < $i) return [];
        $raw = substr($raw, $i + strlen(SETTINGS_OPEN), $j - $i - strlen(SETTINGS_OPEN));
    }
    $d = json_decode((string)$raw, true);
    return is_array($d) ? $d : [];
}

function settings_save(array $s): bool {
    $a = settings_store_active();
    $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = $a['kind'] === 'inside'
        ? "<?php\n/* Ρυθμίσεις. Η PHP το εκτελεί και δεν τυπώνει τίποτα.\n"
        . "   Μην το επεξεργαστείς με το χέρι: το διαχειρίζεται το admin/guests.php */\n"
        . "return " . SETTINGS_OPEN . $json . SETTINGS_CLOSE . "\n"
        : (string)$json;
    $tmp = $a['file'] . '.tmp';
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $a['file'])) { @unlink($tmp); return false; }
    @chmod($a['file'], 0600);
    return true;
}

/** Ο τρέχων προεπιλεγμένος κωδικός WiFi. */
function wifi_default(): string {
    $v = trim((string)(settings_load()['wifiDefault'] ?? ''));
    return $v !== '' ? $v : DEFAULT_WIFI_PASSWORD;
}

/** Ο κωδικός που βλέπει ο επισκέπτης: δικός του, αλλιώς ο προεπιλεγμένος.
 *  Κενό σημαίνει «ακολούθησε τον προεπιλεγμένο», ώστε μια αλλαγή στον κωδικό
 *  του ρούτερ να φτάνει σε όλους όσους δεν έχουν ειδικό κωδικό. */
function guest_wifi(array $g): string {
    $w = trim((string)($g['wifi'] ?? ''));
    return $w !== '' ? $w : wifi_default();
}

/* ================================================================ booking fees
 *
 * Τα δύο υποχρεωτικά τέλη διαμονής, όπως τα περιγράφει ο οδηγός του ιδιοκτήτη.
 *
 *   ΤΑΚΚ — Τέλος Ανθεκτικότητας στην Κλιματική Κρίση
 *       Σταθερή χρέωση ανά δωμάτιο ανά νύχτα, ΧΩΡΙΣ ΦΠΑ. Δεν μπαίνει στο
 *       τιμολόγιο διαμονής· εκδίδεται ξεχωριστό ειδικό στοιχείο.
 *       Υψηλή περίοδος (Απρίλιος–Οκτώβριος): 2,00 € / νύχτα
 *       Χαμηλή περίοδος (Νοέμβριος–Μάρτιος): 0,50 € / νύχτα
 *
 *   Τέλος Παρεπιδημούντων
 *       0,5% της καθαρής αξίας (αφού αφαιρεθούν ΦΠΑ και ΤΑΚΚ).
 *       Ο δήμος μπορεί να εφαρμόσει αυξημένο συντελεστή 0,75%.
 *
 * Σειρά υπολογισμού, με συνολικό ποσό T (ό,τι εισπράττεται από τον πελάτη):
 *       ΤΑΚΚ     = άθροισμα νυχτών × συντελεστής περιόδου
 *       Καθαρή S = (T − ΤΑΚΚ) / 1,13
 *       ΦΠΑ      = (T − ΤΑΚΚ) − S
 *       Παρεπ.   = S × 0,5%
 *
 * Το ΤΑΚΚ βγαίνει ανά ΝΥΧΤΑ, όχι ανά κράτηση: διαμονή που περνά από τον
 * Οκτώβριο στον Νοέμβριο χρεώνεται με δύο συντελεστές. Για διαμονή μέσα στον
 * ίδιο μήνα το αποτέλεσμα είναι πανομοιότυπο με τον τύπο του οδηγού.
 *
 * Οι συντελεστές είναι ΡΥΘΜΙΣΕΙΣ, όχι σταθερές: το ΤΑΚΚ αλλάζει με νόμο και ο
 * δήμος μπορεί να αλλάξει το ποσοστό. Τα αλλάζει ο ιδιοκτήτης από το admin. */

const DEFAULT_FEES = [
    'vatRate'   => 0.13,    // ΦΠΑ διαμονής
    'takkHigh'  => 2.00,    // €/νύχτα, υψηλή περίοδος
    'takkLow'   => 0.50,    // €/νύχτα, χαμηλή περίοδος
    'highFrom'  => 4,       // από Απρίλιο…
    'highTo'    => 10,      // …έως Οκτώβριο
    'municipal' => 0.005,   // 0,5% — 0,0075 αν ο δήμος έχει αυξημένο
];

/** Οι ισχύοντες συντελεστές: ρυθμίσεις πάνω από τις προεπιλογές. */
function fee_rates(): array {
    $s = settings_load();
    $r = DEFAULT_FEES;
    foreach (array_keys(DEFAULT_FEES) as $k) {
        if (isset($s['fees'][$k]) && is_numeric($s['fees'][$k])) $r[$k] = (float)$s['fees'][$k];
    }
    // Συντελεστής ΦΠΑ ≤ −100% θα μηδένιζε ή θα ανέστρεφε τη διαίρεση.
    if ($r['vatRate'] <= -1.0) $r['vatRate'] = DEFAULT_FEES['vatRate'];
    if ($r['takkHigh'] < 0) $r['takkHigh'] = 0.0;
    if ($r['takkLow']  < 0) $r['takkLow']  = 0.0;
    if ($r['municipal'] < 0) $r['municipal'] = 0.0;
    return $r;
}

/** Το ΤΑΚΚ μιας διαμονής, αθροισμένο ανά νύχτα. */
function booking_takk(array $g, ?array $r = null): float {
    $r    = $r ?? fee_rates();
    $from = (string)($g['stayFrom'] ?? '');
    $n    = guest_nights($g);
    $ts   = strtotime($from);
    if ($n <= 0 || !$ts) return 0.0;
    $hi   = (int)$r['highFrom'];
    $lo   = (int)$r['highTo'];
    $sum  = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $m    = (int)date('n', $ts + $i * 86400);
        $sum += ($m >= $hi && $m <= $lo) ? $r['takkHigh'] : $r['takkLow'];
    }
    return round($sum, 2);
}

/** Όλα τα ποσά μιας κράτησης. «has» = false όταν δεν έχει καταχωρηθεί ποσό. */
function booking_calc(array $g, ?array $r = null): array {
    $r      = $r ?? fee_rates();
    $nights = guest_nights($g);
    $takk   = booking_takk($g, $r);
    $total  = amount_value($g['total'] ?? '');
    $zero   = ['nights' => $nights, 'total' => 0.0, 'stay' => 0.0, 'takk' => 0.0,
               'net' => 0.0, 'vat' => 0.0, 'municipal' => 0.0, 'has' => false];
    if ($total <= 0) return $zero;
    $stay = round($total - $takk, 2);                    // διαμονή μαζί με ΦΠΑ
    $net  = round($stay / (1 + $r['vatRate']), 2);       // καθαρή αξία
    return [
        'nights'    => $nights,
        'total'     => $total,
        'stay'      => $stay,
        'takk'      => $takk,
        'net'       => $net,
        'vat'       => round($stay - $net, 2),
        'municipal' => round($net * $r['municipal'], 2),
        'has'       => true,
    ];
}

/** Ποσό σε ελληνική γραφή: 1234.5 → «1.234,50 €». */
function money(float $v): string {
    return number_format($v, 2, ',', '.') . ' €';
}

/** Καθαρίζει ποσό που πληκτρολογήθηκε. Δέχεται κόμμα ή τελεία ως δεκαδικό.
 *  Επιστρέφει «1234.50» ή '' αν δεν είναι αριθμός. */
function amount_norm($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    $v = str_replace(['€', ' ', "\u{00A0}"], '', $v);
    if (!preg_match('/^[\d.,]+$/', $v)) return '';
    $lastC = strrpos($v, ',');
    $lastD = strrpos($v, '.');
    if ($lastC !== false && $lastD !== false) {
        // Το τελευταίο από τα δύο είναι το δεκαδικό· το άλλο χωρίζει χιλιάδες.
        $dec = $lastC > $lastD ? ',' : '.';
        $v   = str_replace($dec === ',' ? '.' : ',', '', $v);
        $v   = str_replace($dec, '.', $v);
    } elseif ($lastC !== false) {
        $v = str_replace(',', '.', $v);
    } elseif ($lastD !== false && preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
        $v = str_replace('.', '', $v);   // «1.234» = χίλια διακόσια τριάντα τέσσερα
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) return '';
    return number_format((float)$v, 2, '.', '');
}

/** Το ποσό ως αριθμός· 0 αν λείπει ή είναι άκυρο. */
function amount_value($v): float {
    $n = amount_norm($v);
    return $n === '' ? 0.0 : (float)$n;
}

/** Άτομα: ακέραιος 1..12, ακριβώς ό,τι λέει η φόρμα (min="1" max="12").
 *
 *  Επιστρέφει null όταν η τιμή δεν είναι αποδεκτή, ώστε ο καλών να βγάλει μήνυμα
 *  αντί να αποθηκεύσει σιωπηλά κάτι άλλο από αυτό που φαίνεται στη φόρμα.
 *  Κενό = προεπιλογή 1, γιατί η φόρμα το στέλνει πάντα. */
function adults_norm($v): ?int {
    $s = trim((string)$v);
    if ($s === '') return 1;
    if (!preg_match('/^\d{1,2}$/', $s)) return null;
    $n = (int)$s;
    return ($n >= 1 && $n <= 12) ? $n : null;
}

/* ------------------------------------------------------------- guest records */

/** Slug: μόνο πεζά λατινικά, ψηφία και παύλες. Ελληνικά → μεταγραμματισμός.
 *  Δεν στηρίζεται σε mbstring/iconv: αν λείπουν, δουλεύει με πίνακα byte. */
function guest_slug(string $surname): string {
    $map = ['α'=>'a','β'=>'v','γ'=>'g','δ'=>'d','ε'=>'e','ζ'=>'z','η'=>'i','θ'=>'th','ι'=>'i',
            'κ'=>'k','λ'=>'l','μ'=>'m','ν'=>'n','ξ'=>'x','ο'=>'o','π'=>'p','ρ'=>'r','σ'=>'s',
            'ς'=>'s','τ'=>'t','υ'=>'y','φ'=>'f','χ'=>'ch','ψ'=>'ps','ω'=>'o','ά'=>'a','έ'=>'e',
            'ή'=>'i','ί'=>'i','ό'=>'o','ύ'=>'y','ώ'=>'o','ϊ'=>'i','ϋ'=>'y','ΐ'=>'i','ΰ'=>'y',
            'Α'=>'a','Β'=>'v','Γ'=>'g','Δ'=>'d','Ε'=>'e','Ζ'=>'z','Η'=>'i','Θ'=>'th','Ι'=>'i',
            'Κ'=>'k','Λ'=>'l','Μ'=>'m','Ν'=>'n','Ξ'=>'x','Ο'=>'o','Π'=>'p','Ρ'=>'r','Σ'=>'s',
            'Τ'=>'t','Υ'=>'y','Φ'=>'f','Χ'=>'ch','Ψ'=>'ps','Ω'=>'o',
            'Ά'=>'a','Έ'=>'e','Ή'=>'i','Ί'=>'i','Ό'=>'o','Ύ'=>'y','Ώ'=>'o'];
    // Τα κεφαλαία ελληνικά μπαίνουν στον πίνακα γιατί το strtolower() δεν αγγίζει
    // UTF-8 όταν λείπει το mbstring — χωρίς αυτά, το «Παπαδόπουλος» έχανε το αρχικό Π.
    $s = trim($surname);
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = strtr($s, $map);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'guest';
}

/** 4 χαρακτήρες, γράμματα και ψηφία — πολύ πιο δυνατό από 4 ψηφία. */
function guest_code(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789'; // χωρίς 0/o/1/l/i που μπερδεύονται
    $out = '';
    for ($i = 0; $i < 4; $i++) $out .= $abc[random_int(0, strlen($abc) - 1)];
    return $out;
}

function guest_status(array $g): string {
    $today = date('Y-m-d');
    if (!empty($g['activeTo'])   && $today > $g['activeTo'])   return 'expired';
    if (empty($g['enabled']))                                  return 'disabled';
    if (!empty($g['activeFrom']) && $today < $g['activeFrom']) return 'scheduled';
    return 'active';
}

/** Το κείμενο της κατάστασης, μεταφρασμένο. */
function status_label(string $st): string {
    return [
        'active'    => 'Ενεργό',
        'disabled'  => 'Απενεργοποιημένο',
        'scheduled' => 'Προγραμματισμένο',
        'expired'   => 'Έληξε',
    ][$st] ?? $st;
}

function guest_file_name(array $g): string {
    return $g['slug'] . (!empty($g['code']) ? '-' . $g['code'] : '');
}

function guest_link(array $g, string $base): string {
    return rtrim($base, '/') . '/welcome/' . guest_file_name($g) . '.php';
}

function guests_find(array $guests, string $slug): ?array {
    foreach ($guests as $g) if (($g['slug'] ?? '') === $slug) return $g;
    return null;
}

function guests_index(array $guests, string $id): ?int {
    foreach ($guests as $i => $g) if (($g['id'] ?? '') === $id) return $i;
    return null;
}

/* -------------------------------------------------------------- stub files
 *
 * Το link του επισκέπτη πρέπει να δουλέψει σε nginx ΧΩΡΙΣ κανόνες rewrite, που
 * δεν τους ελέγχουμε. Γι' αυτό γράφουμε ένα πραγματικό, ελάχιστο αρχείο ανά
 * επισκέπτη: το nginx το σερβίρει απευθείας, δεν χρειάζεται τίποτα από τον
 * πάροχο. Το αρχείο λέει μόνο ποιον αφορά — όλη η λογική είναι στο _view.php,
 * ώστε μια αλλαγή στη σελίδα να μη χρειάζεται ξαναγράψιμο όλων των stubs. */

const WELCOME_DIR_NAME = 'welcome';

function welcome_dir(): string { return dirname(ADMIN_DIR) . '/' . WELCOME_DIR_NAME; }

function welcome_write_stub(array $g): bool {
    $dir = welcome_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755)) return false;
    $name = guest_file_name($g);
    if (!preg_match('/^[a-z0-9-]+$/', $name)) return false;
    $php = "<?php\n\$GUEST_SLUG = " . var_export($g['slug'], true) . ";\n"
         . "\$GUEST_CODE = " . var_export((string)($g['code'] ?? ''), true) . ";\n"
         . "require __DIR__ . '/_view.php';\n";
    return @file_put_contents($dir . '/' . $name . '.php', $php, LOCK_EX) !== false;
}

function welcome_remove_stub(array $g): void {
    $name = guest_file_name($g);
    if (preg_match('/^[a-z0-9-]+$/', $name)) @unlink(welcome_dir() . '/' . $name . '.php');
}
