#!/usr/bin/env python3
"""End-to-end check of the Naxos admin: auth gate, roles, hours, view-as."""
import re, sys, html, json, urllib.request, urllib.parse, http.cookiejar

BASE = "https://work-1-hnyicijvgiyfjvbs.prod-runtime.all-hands.dev"
ok = fail = 0

class NoRedirect(urllib.request.HTTPRedirectHandler):
    """Το POST δεν ακολουθεί το redirect: το μήνυμα λάθους είναι μίας χρήσης
    και καίγεται στη σελίδα προορισμού."""
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect)

def get(op, path):
    r = urllib.request.Request(BASE + path, headers={"User-Agent": "test"})
    try:
        with op.open(r, timeout=20) as f:
            return f.status, f.read().decode("utf-8", "replace"), dict(f.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), dict(e.headers)

def post(op, path, data):
    """Στέλνει POST. Το 302 είναι φυσιολογικό: επιστρέφουμε πού στέλνει."""
    body = urllib.parse.urlencode(data).encode()
    r = urllib.request.Request(BASE + path, data=body, headers={"User-Agent": "test"})
    try:
        with op.open(r, timeout=20) as f:
            return f.status, f.read().decode("utf-8", "replace"), f.url
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), resolve(path, e.headers.get("Location", path))

def resolve(base, loc):
    """Το Location είναι σχετικό («guests.php»)· το κάνουμε απόλυτη διαδρομή."""
    if loc.startswith("http"):
        loc = "/" + loc.split("/", 3)[3]
    if not loc.startswith("/"):
        loc = base.rsplit("/", 1)[0] + "/" + loc
    return loc

def solve(page):
    """Read the sum off the login page and work it out."""
    m = re.search(r"([0-9]+)\s*([+\u2212-])\s*([0-9]+)\s*=", page)
    if not m:
        m = re.search(r"How much is ([0-9]+)\s*([+\u2212-])\s*([0-9]+)\?", page)
    if not m:
        return None, None
    a, op_, b = int(m.group(1)), m.group(2), int(m.group(3))
    ans = a + b if op_ == "+" else a - b
    idm = re.search(r'name="captcha_id" value="([^"]+)"', page)
    return ans, (idm.group(1) if idm else "")

def check(name, cond, extra=""):
    global ok, fail
    if cond:
        ok += 1
        print(f"  \033[32mPASS\033[0m {name}")
    else:
        fail += 1
        print(f"  \033[31mFAIL\033[0m {name} {extra}")

def login(op, path, pw):
    """Συνδέεται. Επιστρέφει (κατάσταση, σώμα, πού κατέληξε).

    Το POST απαντά με redirect· το ακολουθούμε χειροκίνητα, γιατί ο opener δεν
    το κάνει. Έτσι βλέπουμε πού οδηγεί πραγματικά ο ρόλος."""
    st, page, _ = get(op, path)
    ans, cid = solve(page)
    if ans is None:
        return st, "no-form", None
    st, body, loc = post(op, path, {"action": "login", "password": pw,
                                    "captcha_answer": str(ans), "captcha_id": cid})
    if st in (301, 302, 303, 307, 308) or loc.startswith("/admin/"):
        st2, page2, _ = get(op, loc)
        return st2, page2, loc
    return st, body, path

print("\n=== 1. Ο ΑΣΥΝΔΕΤΟΣ ΔΕΝ ΒΛΕΠΕΙ ΔΕΔΟΜΕΝΑ (η κρίσιμη τρύπα) ===")
LEAKS = ["Παπαδόπουλος", "555666777", "123456789", "ΑΦΜ"]
for p in ["/admin/accountant.php", "/admin/calendar.php", "/admin/stats.php",
          "/admin/guests.php", "/admin/staff.php", "/admin/cleaning.php"]:
    op = session()
    st, body, hdr = get(op, p)
    leaked = [t for t in LEAKS if t in body]
    hasform = "captcha_answer" in body
    check(f"{p:26} → φόρμα σύνδεσης, καμία διαρροή",
          st == 200 and hasform and not leaked,
          f"(HTTP {st}, form={hasform}, leaked={leaked})")

print("\n=== 2. noindex σε κάθε σελίδα προσωπικού ===")
for p in ["/admin/accountant.php", "/admin/calendar.php", "/admin/stats.php",
          "/admin/guests.php", "/admin/staff.php", "/admin/cleaning.php", "/admin/"]:
    op = session()
    st, body, hdr = get(op, p)
    xr = hdr.get("X-Robots-Tag", "")
    check(f"{p:26} → X-Robots-Tag", "noindex" in xr, f"(got {xr!r})")

print("\n=== 3. ΣΩΣΤΗ ΣΥΝΔΕΣΗ ΓΙΑ ΚΑΘΕ ΡΟΛΟ ===")
CASES = [("demo1234", "admin", "guests.php"),
         ("acc1234", "accountant", "accountant.php"),
         ("cln1234", "cleaner", "cleaning.php")]
for pw, role, land in CASES:
    op = session()
    st, body, url = login(op, "/admin/guests.php", pw)
    check(f"{role:11} {pw:9} → {land}", url and url.endswith(land), f"(landed {url})")

print("\n=== 4. Ο ΛΟΓΙΣΤΗΣ ΒΛΕΠΕΙ ΤΑ ΠΑΝΤΑ (απαίτηση 1) ===")
op = session(); login(op, "/admin/guests.php", "acc1234")
st, body, _ = get(op, "/admin/accountant.php")
for field in ["ΑΦΜ", "Ταυτ", "Διεύθυνση", "Τηλέφωνο", "Email"]:
    check(f"λογιστής βλέπει πεδίο «{field}»", field in body)

print("\n=== 5. Ο ΛΟΓΙΣΤΗΣ ΔΕΝ ΑΛΛΑΖΕΙ ΤΙΠΟΤΑ (απαίτηση 2) ===")
op = session(); login(op, "/admin/guests.php", "acc1234")
st, before, _ = get(op, "/admin/accountant.php")
n_before = len(re.findall(r"<tr>", before))
st, body, url = post(op, "/admin/accountant.php",
                     {"action": "add", "surname": "HACKER", "room": "room3",
                      "stayFrom": "2026-12-01", "stayTo": "2026-12-05"})
check("POST στη σελίδα λογιστή δεν προσθέτει κράτηση", "HACKER" not in body)

print("\n=== 6. Η ΚΑΘΑΡΙΣΤΡΙΑ ΒΛΕΠΕΙ ΜΟΝΟ ΤΑ ΔΙΚΑ ΤΗΣ, ΧΩΡΙΣ ΠΡΟΣΩΠΙΚΑ ===")
op = session(); login(op, "/admin/guests.php", "cln1234")
st, body, _ = get(op, "/admin/cleaning.php")
check("καμία διαρροή προσωπικών δεδομένων",
      not any(t in body for t in LEAKS))
check("δεν βλέπει δωμάτια άλλης καθαρίστριας", "Room 5" not in body and "Room 6" not in body)

print("\n=== 7. ΏΡΕΣ: Η ΚΑΘΑΡΙΣΤΡΙΑ ΓΡΑΦΕΙ, ΒΓΑΙΝΕΙ ΣΥΝΟΛΟ ΜΗΝΑ (απαίτηση 3) ===")
op = session(); login(op, "/admin/guests.php", "cln1234")
st, page, _ = get(op, "/admin/cleaning.php")
m = re.search(r'name="csrf" value="([^"]+)"', page)
csrf = m.group(1) if m else ""
day = "2026-10-20"
st, _, loc = post(op, "/admin/cleaning.php",
                   {"action": "hours", "csrf": csrf, "hDay": day,
                    "hVal[room3]": "2,5", "hVal[room4]": "1:30"})
st, body, _ = get(op, loc if loc.startswith("/") else "/admin/cleaning.php?d=" + day)
check("καταχώρηση 2,5 + 1:30 ωρών", "Οι ώρες καταχωρήθηκαν" in body)
check("σύνολο μήνα = 4 ώρες", "Ώρες τον μήνα" in body and "4" in body)

print("\n=== 8. ΔΕΝ ΠΕΡΝΑΕΙ ΑΚΥΡΗ ΤΙΜΗ ΩΡΩΝ ===")
st, page, _ = get(op, "/admin/cleaning.php")
csrf = re.search(r'name="csrf" value="([^"]+)"', page).group(1)
st, _, loc = post(op, "/admin/cleaning.php",
                   {"action": "hours", "csrf": csrf, "hDay": day,
                    "hVal[room3]": "abc", "hVal[room4]": "999"})
st, after, _ = get(op, loc if loc.startswith("/") else "/admin/cleaning.php?d=" + day)
check("«abc» απορρίπτεται με μήνυμα", "Λάθος ώρες" in after)
check("η παλιά τιμή 2,5 μένει ανέπαφη", "2,5" in after)

print("\n=== 9. Ο ADMIN ΒΛΕΕΙ ΤΟΝ ΠΙΝΑΚΑ ΩΡΩΝ ΚΑΙ ΔΙΟΡΘΩΝΕΙ (απαιτήσεις 3,4) ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, body, _ = get(op, "/admin/staff.php")
check("σελίδα κωδικών: μόνο admin", st == 200 and "Κωδικοί προσωπικού" in body)
check("πλαίσιο ωρών με σύνολο μήνα", "Ώρες καθαριότητας" in body and "Σύνολο μήνα" in body)

print("\n=== 10. «ΒΛΕΠΩ ΩΣ» — Ο ADMIN ΒΛΕΠΕΙ ΟΠΩΣ Ο ΑΛΛΟΣ (απαίτηση 5) ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, body, url = get(op, "/admin/staff.php?as=accountant")
st2, body2, _ = get(op, "/admin/accountant.php")
check("admin βλέπει ως λογιστής", "Βλέπεις τη σελίδα ως" in body2)
check("admin ως λογιστής ΔΕΝ χάνει τα δικαιώματά του",
      "Πίνακας" in body2, "(πρέπει να υπάρχει σύνδεσμος επιστροφής)")

print("\n=== 11. Η ΚΑΘΑΡΙΣΤΡΙΑ ΔΕΝ ΜΠΑΙΝΕΙ ΣΕ ΞΕΝΗ ΣΕΛΙΔΑ ===")
op = session(); login(op, "/admin/guests.php", "cln1234")
st, body, url = get(op, "/admin/staff.php")
check("καθαρίστρια στο staff.php → επιστρέφει στη σελίδα της",
      "Κωδικοί προσωπικού" not in body, f"(url {url})")

print("\n=== 12. ΛΑΘΟΣ ΚΩΔΙΚΟΣ → ΜΗΝΥΜΑ, ΟΧΙ ΕΙΣΟΔΟΣ ===")
op = session()
st, page, _ = get(op, "/admin/guests.php")
ans, cid = solve(page)
st, body, url = post(op, "/admin/guests.php",
                     {"action": "login", "password": "totally-wrong",
                      "captcha_answer": str(ans), "captcha_id": cid})
st2, page2, _ = get(op, "/admin/guests.php")
check("λάθος κωδικός → «Λάθος κωδικός»", "Λάθος κωδικός" in page2)

print("\n=== 13. ΛΑΘΟΣ ΠΡΑΞΗ ΔΕΝ ΜΕΤΡΑΕΙ ΩΣ ΑΠΟΤΥΧΙΑ ΚΩΔΙΚΟΥ ===")
op = session()
st, page, _ = get(op, "/admin/guests.php")
ans, cid = solve(page)
st, body, url = post(op, "/admin/guests.php",
                     {"action": "login", "password": "demo1234",
                      "captcha_answer": "99999", "captcha_id": cid})
st2, page2, _ = get(op, "/admin/guests.php")
check("λάθος πράξη → ζητά ξανά, δεν κλειδώνει", "πράξη" in page2.lower())
# και μετά με σωστή πράξη περνάει
st, page, _ = get(op, "/admin/guests.php")
ans, cid = solve(page)
st, body, url = post(op, "/admin/guests.php",
                     {"action": "login", "password": "demo1234",
                      "captcha_answer": str(ans), "captcha_id": cid})
check("αμέσως μετά, σωστός κωδικός περνάει", url.endswith("guests.php"))

print("\n=== 14. ΞΕΧΩΡΙΣΤΟ ΚΛΕΙΔΩΜΑ ΑΝΑ ΡΟΛΟ ===")
for _ in range(6):
    op = session()
    st, page, _ = get(op, "/admin/accountant.php")
    ans, cid = solve(page)
    if ans is not None:
        post(op, "/admin/accountant.php",
             {"action": "login", "password": "wrong" + str(_),
              "captcha_answer": str(ans), "captcha_id": cid})
op = session()
st, page, _ = get(op, "/admin/guests.php")
ans, cid = solve(page)
st, body, url = post(op, "/admin/guests.php",
                     {"action": "login", "password": "cln1234",
                      "captcha_answer": str(ans), "captcha_id": cid})
check("6 αποτυχίες λογιστή ΔΕΝ κλειδώνουν την καθαρίστρια",
      url.endswith("cleaning.php"), f"(landed {url})")

# Καθαρίζουμε το κλείδωμα που μόλις προκαλέσαμε, ώστε τα επόμενα τεστ να
# μπορούν να συνδεθούν ως λογιστής. Το κλείδωμα είναι σκόπιμο, όχι σφάλμα.
import glob as _glob, os as _os
for _f in _glob.glob("/tmp/demo/admin/.login-attempts*"):
    _os.remove(_f)

print("\n=== 15. Ο ADMIN ΔΙΟΡΘΩΝΕΙ ΤΙΣ ΩΡΕΣ (απαίτηση 3) ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, page, _ = get(op, "/admin/staff.php?mo=2026-10")
csrf = re.search(r'name="csrf" value="([^"]+)"', page).group(1)
check("ο πίνακας δείχνει την καταχώρηση της καθαρίστριας", "2026-10-20" in page)
st, _, loc = post(op, "/admin/staff.php",
                  {"action": "hours_edit", "csrf": csrf, "hRole": "cleaner1",
                   "hDate": "2026-10-20", "hRoom": "room3", "hVal": "3"})
st, after, _ = get(op, "/admin/staff.php?mo=2026-10")
check("ο admin αλλάζει 2,5 → 3", "3" in after and "Οι ώρες αποθηκεύτηκαν" in after)
st, _, _ = post(op, "/admin/staff.php",
                {"action": "hours_edit", "csrf": csrf, "hRole": "cleaner1",
                 "hDate": "2026-10-20", "hRoom": "room3", "hVal": ""})
st, after, _ = get(op, "/admin/staff.php?mo=2026-10")
check("κενό πεδίο σβήνει την καταχώρηση", "Η καταχώρηση διαγράφηκε" in after)

print("\n=== 16. ΤΟ ΚΕΝΟ CSRF TOKEN ΔΕΝ ΠΕΡΝΑΕΙ (η τρύπα) ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
st, body, _ = post(op, "/admin/guests.php",
                   {"action": "add", "surname": "CSRFBYPASS", "room": "room3",
                    "stayFrom": "2026-12-01", "stayTo": "2026-12-05", "csrf": ""})
st2, after, _ = get(op, "/admin/guests.php")
check("POST με κενό csrf απορρίπτεται", "CSRFBYPASS" not in after)
st, body, _ = post(op, "/admin/guests.php",
                   {"action": "add", "surname": "CSRFBYPASS2", "room": "room3",
                    "stayFrom": "2026-12-01", "stayTo": "2026-12-05"})
st2, after, _ = get(op, "/admin/guests.php")
check("POST χωρίς csrf απορρίπτεται", "CSRFBYPASS2" not in after)

print("\n=== 17. Ο ADMIN ΔΕΝ ΧΑΝΕΙ ΤΟΝ ΡΟΛΟ ΤΟΥ ΜΕ ΤΟ «ΒΛΕΠΩ ΩΣ» ===")
op = session(); login(op, "/admin/guests.php", "demo1234")
get(op, "/admin/staff.php?as=cleaner1")
st, body, _ = get(op, "/admin/staff.php")
check("ο admin βλέπει ακόμη τη σελίδα κωδικών", "Κωδικοί προσωπικού" in body)
st, body, _ = get(op, "/admin/guests.php")
check("ο admin βλέπει ακόμη τους επισκέπτες", "Links επισκεπτών" in body)
get(op, "/admin/staff.php?as=admin")

print("\n=== 18. Η ΣΕΛΙΔΑ ΣΥΝΔΕΣΗΣ ΔΕΙΧΝΕΙ ΤΟΝ ΡΟΛΟ ===")
for p, who in [("/admin/guests.php", "Διαχειριστής"),
               ("/admin/accountant.php", "Λογιστής"),
               ("/admin/cleaning.php", "Καθαρίστρια")]:
    st, body, _ = get(session(), p)
    h1 = re.search(r"<h1>([^<]+)</h1>", body)
    got = h1.group(1).strip() if h1 else ""
    check(f"{p:24} → «{who}» στην κεφαλίδα", got.startswith(who), f"(είδε «{got}»)")
    title = re.search(r"<title>([^<]+)</title>", body)
    check(f"{p:24} → τίτλος με τον ρόλο",
          bool(title) and who in title.group(1),
          f"(είδε «{title.group(1) if title else ''}»)")

print("\n=== 19. ΑΝΟΙΓΜΑ ΞΕΝΗΣ ΣΕΛΙΔΑΣ ΕΞΗΓΕΙΤΑΙ (δεν είναι σιωπηλό) ===")
# Η καθαρίστρια: το τεστ 14 κλειδώνει σκόπιμα τον λογιστή, οπότε δεν μπορεί
# να συνδεθεί ξανά εδώ.
op = session(); login(op, "/admin/cleaning.php", "cln1234")
# get() → (status, body, headers). Το Location είναι σχετικό.
st, body, hdr = get(op, "/admin/accountant.php")
loc = resolve("/admin/accountant.php", hdr.get("Location", ""))
check("η καθαρίστρια στέλνεται στη σελίδα της",
      st == 302 and loc.endswith("cleaning.php"), f"(HTTP {st} → {loc})")
st2, body2, _ = get(op, loc)
m = re.search(r"border-left:4px solid var\(--accent\)[^>]*>(.*?)</div>", body2, re.S)
msg = re.sub(r"\s+", " ", m.group(1)).strip() if m else ""
check("και βλέπει εξήγηση, όχι σιωπή", "Καθαρίστρια" in msg and "Λογιστής" in msg,
      f"(μήνυμα «{msg[:70]}»)")
st, body3, hdr3 = get(op, "/admin/staff.php")
loc3 = resolve("/admin/staff.php", hdr3.get("Location", ""))
check("το ίδιο και για σελίδα διαχειριστή", loc3.endswith("cleaning.php"),
      f"(→ {loc3})")

print("\n=== 20. ΔΕΝ ΑΝΕΒΑΙΝΟΥΝ ΜΥΣΤΙΚΑ ΣΤΟ GIT ===")
import subprocess as sp
for f in ["admin/credentials.php", "admin/guests-data.php", "admin/staff-data.php",
          "admin/settings-data.php", "admin/hours-data.php", "admin/staff.json"]:
    r = sp.run(["git", "-C", "/workspace/project", "check-ignore", "-q", f])
    check(f"{f:28} → αγνοείται", r.returncode == 0)

print("\n=== 21. ΞΕΧΩΡΙΣΤΟ ΣΥΝΤΟΜΟ ΛΙΝΚ ΑΝΑ ΡΟΛΟ ===")
ALIASES = [("/admin/logistis.php", "Λογιστής", "acc1234", "accountant.php"),
           ("/admin/katharistria.php", "Καθαρίστρια", "cln1234", "cleaning.php"),
           ("/admin/diaxiristis.php", "Διαχειριστής", "demo1234", "guests.php")]
for u, who, pw, land in ALIASES:
    st, body, _ = get(session(), u)
    h1 = re.search(r"<h1>([^<]+)</h1>", body)
    got = h1.group(1).strip() if h1 else ""
    check(f"{u:26} → φόρμα «{who}»", st == 200 and got.startswith(who), f"(είδε «{got}»)")
for u, who, pw, land in ALIASES:
    st, body, url = login(session(), u, pw)
    check(f"{u:26} → μπαίνει στο {land}", url.endswith(land), f"(κατέληξε {url})")
# Ο ασύνδετος δεν βλέπει δεδομένα ούτε από τα σύντομα λινκ.
for u, who, pw, land in ALIASES:
    st, body, _ = get(session(), u)
    leaked = [t for t in ["Παπαδόπουλος", "555666777", "ΑΦΜ"] if t in body]
    check(f"{u:26} → καμία διαρροή", not leaked, f"(διέρρευσε {leaked})")

print("\n=== 22. Η ΓΚΑΛΕΡΙ ΕΙΝΑΙ ΜΟΝΟ ΓΙΑ ΤΟΝ ΔΙΑΧΕΙΡΙΣΤΗ ===")
# Η Γκαλερί μοιράζεται τη συνεδρία με τις σελίδες του _lib.php (ίδια κλειδιά
# ok/role). Παλιά αρκούνταν στο «είναι συνδεδεμένος κάποιος», οπότε ο λογιστής
# και οι καθαρίστριες έμπαιναν και μπορούσαν να ανεβάσουν/διαγράψουν φωτογραφίες.
GALLERY_MARK = 'type="file"'
for pw, who, home in [("acc1234", "λογιστής", "/admin/accountant.php"),
                      ("cln1234", "καθαρίστρια", "/admin/cleaning.php")]:
    op = session(); login(op, "/admin/guests.php", pw)
    st, body, _ = get(op, "/admin/")
    check(f"{who:11} → δεν βλέπει φόρμα ανεβάσματος",
          st != 200 or GALLERY_MARK not in body, f"(HTTP {st})")
    check(f"{who:11} → δεν παίρνει CSRF token από τη Γκαλερί",
          not re.search(r'name="csrf" value="[^"]+"', body))
    # Η πύλη τρέχει πριν το check_csrf(), οπότε το POST δεν φτάνει ποτέ στον
    # χειριστή: τον γυρίζει στη δική του σελίδα. Ελέγχουμε ότι δεν εκτελέστηκε.
    st2, body2, hdr2 = post(op, "/admin/", {"action": "delete", "file": "denyparchei-9999.jpg"})
    check(f"{who:11} → POST στη Γκαλερί δεν εκτελείται",
          st2 in (301, 302, 303, 400), f"(HTTP {st2})")

# Ο διαχειριστής δεν έχασε τίποτα.
op = session(); login(op, "/admin/guests.php", "demo1234")
st, body, _ = get(op, "/admin/")
check("admin      → βλέπει τη Γκαλερί", st == 200 and GALLERY_MARK in body, f"(HTTP {st})")
check("admin      → παίρνει CSRF token", bool(re.search(r'name="csrf" value="[^"]+"', body)))

# Ο λόγος λέγεται, δεν είναι σιωπηλός (αλλιώς μοιάζει με «όλα τα link ίδια»).
op = session(); login(op, "/admin/guests.php", "cln1234")
st, _, hdr = get(op, "/admin/")
loc = hdr.get("Location", "")
st2, body2, _ = get(op, loc if loc.startswith("/admin/") else "/admin/cleaning.php")
check("καθαρίστρια → εξηγείται γιατί έφυγε",
      "μόνο για τον διαχειριστή" in body2, f"(HTTP {st2}, loc {loc!r})")

print("\n=== 23. ΠΡΟΓΡΑΜΜΑ ΚΑΘΑΡΙΟΤΗΤΑΣ (όλες οι καθαριότητες, όχι μόνο η επόμενη) ===")

def segment(html, marker, stop=None):
    i = html.find(marker)
    if i < 0:
        return ""
    if stop:
        j = html.find(stop, i)
        if j > 0:
            return html[i:j]
    return html[i:]

def rows_of(seg):
    m = re.search(r"<tbody>(.*?)</tbody>", seg, re.S)
    return re.findall(r"<tr[^>]*>(.*?)</tr>", m.group(1), re.S) if m else []

def cells_of(tr):
    return [re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", c)).strip()
            for c in re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)]

op = session(); login(op, "/admin/guests.php", "cln1234")
st, page, _ = get(op, "/admin/cleaning.php")
check("η κάρτα «Πρόγραμμα καθαριότητας» υπάρχει",
      st == 200 and "Πρόγραμμα καθαριότητας" in page, f"(HTTP {st})")
check("η κάρτα «Καθαριότητες ανά κράτηση» υπάρχει", "Καθαριότητες ανά κράτηση" in page)
# Το μενού δωματίου χτίζεται από τα δικά της δωμάτια, ποτέ από όλα.
check("το μενού δωματίου δεν έχει Room 5/6",
      'value="room5"' not in page and 'value="room6"' not in page)
check("ούτε τα δωμάτια της άλλης φαίνονται πουθενά",
      "Room 5" not in page and "Room 6" not in page)

SCHED = ("Καθαριότητες ανά κράτηση")
seg = segment(page, 'id="programma"', SCHED)
check("προεπιλογή → όλες οι ημερομηνίες, με γραμμές", len(rows_of(seg)) > 0)
check("προεπιλογή → το λέει στη σύνοψη", "Όλες τις ημερομηνίες" in seg)

# Φίλτρο μήνα: ό,τι δείχνει ανήκει στον μήνα.
st, page, _ = get(op, "/admin/cleaning.php?pscope=month&pdate=2026-10-22")
seg = segment(page, 'id="programma"', SCHED)
ym = {yy + "-" + mm for tr in rows_of(seg) for c in cells_of(tr)
      for _d, mm, yy in re.findall(r"(\d{2})/(\d{2})/(\d{4})", c)}
check("φίλτρο μήνα → μόνο Οκτώβριος 2026", ym == {"2026-10"}, f"({ym})")

# Φίλτρο ημέρας: μία μόνο ημέρα.
st, page, _ = get(op, "/admin/cleaning.php?pscope=day&pdate=2026-10-24")
seg = segment(page, 'id="programma"', SCHED)
days = {yy + "-" + mm + "-" + dd for tr in rows_of(seg) for c in cells_of(tr)
        for dd, mm, yy in re.findall(r"(\d{2})/(\d{2})/(\d{4})", c)}
check("φίλτρο ημέρας → μόνο 2026-10-24", days == {"2026-10-24"}, f"({days})")

# Φίλτρο δωματίου. Δύο παγίδες: (α) το μενού επιλογής δείχνει ΚΑΙ τα δύο
# δωμάτια της (σωστά, για να μπορεί να αλλάξει), (β) το rowspan βάζει την
# ημερομηνία στο πρώτο κελί της ομάδας. Ψάχνουμε λοιπόν κελιά που μοιάζουν με
# δωμάτιο, σε όλα τα κελιά.
st, page, _ = get(op, "/admin/cleaning.php?proom[]=room4")
seg = segment(page, 'id="programma"', SCHED)
all_cells = [c for tr in rows_of(seg) for c in cells_of(tr)]
sched_rooms = {c for c in all_cells if re.fullmatch(r"Room \d+", c)}
check("φίλτρο δωματίου → ο πίνακας έχει μόνο Room 4",
      sched_rooms == {"Room 4"}, f"({sched_rooms})")
check("φίλτρο δωματίου → το μενού κρατά και τα δύο (για αλλαγή)",
      'value="room3"' in page and 'value="room4"' in page)

# Άκυρα ορίσματα: δεν σκάνε και δεν δίνουν ξένα δωμάτια.
st, page, _ = get(op, "/admin/cleaning.php?proom[]=room5")
check("άκυρο δωμάτιο (room5) → αγνοείται", st == 200 and "Room 5" not in page, f"(HTTP {st})")
st, page, _ = get(op, "/admin/cleaning.php?pscope=olokliro&pdate=den-einai-imerominia")
check("άκυρες παράμετροι → χωρίς σφάλμα",
      st == 200 and "Fatal" not in page and "Warning" not in page, f"(HTTP {st})")

# Ανά κράτηση: το «Πλήθος» συμφωνεί με τις ημερομηνίες που τυπώνονται.
st, page, _ = get(op, "/admin/cleaning.php")
bad = []
for tr in rows_of(segment(page, SCHED)):
    c = cells_of(tr)
    if len(c) < 4:
        continue
    n = re.sub(r"\D", "", c[2])
    listed = len(re.findall(r"\d{2}/\d{2}", c[3]))
    if n and int(n) != listed:
        bad.append((c[0], n, listed))
check("ανά κράτηση → πλήθος == αριθμός ημερομηνιών", not bad, f"({bad})")

found = [t for t in ["Παπαδόπουλος", "555666777", "123456789", "ΑΦΜ"] if t in page]
check("πρόγραμμα → καμία διαρροή προσωπικών", not found, f"({found})")

print(f"\n{'='*60}\n  ΑΠΟΤΕΛΕΣΜΑ: {ok} πέρασαν, {fail} απέτυχαν\n{'='*60}")
sys.exit(1 if fail else 0)
