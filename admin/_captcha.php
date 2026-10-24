<?php
/* ============================================================================
 * Naxos Balcony Apartments — απλή μαθηματική πράξη στη σύνδεση
 * ============================================================================
 * Ζητάει μια πρόσθεση ή αφαίρεση μαζί με τον κωδικό. Σκοπός: να μη μπορεί ένα
 * bot να δοκιμάζει κωδικούς σε κάθε φόρμα σύνδεσης με τη σειρά.
 *
 * ΤΙ ΚΑΝΕΙ ΚΑΙ ΤΙ ΔΕΝ ΚΑΝΕΙ
 * Σταματά τα «τυφλά» bots και τους ανιχνευτές που χτυπούν ό,τι βρουν. ΔΕΝ σταματά
 * στοχευμένη επίθεση: η πράξη είναι απλή και γραμμένη ως κείμενο, οπότε ένα
 * script που διαβάζει τη σελίδα τη λύνει. Την πραγματική άμυνα την κάνει το όριο
 * προσπαθειών (throttle_* στο _lib.php) μαζί με το κατακερματισμένο password_verify.
 * Γι' αυτό η λάθος απάντηση στην πράξη ΔΕΝ μετράει ως αποτυχημένος κωδικός.
 *
 * Το αρχείο είναι ξεχωριστό γιατί το index.php (φωτογραφίες) δεν φορτώνει το
 * _lib.php: έχει δικό του αντίγραφο της σύνδεσης, σκόπιμα, ώστε μια αλλαγή στο
 * _lib.php να μη σπάσει ένα εργαλείο που δουλεύει. Και τα δύο φορτώνουν αυτό, που
 * είναι και το μόνο σημείο κοινό στα δύο, γι' αυτό φιλοξενεί και το
 * staff_password_elsewhere() παρακάτω.
 * ========================================================================== */

declare(strict_types=1);

/** Είναι το αίτημα πάνω από https;
 *
 *  Ο server είναι nginx, που τερματίζει το TLS πριν φτάσει το αίτημα στην PHP.
 *  Τότε το $_SERVER['HTTPS'] συχνά λείπει, και το cookie της συνεδρίας θα έβγαινε
 *  χωρίς τη σημαία Secure — δηλαδή θα μπορούσε να σταλεί και πάνω από απλό http.
 *
 *  Η λογική είναι επίτηδες μονόδρομη: ο,τιδήποτε κι αν στείλει ο πελάτης μπορεί
 *  μόνο να ΠΡΟΣΘΕΣΕΙ το Secure, ποτέ να το αφαιρέσει. Έτσι ένα ψεύτικο
 *  X-Forwarded-Proto δεν χαλαρώνει τίποτα. */
function request_is_https(): bool {
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    // Πίσω από proxy η τιμή μπορεί να είναι λίστα: «https, http».
    $proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $proto === 'https';
}

/** Νέα πράξη. Επιστρέφει το κείμενο της ερώτησης και ένα αναγνωριστικό. */
function captcha_new(): array {
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $plus = (bool)random_int(0, 1);
    if (!$plus && $a < $b) { $t = $a; $a = $b; $b = $t; }   // να μη βγαίνει αρνητικό

    $_SESSION['captcha'] = [
        'answer' => $plus ? $a + $b : $a - $b,
        'id'     => bin2hex(random_bytes(8)),
        'at'     => time(),
    ];
    return ['q' => $a . ($plus ? ' + ' : ' − ') . $b, 'id' => $_SESSION['captcha']['id']];
}

/** Έλεγχος απάντησης. Μίας χρήσης: όποιο κι αν είναι το αποτέλεσμα, η πράξη σβήνει. */
function captcha_check(string $answer, string $id): bool {
    $c = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']);                       // δεν επιτρέπεται επανάληψη
    if (!is_array($c)) return false;
    if (!hash_equals((string)($c['id'] ?? ''), $id)) return false;
    if (time() - (int)($c['at'] ?? 0) > 1800) return false;   // 30 λεπτά και πολύ είναι
    // Αυστηρός έλεγχος: το «abc» δεν πρέπει να περνάει ως 0.
    $ans = trim($answer);
    if (!preg_match('/^-?\d{1,3}$/', $ans)) return false;
    return (int)$ans === (int)$c['answer'];
}

/** Ο κωδικός ανήκει σε κάποιον ρόλο προσωπικού;
 *
 *  Το index.php δέχεται μόνο τον κωδικό του διαχειριστή. Όταν κάποιος βάλει εκεί
 *  τον κωδικό του λογιστή, το «λάθος κωδικός» είναι παραπλανητικό — ο κωδικός
 *  είναι σωστός, απλώς ανήκει σε άλλη σελίδα. Αυτό το ψάχνει και το λέει.
 *
 *  Διαβάζει το αρχείο προσωπικού μόνο του, γιατί το index.php δεν φορτώνει το
 *  _lib.php. Δεν επιστρέφει ΠΟΤΕ ποιος ρόλος είναι — μόνο ναι/όχι — ώστε η
 *  απάντηση να μη δίνει στοιχείο σε όποιον μαντεύει κωδικούς. */
function staff_password_elsewhere(string $pw): bool {
    if ($pw === '') return false;

    $dir     = __DIR__;
    $outside = dirname(dirname($dir)) . '/naxos-data';
    $candidates = [
        $outside . '/staff.json',
        $dir . '/staff-data.php',
        $dir . '/staff.json',            // παλιά θέση
    ];

    foreach ($candidates as $file) {
        if (!is_file($file)) continue;
        $raw = (string)@file_get_contents($file);
        if (str_ends_with($file, '.php')) {
            $i = strpos($raw, "<<<'NAXOS_GUESTS'\n");
            $j = strrpos($raw, "\nNAXOS_GUESTS;");
            if ($i === false || $j === false || $j < $i) continue;
            $raw = substr($raw, $i + strlen("<<<'NAXOS_GUESTS'\n"), $j - $i - strlen("<<<'NAXOS_GUESTS'\n"));
        }
        $d = json_decode($raw, true);
        foreach ((array)($d['staff'] ?? []) as $s) {
            $h = (string)($s['hash'] ?? '');
            if ($h !== '' && password_verify($pw, $h)) return true;
        }
    }
    return false;
}
