<?php
/* ============================================================================
 * Naxos Balcony Apartments — shared admin library
 * ============================================================================
 * Authentication, CSRF, login throttling and the guest-link store.
 *
 * Used by guests.php. index.php (the gallery admin) still carries its own copy
 * of the auth code; that is deliberate, so a change here cannot break a tool
 * that is already working. Both tools share credentials.php and the throttle
 * file, so a failed login on either page counts against the other.
 *
 * Deploy: upload to  <public_html>/admin/_lib.php
 * ========================================================================== */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
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
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad request.');
    }
}

function logged_in(): bool { return !empty($_SESSION['ok']); }

function current_role(): string { return (string)($_SESSION['role'] ?? ''); }
function is_admin(): bool { return current_role() === 'admin'; }

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
 * διπλασιάζεται σε κάθε νέο κύκλο. Μοιράζεται το ίδιο αρχείο με το gallery
 * admin, ώστε οι προσπάθειες να μετριούνται συνολικά. */

function throttle_state(): array {
    if (!is_file(THROTTLE_F)) return ['fails' => [], 'until' => 0, 'cycles' => 0];
    $d = json_decode((string)@file_get_contents(THROTTLE_F), true);
    return is_array($d) ? $d + ['fails' => [], 'until' => 0, 'cycles' => 0]
                        : ['fails' => [], 'until' => 0, 'cycles' => 0];
}

function throttle_save(array $d): void {
    @file_put_contents(THROTTLE_F, json_encode($d), LOCK_EX);
    @chmod(THROTTLE_F, 0600);
}

function throttle_wait(): int {
    $d = throttle_state();
    $left = (int)$d['until'] - time();
    return $left > 0 ? $left : 0;
}

function throttle_fail(): void {
    $now = time();
    $d = throttle_state();
    $d['fails'] = array_values(array_filter((array)$d['fails'], fn($t) => $now - (int)$t < WINDOW));
    $d['fails'][] = $now;
    if (count($d['fails']) >= MAX_ATTEMPTS) {
        $d['cycles'] = (int)($d['cycles'] ?? 0) + 1;
        $d['until']  = $now + LOCKOUT * (2 ** min($d['cycles'] - 1, 4));
        $d['fails']  = [];
    }
    throttle_save($d);
}

function throttle_clear(): void { @unlink(THROTTLE_F); }

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

/** Πρέπει να κληθεί μετά το session_start() και πριν τυπωθεί οποιοδήποτε HTML.
 *  $need: null = οποιοσδήποτε συνδεδεμένος· αλλιώς ένας ρόλος ή λίστα ρόλων. */
function require_login(string|array|null $need = null): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $wait = throttle_wait();
        if ($wait > 0) {
            $_SESSION['login_error'] = 'Πολλές προσπάθειες. Δοκίμασε σε ' . (int)ceil($wait / 60) . ' λεπτά.';
        } else {
            $role = verify_login((string)($_POST['password'] ?? ''));
            if ($role !== null) {
                throttle_clear();
                session_regenerate_id(true);
                $_SESSION['ok']   = true;
                $_SESSION['role'] = $role;
                unset($_SESSION['login_error']);
                header('Location: ' . home_for_role($role)); exit;
            }
            throttle_fail();
            $_SESSION['login_error'] = 'Λάθος κωδικός.';
        }
        header('Location: ' . (($_SERVER['PHP_SELF'] ?? '/admin/guests.php'))); exit;
    }

    if (isset($_GET['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: guests.php'); exit;
    }

    // Ένας συνδεδεμένος χρήστης δεν έχει δουλειά σε σελίδα άλλου ρόλου: τον
    // στέλνουμε εκεί που ανήκει, αντί να του δείξουμε σελίδα που δεν επιτρέπεται.
    if (logged_in() && $need !== null) {
        $role = current_role();
        $allowed = is_array($need) ? $need : [$need];
        if (!in_array($role, $allowed, true)) {
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
