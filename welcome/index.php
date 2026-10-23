<?php
/* ============================================================================
 *  welcome/index.php — ασφαλής προσγείωση για άκυρα ή παλιά links
 *
 *  Ο σκοπός είναι να μη δει ποτέ ο επισκέπτης σφάλμα 404 του server. Όποιος
 *  φτάσει εδώ με λάθος ή ληγμένο link βλέπει μια ευγενική σελίδα που εξηγεί
 *  τι συνέβη, στη γλώσσα του.
 *
 *  Deploy: upload to  <public_html>/welcome/index.php
 * ========================================================================== */

declare(strict_types=1);

$lang = (($_GET['lang'] ?? '') === 'en') ? 'en' : 'el';

$t = [
    'el' => [
        'title'  => 'Ο σύνδεσμος δεν είναι ενεργός',
        'h1'     => 'Ο σύνδεσμος δεν είναι ενεργός',
        'p1'     => 'Ο σύνδεσμος που ανοίξατε δεν αντιστοιχεί σε κάποια ενεργή κράτηση. '
                  . 'Μπορεί να έχει λήξει, ή να μην έχει αντιγραφεί ολόκληρος.',
        'p2'     => 'Επικοινωνήστε μαζί μας και θα σας στείλουμε νέο σύνδεσμο.',
        'whatsapp' => 'WhatsApp',
        'call'   => 'Τηλέφωνο',
        'email'  => 'Email',
        'home'   => 'Ιστοσελίδα',
        'other'  => 'English',
    ],
    'en' => [
        'title'  => 'This link is not active',
        'h1'     => 'This link is not active',
        'p1'     => 'The link you opened does not match an active booking. '
                  . 'It may have expired, or it may not have been copied in full.',
        'p2'     => 'Get in touch and we will send you a new link.',
        'whatsapp' => 'WhatsApp',
        'call'   => 'Phone',
        'email'  => 'Email',
        'home'   => 'Website',
        'other'  => 'Ελληνικά',
    ],
][$lang];

$phone    = '+306942279665';
$wa       = '306942279665';
$mail     = 'naxosbalcony@gmail.com';
$otherUrl = 'index.php?lang=' . ($lang === 'el' ? 'en' : 'el');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?> — Naxos Balcony Apartments</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0c1822">
<link rel="icon" href="../favicon.ico" sizes="any">
<style>
:root{--ink:#0c1822;--muted:#5d6b78;--line:#e2e8ee;--accent:#c2703f;--accent-dark:#a85c30}
*{box-sizing:border-box}
body{margin:0;background:#f7f9fb;color:var(--ink);
     font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
     display:grid;place-items:center;min-height:100vh;padding:24px 18px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:34px 30px;
      max-width:520px;width:100%;text-align:center;
      box-shadow:0 6px 24px rgba(12,24,34,.07)}
.mark{font-size:2.6rem;line-height:1;margin:0 0 14px}
h1{margin:0 0 12px;font-size:1.45rem;letter-spacing:-.01em}
p{margin:0 0 14px;color:var(--muted);font-size:.96rem}
p.lead{color:var(--ink)}
.acts{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:22px 0 0}
a.btn{text-decoration:none;padding:12px 20px;border-radius:11px;border:1px solid var(--line);
      background:#fff;color:var(--ink);font-weight:600;font-size:.92rem;min-height:46px;
      display:inline-flex;align-items:center;gap:8px}
a.btn.pri{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
.foot{margin:22px 0 0;padding:16px 0 0;border-top:1px solid var(--line);
      font-size:.85rem;color:var(--muted)}
.foot a{color:var(--accent-dark);text-decoration:none;font-weight:600}
@media(max-width:420px){.card{padding:26px 20px}a.btn{flex:1 1 100%;justify-content:center}}
</style>
</head>
<body>
<div class="card">
  <div class="mark">🔑</div>
  <h1><?= htmlspecialchars($t['h1'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="lead"><?= htmlspecialchars($t['p1'], ENT_QUOTES, 'UTF-8') ?></p>
  <p><?= htmlspecialchars($t['p2'], ENT_QUOTES, 'UTF-8') ?></p>

  <div class="acts">
    <a class="btn pri" href="https://wa.me/<?= $wa ?>" rel="noopener"><?= htmlspecialchars($t['whatsapp'], ENT_QUOTES, 'UTF-8') ?></a>
    <a class="btn" href="tel:<?= $phone ?>"><?= htmlspecialchars($t['call'], ENT_QUOTES, 'UTF-8') ?></a>
    <a class="btn" href="mailto:<?= $mail ?>"><?= htmlspecialchars($t['email'], ENT_QUOTES, 'UTF-8') ?></a>
  </div>

  <p class="foot">
    Naxos Balcony Apartments · Γλινάδο, Νάξος<br>
    <a href="../index.html"><?= htmlspecialchars($t['home'], ENT_QUOTES, 'UTF-8') ?></a>
    &nbsp;·&nbsp;
    <a href="<?= htmlspecialchars($otherUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['other'], ENT_QUOTES, 'UTF-8') ?></a>
  </p>
</div>
</body>
</html>
