<?php
/* ============================================================================
 * Naxos Balcony Apartments — guest welcome page
 * ============================================================================
 * Rendered by the small stub files in this folder:
 *
 *     /welcome/papadopoulos-a7f3.php  ->  $GUEST_SLUG, $GUEST_CODE, require _view.php
 *
 * The stubs exist because the site runs on nginx, which does not read .htaccess,
 * so we cannot rely on a rewrite rule to route /welcome/<name> to a handler.
 * A real file per guest is served directly by nginx and needs nothing from the
 * host. All the logic lives here, so changing the page never means rewriting
 * the stubs.
 *
 * ⚠️  THIS PAGE IS REACHED BY ANYONE WHO HAS THE LINK, so anything printed here is
 *     only as private as the link. The Wi-Fi password and the door code ARE printed
 *     here at the owner's request. Treat both as semi-public: change them when a stay
 *     ends, and keep the stub filename and code hard to guess (see guest_code()).
 *     Never print anything here that would be costly to leak to a forwarded link.
 * ========================================================================== */

declare(strict_types=1);

$GUEST_SLUG = $GUEST_SLUG ?? '';
$GUEST_CODE = $GUEST_CODE ?? '';

require dirname(__DIR__) . '/admin/_lib.php';

/* ------------------------------------------------------------------- lookup */

$guest = guests_find(guests_load(), (string)$GUEST_SLUG);

// Ο κωδικός στο URL πρέπει να ταιριάζει: αν ο διαχειριστής ζήτησε νέο κωδικό,
// το παλιό link σταματά να δουλεύει.
if ($guest !== null && (string)($guest['code'] ?? '') !== (string)$GUEST_CODE) $guest = null;

$status = $guest !== null ? guest_status($guest) : 'missing';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');

$lang = ($guest['lang'] ?? 'el') === 'en' ? 'en' : 'el';

/* ------------------------------------------------------------------- strings
 *
 * ⚠️ ΠΡΟΣΩΡΙΝΑ ΚΕΙΜΕΝΑ. Περιμένουν τα δικά σου, στα ελληνικά και στα αγγλικά. */

$T = [
    'el' => [
        'title'       => 'Το δωμάτιό σας — Naxos Balcony Apartments',
        'welcome'     => 'Καλώς ήρθατε!',
        'yourRoom'    => 'Το δωμάτιό σας είναι το',
        'wifiLabel'   => 'Κωδικός WiFi',
        'wifiHint'    => 'Όλα κεφαλαία, λατινικά γράμματα, χωρίς κενά.',
        'doorLabel'   => 'Κωδικός πρόσβασης',
        'stay'        => 'Η διαμονή σας',
        'checkin'     => 'Άφιξη από',
        'checkout'    => 'Αναχώρηση έως',
        'address'     => 'Διεύθυνση',
        'addressVal'  => 'Γλινάδο, Νάξος 84300',
        'map'         => 'Άνοιγμα στους χάρτες',
        'needHelp'    => 'Χρειάζεστε κάτι;',
        'whatsapp'    => 'WhatsApp',
        'call'        => 'Κλήση',
        'email'       => 'Email',
        'arrivalNote' => 'Θα λάβετε τις οδηγίες πρόσβασης και τον κωδικό για την κάρτα-κλειδί σας λίγο πριν την άφιξή σας.',
        'expiredT'    => 'Ο σύνδεσμος έληξε',
        'expiredB'    => 'Ο σύνδεσμος για τη διαμονή σας δεν είναι πλέον ενεργός. Αν χρειάζεστε τις πληροφορίες, επικοινωνήστε μαζί μας και θα σας τις στείλουμε.',
        'scheduledT'  => 'Ο σύνδεσμος δεν είναι ακόμη ενεργός',
        'scheduledB'  => 'Ο σύνδεσμος για τη διαμονή σας θα ενεργοποιηθεί στις',
        'missingT'    => 'Ο σύνδεσμος δεν βρέθηκε',
        'missingB'    => 'Ο σύνδεσμος που ανοίξατε δεν αντιστοιχεί σε κάποια κράτηση. Ελέγξτε το μήνυμα που λάβατε, ή επικοινωνήστε μαζί μας.',
        'footer'      => 'Naxos Balcony Apartments — Γλινάδο, Νάξος',
        'langOther'   => 'English',
        'goSite'      => 'Πηγαίνετε στο site',
        'draft'       => 'ΠΡΟΧΕΙΡΟ ΚΕΙΜΕΝΟ',

        /* ---------------------------------------------- Οδηγίες εισόδου & κάρτας */
        'guideT'      => '🔑 Οδηγίες Εισόδου & Χρήσης Κάρτας',
        'guideIntro'  => 'Κατά την πρώτη σας άφιξη, θα χρησιμοποιήσετε έναν προσωρινό κωδικό για να ανοίξετε την πόρτα. Ο κωδικός αυτός είναι μοναδικός και ισχύει μόνο για μία χρήση. Θα σας τον στείλουμε με μήνυμα πριν την άφιξή σας.',

        'guideAT'     => '🔢 Χρήση προσωρινού κωδικού',
        'guideA1'     => 'Κατά την πληκτρολόγηση, θα ακούγεται χαρακτηριστικός ήχος σε κάθε πάτημα, ώστε να γνωρίζετε πως το κάθε πλήκτρο έχει καταχωρηθεί.',
        'guideA2'     => 'Αφού εισάγετε τον κωδικό, πατήστε το πλήκτρο # για να τον επικυρώσετε και να ανοίξει η πόρτα.',

        'guideCardT'  => '💳 Κάρτα Πρόσβασης',
        'guideCard1'  => 'Μόλις μπείτε, πάνω στο τραπέζι θα βρείτε μια κάρτα πρόσβασης, με το λογότυπό μας μπροστά και τον αριθμό του δωματίου σας στο πίσω μέρος.',
        'guideCard2'  => 'Από εκείνη τη στιγμή και μετά, θα χρησιμοποιείτε μόνο την κάρτα για την είσοδο στο διαμέρισμα, καθώς ο αρχικός κωδικός θα απενεργοποιηθεί.',
        'guideCard3'  => '❗ Πάντα να έχετε μαζί σας την κάρτα, ώστε να μπορείτε να επιστρέψετε στο διαμέρισμα.',

        'guideDoorT'  => '🚪 Είσοδος με κάρτα',
        'guideDoor1'  => 'Ακουμπήστε την κάρτα στο μαύρο πλαίσιο κάτω από το πληκτρολόγιο που χρησιμοποιήσατε για την πρώτη είσοδο.',
        'guideDoor2'  => 'Μόλις γίνει η αναγνώριση:',
        'guideDoorL1' => 'Τραβήξτε απαλά την πόρτα προς τα έξω',
        'guideDoorL2' => 'Σπρώξτε στη συνέχεια προς τα μέσα για να ανοίξει',

        'guidePowerT' => '⚡ Ρεύμα στο δωμάτιο',
        'guidePower1' => 'Για να ενεργοποιηθεί το ρεύμα, τοποθετήστε την κάρτα στον καρτοδιακόπτη, που βρίσκεται δίπλα στο κουμπί EXIT και κοντά στην πόρτα.',
        'guidePower2' => 'Όσο η κάρτα είναι στον καρτοδιακόπτη, το διαμέρισμα έχει ρεύμα.',

        'guideAcT'     => '❄️ Κλιματισμός',
        'guideAc1'     => 'Για να λειτουργεί το κλιματιστικό, όλα τα παράθυρα πρέπει να είναι κλειστά. Αν κάποιο παραμένει ανοιχτό, η μονάδα δεν ενεργοποιείται.',

        'guideInsideT' => '🔒 Όταν είστε μέσα',
        'guideInside1' => 'Η πόρτα κλειδώνει αυτόματα μόλις την κλείσετε.',
        'guideInside2' => 'Για να βγείτε:',
        'guideInsideL1'=> 'Πατήστε το κουμπί «EXIT»',
        'guideInsideL2'=> 'Σπρώξτε απαλά την πόρτα προς τα έξω',
        'guideInsideL3'=> 'Έπειτα, τραβήξτε την προς το μέρος σας για να ανοίξει',
        'guideInside3' => '❗ Πριν φύγετε, μην ξεχάσετε να πάρετε την κάρτα από τον καρτοδιακόπτη, ώστε να μπορέσετε να επιστρέψετε.',

        /* ---------------------------------------------- Κανόνες καταλύματος */
        'rulesT'       => '🏡 Οδηγίες & Κανόνες Καταλύματος',
        'rulesToiletT' => '🧻 Τουαλέτα',
        'rulesToilet1' => 'Παρακαλούμε μην απορρίπτετε χαρτιά, χαρτομάντιλα ή άλλα αντικείμενα μέσα στη λεκάνη.',
        'rulesToilet2' => 'Χρησιμοποιείτε πάντα τον κάδο που υπάρχει στον χώρο, για να αποφύγουμε φραξίματα στο αποχετευτικό σύστημα.',
        'rulesSmokeT'  => '🚭 Κάπνισμα',
        'rulesSmoke1'  => 'Το κάπνισμα απαγορεύεται αυστηρά σε όλους τους χώρους του καταλύματος.',
        'rulesPetsT'   => '🐾 Κατοικίδια',
        'rulesPets1'   => 'Τα κατοικίδια ζώα δεν επιτρέπονται σε κανένα χώρο του καταλύματος μας.',
        'rulesThanks'  => '🙏 Σας ευχαριστούμε που συμβάλλετε στη διατήρηση της καθαριότητας, της υγιεινής και της θετικής εμπειρίας για όλους!',

        /* ---------------------------------------------- Δώρο & αναχώρηση */
        'gift1'        => '🎁 Πάνω στο τραπέζι θα βρείτε την κάρτα εισόδου, καθώς και ένα δώρο από εμάς – με τις ευχές μας για καλή διαμονή!',
        'gift2'        => '🚪 Παρακαλούμε, κατά την αναχώρησή σας, αφήστε την κάρτα εισόδου στο ίδιο τραπέζι όπου τη βρήκατε.',

        /* ---------------------------------------------- Το τελευταίο, το κρίσιμο */
        'finalT'       => '🚪 Πώς να μπείτε και να βγείτε',
        'finalNote'    => 'Διαβάστε το προσεκτικά — χωρίς αυτό η πόρτα δεν ανοίγει.',
        'finalInT'     => 'Για να μπείτε στο διαμέρισμα:',
        'finalIn1'     => '🔹 Ακουμπήστε την κάρτα εισόδου στο μαύρο πλαίσιο κάτω από το πληκτρολόγιο.',
        'finalIn2'     => '🔹 Μόλις ακούσετε τον ήχο επιβεβαίωσης και ανάψει πράσινο φως,',
        'finalIn3'     => '🔹 Τραβήξτε ελαφρώς την πόρτα προς τα έξω, και μετά σπρώξτε απαλά προς τα μέσα για να ανοίξει.',
        'finalOutT'    => 'Για να βγείτε από το διαμέρισμα:',
        'finalOut1'    => '🔹 Πατήστε το κουμπί EXIT.',
        'finalOut2'    => '🔹 Στη συνέχεια, σπρώξτε ελαφρώς την πόρτα προς τα έξω και έπειτα τραβήξτε την προς το μέρος σας.',
        'mustRead'     => 'Διάβασα τις οδηγίες εισόδου',
        'readHint'     => 'Είναι απαραίτητο για να ανοίξετε την πόρτα.',
        'readDone'     => 'Τέλεια — είστε έτοιμοι! ✓',

        'closing'      => 'Αν χρειαστείτε οτιδήποτε κατά τη διάρκεια της διαμονής σας, μη διστάσετε να επικοινωνήσετε μαζί μας.',
        'goodStay'     => 'Καλή διαμονή! 😊',
    ],
    'en' => [
        'title'       => 'Your room — Naxos Balcony Apartments',
        'welcome'     => 'Welcome!',
        'yourRoom'    => 'Your room is',
        'wifiLabel'   => 'Wi-Fi password',
        'wifiHint'    => 'All capital letters, no spaces.',
        'doorLabel'   => 'Access code',
        'stay'        => 'Your stay',
        'checkin'     => 'Check-in from',
        'checkout'    => 'Check-out by',
        'address'     => 'Address',
        'addressVal'  => 'Glinado, Naxos 84300, Greece',
        'map'         => 'Open in maps',
        'needHelp'    => 'Need anything?',
        'whatsapp'    => 'WhatsApp',
        'call'        => 'Call',
        'email'       => 'Email',
        'arrivalNote' => 'You will receive the access instructions and the key-card code shortly before your arrival.',
        'expiredT'    => 'This link has expired',
        'expiredB'    => 'The link for your stay is no longer active. If you still need the information, get in touch and we will send it to you.',
        'scheduledT'  => 'This link is not active yet',
        'scheduledB'  => 'The link for your stay becomes active on',
        'missingT'    => 'Link not found',
        'missingB'    => 'The link you opened does not match a booking. Check the message you received, or get in touch with us.',
        'footer'      => 'Naxos Balcony Apartments — Glinado, Naxos',
        'langOther'   => 'Ελληνικά',
        'goSite'      => 'Go to the website',
        'draft'       => 'DRAFT TEXT',

        /* ---------------------------------------------- Entry & card instructions */
        'guideT'      => '🔑 Entry Instructions & Card Use',
        'guideIntro'  => 'On your first arrival, you will use a temporary code to open the door. This code is unique and valid for one use only. We will send it to you by message before your arrival.',

        'guideAT'     => '🔢 Using the temporary code',
        'guideA1'     => 'As you type, you will hear a distinct sound with every press, so you know each key has been registered.',
        'guideA2'     => 'After entering the code, press the # key to confirm it and open the door.',

        'guideCardT'  => '💳 Access Card',
        'guideCard1'  => 'Once you are inside, you will find an access card on the table, with our logo on the front and your room number on the back.',
        'guideCard2'  => 'From that moment on, you will use only the card to enter the apartment, as the initial code will be deactivated.',
        'guideCard3'  => '❗ Always keep the card with you, so you can return to the apartment.',

        'guideDoorT'  => '🚪 Entering with the card',
        'guideDoor1'  => 'Touch the card to the black panel below the keypad you used for your first entry.',
        'guideDoor2'  => 'Once it is recognised:',
        'guideDoorL1' => 'Pull the door gently outwards',
        'guideDoorL2' => 'Then push it inwards to open',

        'guidePowerT' => '⚡ Power in the room',
        'guidePower1' => 'To turn on the power, place the card in the card switch, which is next to the EXIT button and close to the door.',
        'guidePower2' => 'As long as the card is in the card switch, the apartment has power.',

        'guideAcT'     => '❄️ Air conditioning',
        'guideAc1'     => 'For the air conditioner to work, all windows must be closed. If any window stays open, the unit will not turn on.',

        'guideInsideT' => '🔒 When you are inside',
        'guideInside1' => 'The door locks automatically as soon as you close it.',
        'guideInside2' => 'To get out:',
        'guideInsideL1'=> 'Press the "EXIT" button',
        'guideInsideL2'=> 'Push the door gently outwards',
        'guideInsideL3'=> 'Then pull it towards you to open',
        'guideInside3' => '❗ Before you leave, do not forget to take the card from the card switch, so you can return.',

        /* ---------------------------------------------- Property rules */
        'rulesT'       => '🏡 Property Instructions & Rules',
        'rulesToiletT' => '🧻 Toilet',
        'rulesToilet1' => 'Please do not throw paper, tissues or other items into the toilet bowl.',
        'rulesToilet2' => 'Always use the bin provided in the area, to avoid blockages in the drainage system.',
        'rulesSmokeT'  => '🚭 Smoking',
        'rulesSmoke1'  => 'Smoking is strictly prohibited in all areas of the property.',
        'rulesPetsT'   => '🐾 Pets',
        'rulesPets1'   => 'Pets are not allowed in any area of our property.',
        'rulesThanks'  => '🙏 Thank you for helping us keep the property clean, hygienic and a positive experience for everyone!',

        /* ---------------------------------------------- Gift & departure */
        'gift1'        => '🎁 On the table you will find the access card, as well as a gift from us – with our best wishes for a pleasant stay!',
        'gift2'        => '🚪 When you check out, please leave the access card on the same table where you found it.',

        /* ---------------------------------------------- The last one, the critical one */
        'finalT'       => '🚪 How to enter and exit',
        'finalNote'    => 'Please read this carefully — without it the door will not open.',
        'finalInT'     => 'To enter the apartment:',
        'finalIn1'     => '🔹 Touch the access card to the black panel below the keypad.',
        'finalIn2'     => '🔹 Once you hear the confirmation sound and a green light appears,',
        'finalIn3'     => '🔹 Pull the door slightly outwards, then push it gently inwards to open.',
        'finalOutT'    => 'To leave the apartment:',
        'finalOut1'    => '🔹 Press the EXIT button.',
        'finalOut2'    => '🔹 Then push the door slightly outwards and then pull it towards you.',
        'mustRead'     => 'I have read the entry instructions',
        'readHint'     => 'This is required so you can open the door.',
        'readDone'     => 'All set — you are ready! ✓',

        'closing'      => 'If you need anything during your stay, do not hesitate to contact us.',
        'goodStay'     => 'Enjoy your stay! 😊',
    ],
];

// Η γλώσσα ορίζεται από τη ρύθμιση του επισκέπτη. Το ?lang= είναι προαιρετικό.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'en'], true)) {
    $lang = (string)$_GET['lang'];
}
$t = $T[$lang];

/* --------------------------------------------------------- stay formatting */

function fmt_date(string $iso, string $lang): string {
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$d) return $iso;
    if ($lang === 'en') return $d->format('j F Y');
    $m = [1=>'Ιανουαρίου','Φεβρουαρίου','Μαρτίου','Απριλίου','Μαΐου','Ιουνίου',
          'Ιουλίου','Αυγούστου','Σεπτεμβρίου','Οκτωβρίου','Νοεμβρίου','Δεκεμβρίου'];
    return $d->format('j') . ' ' . $m[(int)$d->format('n')] . ' ' . $d->format('Y');
}

$roomLabel = $guest !== null ? (ROOMS_G[$guest['room'] ?? ''][$lang] ?? '') : '';

// Στοιχεία πρόσβασης. Ο κωδικός WiFi έχει προεπιλογή· ο κωδικός εισόδου
// εμφανίζεται μόνο αν τον έχει δώσει ο διαχειριστής.
$wifiPass = $guest !== null ? guest_wifi($guest) : '';
$doorCode = $guest !== null
    ? trim((string)($guest['doorCode'] ?? '')) : '';

// Φωτογραφία του δωματίου από τη λίστα της γκαλερί, αν υπάρχει.
$photo = null;
if ($guest !== null) {
    $tilesFile = dirname(__DIR__) . '/gallery-tiles.json';
    if (is_file($tilesFile)) {
        $tiles = json_decode((string)file_get_contents($tilesFile), true);
        if (is_array($tiles)) {
            foreach ($tiles as $tl) {
                if (($tl['cat'] ?? '') !== ($guest['room'] ?? '')) continue;
                $rel = ltrim((string)($tl['thumb'] ?? ''), '/');
                if ($rel !== '' && is_file(dirname(__DIR__) . '/' . $rel)) { $photo = $rel; break; }
            }
        }
    }
}

$otherLang = ($guest !== null && $status === 'active')
    ? '?lang=' . ($lang === 'el' ? 'en' : 'el')
    : null;
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($t['title']) ?></title>
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="../favicon-32.png">
<link rel="apple-touch-icon" href="../apple-touch-icon.png">
<link rel="stylesheet" href="../fonts.css">
<link rel="stylesheet" href="../styles.css">
<style>
  /* Σελίδα επισκέπτη — ίδια χρώματα, γραμματοσειρές και ακμές με το site. */
  .w-hero{
    background:linear-gradient(180deg,rgba(12,24,34,.92),rgba(12,24,34,.74)),
               url("../naxos-balcony-apartments-glinado-view-1280.jpg") center/cover no-repeat;
    color:#fff;padding:40px 0 34px;position:relative;
  }
  .w-hero .logo img{height:42px;width:auto;display:block}
  .w-hero h1{font-size:clamp(1.6rem,5vw,2.25rem);margin:20px 0 4px;line-height:1.2}
  .w-sub{opacity:.9;font-size:.98rem;margin:0}
  .w-wrap{max-width:var(--maxw);margin:0 auto;padding:0 20px}
  .w-main{max-width:720px;margin:0 auto;padding:26px 20px 56px}
  .w-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);
          padding:24px;box-shadow:var(--shadow);margin:0 0 18px}
  .w-room{display:inline-block;font-weight:700;color:#fff;background:var(--accent-dark);
          padding:8px 20px;border-radius:999px;font-size:1.3rem;margin-top:4px}
  .w-photo{width:100%;height:auto;border-radius:var(--radius);display:block;margin:0 0 18px}
  .w-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}
  .w-item .k{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700}
  .w-item .v{font-size:1.03rem;margin-top:3px;color:var(--ink)}
  .w-access-k{margin:16px 0 6px;color:var(--muted);font-size:.94rem}
  .w-hint{margin:8px 0 0;color:var(--muted);font-size:.86rem}
  .w-code{font-size:1.05rem;letter-spacing:.08em}

  /* ---------------------------------------------------- οδηγίες & κανόνες
     Αναγνώσιμα: μεγάλα περιθώρια, καθαρές ενότητες, λίστες με οδηγό. */
  .w-guide h3{margin:22px 0 8px;font-size:1.02rem;color:var(--ink);
              display:flex;align-items:center;gap:8px}
  .w-guide h3:first-of-type{margin-top:4px}
  .w-guide p{margin:0 0 10px;color:var(--ink);font-size:.96rem;line-height:1.62}
  .w-guide ul{margin:0 0 12px;padding-left:22px;color:var(--ink);font-size:.96rem;line-height:1.62}
  .w-guide li{margin:0 0 5px}
  .w-guide .w-warn{color:var(--accent-dark);font-weight:600}
  .w-guide .w-soft{background:var(--soft);border-radius:10px;padding:13px 15px;margin:0 0 12px}
  .w-guide .w-soft p:last-child{margin-bottom:0}
  .w-closing{background:var(--soft);border-left:4px solid var(--accent-2);
             border-radius:10px;padding:16px 18px;margin:0 0 18px}
  .w-closing p{margin:0;font-size:.98rem;color:var(--ink)}
  .w-closing .w-good{margin-top:8px;font-weight:700;font-size:1.05rem}

  /* Το κρίσιμο μπλοκ: ξεχωρίζει, αλλά δεν κρύβεται. */
  .w-final{border:2px solid var(--accent-dark);border-radius:var(--radius);
           background:#fffdf9;padding:22px 22px 18px;margin:0 0 18px}
  .w-final h2{margin:0 0 6px;font-size:1.12rem;color:var(--accent-dark)}
  .w-final .w-must{color:var(--accent-dark);font-weight:700;font-size:.94rem;margin:0 0 14px}
  .w-final h3{margin:16px 0 8px;font-size:.98rem}
  .w-final ul{margin:0;padding-left:20px;font-size:.96rem;line-height:1.6}
  /* Το «διάβασα»: καθαρή, μεγάλη επιλογή — όχι μικρό checkbox. */
  .w-read{margin:18px 0 0;padding:14px 16px;border:1px solid var(--line);border-radius:12px;
          background:#fff;display:flex;gap:12px;align-items:flex-start;cursor:pointer}
  .w-read input{width:22px;height:22px;margin:1px 0 0;flex:0 0 auto;accent-color:var(--accent-dark);cursor:pointer}
  .w-read .w-read-t{font-weight:700;font-size:.97rem;color:var(--ink)}
  .w-read .w-read-h{font-size:.85rem;color:var(--muted);margin-top:3px}
  .w-read.is-done{background:#f0f8f3;border-color:#bfe3c9}
  .w-read.is-done .w-read-h{color:#1d7a4d;font-weight:600}
  .w-note{background:var(--soft);border-left:4px solid var(--accent-2);border-radius:10px;
          padding:14px 16px;font-size:.94rem;margin:0 0 18px}
  .w-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:4px}
  .w-actions a{flex:1 1 140px;min-height:48px;display:flex;align-items:center;justify-content:center;
               gap:8px;text-decoration:none;font-weight:600;border-radius:12px;
               border:1px solid var(--line);color:var(--ink);background:#fff}
  .w-actions a.primary{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
  .w-state{text-align:center;padding:32px 22px}
  .w-state .ico{font-size:2.3rem;line-height:1;margin-bottom:10px}
  .w-state h2{margin:0 0 10px;font-size:1.25rem}
  .w-state p{color:var(--muted);margin:0 0 20px}
  .w-foot{text-align:center;color:var(--muted);font-size:.85rem;padding:0 20px 36px}
  .w-lang{position:absolute;top:16px;right:20px;color:#fff;font-size:.88rem;text-decoration:none;
          border:1px solid rgba(255,255,255,.55);padding:7px 14px;border-radius:999px}
  /* Πρόχειρο σήμα: φεύγει μόλις μπουν τα πραγματικά κείμενα. */
  .w-draft{background:#fff8e6;border:1px dashed #d9a94a;color:#7a5a12;border-radius:10px;
           padding:10px 14px;font-size:.82rem;margin:0 0 16px}
  @media(max-width:520px){
    .w-card{padding:19px}
    .w-lang{top:12px;right:14px}
    .w-hero .logo img{height:36px}
    .w-final{padding:18px 16px 14px}
    .w-guide p,.w-guide ul{font-size:.94rem}
  }
</style>
</head>
<body>

<header class="w-hero">
  <div class="w-wrap">
    <a class="logo" href="../<?= $lang === 'en' ? 'en/' : '' ?>index.html">
      <picture>
        <source type="image/webp" srcset="../logo-naxos-balcony-240.webp 240w, ../logo-naxos-balcony-480.webp 480w" sizes="140px">
        <img src="../logo-naxos-balcony-240.png" width="240" height="128" alt="Naxos Balcony Apartments logo">
      </picture>
    </a>
    <?php if ($otherLang !== null): ?>
      <a class="w-lang" href="<?= h($otherLang) ?>" hreflang="<?= $lang === 'el' ? 'en' : 'el' ?>"><?= h($t['langOther']) ?></a>
    <?php endif; ?>
    <h1><?= h($t['welcome']) ?></h1>
    <p class="w-sub"><?= h($t['footer']) ?></p>
  </div>
</header>

<main class="w-main">

<?php /* Υπενθύμιση για τον ιδιοκτήτη, όχι για τον επισκέπτη. */ ?>
<?php if (is_admin()): ?>
<div class="w-draft">
  <strong><?= h($t['draft']) ?></strong> —
  <?= $lang === 'en'
      ? 'the wording below is a placeholder and will be replaced with your own text.'
      : 'τα κείμενα παρακάτω είναι προσωρινά και θα αντικατασταθούν με τα δικά σου.' ?>
</div>
<?php endif; ?>

<?php if ($status === 'active' && $guest !== null): ?>

  <?php if ($photo !== null): ?>
    <img class="w-photo" src="../<?= h($photo) ?>" alt="<?= h($roomLabel) ?>" width="700" height="467">
  <?php endif; ?>

  <div class="w-card">
    <p style="margin:0 0 6px;color:var(--muted);font-size:.94rem"><?= h($t['yourRoom']) ?></p>
    <span class="w-room"><?= h($roomLabel) ?></span>

    <?php if ($wifiPass !== ''): ?>
      <p class="w-access-k"><?= h($t['wifiLabel']) ?></p>
      <span class="w-room w-code"><?= h($wifiPass) ?></span>
      <p class="w-hint"><?= h($t['wifiHint']) ?></p>
    <?php endif; ?>

    <?php if ($doorCode !== ''): ?>
      <p class="w-access-k"><?= h($t['doorLabel']) ?></p>
      <span class="w-room w-code"><?= h($doorCode) ?></span>
    <?php endif; ?>
  </div>

  <div class="w-card">
    <h2 style="margin:0 0 16px;font-size:1.08rem"><?= h($t['stay']) ?></h2>
    <div class="w-grid">
      <div class="w-item">
        <div class="k"><?= h($t['checkin']) ?></div>
        <div class="v"><?= h(fmt_date((string)($guest['stayFrom'] ?? ''), $lang)) ?> — 15:00</div>
      </div>
      <div class="w-item">
        <div class="k"><?= h($t['checkout']) ?></div>
        <div class="v"><?= h(fmt_date((string)($guest['stayTo'] ?? ''), $lang)) ?> — 11:00</div>
      </div>
      <div class="w-item">
        <div class="k"><?= h($t['address']) ?></div>
        <div class="v">
          <?= h($t['addressVal']) ?><br>
          <a href="https://www.google.com/maps/search/?api=1&amp;query=Naxos+Balcony+Apartments+Glinado+Naxos"
             target="_blank" rel="noopener" style="color:var(--accent-2)"><?= h($t['map']) ?></a>
        </div>
      </div>
    </div>
  </div>

  <?php /* Όταν ο κωδικός εισόδου είναι ήδη εδώ, το «θα λάβετε τον κωδικό πριν την
           άφιξη» θα ήταν αναληθές — το κρύβουμε χωρίς να αλλάξουμε το κείμενο. */ ?>
  <?php if ($doorCode === ''): ?>
    <div class="w-note"><?= h($t['arrivalNote']) ?></div>
  <?php endif; ?>

  <div class="w-card">
    <h2 style="margin:0 0 14px;font-size:1.08rem"><?= h($t['needHelp']) ?></h2>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
      <a href="mailto:naxosbalcony@gmail.com"><span aria-hidden="true">✉️</span> <?= h($t['email']) ?></a>
    </div>
  </div>

  <?php /* ------------------------------------------------------ ΟΔΗΓΙΕΣ ΕΙΣΟΔΟΥ
     Τα κείμενα είναι ακριβώς όπως τα έδωσε ο ιδιοκτήτης. Δεν αλλάζουμε λέξη. */ ?>
  <div class="w-card w-guide">
    <h2 style="margin:0 0 14px;font-size:1.15rem"><?= h($t['guideT']) ?></h2>
    <p><?= h($t['guideIntro']) ?></p>

    <h3><?= h($t['guideAT']) ?></h3>
    <p><?= h($t['guideA1']) ?></p>
    <p><?= h($t['guideA2']) ?></p>

    <h3><?= h($t['guideCardT']) ?></h3>
    <p><?= h($t['guideCard1']) ?></p>
    <p><?= h($t['guideCard2']) ?></p>
    <p class="w-warn"><?= h($t['guideCard3']) ?></p>

    <h3><?= h($t['guideDoorT']) ?></h3>
    <p><?= h($t['guideDoor1']) ?></p>
    <p><?= h($t['guideDoor2']) ?></p>
    <ul>
      <li><?= h($t['guideDoorL1']) ?></li>
      <li><?= h($t['guideDoorL2']) ?></li>
    </ul>

    <h3><?= h($t['guidePowerT']) ?></h3>
    <p><?= h($t['guidePower1']) ?></p>
    <p><?= h($t['guidePower2']) ?></p>

    <h3><?= h($t['guideAcT']) ?></h3>
    <p><?= h($t['guideAc1']) ?></p>

    <h3><?= h($t['guideInsideT']) ?></h3>
    <p><?= h($t['guideInside1']) ?></p>
    <p><?= h($t['guideInside2']) ?></p>
    <ul>
      <li><?= h($t['guideInsideL1']) ?></li>
      <li><?= h($t['guideInsideL2']) ?></li>
      <li><?= h($t['guideInsideL3']) ?></li>
    </ul>
    <p class="w-warn"><?= h($t['guideInside3']) ?></p>
  </div>

  <?php /* ------------------------------------------------------ ΚΑΝΟΝΕΣ ΚΑΤΑΛΥΜΑΤΟΣ */ ?>
  <div class="w-card w-guide">
    <h2 style="margin:0 0 14px;font-size:1.15rem"><?= h($t['rulesT']) ?></h2>

    <h3><?= h($t['rulesToiletT']) ?></h3>
    <p><?= h($t['rulesToilet1']) ?></p>
    <p><?= h($t['rulesToilet2']) ?></p>

    <h3><?= h($t['rulesSmokeT']) ?></h3>
    <p><?= h($t['rulesSmoke1']) ?></p>

    <h3><?= h($t['rulesPetsT']) ?></h3>
    <p><?= h($t['rulesPets1']) ?></p>

    <div class="w-soft"><p><?= h($t['rulesThanks']) ?></p></div>
  </div>

  <?php /* ------------------------------------------------------ ΔΩΡΟ & ΑΝΑΧΩΡΗΣΗ */ ?>
  <div class="w-card w-guide">
    <p><?= h($t['gift1']) ?></p>
    <p style="margin-bottom:0"><?= h($t['gift2']) ?></p>
  </div>

  <?php /* ------------------------------------- ΤΟ ΚΡΙΣΙΜΟ: ΜΠΑΙΝΕΙ ΤΕΛΕΥΤΑΙΟ
     Ο ιδιοκτήτης το θέλει στο τέλος, ώστε να το έχουν διαβάσει όλοι. Γι' αυτό
     έχει το πλαίσιο, τη μεγαλύτερη αντίθεση και το «Διάβασα» που πρέπει να
     πατηθεί — αλλιώς περνά απαρατήρητο και η πόρτα δεν ανοίγει. */ ?>
  <div class="w-final">
    <h2><?= h($t['finalT']) ?></h2>
    <p class="w-must"><?= h($t['finalNote']) ?></p>

    <h3><?= h($t['finalInT']) ?></h3>
    <ul>
      <li><?= h($t['finalIn1']) ?></li>
      <li><?= h($t['finalIn2']) ?></li>
      <li><?= h($t['finalIn3']) ?></li>
    </ul>

    <h3><?= h($t['finalOutT']) ?></h3>
    <ul>
      <li><?= h($t['finalOut1']) ?></li>
      <li><?= h($t['finalOut2']) ?></li>
    </ul>

    <label class="w-read" id="wRead">
      <input type="checkbox" id="wReadBox">
      <span>
        <span class="w-read-t"><?= h($t['mustRead']) ?></span>
        <span class="w-read-h" id="wReadHint"><?= h($t['readHint']) ?></span>
      </span>
    </label>
  </div>

  <div class="w-closing">
    <p><?= h($t['closing']) ?></p>
    <p class="w-good"><?= h($t['goodStay']) ?></p>
  </div>

<?php elseif ($status === 'scheduled' && $guest !== null): ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">⏳</div>
    <h2><?= h($t['scheduledT']) ?></h2>
    <p><?= h($t['scheduledB']) ?> <strong><?= h(fmt_date((string)($guest['activeFrom'] ?? ''), $lang)) ?></strong>.</p>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
    </div>
  </div>

<?php elseif ($status === 'expired' || $status === 'disabled'): ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">🔒</div>
    <h2><?= h($t['expiredT']) ?></h2>
    <p><?= h($t['expiredB']) ?></p>
    <div class="w-actions">
      <a class="primary" href="https://wa.me/306942279665" target="_blank" rel="noopener">
        <span aria-hidden="true">💬</span> <?= h($t['whatsapp']) ?></a>
      <a href="mailto:naxosbalcony@gmail.com"><span aria-hidden="true">✉️</span> <?= h($t['email']) ?></a>
    </div>
  </div>

<?php else: ?>

  <div class="w-card w-state">
    <div class="ico" aria-hidden="true">❓</div>
    <h2><?= h($t['missingT']) ?></h2>
    <p><?= h($t['missingB']) ?></p>
    <div class="w-actions">
      <a class="primary" href="../<?= $lang === 'en' ? 'en/' : '' ?>index.html"><?= h($t['goSite']) ?></a>
      <a href="tel:+306942279665"><span aria-hidden="true">📞</span> <?= h($t['call']) ?></a>
    </div>
  </div>

<?php endif; ?>

</main>

<p class="w-foot"><?= h($t['footer']) ?></p>

<script>
/* Το «Διάβασα τις οδηγίες»: αλλάζει χρώμα όταν πατηθεί, ώστε να φαίνεται ότι
   καταχωρήθηκε. Δεν μπλοκάρει τίποτα — η πόρτα ανοίγει με την κάρτα, όχι με
   αυτό. Είναι υπενθύμιση, όχι φραγμός: ένα checkbox που σε εμποδίζει θα
   εκνεύριζε τον επισκέπτη που μόλις ταξίδεψε. */
(function () {
  var box = document.getElementById('wReadBox');
  var card = document.getElementById('wRead');
  var hint = document.getElementById('wReadHint');
  if (!box || !card) return;
  var done = <?= json_encode($t['readDone'], JSON_UNESCAPED_UNICODE) ?>;
  var back = <?= json_encode($t['readHint'], JSON_UNESCAPED_UNICODE) ?>;
  function paint() {
    card.classList.toggle('is-done', box.checked);
    if (hint) hint.textContent = box.checked ? done : back;
  }
  box.addEventListener('change', paint);
  paint();
})();
</script>

</body>
</html>
