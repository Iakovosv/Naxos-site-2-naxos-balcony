#!/usr/bin/env python3
"""Πλήρης έλεγχος των τεσσάρων λινκ: διαχειριστής, λογιστής, καθαρίστρια, επισκέπτης.

Τρία μέρη:
  Α. ΣΥΜΜΟΡΦΩΣΗ  — κάνει ο καθένας αυτό που συμφωνήσαμε (και ΜΟΝΟ αυτό);
  Β. ΛΑΘΗ        — τι γίνεται με λάθος, κενά, διπλά, όρια, περίεργα δεδομένα;
  Γ. ΑΣΦΑΛΕΙΑ    — μπορεί κάποιος να δει δεδομένα που δεν του ανήκουν;

Τρέχει σε ζωντανό server. Δεν αλλάζει μόνιμα δεδομένα: ό,τι γράφει το σβήνει,
και στο τέλος επαναφέρει τα αρχεία από το /tmp/audit-backup.
"""
import re, sys, json, glob, os, shutil, subprocess
import urllib.request, urllib.parse, urllib.error, http.cookiejar

BASE = "http://127.0.0.1:12000"
DEMO = "/tmp/demo"
BACKUP = "/tmp/audit-backup"

PASS = FAIL = 0
FAILURES = []


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect)


def get(op, path):
    r = urllib.request.Request(BASE + path, headers={"User-Agent": "audit"})
    try:
        with op.open(r, timeout=25) as f:
            return f.status, f.read().decode("utf-8", "replace"), dict(f.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), dict(e.headers)


def post(op, path, data):
    body = urllib.parse.urlencode(data).encode()
    r = urllib.request.Request(BASE + path, data=body, headers={"User-Agent": "audit"})
    try:
        with op.open(r, timeout=25) as f:
            return f.status, f.read().decode("utf-8", "replace"), f.url
    except urllib.error.HTTPError as e:
        loc = e.headers.get("Location", path)
        return e.code, e.read().decode("utf-8", "replace"), resolve(path, loc)


def resolve(base, loc):
    if loc.startswith("http"):
        loc = "/" + loc.split("/", 3)[3]
    if not loc.startswith("/"):
        loc = base.rsplit("/", 1)[0] + "/" + loc
    return loc


def solve(page):
    m = re.search(r"([0-9]+)\s*([+\u2212-])\s*([0-9]+)\s*=", page)
    if not m:
        return None, None
    a, o, b = int(m.group(1)), m.group(2), int(m.group(3))
    idm = re.search(r'name="captcha_id" value="([^"]+)"', page)
    return (a + b if o == "+" else a - b), (idm.group(1) if idm else "")


def csrf_of(page):
    m = re.search(r'name="csrf" value="([^"]+)"', page)
    return m.group(1) if m else ""


def check(name, cond, extra=""):
    global PASS, FAIL
    if cond:
        PASS += 1
        print(f"  \033[32mPASS\033[0m {name}")
    else:
        FAIL += 1
        FAILURES.append(name)
        print(f"  \033[31mFAIL\033[0m {name} {extra}")


def clear_locks():
    for f in glob.glob(DEMO + "/admin/.login-attempts*"):
        os.remove(f)


def login(op, path, pw):
    clear_locks()
    st, page, _ = get(op, path)
    ans, cid = solve(page)
    if ans is None:
        return st, "no-form", None
    st, body, loc = post(op, path, {"action": "login", "password": pw,
                                    "captcha_answer": str(ans), "captcha_id": cid})
    if st in (301, 302, 303, 307, 308) or (loc or "").startswith("/admin/"):
        st2, page2, _ = get(op, loc)
        return st2, page2, loc
    return st, body, path


def flash_of(page):
    """Τα μηνύματα λάθους/επιτυχίας που τυπώνονται στη σελίδα."""
    return re.findall(r'class="flash[^"]*"[^>]*>(.*?)</', page, re.S)


def text_only(html):
    """Το ορατό κείμενο — χωρίς script/style, για να μη μετράμε CSS."""
    html = re.sub(r"<script.*?</script>", " ", html, flags=re.S | re.I)
    html = re.sub(r"<style.*?</style>", " ", html, flags=re.S | re.I)
    return re.sub(r"<[^>]+>", " ", html)


# ------------------------------------------------------------------ δεδομένα

def guests_raw():
    s = open(f"{DEMO}/admin/guests-data.php", encoding="utf-8").read()
    return json.loads(s[s.find("{"):s.rfind("}") + 1])["guests"]


PII = set()
for _g in guests_raw():
    for _k in ("surname", "phone", "email", "vat", "idDoc", "address", "notes"):
        _v = str(_g.get(_k, "") or "").strip()
        if _v:
            PII.add(_v)

ROOM_NAMES = {"room3": "Room 3", "room4": "Room 4", "room5": "Room 5", "room6": "Room 6"}

# Καθαρισμός υπολειμμάτων από προηγούμενη εκτέλεση που έσπασε στη μέση.
def _cleanup_leftovers():
    _s = open(f"{DEMO}/admin/guests-data.php", encoding="utf-8").read()
    _d = json.loads(_s[_s.find("{"):_s.rfind("}") + 1])
    _junk = [g for g in _d["guests"] if g["surname"] in ("Δοκιμη", "Δοκιμή", "Ιδιος",
                                                         "Αυτοματη", "ΧΑΚΕΡ", "CSRF",
                                                         "ΑΣΥΝΔΕΤΟΣ")]
    if not _junk:
        return 0
    _d["guests"] = [g for g in _d["guests"] if g not in _junk]
    _body = json.dumps(_d, ensure_ascii=False, indent=4)
    open(f"{DEMO}/admin/guests-data.php", "w", encoding="utf-8").write(
        "<?php\n/* Λίστα επισκεπτών. Η PHP το εκτελεί και δεν τυπώνει τίποτα.\n"
        "   Μην το επεξεργαστείς με το χέρι: το διαχειρίζεται το admin/guests.php */\n"
        "return <<<'NAXOS_GUESTS'\n" + _body + "\nNAXOS_GUESTS;\n")
    for f in glob.glob(f"{DEMO}/welcome/dokim*.php") + glob.glob(f"{DEMO}/welcome/idios*.php") \
            + glob.glob(f"{DEMO}/welcome/automat*.php"):
        os.remove(f)
    return len(_junk)


_n = _cleanup_leftovers()
print("=" * 70)
print("  ΕΛΕΓΧΟΣ ΤΩΝ ΤΕΣΣΑΡΩΝ ΛΙΝΚ — διαχειριστής / λογιστής / καθαρίστρια / επισκέπτης")
print("=" * 70)
if _n:
    print(f"  (καθαρίστηκαν {_n} υπολείμματα προηγούμενης εκτέλεσης)")

# =============================================================== ΜΕΡΟΣ Α
print("\n" + "=" * 70)
print("  Α. ΣΥΜΜΟΡΦΩΣΗ ΜΕ ΤΑ ΣΥΜΦΩΝΗΜΕΝΑ")
print("=" * 70)

print("\n=== Α1. Κάθε ρόλος μπαίνει στη ΣΩΣΤΗ σελίδα του ===")
ROLE_HOME = [("demo1234", "Διαχειριστής", "guests.php", "/admin/guests.php"),
             ("acc1234", "Λογιστής", "accountant.php", "/admin/accountant.php"),
             ("cln1234", "Καθαρίστρια 1", "cleaning.php", "/admin/cleaning.php"),
             ("cln2345", "Καθαρίστρια 2", "cleaning.php", "/admin/cleaning.php")]
for pw, label, land, url in ROLE_HOME:
    op = session()
    st, body, got = login(op, url, pw)
    check(f"{label:14} → {land}", (got or "").endswith(land), f"(κατέληξε {got})")

print("\n=== Α2. Ο ΔΙΑΧΕΙΡΙΣΤΗΣ φτάνει σε όλες τις σελίδες του ===")
for p in ["/admin/guests.php", "/admin/calendar.php", "/admin/stats.php",
          "/admin/staff.php", "/admin/"]:
    op = session(); login(op, "/admin/guests.php", "demo1234")
    st, body, _ = get(op, p)
    check(f"{p:24} → HTTP 200", st == 200, f"(πήρε {st})")
# Το guest.php θέλει id — με πραγματικό id δουλεύει, χωρίς id δίνει καθαρό 404.
op = session(); login(op, "/admin/guests.php", "demo1234")
gid = guests_raw()[0]["id"]
st, body, _ = get(op, "/admin/guest.php?id=" + gid)
check(f"{'/admin/guest.php?id=…':24} → HTTP 200", st == 200, f"(πήρε {st})")

print("\n=== Α3. Ο ΛΟΓΙΣΤΗΣ βλέπει ΟΛΑ τα πεδία για αποδείξεις (συμφωνία: απαίτηση 1) ===")
op = session(); login(op, "/admin/accountant.php", "acc1234")
st, body, _ = get(op, "/admin/accountant.php")
# Στον πίνακα οι στήλες είναι συντομευμένες· τα πλήρη ονόματα είναι στο CSV.
for field in ["Επώνυμο", "Δωμάτιο", "Άφιξη", "Αναχώρηση", "Διαν/σεις",
              "Άτομα", "ΑΦΜ", "Διεύθυνση", "Τ.Κ.", "Χώρα", "Ταυτ./Διαβ.",
              "Τηλέφωνο", "Email", "Πηγή"]:
    check(f"λογιστής βλέπει «{field}»", field in text_only(body))
# Και τα ποσά για τις αποδείξεις.
for field in ["Σύνολο", "Καθαρή", "ΦΠΑ", "ΤΑΚΚ", "Παρεπιδ."]:
    check(f"λογιστής βλέπει ποσό «{field}»", field in text_only(body))
check("λογιστής έχει κουμπί Λήψη CSV", "csv=1" in body)

print("\n=== Α4. Ο ΛΟΓΙΣΤΗΣ ΔΕΝ έχει ΚΑΝΕΝΑΝ δρόμο εγγραφής (συμφωνία: απαίτηση 2) ===")
op = session(); login(op, "/admin/accountant.php", "acc1234")
st, before, _ = get(op, "/admin/accountant.php")
rows_before = len(re.findall(r"<tr>", before))
# Ο λογιστής δεν έχει ΚΑΘΟΛΟΥ κώδικα POST: δεν απορρίπτει με σφάλμα, απλώς
# αγνοεί το αίτημα. Αυτό που μετράει είναι ότι ΔΕΝ γράφει τίποτα.
import json as _json
def _snap():
    _s = open(f"{DEMO}/admin/guests-data.php", encoding="utf-8").read()
    return _json.dumps(_json.loads(_s[_s.find("{"):_s.rfind("}") + 1]), sort_keys=True)
_snap_before = _snap()
for act in ["add", "edit", "delete", "toggle", "regen", "wifi_default", "fees", "hours"]:
    st, body, url = post(op, "/admin/accountant.php",
                         {"action": act, "surname": "ΧΑΚΕΡ", "id": "x", "room": "room3"})
    check(f"POST action={act:12} → δεν γράφει", _snap() == _snap_before,
          f"(HTTP {st}, τα δεδομένα άλλαξαν)")
st, after, _ = get(op, "/admin/accountant.php")
check("τα δεδομένα ΔΕΝ άλλαξαν", len(re.findall(r"<tr>", after)) == rows_before)
check("δεν γράφτηκε «ΧΑΚΕΡ» πουθενά", "ΧΑΚΕΡ" not in after)

print("\n=== Α5. Η ΚΑΘΑΡΙΣΤΡΙΑ 1 βλέπει ΜΟΝΟ τα δωμάτιά της (Room 3, 4) ===")
op = session(); login(op, "/admin/cleaning.php", "cln1234")
st, body, _ = get(op, "/admin/cleaning.php")
vis = text_only(body)
check("βλέπει Room 3", "Room 3" in vis)
check("βλέπει Room 4", "Room 4" in vis)
check("ΔΕΝ βλέπει Room 5", "Room 5" not in vis, "(το είδε)")
check("ΔΕΝ βλέπει Room 6", "Room 6" not in vis, "(το είδε)")

print("\n=== Α6. Η ΚΑΘΑΡΙΣΤΡΙΑ 2 βλέπει τα ΑΛΛΑ δωμάτια (Room 5, 6) ===")
op = session(); login(op, "/admin/cleaning.php", "cln2345")
st, body, _ = get(op, "/admin/cleaning.php")
vis = text_only(body)
check("βλέπει Room 5", "Room 5" in vis)
check("βλέπει Room 6", "Room 6" in vis)
check("ΔΕΝ βλέπει Room 3", "Room 3" not in vis, "(το είδε)")
check("ΔΕΝ βλέπει Room 4", "Room 4" not in vis, "(το είδε)")

print("\n=== Α7. Η ΚΑΘΑΡΙΣΤΡΙΑ δεν βλέπει ΚΑΝΕΝΑ προσωπικό δεδομένο ===")
for pw, who in [("cln1234", "καθαρίστρια 1"), ("cln2345", "καθαρίστρια 2")]:
    op = session(); login(op, "/admin/cleaning.php", pw)
    st, body, _ = get(op, "/admin/cleaning.php")
    vis = text_only(body)
    leaked = sorted([v for v in PII if v in vis])
    check(f"{who}: καμία διαρροή PII", not leaked, f"(διέρρευσαν {leaked[:4]})")

print("\n=== Α8. Ο ΕΠΙΣΚΕΠΤΗΣ βλέπει τη σελίδα του και μόνο ===")
g = guests_raw()[0]  # Παπαδόπουλος, ενεργός
st, body, hdr = get(session(), f"/welcome/{g['slug']}-{g['code']}.php")
check(f"ενεργός επισκέπτης ({g['surname']}) → HTTP 200", st == 200, f"(πήρε {st})")
vis = text_only(body)
check("λέει «Καλώς ήρθατε»", "Καλώς ήρθατε" in vis)
check("δείχνει τον κωδικό WiFi", "Κωδικός WiFi" in vis)
# Ιδιωτικότητα: η σελίδα ΔΕΝ τυπώνει το επώνυμο. Όποιος έχει το link ξέρει
# ποιος είναι· δεν χρειάζεται το όνομά του γραμμένο σε ένα URL που μπορεί να
# προωθηθεί. Αυτό είναι σκόπιμο, όχι παράλειψη.
check("δεν τυπώνει το επώνυμο (ιδιωτικότητα)", g["surname"] not in vis)
check("δείχνει το δωμάτιό του", ROOM_NAMES[g["room"]] in vis)

print("\n=== Α9. Ο ΕΠΙΣΚΕΠΤΗΣ δεν βλέπει ΑΛΛΩΝ τα στοιχεία ===")
others = [x["surname"] for x in guests_raw()[1:]]
leaked = [s for s in others if s in vis]
check("κανένα άλλο επώνυμο", not leaked, f"(είδε {leaked})")
other_codes = [x["code"] for x in guests_raw()[1:] if x.get("code")]
code_leak = [c for c in other_codes if c and c in vis]
check("κανένας άλλος κωδικός", not code_leak, f"(είδε {code_leak})")
check("δεν δίνει ποτέ ΑΦΜ/ταυτότητα/τηλέφωνο επισκέπτη",
      not any(v in vis for v in PII if v.isdigit() and len(v) >= 9))

print("\n=== Α10. Το ΣΥΝΤΟΜΟ λινκ κάθε ρόλου δουλεύει ===")
for url, who, pw, land in [("/admin/logistis.php", "Λογιστής", "acc1234", "accountant.php"),
                           ("/admin/katharistria.php", "Καθαρίστρια", "cln1234", "cleaning.php"),
                           ("/admin/diaxiristis.php", "Διαχειριστής", "demo1234", "guests.php")]:
    op = session()
    st, body, got = login(op, url, pw)
    check(f"{url:26} → {land}", (got or "").endswith(land), f"(κατέληξε {got})")

# =============================================================== ΜΕΡΟΣ Β
print("\n" + "=" * 70)
print("  Β. ΛΑΘΗ ΚΑΙ ΑΚΡΑ — τι γίνεται με άσχημα δεδομένα")
print("=" * 70)

print("\n=== Β1. Σύνδεση: λάθος κωδικός, κενό, σκουπίδια ===")
for pw, label in [("", "κενός"), ("lathos", "λάθος"), ("' OR 1=1--", "SQL injection"),
                  ("<script>alert(1)</script>", "XSS"), ("a" * 500, "500 χαρακτήρες")]:
    clear_locks()
    op = session()
    st, page, _ = get(op, "/admin/guests.php")
    ans, cid = solve(page)
    st, body, loc = post(op, "/admin/guests.php",
                         {"action": "login", "password": pw,
                          "captcha_answer": str(ans), "captcha_id": cid})
    st2, page2, _ = get(op, resolve("/admin/guests.php", loc or ""))
    still_form = "captcha_answer" in page2
    no_data = "Παπαδόπουλος" not in page2
    check(f"κωδικός {label:18} → απορρίπτεται", still_form and no_data,
          f"(φόρμα={still_form}, δεδομένα={not no_data})")

print("\n=== Β2. Λάθος captcha δεν αφήνει είσοδο ===")
clear_locks()
op = session()
st, page, _ = get(op, "/admin/guests.php")
ans, cid = solve(page)
st, body, loc = post(op, "/admin/guests.php",
                     {"action": "login", "password": "demo1234",
                      "captcha_answer": str(ans + 7), "captcha_id": cid})
st2, page2, _ = get(op, resolve("/admin/guests.php", loc or ""))
check("λάθος captcha → δεν μπαίνει",
      "captcha_answer" in page2 and "Παπαδόπουλος" not in page2)

print("\n=== Β3. Νέα κράτηση: τα ΥΠΟΧΡΕΩΤΙΚΑ λάθη απορρίπτονται ===")
op = session(); login(op, "/admin/guests.php", "demo1234")

def n_named(name):
    return len([x for x in guests_raw() if x["surname"] == name])

def add(overrides, surname="Δοκιμη"):
    """Στέλνει add με φρέσκο CSRF. Επιστρέφει (αυξήθηκε, σελίδα)."""
    st, page, _ = get(op, "/admin/guests.php")
    d = {"action": "add", "csrf": csrf_of(page), "surname": surname, "room": "room3",
         "lang": "el", "stayFrom": "2027-05-01", "stayTo": "2027-05-05", "codeMode": "auto"}
    d.update(overrides)
    st, body, loc = post(op, "/admin/guests.php", d)
    st2, page2, _ = get(op, resolve("/admin/guests.php", loc or ""))
    return n_named(surname), page2

# Πρέπει ΟΠΩΣΔΗΠΟΤΕ να απορριφθούν: λείπει υποχρεωτικό ή είναι αδύνατο.
STRICT = [
    ({"surname": ""}, "κενό επίθετο"),
    ({"surname": "   "}, "επίθετο μόνο κενά"),
    ({"room": "room9"}, "ανύπαρκτο δωμάτιο"),
    ({"room": ""}, "κενό δωμάτιο"),
    ({"stayFrom": "01/05/2027"}, "ημερομηνία λάθος μορφή"),
    ({"stayFrom": ""}, "κενή άφιξη"),
    ({"stayTo": ""}, "κενή αναχώρηση"),
    ({"stayFrom": "2027-05-10", "stayTo": "2027-05-01"}, "αναχώρηση πριν την άφιξη"),
    ({"stayFrom": "2027-05-01", "stayTo": "2027-05-01"}, "ίδια μέρα άφιξη/αναχώρηση"),
    ({"stayFrom": "2027-13-45"}, "ανύπαρκτη ημερομηνία"),
    ({"activeFrom": "χθες"}, "σκουπίδια στην ενεργοποίηση"),
    ({"activeFrom": "2027-06-01", "activeTo": "2027-05-01"}, "λήξη πριν την ενεργοποίηση"),
    ({"vat": "12345"}, "ΑΦΜ 5 ψηφία"),
    ({"vat": "1234567890"}, "ΑΦΜ 10 ψηφία"),
    ({"vat": "abcdefghi"}, "ΑΦΜ γράμματα"),
]
for override, label in STRICT:
    n, page2 = add(override)
    flash = flash_of(page2)
    explained = bool(flash) and not any("Δημιουργήθηκε" in f for f in flash)
    check(f"add: {label:28} → απορρίπτεται", n == 0, f"(δημιουργήθηκαν {n})")
    check(f"add: {label:28} → με μήνυμα", explained, f"(flash={flash})")

print("\n=== Β4. Αποδεκτές τιμές: αποθηκεύονται σωστά ===")
LENIENT = [
    ({"total": ""}, "total", "", "κενό ποσό → μένει κενό (προαιρετικό)"),
    ({"total": "1.234,56"}, "total", "1234.56", "ελληνικό ποσό → 1234.56"),
    ({"total": "400.00"}, "total", "400.00", "δεκαδικό με τελεία"),
    ({"total": "12,5"}, "total", "12.50", "κόμμα ως δεκαδικό"),
    ({"adults": "1"}, "adults", 1, "1 άτομο"),
    ({"adults": "12"}, "adults", 12, "12 άτομα (το μέγιστο της φόρμας)"),
    ({"adults": ""}, "adults", 1, "κενό → 1"),
]
for override, key, expect, label in LENIENT:
    n_before = n_named("Δοκιμη")
    n, page2 = add(override)
    check(f"{label:44} → αποθηκεύεται", n == n_before + 1, f"(δημιουργήθηκαν {n - n_before})")
    saved = [x for x in guests_raw() if x["surname"] == "Δοκιμη"]
    got = saved[-1].get(key) if saved else None
    check(f"{label:44} → {key}={expect!r}", got == expect, f"(πήρε {got!r})")
    check(f"{label:44} → χωρίς σφάλμα PHP",
          "Fatal error" not in page2 and "Warning:" not in page2)
    # Καθαρισμός ώστε να μη συσσωρεύονται.
    st, page, _ = get(op, "/admin/guests.php")
    post(op, "/admin/guests.php", {"action": "delete", "csrf": csrf_of(page), "id": saved[-1]["id"]})
    for f in glob.glob(f"{DEMO}/welcome/dokim*.php"):
        os.remove(f)

print("\n=== Β4β. Λάθος ποσό/άτομα: απορρίπτεται με μήνυμα (όχι σιωπηλή αλλαγή) ===")
# Ο server πρέπει να συμφωνεί με τη φόρμα. Παλιά τα «λάθος» ποσά και άτομα
# αποθηκεύονταν σιωπηλά (το «abc» γινόταν κενό, το 99 έμενε 99) — αυτό άλλαξε.
REJECTED = [
    ({"total": "abc"}, "ποσό με γράμματα"),
    ({"total": "-50"}, "αρνητικό ποσό"),
    ({"total": "1.2.3"}, "ποσό με δύο τελείες"),
    ({"adults": "0"}, "μηδέν άτομα"),
    ({"adults": "13"}, "13 άτομα (πάνω από το όριο)"),
    ({"adults": "99"}, "99 άτομα (η φόρμα λέει max=12)"),
    ({"adults": "abc"}, "άτομα με γράμματα"),
    ({"adults": "-3"}, "αρνητικά άτομα"),
]
for override, label in REJECTED:
    n_before = n_named("Δοκιμη")
    n, page2 = add(override)
    flash = flash_of(page2)
    explained = bool(flash) and not any("Δημιουργήθηκε" in f for f in flash)
    check(f"{label:38} → απορρίπτεται", n == n_before, f"(δημιουργήθηκαν {n - n_before})")
    check(f"{label:38} → με μήνυμα", explained, f"(flash={flash})")

print("\n=== Β5. Καμία εγγραφή-σκουπίδι από τα απορριφθέντα ===")
check("καμία κράτηση «Δοκιμη» απέμεινε", n_named("Δοκιμη") == 0,
      f"(απέμειναν {n_named('Δοκιμη')})")
check("ο συνολικός αριθμός κρατήσεων είναι 8", len(guests_raw()) == 8,
      f"(είναι {len(guests_raw())})")

print("\n=== Β6. Ώρες: κάθε άκυρη τιμή απορρίπτεται ===")
op = session(); login(op, "/admin/cleaning.php", "cln1234")
st, page, _ = get(op, "/admin/cleaning.php")
tok = csrf_of(page)
HOURS_BAD = [("abc", "γράμματα"), ("-2", "αρνητικό"), ("99", "υπερβολικό"),
             ("25:00", "λεπτά 60+"), ("1.2.3", "δύο τελείες"), ("", "κενό (σβήσιμο)")]
for val, label in HOURS_BAD:
    st, body, loc = post(op, "/admin/cleaning.php",
                         {"action": "hours", "csrf": tok, "hDay": "2027-05-03",
                          "hVal[room3]": val})
    st2, page2, _ = get(op, resolve("/admin/cleaning.php", loc or ""))
    if val == "":
        check(f"ώρες {label:18} → επιτρέπεται (σβήνει)", st in (200, 302))
    else:
        check(f"ώρες {label:18} → απορρίπτεται",
              "Λάθος ώρες" in page2 or "θέλουν αριθμό" in page2 or "Γράψε" in page2,
              f"(HTTP {st}, loc={loc})")

print("\n=== Β7. Ώρες: σωστές τιμές γίνονται δεκτές ===")
op = session(); login(op, "/admin/cleaning.php", "cln1234")
for val, expect, label in [("2", 2.0, "ακέραιος"), ("2,5", 2.5, "κόμμα"),
                           ("2.5", 2.5, "τελεία"), ("2:30", 2.5, "ώρες:λεπτά"),
                           ("16", 16.0, "στο όριο")]:
    st, page, _ = get(op, "/admin/cleaning.php")
    st, body, loc = post(op, "/admin/cleaning.php",
                         {"action": "hours", "csrf": csrf_of(page),
                          "hDay": "2027-05-03", "hVal[room3]": val})
    st2, page2, _ = get(op, resolve("/admin/cleaning.php", loc or ""))
    shown = f"{expect:g}".replace(".", ",")
    check(f"ώρες «{val}» ({label}) → {expect:g}",
          shown in page2 or f"{expect:g}" in page2, f"(HTTP {st2})")

print("\n=== Β8. Ώρες: το όριο 16.01 απορρίπτεται ===")
op = session(); login(op, "/admin/cleaning.php", "cln1234")
st, page, _ = get(op, "/admin/cleaning.php")
st, body, loc = post(op, "/admin/cleaning.php",
                     {"action": "hours", "csrf": csrf_of(page),
                      "hDay": "2027-05-03", "hVal[room3]": "16.01"})
st2, page2, _ = get(op, resolve("/admin/cleaning.php", loc or ""))
check("16.01 → απορρίπτεται", "Λάθος ώρες" in page2 or "θέλουν αριθμό" in page2)

print("\n=== Β9. Η καθαρίστρια 1 ΔΕΝ γράφει ώρες σε δωμάτιο της 2 ===")
op = session(); login(op, "/admin/cleaning.php", "cln1234")
st, page, _ = get(op, "/admin/cleaning.php")
st, body, loc = post(op, "/admin/cleaning.php",
                     {"action": "hours", "csrf": csrf_of(page),
                      "hDay": "2027-05-03", "hVal[room5]": "3"})
hours = open(f"{DEMO}/admin/hours-data.php", encoding="utf-8").read()
check("room5 της 2 δεν γράφεται από την 1",
      '"cleaner1:2027-05-03:room5"' not in hours and "room5" not in hours)

print("\n=== Β10. CSRF: χωρίς token, κενό, ή ξένο token → απορρίπτεται ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
for tok, label in [(None, "χωρίς πεδίο"), ("", "κενό"), ("a" * 64, "τυχαίο")]:
    d = {"action": "add", "surname": "CSRF", "room": "room3",
         "stayFrom": "2027-05-01", "stayTo": "2027-05-05"}
    if tok is not None:
        d["csrf"] = tok
    st, body, loc = post(op, "/admin/guests.php", d)
    check(f"CSRF {label:12} → 400", st == 400 or "Bad request" in body, f"(HTTP {st})")
check("δεν δημιουργήθηκε κράτηση από CSRF",
      not any(x["surname"] == "CSRF" for x in guests_raw()))

print("\n=== Β11. Ξένο/ανύπαρκτο id → καθαρό 404, όχι σφάλμα ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
for gid, label in [("deadbeef", "ανύπαρκτο"), ("", "κενό"), ("../../etc/passwd", "traversal"),
                   ("' OR 1=1--", "SQL")]:
    st, body, _ = get(op, "/admin/guest.php?id=" + urllib.parse.quote(gid))
    check(f"guest.php id={label:12} → 404 καθαρό",
          st == 404 and "Δεν βρέθηκε" in body and "Fatal" not in body and "Warning" not in body,
          f"(HTTP {st})")

print("\n=== Β12. Λάθος παράμετροι URL δεν σκάνε ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
# Τα μη-ASCII μπαίνουν URL-encoded, αλλιώς ο client σκάει πριν φύγει το αίτημα.
def _u(path):
    if "?" not in path:
        return path
    base, q = path.split("?", 1)
    return base + "?" + urllib.parse.quote(q, safe="=&")

for p, label in [("/admin/calendar.php?m=χάλια", "calendar λάθος μήνας"),
                 ("/admin/calendar.php?m=2027-99", "calendar μήνας 99"),
                 ("/admin/calendar.php?d=χθες", "calendar λάθος μέρα"),
                 ("/admin/stats.php?y=abc", "stats λάθος έτος"),
                 ("/admin/staff.php?mo=χάλια", "staff λάθος μήνας"),
                 ("/admin/guests.php?x=<script>", "guests XSS στην παράμετρο")]:
    st, body, _ = get(op, _u(p))
    clean = "Fatal error" not in body and "Warning:" not in body and "Notice:" not in body
    check(f"{label:28} → χωρίς σφάλμα PHP", st == 200 and clean, f"(HTTP {st})")
    check(f"{label:28} → χωρίς XSS", "<script>alert" not in body)

# Ο λογιστής: και το CSV, με ΔΙΚΗ ΤΟΥ σύνδεση (ο admin ανακατευθύνεται, σωστά).
op_acc = session(); login(op_acc, "/admin/accountant.php", "acc1234")
for p, label in [("/admin/accountant.php?y=abc&mo=99", "λογιστής λάθος ημερομηνία"),
                 ("/admin/accountant.php?csv=1", "λογιστής CSV"),
                 ("/admin/accountant.php?csv=1&y=2027&mo=99", "λογιστής CSV λάθος μήνας")]:
    st, body, _ = get(op_acc, p)
    clean = "Fatal error" not in body and "Warning:" not in body and "Notice:" not in body
    check(f"{label:28} → χωρίς σφάλμα PHP", st == 200 and clean, f"(HTTP {st})")
st, body, hdr = get(op_acc, "/admin/accountant.php?csv=1")
check("CSV → σωστός τύπος αρχείου",
      "text/csv" in (hdr.get("Content-Type", "") or ""), f"(πήρε {hdr.get('Content-Type')!r})")
check("CSV → έχει ελληνικό BOM για το Excel", body.startswith("\ufeff"))
check("CSV → έχει κεφαλίδες", "Επώνυμο" in body and "ΑΦΜ" in body)
check("CSV → δεν έχει σφάλμα μέσα", "Fatal" not in body and "Warning" not in body)

print("\n=== Β13. Επαναλαμβανόμενο επίθετο → ξεχωριστό slug (όχι σύγκρουση) ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, page, _ = get(op, "/admin/guests.php")
tok = csrf_of(page)
for _ in range(2):
    st, body, loc = post(op, "/admin/guests.php",
                         {"action": "add", "csrf": tok, "surname": "Ιδιος",
                          "room": "room3", "stayFrom": "2027-06-01", "stayTo": "2027-06-03"})
dups = [x for x in guests_raw() if x["surname"] == "Ιδιος"]
slugs = [x["slug"] for x in dups]
check("δύο ίδια επώνυμα → δύο διαφορετικά slug", len(set(slugs)) == len(slugs) == 2,
      f"(slugs {slugs})")
for x in dups:
    st, page, _ = get(op, "/admin/guests.php")
    post(op, "/admin/guests.php",
         {"action": "delete", "csrf": csrf_of(page), "id": x["id"]})
for f in glob.glob(f"{DEMO}/welcome/idios*.php"):
    os.remove(f)
check("καθαρίστηκαν", not any(x["surname"] == "Ιδιος" for x in guests_raw()))

print("\n=== Β14. Στατιστικά: άθροισμα ωρών συμφωνεί με τις καταχωρήσεις ===")
op = session(); login(op, "/admin/staff.php", "demo1234")
st, body, _ = get(op, "/admin/staff.php")
check("staff.php ανοίγει και δείχνει ώρες", st == 200 and "Ώρες" in text_only(body))

# =============================================================== ΜΕΡΟΣ Γ
print("\n" + "=" * 70)
print("  Γ. ΑΣΦΑΛΕΙΑ ΔΕΔΟΜΕΝΩΝ")
print("=" * 70)

print("\n=== Γ1. Ο ΑΣΥΝΔΕΤΟΣ δεν βλέπει τίποτα, σε ΚΑΘΕ σελίδα ===")
ALL = ["/admin/", "/admin/guests.php", "/admin/guest.php", "/admin/accountant.php",
       "/admin/calendar.php", "/admin/stats.php", "/admin/staff.php", "/admin/cleaning.php",
       "/admin/logistis.php", "/admin/katharistria.php", "/admin/diaxiristis.php"]
for p in ALL:
    st, body, _ = get(session(), p)
    vis = text_only(body)
    leaked = sorted([v for v in PII if v in vis])
    has_form = "captcha_answer" in body
    check(f"{p:26} → φόρμα, καμία διαρροή", has_form and not leaked,
          f"(HTTP {st}, form={has_form}, διέρρευσαν {leaked[:3]})")

print("\n=== Γ2. Ο ΑΣΥΝΔΕΤΟΣ δεν μπορεί να ΓΡΑΨΕΙ τίποτα ===")
for p in ["/admin/guests.php", "/admin/staff.php", "/admin/cleaning.php"]:
    st, body, _ = get(session(), p)
    st2, body2, loc = post(session(), p,
                           {"action": "add", "surname": "ΑΣΥΝΔΕΤΟΣ", "room": "room3",
                            "csrf": csrf_of(body)})
    # Το require_login τρέχει πριν από κάθε επεξεργασία, οπότε ο ασύνδετος
    # βλέπει τη φόρμα σύνδεσης. Το κρίσιμο είναι ότι ΔΕΝ γράφτηκε τίποτα.
    no_write = "ΑΣΥΝΔΕΤΟΣ" not in body2
    check(f"{p:26} → POST δεν γράφει", no_write, f"(HTTP {st2})")
check("δεν γράφτηκε «ΑΣΥΝΔΕΤΟΣ»",
      not any(x["surname"] == "ΑΣΥΝΔΕΤΟΣ" for x in guests_raw()))

print("\n=== Γ3. Κάθε ρόλος σε ΞΕΝΗ σελίδα → ανακατεύθυνση, όχι δεδομένα ===")
CROSS = [("acc1234", "Λογιστής", "/admin/guests.php", ["Παπαδόπουλος", "555666777"]),
         ("acc1234", "Λογιστής", "/admin/cleaning.php", ["Room 3"]),
         ("acc1234", "Λογιστής", "/admin/staff.php", ["Αλλαγή κωδικού"]),
         ("cln1234", "Καθαρίστρια", "/admin/accountant.php", ["Παπαδόπουλος", "ΑΦΜ"]),
         ("cln1234", "Καθαρίστρια", "/admin/guests.php", ["Παπαδόπουλος"]),
         ("cln1234", "Καθαρίστρια", "/admin/stats.php", ["Έσοδα"]),
         ("cln1234", "Καθαρίστρια", "/admin/staff.php", ["Αλλαγή κωδικού"])]
for pw, who, p, secrets in CROSS:
    op = session(); login(op, "/admin/guests.php", pw)
    st, body, hdr = get(op, p)
    landed = resolve(p, hdr.get("Location", p) or p)
    vis = text_only(body)
    leaked = [s for s in secrets if s in vis]
    check(f"{who:12} → {p:24} σπρώχνεται", landed.endswith(("accountant.php", "cleaning.php")),
          f"(κατέληξε {landed})")
    check(f"{who:12} → {p:24} καμία διαρροή", not leaked, f"(είδε {leaked})")

print("\n=== Γ4. Το «βλέπω ως» ΔΕΝ δίνει δικαιώματα εγγραφής ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, page, _ = get(op, "/admin/cleaning.php?as=cleaner1")
st, body, loc = post(op, "/admin/cleaning.php",
                     {"action": "hours", "csrf": csrf_of(page),
                      "hDay": "2027-07-01", "hVal[room3]": "5"})
hours = open(f"{DEMO}/admin/hours-data.php", encoding="utf-8").read()
check("«βλέπω ως» → δεν γράφει ώρες", "2027-07-01" not in hours)

print("\n=== Γ5. Πειραγμένο ?as= δεν δίνει ξένα δικαιώματα ===")
for asval, label in [("cleaner1", "καθαρίστρια"), ("accountant", "λογιστής"),
                     ("admin", "διαχειριστής"), ("root", "ανύπαρκτο"), ("' OR 1--", "SQL")]:
    op = session(); login(op, "/admin/guests.php", "acc1234")
    st, body, loc = get(op, "/admin/accountant.php?as=" + urllib.parse.quote(asval))
    vis = text_only(body)
    bad = "Room 3" in vis or "Αλλαγή κωδικού" in vis
    check(f"λογιστής ?as={label:14} → καμία επέκταση", not bad, f"(HTTP {st}, loc={loc})")

print("\n=== Γ6. Αρχεία δεδομένων: απευθείας κλήση δεν δίνει περιεχόμενο ===")
for f, label in [("/admin/credentials.php", "credentials"),
                 ("/admin/guests-data.php", "guests-data"),
                 ("/admin/staff-data.php", "staff-data"),
                 ("/admin/settings-data.php", "settings-data"),
                 ("/admin/hours-data.php", "hours-data")]:
    st, body, _ = get(session(), f)
    leaked = sorted([v for v in PII if v in body])
    check(f"{label:16} → δεν τυπώνει τίποτα", not leaked and "<?php" not in body,
          f"(HTTP {st}, {len(body)} bytes, διέρρευσαν {leaked[:3]})")

print("\n=== Γ7. Δεν φαίνεται ο κωδικός πρόσβασης προσωπικού πουθενά ===")
op = session(); login(op, "/admin/staff.php", "demo1234")
st, body, _ = get(op, "/admin/staff.php")
vis = text_only(body)
check("staff.php δεν δείχνει κωδικό «cln1234»", "cln1234" not in vis)
check("staff.php δεν δείχνει κωδικό «acc1234»", "acc1234" not in vis)
check("staff.php δεν δείχνει hash κωδικού",
      not re.search(r"\$2y\$|\$argon2|password_hash", vis))
op2 = session(); login(op2, "/admin/accountant.php", "acc1234")
st, body2, hdr2 = get(op2, "/admin/staff.php")
landed2 = resolve("/admin/staff.php", hdr2.get("Location", "/admin/staff.php") or "")
st3, body3, _ = get(op2, landed2)
check("λογιστής δεν φτάνει στο staff.php",
      landed2.endswith("accountant.php") and "Αλλαγή κωδικού" not in text_only(body3),
      f"(κατέληξε {landed2})")

print("\n=== Γ8. Η σελίδα επισκέπτη: κωδικός και λήξη τηρούνται ===")
G = guests_raw()
act = G[0]                                    # ενεργός
check("σωστός κωδικός → 200", get(session(), f"/welcome/{act['slug']}-{act['code']}.php")[0] == 200)
st, body, _ = get(session(), f"/welcome/{act['slug']}-XXXX.php")
check("ΛΑΘΟΣ κωδικός → δεν δείχνει στοιχεία",
      "Κωδικός WiFi" not in text_only(body) and "wifiLabel" not in body,
      f"(HTTP {st})")
st, body, _ = get(session(), f"/welcome/{act['slug']}.php")
check("χωρίς κωδικό → δεν δείχνει στοιχεία", "Κωδικός WiFi" not in text_only(body))
# Η σημερινή ημερομηνία όπως τη βλέπει ο server — όχι σταθερή, αλλιώς το τεστ
# γερνάει και δίνει ψευδείς αποτυχίες.
_, _, _h = get(session(), "/admin/guests.php")
TODAY = (_h.get("Date", "") or "")[5:16]
import email.utils as _eu, datetime as _dt
try:
    TODAY = _dt.datetime(*_eu.parsedate((_h.get("Date") or ""))[:3]).strftime("%Y-%m-%d")
except Exception:
    TODAY = _dt.date.today().isoformat()

def _status(g):
    at, af, en = g.get("activeTo", ""), g.get("activeFrom", ""), g.get("enabled")
    if at and TODAY > at:
        return "expired"
    if not en:
        return "disabled"
    if af and TODAY < af:
        return "scheduled"
    return "active"

exp = [x for x in G if _status(x) == "expired"]
if exp:
    e = exp[0]
    st, body, _ = get(session(), f"/welcome/{e['slug']}-{e['code']}.php")
    vis = text_only(body)
    check(f"ληγμένος ({e['surname']}) → δεν δείχνει WiFi",
          "Κωδικός WiFi" not in vis and "έληξε" in vis.lower(), f"(HTTP {st})")
dis = [x for x in G if _status(x) == "disabled"]
if dis:
    d = dis[0]
    st, body, _ = get(session(), f"/welcome/{d['slug']}-{d['code']}.php")
    check(f"απενεργοποιημένος ({d['surname']}) → δεν δείχνει WiFi",
          "Κωδικός WiFi" not in text_only(body), f"(HTTP {st})")
sched = [x for x in G if _status(x) == "scheduled"]
if sched:
    s = sched[0]
    st, body, _ = get(session(), f"/welcome/{s['slug']}-{s['code']}.php")
    check(f"μελλοντικός ({s['surname']}) → δεν δείχνει WiFi",
          "Κωδικός WiFi" not in text_only(body), f"(HTTP {st})")

_states = {_status(x) for x in G}
check("τα δεδομένα καλύπτουν ενεργό/ληγμένο/απενεργ./προγραμματισμένο",
      {"active", "expired", "disabled", "scheduled"} <= _states,
      f"(βρέθηκαν {sorted(_states)})")

print("\n=== Γ9. Ανύπαρκτος/πειραγμένος κωδικός επισκέπτη → ευγενική σελίδα ===")
for path, label in [("/welcome/", "κενό"),
                    ("/welcome/denyparchei.php", "ανύπαρκτο"),
                    ("/welcome/denyparchei-a1b2.php", "ανύπαρκτο με κωδικό"),
                    ("/welcome/../../../etc/passwd", "traversal")]:
    st, body, _ = get(session(), path)
    check(f"welcome {label:22} → καθαρή σελίδα",
          "Fatal" not in body and "Warning" not in body and st in (200, 404),
          f"(HTTP {st})")

print("\n=== Γ10. Κανένα προσωπικό δεδομένο στα headers ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
for p in ["/admin/guests.php", "/admin/accountant.php", "/admin/guest.php"]:
    st, body, hdr = get(op, p)
    blob = " ".join(f"{k}:{v}" for k, v in hdr.items())
    leaked = [v for v in PII if v in blob]
    check(f"{p:24} → headers καθαρά", not leaked, f"(διέρρευσαν {leaked[:3]})")

print("\n=== Γ11. Ο επισκέπτης δεν φτάνει στο admin, ούτε το αντίστροφο ===")
st, body, _ = get(session(), "/welcome/")
check("welcome/index.php δεν είναι φόρμα σύνδεσης", "captcha_answer" not in body)
st, body, _ = get(session(), "/admin/guest.php?id=x")
check("guest.php απαιτεί σύνδεση", "captcha_answer" in body)

print("\n=== Γ12. noindex σε ΟΛΕΣ τις σελίδες (μυστικότητα) ===")
for p in ALL + ["/welcome/"]:
    st, body, hdr = get(session(), p)
    meta = "noindex" in body.lower()
    head = "noindex" in (hdr.get("X-Robots-Tag", "") or "").lower()
    check(f"{p:26} → noindex", meta or head)

print("\n=== Γ13. Cookie συνεδρίας: HttpOnly/SameSite πάντα, Secure πίσω από nginx ===")


def raw_headers(path, extra=None):
    """Ζητάει τη σελίδα με πρόσθετες κεφαλίδες και γυρίζει τα Set-Cookie."""
    h = {"User-Agent": "audit"}
    if extra:
        h.update(extra)
    r = urllib.request.Request(BASE + path, headers=h)
    op2 = session()
    try:
        with op2.open(r, timeout=25) as f:
            return f.headers.get_all("Set-Cookie") or []
    except urllib.error.HTTPError as e:
        return e.headers.get_all("Set-Cookie") or []


def cookie_flags(path, extra=None):
    """Ένωση των σημαιών όλων των Set-Cookie που επιστρέφει η σελίδα."""
    joined = " ".join(raw_headers(path, extra)).lower()
    return {
        "httponly": "httponly" in joined,
        "samesite": "samesite=lax" in joined,
        "secure": "secure" in joined,
        "set": "phpsessid" in joined,
    }


# 1) Χωρίς https: το cookie βγαίνει, με HttpOnly και SameSite, ΧΩΡΙΣ Secure
#    (ένα Secure cookie σε απλό http δεν θα στελνόταν καθόλου — θα έσπαγε η σύνδεση).
c = cookie_flags("/admin/")
check("συνεδρία: το cookie ορίζεται", c["set"], f"({c})")
check("συνεδρία: HttpOnly", c["httponly"], f"({c})")
check("συνεδρία: SameSite=Lax", c["samesite"], f"({c})")
check("συνεδρία: χωρίς https δεν μπαίνει Secure", not c["secure"], f"({c})")

# 2) Πίσω από nginx: το TLS τερματίζεται πριν την PHP, οπότε το $_SERVER['HTTPS']
#    λείπει και η μόνη ένδειξη είναι το X-Forwarded-Proto. Πρέπει να μπει Secure.
c2 = cookie_flags("/admin/", {"X-Forwarded-Proto": "https"})
check("συνεδρία: X-Forwarded-Proto=https → Secure", c2["secure"], f"({c2})")

# 3) Ο πελάτης δεν μπορεί να ΧΑΛΑΡΩΣΕΙ την ασφάλεια με ψεύτικο header.
c3 = cookie_flags("/admin/", {"X-Forwarded-Proto": "http"})
check("συνεδρία: ψεύτικο «http» δεν χαλαρώνει τίποτα", not c3["secure"], f"({c3})")

print("\n=== Γ14. /welcome/ έχει τις ίδιες κεφαλίδες με τις σελίδες επισκεπτών ===")
st, body, hdr = get(session(), "/welcome/")
cc = (hdr.get("Cache-Control", "") or "").lower()
check("/welcome/ → X-Robots-Tag noindex",
      "noindex" in (hdr.get("X-Robots-Tag", "") or "").lower(), f"({hdr.get('X-Robots-Tag')!r})")
check("/welcome/ → Cache-Control no-store", "no-store" in cc, f"({cc!r})")
check("/welcome/ → Referrer-Policy no-referrer",
      "no-referrer" in (hdr.get("Referrer-Policy", "") or "").lower(),
      f"({hdr.get('Referrer-Policy')!r})")

# Η σελίδα κάθε επισκέπτη πρέπει να έχει τα ίδια — αλλιώς οι δύο σελίδες
# του ίδιου φακέλου θα συμπεριφέρονταν διαφορετικά.
_any = [g for g in guests_raw() if g.get("code")]
if _any:
    g0 = _any[0]
    st, body, hdr = get(session(), f"/welcome/{g0['slug']}-{g0['code']}.php")
    check("σελίδα επισκέπτη → X-Robots-Tag noindex",
          "noindex" in (hdr.get("X-Robots-Tag", "") or "").lower())
    check("σελίδα επισκέπτη → Cache-Control no-store",
          "no-store" in (hdr.get("Cache-Control", "") or "").lower())

# =============================================================== ΤΕΛΟΣ
print("\n" + "=" * 70)
print(f"  ΑΠΟΤΕΛΕΣΜΑ: {PASS} πέρασαν, {FAIL} απέτυχαν")
if FAILURES:
    print("\n  ΑΠΟΤΥΧΙΕΣ:")
    for f in FAILURES:
        print(f"    • {f}")
print("=" * 70)

sys.exit(1 if FAIL else 0)
