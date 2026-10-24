#!/usr/bin/env python3
"""Φτιάχνει το naxos-deploy.zip — έτοιμο για εξαγωγή μέσα στο public_html.

Τρέξε από τη ρίζα του repo:  python3 tools/gen/package.py

Ο κανόνας είναι «λίστα επιτρεπομένων»: ανεβαίνει μόνο ό,τι είναι ρητά εδώ.
Έτσι δεν μπορεί να ξεφύγει κατά λάθος κωδικός, δεδομένα πελάτη ή δοκιμαστικό
αρχείο — που είναι και ο λόγος που ο έλεγχος στο τέλος το επιβεβαιώνει.
"""
import os
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, "naxos-deploy.zip")

# Σελίδες και στατικά αρχεία στη ρίζα. Ό,τι δεν είναι εδώ δεν μπαίνει.
ROOT_FILES = [
    "index.html", "gallery.html", "amenities.html", "weather.html", "contact.html",
    "forecast-pro.html", "privacy.html", "cookies.html", "404.html",
    "styles.css", "fonts.css", "site.js", "consent.js", "lang-nav.js", "pro.js",
    "lang-system.js", "gallery-data.js", "gallery-tiles.json",
    "robots.txt", "sitemap.xml", "site.webmanifest", ".htaccess",
    "favicon.ico", "favicon-32.png", "favicon-192.png", "favicon-512.png",
    "apple-touch-icon.png", "og-image.jpg",
    "logo-naxos-balcony.png", "logo-naxos-balcony-240.png",
    "logo-naxos-balcony-240.webp", "logo-naxos-balcony-480.webp",
    "favicon-naxos-balcony.png",
    "naxos-balcony-apartments-glinado-view.jpg",
    "naxos-balcony-apartments-glinado-view-480.jpg",
    "naxos-balcony-apartments-glinado-view-480.webp",
    "naxos-balcony-apartments-glinado-view-800.jpg",
    "naxos-balcony-apartments-glinado-view-800.webp",
    "naxos-balcony-apartments-glinado-view-1280.jpg",
    "naxos-balcony-apartments-glinado-view-1280.webp",
    "naxos-balcony-apartments-glinado-view-1600.jpg",
    "naxos-balcony-apartments-glinado-view-1600.webp",
]

# Όλα τα .php του admin — ΜΕ ΕΞΑΙΡΕΣΗ τα αρχεία δεδομένων.
ADMIN_SKIP = {
    "credentials.php",       # ο κωδικός του ιδιοκτήτη
    "guests-data.php",       # ονόματα, ΑΦΜ, τηλέφωνα πελατών
    "staff-data.php",        # κωδικοί προσωπικού
    "settings-data.php",     # ρυθμίσεις (WiFi κ.λπ.)
    "hours-data.php",        # ώρες καθαριότητας
}

# Στο welcome/ ανεβαίνουν μόνο τα δύο σταθερά. Τα προσωπικά λινκ πελατών
# (π.χ. papadopoylos-wg8g.php) τα φτιάχνει το admin στον server.
WELCOME_FILES = ["_view.php", "index.php"]

DIRS = ["fonts", "images/gallery", "en"]

# Τίποτα από αυτά δεν επιτρέπεται να βρεθεί μέσα στο zip, ό,τι κι αν συμβεί.
FORBIDDEN_SUBSTRINGS = ("-data.php", "credentials.php", ".login-attempts",
                        "staff.json", ".md", "test_")
FORBIDDEN_PATTERNS = ("welcome/",)


def collect():
    files = []
    for rel in ROOT_FILES:
        p = os.path.join(ROOT, rel)
        if os.path.isfile(p):
            files.append(rel)
        else:
            print(f"  ⚠ λείπει: {rel}")
    for name in sorted(os.listdir(os.path.join(ROOT, "admin"))):
        if name.endswith(".php") and name not in ADMIN_SKIP:
            files.append("admin/" + name)
    for name in WELCOME_FILES:
        files.append("welcome/" + name)
    for d in DIRS:
        base = os.path.join(ROOT, d)
        for dirpath, _dirnames, filenames in os.walk(base):
            for fn in sorted(filenames):
                full = os.path.join(dirpath, fn)
                files.append(os.path.relpath(full, ROOT))
    return files


def main():
    files = collect()

    # Δίχτυ ασφαλείας: έλεγξε ΠΡΙΝ γράψεις το zip.
    bad = []
    for f in files:
        if any(s in f for s in FORBIDDEN_SUBSTRINGS) and not f.startswith("welcome/"):
            bad.append(f)
        if f.startswith("welcome/") and f not in ("welcome/_view.php", "welcome/index.php"):
            bad.append(f)
    if bad:
        print("  🔴 ΣΦΑΛΜΑ: θα έμπαιναν απαγορευμένα αρχεία:")
        for b in bad:
            print("     ", b)
        sys.exit(1)

    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for f in sorted(files):
            z.write(os.path.join(ROOT, f), f)

    size = os.path.getsize(OUT) / (1024 * 1024)
    print(f"  ✅ {OUT}")
    print(f"     {len(files)} αρχεία, {size:.1f} MB")

    # Επαλήθευση του ΤΕΛΙΚΟΥ zip, όχι της λίστας.
    with zipfile.ZipFile(OUT) as z:
        names = z.namelist()
        leaked = [n for n in names
                  if n.endswith("-data.php") or n.endswith("credentials.php")
                  or ".login-attempts" in n or n.endswith(".md")
                  or (n.startswith("welcome/") and n not in
                      ("welcome/_view.php", "welcome/index.php"))]
        if leaked:
            print("  🔴 ΔΙΑΡΡΟΗ στο zip:", leaked)
            sys.exit(1)
        for need in ("index.html", "admin/_captcha.php", "admin/_lib.php",
                     "admin/logistis.php", "welcome/_view.php", ".htaccess"):
            if need not in names:
                print(f"  🔴 ΛΕΙΠΕΙ: {need}")
                sys.exit(1)
    print("  ✅ καμία διαρροή, όλα τα κρίσιμα αρχεία μέσα")


if __name__ == "__main__":
    main()
