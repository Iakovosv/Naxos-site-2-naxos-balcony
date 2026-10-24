<?php
/* ============================================================================
 * Naxos Balcony Apartments — Gallery admin
 * ============================================================================
 * A single-file photo manager. Pick a room, drop in photos, done.
 *
 *   - first visit asks you to set a password (stored hashed, never in the repo)
 *   - photos are re-encoded through GD, so EXIF data and anything embedded in
 *     the file is stripped; originals are never served
 *   - each upload also gets a small grid-size version, which is what the
 *     gallery page actually loads
 *   - the gallery pages are pre-rendered for search engines, so the tool
 *     rewrites the tile list in gallery.html and en/gallery.html as well
 *
 * Deploy: upload this file to  <public_html>/admin/index.php
 * ========================================================================== */

declare(strict_types=1);

// Η πράξη ασφαλείας στη σύνδεση είναι κοινή με τις σελίδες που φορτώνουν το _lib.php.
require_once __DIR__ . '/_captcha.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    // Πίσω από nginx το $_SERVER['HTTPS'] μπορεί να λείπει· το request_is_https()
    // κοιτάζει και το X-Forwarded-Proto (δες _captcha.php).
    'secure'   => request_is_https(),
]);
session_start();

// Καμία σελίδα προσωπικού δεν ανήκει στις μηχανές αναζήτησης.
header('X-Robots-Tag: noindex, nofollow, noarchive');

const ROOT        = __DIR__ . '/..';
const CRED_FILE   = __DIR__ . '/credentials.php';
const TILES_JSON  = ROOT . '/gallery-tiles.json';
const TILES_JS    = ROOT . '/gallery-data.js';
const GRID_DIR    = 'images/gallery';
const MAX_EDGE    = 1920;
const THUMB_EDGE  = 700;
const JPEG_Q      = 82;
const WEBP_Q      = 80;

const ROOMS = [
    'room3'    => ['el' => 'Room 3', 'en' => 'Room 3'],
    'room4'    => ['el' => 'Room 4', 'en' => 'Room 4'],
    'room5'    => ['el' => 'Room 5', 'en' => 'Room 5'],
    'room6'    => ['el' => 'Room 6', 'en' => 'Room 6'],
    'exterior' => ['el' => 'Εξωτερικοί χώροι', 'en' => 'Exterior Areas'],
    'sunset'   => ['el' => 'Ηλιοβασίλεμα', 'en' => 'Sunset'],
];

/* ------------------------------------------------------------------ helpers */

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    // Ίδια διόρθωση με το _lib.php: το κενό token δεν περνάει. Χωρίς αυτό, το
    // hash_equals('','') έδινε true και ένα POST χωρίς session γινόταν δεκτό.
    $sent = (string)($_POST['csrf'] ?? '');
    $have = (string)($_SESSION['csrf'] ?? '');
    if ($sent === '' || $have === '' || !hash_equals($have, $sent)) {
        http_response_code(400); exit('Bad request token. Reload the page and try again.');
    }
}
function logged_in(): bool { return !empty($_SESSION['ok']); }

function fail(string $m): void {
    $_SESSION['flash'][] = ['err', $m];
    header('Location: index.php'); exit;
}
function ok(string $m): void { $_SESSION['flash'][] = ['ok', $m]; }

/* --------------------------------------------------------------------- gate
 *
 * Η Γκαλερί είναι ΜΟΝΟ για τον διαχειριστή.
 *
 * Το /admin/ μοιράζεται τη συνεδρία με τις σελίδες του _lib.php — ίδια κλειδιά
 * ok/role — οπότε μέχρι εδώ αρκούσε το «είναι συνδεδεμένος ΚΑΠΟΙΟΣ». Έτσι ο
 * λογιστής και οι καθαρίστριες έμπαιναν στη Γκαλερί και μπορούσαν να
 * ανεβάσουν, να διαγράψουν και να μετακινήσουν φωτογραφίες.
 *
 * ΔΕΝ καταστρέφουμε τη συνεδρία: ο χρήστης έχει έγκυρη σύνδεση, απλώς όχι για
 * αυτή τη σελίδα. Τον στέλνουμε στη δική του, με το ίδιο κλειδί «notice» που
 * τυπώνει το render_notice() του _lib.php, ώστε να μη μοιάζει σιωπηλό.
 *
 * Ο χάρτης προορισμού αντιγράφει το home_for_role() του _lib.php. Δεν κάνουμε
 * require το _lib.php εδώ: ορίζει τα ίδια ονόματα συναρτήσεων (h, csrf,
 * logged_in, ok) και θα έσκαγε με «Cannot redeclare». Αν αλλάξει το ένα,
 * αλλάζει και το άλλο.
 */
if (logged_in() && (string)($_SESSION['role'] ?? '') !== 'admin') {
    $home = match ((string)$_SESSION['role']) {
        'accountant'           => 'accountant.php',
        'cleaner1', 'cleaner2' => 'cleaning.php',
        default                => '',
    };
    if ($home !== '') {
        $_SESSION['notice'] = 'Η Γκαλερί φωτογραφιών είναι μόνο για τον διαχειριστή. '
                            . 'Σε πήγα στη δική σου σελίδα.';
        header('Location: ' . $home); exit;
    }
    /* Άγνωστος ή κενός ρόλος. Δεν τον στέλνουμε σε σελίδα του _lib.php: εκείνη
       απαιτεί ρόλο, θα τον ξαναστείλει πίσω και θα γίνει βρόχος. Καθαρίζουμε τη
       συνεδρία και ξαναφορτώνουμε, οπότε βλέπει τη φόρμα σύνδεσης. */
    $_SESSION = [];
    session_destroy();
    header('Location: index.php'); exit;
}

function load_tiles(): array {
    if (!is_file(TILES_JSON)) return [];
    $j = json_decode((string)file_get_contents(TILES_JSON), true);
    return is_array($j) ? $j : [];
}
function save_tiles(array $tiles): void {
    $tiles = array_values($tiles);
    file_put_contents(TILES_JSON,
        json_encode($tiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    // keep the JS copy in step (it is the fallback path when the HTML is absent)
    $flat = array_map(fn($t) => [
        'cat' => $t['cat'], 'src' => $t['src'], 'alt' => $t['alt'],
        'thumb' => $t['thumb'] ?? null, 'thumb_jpg' => $t['thumb_jpg'] ?? null,
    ], $tiles);
    file_put_contents(TILES_JS,
        "window.GALLERY_TILES = "
        . json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n", LOCK_EX);
}

/** Rewrite the pre-rendered tile list inside the two gallery pages. */
function rebuild_gallery_pages(array $tiles): void {
    $grouped = [];
    foreach ($tiles as $t) $grouped[$t['cat']][] = $t;

    foreach ([['gallery.html', 'el', ''], ['en/gallery.html', 'en', '../']] as [$rel, $lang, $up]) {
        $path = ROOT . '/' . $rel;
        if (!is_file($path)) continue;
        $html = (string)file_get_contents($path);

        $rows = [];
        foreach (ROOMS as $cat => $_) {
            foreach ($grouped[$cat] ?? [] as $t) {
                $alt = $lang === 'en' ? ($t['alt_en'] ?? $t['alt']) : $t['alt'];
                $src = $up . $t['src'];
                $thumb = isset($t['thumb']) ? $up . $t['thumb'] : '';
                $thumbJpg = isset($t['thumb_jpg']) ? $up . $t['thumb_jpg'] : $thumb;
                if ($thumb !== '') {
                    // Grid gets the 700px thumbnail, the lightbox gets the original.
                    $rows[] = sprintf(
                        '    <div class="tile" data-cat="%s"><a href="%s">'
                        . '<picture><source type="image/webp" srcset="%s">'
                        . '<img loading="lazy" decoding="async" src="%s" alt="%s"></picture></a></div>',
                        h($cat), h($src), h($thumb), h($thumbJpg), h($alt));
                } else {
                    $rows[] = sprintf(
                        '    <div class="tile" data-cat="%s"><a href="%s">'
                        . '<img loading="lazy" decoding="async" width="%d" height="%d" src="%s" alt="%s"></a></div>',
                        h($cat), h($src), (int)$t['w'], (int)$t['h'], h($src), h($alt));
                }
            }
        }
        // Keep the data-room-info attribute: the English pages need it because
        // they do not load lang-system.js.
        $attr = '';
        if (preg_match('#<section class="grid" id="grid"([^>]*)>#', $html, $m)) {
            if (strpos($m[1], 'data-room-info') !== false) {
                preg_match('#data-room-info="[^"]*"#', $m[1], $a);
                $attr = ' ' . ($a[0] ?? '');
            }
        }
        $block = '<section class="grid" id="grid"' . $attr . '>' . "\n" . implode("\n", $rows) . "\n  </section>";
        $new = preg_replace('#<section class="grid" id="grid"[^>]*>.*?</section>#s',
                            str_replace('$', '\\$', $block), $html, 1, $count);
        if ($count) file_put_contents($path, $new, LOCK_EX);
    }
}

/* --------------------------------------------------------------- image work */

/** Decode an upload through GD. Re-encoding strips EXIF and any payload. */
function decode_image(string $tmp): ?GdImage {
    $info = @getimagesize($tmp);
    if (!$info) return null;
    if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
    if ($info[0] < 400 || $info[1] < 300) return null;          // too small to be a real photo
    if ($info[0] * $info[1] > 80000000) return null;            // refuse absurd dimensions
    $im = @imagecreatefromstring((string)file_get_contents($tmp));
    return $im ?: null;
}

function resize_to(GdImage $im, int $maxEdge): GdImage {
    $w = imagesx($im); $h = imagesy($im);
    if ($w <= $maxEdge && $h <= $maxEdge) return $im;
    $scale = $maxEdge / max($w, $h);
    return imagescale($im, (int)round($w * $scale), (int)round($h * $scale), IMG_BICUBIC) ?: $im;
}

function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '');
    return trim($s, '-') ?: 'photo';
}

/* ------------------------------------------------------------------ credentials

   Κρίνουμε από το περιεχόμενο του αρχείου, όχι από την ύπαρξή του: αρχείο χωρίς
   έγκυρο κλειδί θα έκλεινε τη σελίδα ρύθμισης και θα άφηνε τον ιδιοκτήτη χωρίς κανέναν
   τρόπο να το διορθώσει. Η σελίδα είναι αυτόνομη, γι' αυτό τα δύο βοηθήματα υπάρχουν
   και εδώ, όπως και στο admin/_lib.php. */

function cred_hash(): string {
    if (!is_file(CRED_FILE)) return '';
    $creds = @include CRED_FILE;
    if (!is_array($creds)) return '';
    // Δεκτό και το παλιό κλειδί 'pass'.
    foreach (['hash', 'pass'] as $k) {
        $h = (string)($creds[$k] ?? '');
        if ($h !== '') return $h;
    }
    return '';
}

function admin_configured(): bool { return cred_hash() !== ''; }

/* --------------------------------------------------------------- bootstrap */

$needs_setup = !admin_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup' && $needs_setup) {
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) { $setup_error = 'Use at least 8 characters.'; }
    elseif ($pw !== ($_POST['password2'] ?? '')) { $setup_error = 'The two passwords do not match.'; }
    else {
        $php = "<?php\n// Created by the gallery admin on " . date('c') . "\n"
             . "return ['hash' => " . var_export(password_hash($pw, PASSWORD_DEFAULT), true) . "];\n";
        if (@file_put_contents(CRED_FILE, $php, LOCK_EX) === false) {
            $setup_error = 'Could not write admin/credentials.php. Check folder permissions (755).';
        } else {
            @chmod(CRED_FILE, 0600);
            $_SESSION['ok'] = true;
            // The _lib.php pages gate on the role, so the gallery login has to set
            // it too. Without it guests.php sees an empty role and bounces back here.
            $_SESSION['role'] = 'admin';
            header('Location: index.php'); exit;
        }
    }
}


/* ---------------------------------------------------------------- throttling

   Το login δεν είχε όριο προσπαθειών: ένα script μπορούσε να δοκιμάζει
   κωδικούς απεριόριστα. Κρατάμε τις αποτυχίες σε ένα αρχείο δίπλα στα
   credentials (ίδια άδεια, 0600) και κλειδώνουμε προσωρινά.

   Μετά από MAX_ATTEMPTS αποτυχίες μέσα σε WINDOW δευτερόλεπτα, το login
   απορρίπτεται για LOCKOUT δευτερόλεπτα. Το διάστημα διπλασιάζεται σε κάθε
   νέο κύκλο αποτυχιών, ώστε μια παρατεταμένη επίθεση να γίνεται ασύμφορη. */
const THROTTLE_FILE = __DIR__ . '/.login-attempts.json';
const MAX_ATTEMPTS  = 5;
const WINDOW        = 900;   // 15 λεπτά
const LOCKOUT       = 900;   // 15 λεπτά αρχικό κλείδωμα

function throttle_state(): array {
    if (!is_file(THROTTLE_FILE)) return ['fails' => [], 'until' => 0, 'cycles' => 0];
    $d = json_decode((string)@file_get_contents(THROTTLE_FILE), true);
    return is_array($d) ? $d + ['fails' => [], 'until' => 0, 'cycles' => 0]
                        : ['fails' => [], 'until' => 0, 'cycles' => 0];
}

function throttle_save(array $d): void {
    @file_put_contents(THROTTLE_FILE, json_encode($d), LOCK_EX);
    @chmod(THROTTLE_FILE, 0600);
}

/** Πόσα δευτερόλεπτα απομένουν μέχρι να επιτραπεί νέα προσπάθεια (0 = ελεύθερο). */
function throttle_wait(): int {
    $d = throttle_state();
    $left = (int)$d['until'] - time();
    return $left > 0 ? $left : 0;
}

function throttle_fail(): void {
    $now = time();
    $d = throttle_state();
    // Κρατάμε μόνο τις αποτυχίες μέσα στο παράθυρο
    $d['fails'] = array_values(array_filter((array)$d['fails'], fn($t) => $now - (int)$t < WINDOW));
    $d['fails'][] = $now;
    if (count($d['fails']) >= MAX_ATTEMPTS) {
        $d['cycles'] = (int)($d['cycles'] ?? 0) + 1;
        $d['until']  = $now + LOCKOUT * (2 ** min($d['cycles'] - 1, 4));
        $d['fails']  = [];
    }
    throttle_save($d);
}

function throttle_clear(): void {
    @unlink(THROTTLE_FILE);
}

$creds = $needs_setup ? null : ['hash' => cred_hash()];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $wait = throttle_wait();
    if ($wait > 0) {
        $login_error = 'Too many attempts. Try again in ' . (int)ceil($wait / 60) . ' minute(s).';
    } elseif (!captcha_check((string)($_POST['captcha_answer'] ?? ''), (string)($_POST['captcha_id'] ?? ''))) {
        // Λάθος πράξη: δεν μετράει ως αποτυχημένος κωδικός.
        $login_error = 'Wrong answer to the sum. Try again.';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        if (is_array($creds) && isset($creds['hash']) && password_verify($pw, (string)$creds['hash'])) {
            throttle_clear();
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['role'] = 'admin';
            header('Location: index.php'); exit;
        }
        throttle_fail();
        // Οι κωδικοί προσωπικού ισχύουν σε άλλη σελίδα. Το λέμε καθαρά, αντί για
        // ένα σκέτο «λάθος κωδικός» που έστελνε τον λογιστή να ξαναπροσπαθήσει εδώ.
        $login_error = staff_password_elsewhere($pw)
            ? 'This password belongs to the staff pages, not the photo gallery. Sign in at guests.php instead.'
            : 'Wrong password.';
    }
}
if (($_GET['logout'] ?? '') === '1') {
    $_SESSION = []; session_destroy();
    header('Location: index.php'); exit;
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

/* ------------------------------------------------------------------ actions */

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $cat = (string)($_POST['cat'] ?? '');
        if (!isset(ROOMS[$cat])) fail('Unknown room.');
        $files = $_FILES['photos'] ?? null;
        if (!$files || !isset($files['tmp_name'])) fail('No files arrived.');
        $tmp = (array)$files['tmp_name'];
        $names = (array)$files['name'];
        $tiles = load_tiles();
        $added = 0; $skipped = 0;

        foreach ($tmp as $i => $t) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { $skipped++; continue; }
            $im = decode_image($t);
            if (!$im) { $skipped++; continue; }

            $im = resize_to($im, MAX_EDGE);
            $dir = ROOT . '/' . GRID_DIR . '/' . $cat;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $skipped++; continue; }

            $base = substr(slugify(ROOMS[$cat]['en'] . '-' . pathinfo((string)$names[$i], PATHINFO_FILENAME)), 0, 48);
            $file = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.jpg';
            $abs  = $dir . '/' . $file;
            $rel  = GRID_DIR . '/' . $cat . '/' . $file;

            $okSave = imagejpeg($im, $abs, JPEG_Q);
            $w = imagesx($im); $h = imagesy($im);

            // GdImage cannot be cloned in PHP 8, so build the grid version by
            // re-reading the file we just wrote.
            $thumbRel = '';
            if ($okSave) {
                $again = @imagecreatefromjpeg($abs);
                if ($again) {
                    $thumb = resize_to($again, THUMB_EDGE);
                    imagejpeg($thumb, preg_replace('/\.jpg$/', '-thumb.jpg', $abs), JPEG_Q);
                    // WebP is what the grid actually serves; the JPEG is the fallback.
                    imagewebp($thumb, preg_replace('/\.jpg$/', '-thumb.webp', $abs), WEBP_Q);
                    imagedestroy($thumb);
                    imagedestroy($again);
                    $thumbRel = preg_replace('/\.jpg$/', '-thumb.webp', $rel);
                }
            }

            if ($okSave) {
                $n = 1;
                foreach ($tiles as $x) if (($x['cat'] ?? '') === $cat) $n++;
                $tiles[] = [
                    'cat'    => $cat,
                    'src'    => $rel,
                    'w'      => $w,
                    'h'      => $h,
                    'idx'    => (string)$n,
                    'alt'    => ROOMS[$cat]['el'] . ' - φωτογραφία ' . $n,
                    'alt_en' => ROOMS[$cat]['en'] . ' - photo ' . $n,
                    'title_key' => in_array($cat, ['room3','room4','room5','room6'], true)
                                   ? $cat . 'Title' : null,
                    'thumb'     => $thumbRel,
                    'thumb_jpg' => preg_replace('/\.jpg$/', '-thumb.jpg', $rel),
                ];
                $added++;
            } else { $skipped++; }
            imagedestroy($im);
        }
        save_tiles($tiles);
        rebuild_gallery_pages($tiles);
        ok("Added {$added} photo(s)" . ($skipped ? ", skipped {$skipped}" : '') . '.');
        header('Location: index.php'); exit;
    }

    if ($action === 'delete') {
        $src = (string)($_POST['src'] ?? '');
        if (strpos($src, GRID_DIR . '/') !== 0 || strpos($src, '..') !== false) fail('Bad path.');
        $abs = realpath(ROOT . '/' . $src);
        $rootReal = realpath(ROOT . '/' . GRID_DIR);
        if ($abs && $rootReal && strpos($abs, $rootReal) === 0 && is_file($abs)) {
            @unlink($abs);
            @unlink(preg_replace('/\.jpg$/', '-thumb.jpg', $abs));
            @unlink(preg_replace('/\.jpg$/', '-thumb.webp', $abs));
        }
        $tiles = array_values(array_filter(load_tiles(), fn($t) => ($t['src'] ?? '') !== $src));
        save_tiles($tiles);
        rebuild_gallery_pages($tiles);
        ok('Photo deleted.');
        header('Location: index.php'); exit;
    }

    if ($action === 'move') {
        $src = (string)($_POST['src'] ?? '');
        $to  = (string)($_POST['to'] ?? '');
        if (!isset(ROOMS[$to])) fail('Unknown room.');
        $tiles = load_tiles();
        foreach ($tiles as &$t) {
            if (($t['src'] ?? '') === $src) {
                $t['cat'] = $to;
                $t['alt'] = ROOMS[$to]['el'] . ' - φωτογραφία ' . ($t['idx'] ?? '');
                $t['alt_en'] = ROOMS[$to]['en'] . ' - photo ' . ($t['idx'] ?? '');
                $t['title_key'] = in_array($to, ['room3','room4','room5','room6'], true) ? $to . 'Title' : null;
            }
        }
        unset($t);
        save_tiles($tiles);
        rebuild_gallery_pages($tiles);
        ok('Photo moved.');
        header('Location: index.php'); exit;
    }

    if ($action === 'change_pw') {
        $cur   = (string)($_POST['current'] ?? '');
        $new   = (string)($_POST['new'] ?? '');
        $again = (string)($_POST['new2'] ?? '');
        $creds = ['hash' => cred_hash()];
        if (!is_array($creds) || !isset($creds['hash'])
            || !password_verify($cur, (string)$creds['hash'])) {
            fail('The current password is not correct.');
        } elseif (strlen($new) < 8) {
            fail('The new password needs at least 8 characters.');
        } elseif ($new !== $again) {
            fail('The two new passwords do not match.');
        } else {
            $php = "<?php\n// Created by the gallery admin on " . date('c') . "\n"
                 . "return ['hash' => " . var_export(password_hash($new, PASSWORD_DEFAULT), true) . "];\n";
            if (@file_put_contents(CRED_FILE, $php, LOCK_EX) === false) {
                fail('Could not write admin/credentials.php. Check folder permissions (755).');
            }
            @chmod(CRED_FILE, 0600);
            // A password change is a good moment to hand out a fresh session id,
            // so anything that copied the old one stops working.
            session_regenerate_id(true);
            throttle_clear();
            ok('Password changed.');
            header('Location: index.php'); exit;
        }
    }
}

$tiles = load_tiles();
$counts = array_fill_keys(array_keys(ROOMS), 0);
$hash = [];
foreach ($tiles as $t) {
    if (isset($counts[$t['cat']])) $counts[$t['cat']]++;
    $p = ROOT . '/' . $t['src'];
    $h = is_file($p) ? (md5_file($p) ?: '') : '';
    if ($h !== '') $hash[$h][] = $t['src'];
}
$dupes = array_filter($hash, fn($v) => count($v) > 1);

/* --------------------------------------------------------------------- view */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Gallery admin — Naxos Balcony</title>
<style>
:root{--ink:#16232e;--muted:#5b6b7a;--accent:#c2703f;--line:rgba(16,32,48,.12)}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:#f6f8fb;line-height:1.6}
.wrap{max-width:1000px;margin:0 auto;padding:24px 18px 80px}
h1{font-size:1.4rem;margin:0 0 4px}
h2{font-size:1.05rem;margin:28px 0 10px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin:16px 0;box-shadow:0 2px 10px rgba(16,32,48,.04)}
label{display:block;font-weight:600;font-size:.9rem;margin:0 0 6px}
input[type=password],select,input[type=file]{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}
button{font:inherit;font-weight:600;border:0;border-radius:10px;padding:11px 20px;cursor:pointer;background:var(--accent);color:#fff}
button.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
button.danger{background:#b23b3b}
.row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
.row>div{flex:1 1 200px}
.flash{padding:12px 16px;border-radius:10px;margin:12px 0;font-size:.92rem}
.flash.ok{background:#e8f6ec;border:1px solid #bfe3c9}
.flash.err{background:#fdecec;border:1px solid #f2c7c7}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.ph{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.ph img{width:100%;height:110px;object-fit:cover;display:block}
.ph .meta{padding:8px;font-size:.74rem;color:var(--muted);word-break:break-all}
.ph form{margin:0}
.ph button{width:100%;border-radius:0;padding:7px;font-size:.78rem}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 16px}
.tabs a{padding:8px 14px;border-radius:999px;border:1px solid var(--line);text-decoration:none;color:var(--ink);font-size:.86rem;background:#fff}
.tabs a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.hint{color:var(--muted);font-size:.85rem}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.acts{display:flex;gap:6px;flex-wrap:wrap}
a.signout{text-decoration:none;padding:10px 18px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-weight:600;font-size:.9rem}
</style>
</head>
<body>
<div class="wrap">

<?php if ($needs_setup): ?>
  <h1>Set up the gallery admin</h1>
  <p class="hint">Choose a password. It is stored hashed in <code>admin/credentials.php</code> and never leaves the server.</p>
  <?php if (!empty($setup_error)): ?><div class="flash err"><?= h($setup_error) ?></div><?php endif; ?>
  <div class="card">
    <form method="post">
      <input type="hidden" name="action" value="setup">
      <label for="p1">Password (min 8 characters)</label>
      <input id="p1" type="password" name="password" required minlength="8" autocomplete="new-password">
      <label for="p2" style="margin-top:12px">Repeat password</label>
      <input id="p2" type="password" name="password2" required minlength="8" autocomplete="new-password">
      <p style="margin-top:16px"><button type="submit">Create password</button></p>
    </form>
  </div>

<?php elseif (!logged_in()): ?>
  <?php $cap = captcha_new(); ?>
  <h1>Gallery admin</h1>
  <?php if (!empty($login_error)): ?><div class="flash err"><?= h($login_error) ?></div><?php endif; ?>
  <div class="card" style="max-width:420px">
    <form method="post">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="captcha_id" value="<?= h($cap['id']) ?>">
      <label for="p">Password</label>
      <input id="p" type="password" name="password" required autocomplete="current-password" autofocus>
      <label for="cap" style="margin-top:14px">How much is <?= h($cap['q']) ?>?</label>
      <input id="cap" type="text" name="captcha_answer" required inputmode="numeric"
             autocomplete="off" placeholder="Answer">
      <p style="margin-top:16px"><button type="submit">Sign in</button></p>
      <p class="hint" style="margin:12px 0 0">
        Staff sign in at <a href="guests.php">guests.php</a>.
      </p>
    </form>
  </div>

<?php else: ?>
  <div class="top">
    <div>
      <h1>Gallery admin</h1>
      <p class="hint">Uploads go live immediately. The gallery pages are rebuilt automatically.</p>
    </div>
    <div class="acts">
      <a class="signout" href="guests.php">Bookings</a>
      <a class="signout" href="calendar.php">Calendar</a>
      <a class="signout" href="stats.php">Statistics</a>
      <a class="signout" href="?logout=1">Sign out</a>
    </div>
  </div>

  <?php foreach ($flash as [$kind, $msg]): ?>
    <div class="flash <?= h($kind) ?>"><?= h($msg) ?></div>
  <?php endforeach; ?>

  <?php $sel = $_GET['cat'] ?? 'room3'; if (!isset(ROOMS[$sel])) $sel = 'room3'; ?>

  <div class="card">
    <h2 style="margin-top:0">Add photos</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <div class="row">
        <div>
          <label for="cat">Room</label>
          <select id="cat" name="cat">
            <?php foreach (ROOMS as $k => $r): ?>
              <option value="<?= h($k) ?>" <?= $k === $sel ? 'selected' : '' ?>>
                <?= h($r['el']) ?> — <?= (int)$counts[$k] ?> photos
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:2 1 320px">
          <label for="photos">Photos (select several at once)</label>
          <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </div>
        <div style="flex:0 0 auto">
          <button type="submit">Upload</button>
        </div>
      </div>
      <p class="hint" style="margin-bottom:0">JPG, PNG or WebP. Photos are resized to max <?= MAX_EDGE ?>px, re-encoded and given a small grid version. Location data in the file is stripped.</p>
    </form>
  </div>

  <?php if ($dupes): ?>
    <div class="flash err">
      <strong><?= count($dupes) ?> group(s) of identical photos found.</strong>
      <div class="hint">These files have the same content, so a visitor sees the same picture twice:
        <?php foreach ($dupes as $g) echo '<br>' . h(implode('  =  ', $g)); ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="tabs">
    <?php foreach (ROOMS as $k => $r): ?>
      <a class="<?= $k === $sel ? 'on' : '' ?>" href="?cat=<?= h($k) ?>"><?= h($r['el']) ?> (<?= (int)$counts[$k] ?>)</a>
    <?php endforeach; ?>
  </div>

  <div class="grid">
    <?php foreach ($tiles as $t): if (($t['cat'] ?? '') !== $sel) continue; ?>
      <div class="ph">
        <img loading="lazy" src="../<?= h($t['thumb'] ?? $t['src']) ?>" alt="">
        <div class="meta"><?= h(basename($t['src'])) ?></div>
        <form method="post" onsubmit="return confirm('Delete this photo?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="src" value="<?= h($t['src']) ?>">
          <button class="danger" type="submit">Delete</button>
        </form>
        <form method="post">
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="src" value="<?= h($t['src']) ?>">
          <select name="to" onchange="this.form.submit()" style="border-radius:0;font-size:.78rem;padding:6px">
            <option value="">Move to…</option>
            <?php foreach (ROOMS as $k => $r): if ($k === $sel) continue; ?>
              <option value="<?= h($k) ?>"><?= h($r['el']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$counts[$sel]): ?><p class="hint">No photos in this room yet.</p><?php endif; ?>

  <div class="card" style="margin-top:28px">
    <h2 style="margin-top:0">Change password</h2>
    <form method="post" style="max-width:420px">
      <input type="hidden" name="action" value="change_pw">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <label for="cur">Current password</label>
      <input id="cur" type="password" name="current" required autocomplete="current-password">
      <label for="np" style="margin-top:12px">New password (min 8 characters)</label>
      <input id="np" type="password" name="new" required minlength="8" autocomplete="new-password">
      <label for="np2" style="margin-top:12px">Repeat new password</label>
      <input id="np2" type="password" name="new2" required minlength="8" autocomplete="new-password">
      <p style="margin-top:16px"><button type="submit">Change password</button></p>
      <p class="hint" style="margin-bottom:0">If you ever forget it: delete <code>admin/credentials.php</code>
        through your host's file manager (or FTP) and open this page again. You will be asked to
        choose a new password. Nothing else is lost — your photos stay where they are.</p>
    </form>
  </div>

<?php endif; ?>
</div>
</body>
</html>
