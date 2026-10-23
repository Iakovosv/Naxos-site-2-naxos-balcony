# -*- coding: utf-8 -*-
"""Static site generator -- Naxos Balcony Apartments.

Builds the Greek tree at the site root and the English tree under /en/.
Every visible string comes from the original site: the body fragments hold the
Greek markup, and translations.json was extracted verbatim from the unmodified
lang-system.js. Nothing is reworded -- the English tree is generated from the
translations that already shipped inside lang-system.js.
"""
import json, os, re, html
from urllib.parse import quote

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = "/workspace/project/preview"

CFG = json.load(open(os.path.join(HERE, "site-config.json"), encoding="utf-8"))
TR = json.load(open(os.path.join(HERE, "translations.json"), encoding="utf-8"))
BIZ = CFG["business"]
BASE = CFG["base"]
GA4 = CFG["ga4"]
PAGES = CFG["pages"]

# Strings the original left in Greek even in English mode, plus the new UI text
# the GDPR banner and the legal/404 pages need.
EXTRA = {
    "weatherWindDirLabel": ("Προσανατολισμός", "Orientation"),
    "proLiveData": ("Live Δεδομένα", "Live Data"),
    "proTemp": ("Θερμοκρασία", "Temperature"),
    "proFeels": ("Feels Like", "Feels Like"),
    "proWind": ("Άνεμος", "Wind"),
    "proGust": ("Ριπές", "Gusts"),
    "proDir": ("Κατεύθυνση", "Direction"),
    "proHum": ("Υγρασία", "Humidity"),
    "proDew": ("Dew Point", "Dew Point"),
    "proPress": ("Πίεση", "Pressure"),
    "proRainRate": ("Rain Rate", "Rain Rate"),
    "proRainDay": ("Ετήσια Βροχή", "Yearly Rain"),
    "proUV": ("UV Index", "UV Index"),
    "proSolar": ("Ηλιακή Ακτινοβολία", "Solar Radiation"),
    "proForecastTitle": ("Πρόβλεψη Επόμενων Ημερών", "Next Days Forecast"),
    "proLoading": ("Φόρτωση...", "Loading..."),
    "proRainChart": ("Μηνιαία Βροχόπτωση", "Monthly Rainfall"),
    "proPressChart": ("Ατμοσφαιρική Πίεση (24h)", "Atmospheric Pressure (24h)"),
    "proTempChart": ("Min / Max Θερμοκρασίες", "Min / Max Temperatures"),
    "proCtaTitle": ("Η καλύτερη θέα της Νάξου σας περιμένει...",
                    "The best view in Naxos is waiting for you..."),
    "proCtaText": ("Κάντε την κράτησή σας στο Naxos Balcony Apartments και ζήστε την απόλυτη εμπειρία διαμονής στη Νάξο",
                   "Book your stay at Naxos Balcony Apartments and enjoy the ultimate holiday experience in Naxos"),
    "proCtaButton": ("ΔΕΙΤΕ ΤΑ ΔΩΜΑΤΙΑ", "SEE THE ROOMS"),
    "proSeoH1": ("Ζωντανά Μετεωρολογικά Δεδομένα & Καιρός στο Γλινάδο Νάξου – Weather Station Pro.",
                 "Live Weather Data & Conditions in Glinado, Naxos – Weather Station Pro."),
    "proSeoText": ("Παρακολουθήστε σε πραγματικό χρόνο τις καιρικές συνθήκες στο Γλινάδο Νάξου μέσα από τον επαγγελματικό μετεωρολογικό σταθμό του Naxos Balcony. Η σελίδα Weather Station Pro προσφέρει αναλυτικά δεδομένα, όπως ιστορικό βροχόπτωσης 24 μηνών, διακυμάνσεις ατμοσφαιρικής πίεσης και μετρήσεις ηλιακής ακτινοβολίας. Αποτελεί το απόλυτο εργαλείο για windsurfers, λάτρεις της μετεωρολογίας και ταξιδιώτες που αναζητούν την πιο έγκυρη πρόγνωση καιρού στις Κυκλάδες.",
                   "Follow the weather conditions in Glinado, Naxos in real time through the professional weather station at Naxos Balcony. The Weather Station Pro page offers detailed data such as a 24-month rainfall history, atmospheric pressure variations and solar radiation measurements. It is the ideal tool for windsurfers, weather enthusiasts and travellers looking for the most reliable forecast in the Cyclades."),
    "cookieTitle": ("Σεβόμαστε τα δεδομένα σας", "We respect your data"),
    "cookieText": ("Χρησιμοποιούμε cookies για να κατανοήσουμε πώς χρησιμοποιείται ο ιστότοπος. Δεν χρησιμοποιούμε διαφημιστικά cookies. Μπορείτε να αποδεχτείτε ή να απορρίψετε τα αναλυτικά cookies.",
                   "We use cookies to understand how the site is used. We do not use advertising cookies. You can accept or reject analytics cookies."),
    "cookieAccept": ("Αποδοχή", "Accept"),
    "cookieReject": ("Απόρριψη", "Reject"),
    "cookieMore": ("Πολιτική Cookies", "Cookie Policy"),
    "cookieSettings": ("Ρυθμίσεις Cookies", "Cookie settings"),
    "privacyLink": ("Απόρρητο", "Privacy"),
    "cookiesLink": ("Cookies", "Cookies"),
    "notFoundTitle": ("Η σελίδα δεν βρέθηκε", "Page not found"),
    "notFoundText": ("Η σελίδα που ψάχνετε δεν υπάρχει ή μετακινήθηκε.",
                     "The page you are looking for does not exist or has moved."),
    "notFoundHome": ("Επιστροφή στην αρχική", "Back to home"),
    "privacyTitle": ("Πολιτική Απορρήτου", "Privacy Policy"),
    "cookiesTitle": ("Πολιτική Cookies", "Cookie Policy"),
    "legalIntro": ("Η παρούσα πολιτική εξηγεί ποια δεδομένα συλλέγονται όταν επισκέπτεστε τον ιστότοπο του Naxos Balcony Apartments και πώς χρησιμοποιούνται.",
                   "This policy explains what data is collected when you visit the Naxos Balcony Apartments website and how it is used."),
    "legalController": ("Υπεύθυνος επεξεργασίας", "Data controller"),
    "legalDataTitle": ("Ποια δεδομένα συλλέγουμε", "What data we collect"),
    "legalDataText": ("Δεν ζητάμε εγγραφή και δεν συλλέγουμε προσωπικά δεδομένα για διαφημιστικούς σκοπούς. Αν μας καλέσετε ή μας στείλετε email, τα στοιχεία σας χρησιμοποιούνται μόνο για να απαντήσουμε στο αίτημά σας. Αν αποδεχτείτε τα αναλυτικά cookies, η Google Analytics καταγράφει ανώνυμα στατιστικά επισκεψιμότητας.",
                       "We do not require registration and do not collect personal data for advertising. If you call us or send an email, your details are used only to answer your request. If you accept analytics cookies, Google Analytics records anonymised traffic statistics."),
    "legalCookiesTitle": ("Cookies", "Cookies"),
    "legalCookiesText": ("Ο ιστότοπος χρησιμοποιεί δύο κατηγορίες cookies: τα απαραίτητα, που χρειάζονται για τη λειτουργία της σελίδας και την αποθήκευση της γλώσσας που επιλέξατε, και τα αναλυτικά (Google Analytics), τα οποία ενεργοποιούνται μόνο με τη συγκατάθεσή σας.",
                         "The site uses two categories of cookies: essential cookies, needed for the site to work and to remember the language you chose, and analytics cookies (Google Analytics), which are enabled only with your consent."),
    "legalRightsTitle": ("Τα δικαιώματά σας", "Your rights"),
    "legalRightsText": ("Έχετε δικαίωμα πρόσβασης, διόρθωσης και διαγραφής των δεδομένων σας, καθώς και δικαίωμα ανάκλησης της συγκατάθεσής σας ανά πάσα στιγμή. Για οποιοδήποτε αίτημα επικοινωνήστε μαζί μας στο naxosbalcony@gmail.com.",
                        "You have the right to access, correct and delete your data, and to withdraw your consent at any time. For any request contact us at naxosbalcony@gmail.com."),
    "legalContactTitle": ("Επικοινωνία", "Contact"),
    "legalUpdated": ("Τελευταία ενημέρωση: Οκτώβριος 2026", "Last updated: October 2026"),
    # filter captions shown under the gallery tabs (Greek text that the original
    # site kept in site.js untranslated)
    "roomInfo3": ("Room 3: Αυτόνομο δωμάτιο 26 τ.μ. με πλήρη κουζίνα.",
                  "Room 3: Self-contained 26 sqm room with a full kitchen."),
    "roomInfo4": ("Room 4: Ευρύχωρο διαμέρισμα 26 τ.μ. με πλήρη κουζίνα.",
                  "Room 4: Spacious 26 sqm apartment with a full kitchen."),
    "roomInfo5": ("Room 5: Studio 23 τ.μ. με πλήρη κουζίνα και την καλύτερη θέα στο Αιγαίο.",
                  "Room 5: 23 sqm studio with a full kitchen and the best view of the Aegean."),
    "roomInfo6": ("Room 6: Αυτόνομο διαμέρισμα 26 τ.μ. με πλήρη κουζίνα και πανοραμική θέα.",
                  "Room 6: Self-contained 26 sqm apartment with a full kitchen and panoramic views."),
    "roomInfoExterior": ("Εξωτερικοί χώροι του συγκροτήματος.",
                         "Exterior areas of the complex."),
    "roomInfoSunset": ("Το μαγευτικό ηλιοβασίλεμα από το μπαλκόνι μας.",
                       "The magical sunset from our balcony."),
    # Floating booking button. Direct contact only: the owner does not want
    # visitors sent to Booking.com or Airbnb.
    "bookFab": ("ΚΡΑΤΗΣΗ", "BOOK"),
    "bookFabLabel": ("Κράτηση — τρόποι επικοινωνίας", "Book — ways to contact us"),
    "bookWaText": ("Γεια σας! Ενδιαφέρομαι για διαμονή στο Naxos Balcony Apartments.\n"
                   "Ημερομηνίες: \nΆτομα: ",
                   "Hello! I would like to book a stay at Naxos Balcony Apartments.\n"
                   "Dates: \nGuests: "),
    "bookMailSubject": ("Αίτημα κράτησης — Naxos Balcony Apartments",
                        "Booking request — Naxos Balcony Apartments"),
}

def t(lang, key, fallback=""):
    if key in TR[lang]:
        return TR[lang][key]
    if key in EXTRA:
        return EXTRA[key][0 if lang == "el" else 1]
    return fallback

def has(lang, key):
    return key in TR[lang] or key in EXTRA

def esc(s, quote=True):
    return html.escape(s, quote=quote)

def jsonld(obj):
    return ('<script type="application/ld+json">'
            + json.dumps(obj, ensure_ascii=False, separators=(",", ":")) + "</script>")

# ------------------------------------------------------------------ schema.org

def schemas(lang, page):
    pfx = "" if lang == "el" else "en/"
    out = [
        {"@context": "https://schema.org", "@type": "LodgingBusiness",
         "@id": BASE + "/#lodging", "name": BIZ["name"], "url": BASE + "/",
         "telephone": BIZ["telephone"], "email": BIZ["email"],
         "image": [BASE + "/" + BIZ["heroImage"], BASE + "/og-image.jpg"],
         "description": t("el", "heroDescription"),
         "address": {"@type": "PostalAddress", "streetAddress": BIZ["streetAddress"],
                     "addressLocality": BIZ["addressLocality"],
                     "addressRegion": BIZ["addressRegion"],
                     "postalCode": BIZ["postalCode"],
                     "addressCountry": BIZ["addressCountry"]},
         "geo": {"@type": "GeoCoordinates", "latitude": BIZ["latitude"],
                 "longitude": BIZ["longitude"]},
         "sameAs": [BIZ["facebook"], BIZ["instagram"]],
         "identifier": BIZ["license"], "numberOfRooms": BIZ["numberOfRooms"],
         "checkinTime": BIZ["checkinTime"], "checkoutTime": BIZ["checkoutTime"],
         "priceRange": BIZ["priceRange"], "currenciesAccepted": "EUR",
         "amenityFeature": [
             {"@type": "LocationFeatureSpecification", "name": "Free WiFi", "value": True},
             {"@type": "LocationFeatureSpecification", "name": "Free private parking", "value": True},
             {"@type": "LocationFeatureSpecification", "name": "Air conditioning", "value": True},
             {"@type": "LocationFeatureSpecification", "name": "Full kitchen", "value": True},
             {"@type": "LocationFeatureSpecification", "name": "Sea view", "value": True},
             {"@type": "LocationFeatureSpecification", "name": "Self check-in", "value": True}]},
        {"@context": "https://schema.org", "@type": "WebSite", "@id": BASE + "/#website",
         "url": BASE + ("/" if lang == "el" else "/en/"), "name": BIZ["name"],
         "inLanguage": "el-GR" if lang == "el" else "en",
         "publisher": {"@id": BASE + "/#lodging"}},
    ]
    if page.get("breadcrumb"):
        out.append({"@context": "https://schema.org", "@type": "BreadcrumbList",
                    "itemListElement": [
                        {"@type": "ListItem", "position": 1,
                         "name": "Αρχική" if lang == "el" else "Home", "item": BASE + "/"},
                        {"@type": "ListItem", "position": 2, "name": t(lang, page["breadcrumb"]),
                         "item": BASE + "/" + pfx + page["file"]}]})
    if page["file"] == "index.html" and lang == "el":
        out.append({"@context": "https://schema.org", "@type": "ItemList",
                    "name": "Δωμάτια",
                    "itemListElement": [
                        {"@type": "ListItem", "position": i + 1, "name": t(lang, k)}
                        for i, k in enumerate(["room3Title", "room4Title",
                                               "room5Title", "room6Title"])]})
    return "\n".join(jsonld(s) for s in out)

# ------------------------------------------------------------------ body work

ASSET_RE = re.compile(
    r'(?P<attr>\b(?:src|href|poster)\s*=\s*")(?P<url>(?!https?:|//|\.\./|/|#|mailto:|tel:|data:)[^"]*)"')

SRCSET_RE = re.compile(r'(?P<attr>\bsrcset\s*=\s*")(?P<val>[^"]*)"')

def asset_paths(body, depth):
    if depth == 0:
        return body
    up = "../" * depth

    def fix_srcset(m):
        # each entry is "url [descriptor]"; only the url needs prefixing
        parts = []
        for entry in m.group("val").split(","):
            bits = entry.strip().split(None, 1)
            if not bits:
                continue
            url = bits[0]
            if url.startswith(("http:", "https:", "//", "/", "data:", up)):
                parts.append(entry.strip())
            else:
                parts.append(up + url + ((" " + bits[1]) if len(bits) > 1 else ""))
        return m.group("attr") + ", ".join(parts) + '"'

    body = SRCSET_RE.sub(fix_srcset, body)

    def repl(m):
        url = m.group("url")
        # A page link stays in the current directory, so it must not be
        # prefixed. Query strings are allowed too: a room card links to
        # "gallery.html?room=room3#tabs", and without the "?" in this pattern
        # the English page would get "../gallery.html?..." and fall back into
        # the Greek tree.
        if re.match(r"^[a-z0-9_.-]+\.html?(?:\?[^#\"]*)?(?:#.*)?$", url):
            return m.group(0)
        return m.group("attr") + up + url + '"'
    return ASSET_RE.sub(repl, body)

def prerender_gallery(body, lang):
    tiles = json.load(open(os.path.join(HERE, "gallery-tiles.json"), encoding="utf-8"))
    rows = []
    for x in tiles:
        alt = x["alt"]
        if lang == "en" and x.get("title_key") and has("en", x["title_key"]):
            base = t("en", x["title_key"])
            alt = base if not x["idx"] else "%s - photo %s" % (base, x["idx"])
        # The grid shows a 700px thumbnail; the full-size file stays behind the
        # <a> for the lightbox. Without this split the gallery pulled ~15 MB.
        thumb = x.get("thumb")
        thumb_jpg = x.get("thumb_jpg")
        if thumb:
            # width/height let the browser reserve the tile's box before the image
            # arrives, so the grid does not reflow as tiles load (CLS).
            tw, th = x.get("thumb_w"), x.get("thumb_h")
            dim = ' width="%d" height="%d"' % (tw, th) if tw and th else ""
            rows.append('    <div class="tile" data-cat="%s"><a href="%s">'
                        '<picture><source type="image/webp" srcset="%s">'
                        '<img loading="lazy" decoding="async"%s src="%s" alt="%s"></picture>'
                        '</a></div>'
                        % (x["cat"], x["src"], thumb, dim, thumb_jpg or thumb, esc(alt)))
        else:
            rows.append('    <div class="tile" data-cat="%s"><a href="%s">'
                        '<img loading="lazy" decoding="async" width="%d" height="%d" '
                        'src="%s" alt="%s"></a></div>'
                        % (x["cat"], x["src"], x["w"], x["h"], x["src"], esc(alt)))
    info = {"all": "", "room3": t(lang, "roomInfo3"), "room4": t(lang, "roomInfo4"),
            "room5": t(lang, "roomInfo5"), "room6": t(lang, "roomInfo6"),
            "exterior": t(lang, "roomInfoExterior"), "sunset": t(lang, "roomInfoSunset")}
    grid = ('<section class="grid" id="grid" data-room-info="%s">\n'
            % esc(json.dumps(info, ensure_ascii=False)) + "\n".join(rows) + "\n  </section>")
    return body.replace('<section class="grid" id="grid"></section>', grid)

I18N_RE = re.compile(r'(?P<pre><[a-zA-Z][^>]*\bdata-i18n="(?P<key>[^"]+)"[^>]*>)(?:(?!</)[\s\S])*?<')

def strip_i18n(body):
    return re.sub(r'\s+data-i18n="[^"]*"', "", body)

# Inline values that sit *after* a translated label inside the same element, so
# they carry no key of their own. The original site left them Greek in EN mode;
# the English tree translates them.
INLINE_EN = [
    ("Γλινάδο, Νάξος", "Glinado, Naxos"),
    ("Μόλις 5,5 χλμ (περίπου 10-12 λεπτά οδικώς)",
     "Just 5.5 km (about 10-12 minutes by car)"),
    ("Δωρεάν ιδιωτικός χώρος στάθμευσης ακριβώς στην είσοδο του καταλύματος (Η κράτηση δεν είναι εφικτή)",
     "Free private parking right at the entrance of the property (reservation not possible)"),
    ("Προσανατολισμός", "Orientation"),
]

def localize(body, lang):
    if lang == "el":
        return body
    def repl(m):
        val = t("en", m.group("key"), None)
        # m.group("pre") already ends with ">", so do not add another one.
        return m.group(0) if val is None else "%s%s<" % (m.group("pre"), esc(val, False))
    body = I18N_RE.sub(repl, body)
    for el, en in INLINE_EN:
        body = body.replace(el, esc(en, False))
    return body

# forecast-pro ships with no data-i18n attributes at all; attach keys, then translate.
PRO_PAIRS = [
    ("Ζωντανά Μετεωρολογικά Δεδομένα &amp; Καιρός στο Γλινάδο Νάξου – Weather Station Pro.", "proSeoH1"),
    ("Παρακολουθήστε σε πραγματικό χρόνο τις καιρικές συνθήκες στο Γλινάδο Νάξου μέσα από τον επαγγελματικό μετεωρολογικό σταθμό του Naxos Balcony. Η σελίδα Weather Station Pro προσφέρει αναλυτικά δεδομένα, όπως ιστορικό βροχόπτωσης 24 μηνών, διακυμάνσεις ατμοσφαιρικής πίεσης και μετρήσεις ηλιακής ακτινοβολίας. Αποτελεί το απόλυτο εργαλείο για windsurfers, λάτρεις της μετεωρολογίας και ταξιδιώτες που αναζητούν την πιο έγκυρη πρόγνωση καιρού στις Κυκλάδες.", "proSeoText"),
    ("Live Δεδομένα", "proLiveData"),
    ("Θερμοκρασία", "proTemp"),
    ("Feels Like", "proFeels"),
    ("Άνεμος", "proWind"),
    ("Ριπές", "proGust"),
    ("Κατεύθυνση", "proDir"),
    ("Υγρασία", "proHum"),
    ("Dew Point", "proDew"),
    ("Πίεση", "proPress"),
    ("Rain Rate", "proRainRate"),
    ("Ετήσια Βροχή", "proRainDay"),
    ("UV Index", "proUV"),
    ("Ηλιακή Ακτινοβολία", "proSolar"),
    ("Πρόβλεψη Επόμενων Ημερών", "proForecastTitle"),
    ("Φόρτωση...", "proLoading"),
    ("Μηνιαία Βροχόπτωση", "proRainChart"),
    ("Ατμοσφαιρική Πίεση (24h)", "proPressChart"),
    ("Min / Max Θερμοκρασίες", "proTempChart"),
    ("Η καλύτερη θέα της Νάξου σας περιμένει...", "proCtaTitle"),
    ("Κάντε την κράτησή σας στο Naxos Balcony Apartments και ζήστε την απόλυτη εμπειρία διαμονής στη Νάξο", "proCtaText"),
    ("ΔΕΙΤΕ ΤΑ ΔΩΜΑΤΙΑ", "proCtaButton"),
]

def localize_pro(body, lang):
    for text, key in PRO_PAIRS:
        body = body.replace(">%s<" % text, ' data-i18n="%s">%s<' % (key, text))
    body = re.sub(r'(<div class="pro-updated">)([\s\S]*?)(<span id="updated">)',
                  r'\1<span data-i18n="proUpdated">Ενημέρωση:</span> \3', body)
    if lang == "en":
        for text, key in PRO_PAIRS:
            body = body.replace(">%s<" % text, ">%s<" % esc(t("en", key), False))
        body = body.replace('<span data-i18n="proUpdated">Ενημέρωση:</span>',
                            '<span data-i18n="proUpdated">%s</span>' % esc(t("en", "proUpdated"), False))
        body = strip_i18n(body)
    return body

# ------------------------------------------------------------------ shell

NAV_ITEMS = [("index.html", "home", "Αρχική"),
             ("gallery.html", "gallery", "Φωτογραφίες"),
             ("amenities.html", "amenities", "Παροχές"),
             ("weather.html", "weather", "Live Weather"),
             ("contact.html", "contact", "Επικοινωνία")]

def nav(active, lang, cur_file):
    up = "../" if lang == "en" else ""
    li = []
    for href, key, label in NAV_ITEMS:
        cls = ' class="active"' if key == active else ""
        text = t(lang, key) if lang == "en" else label
        i18n = "" if lang == "en" else ' data-i18n="%s"' % key
        # Page links stay in the same directory: on /en/ a "../gallery.html"
        # would send the visitor back to the Greek tree. Only assets need "up".
        li.append('        <li><a href="%s"%s%s>%s</a></li>'
                  % (href, cls, i18n, esc(text)))
    el_href = "#" if lang == "el" else "../" + cur_file
    en_href = "en/" + cur_file if lang == "el" else "#"
    el_cls = " active" if lang == "el" else ""
    en_cls = " active" if lang == "en" else ""
    li.append('        <li class="lang-group">\n'
              '          <a id="langEl" class="lang-link%s" href="%s" hreflang="el" lang="el" aria-label="Ελληνικά">EL</a>\n'
              '          <span class="sep" aria-hidden="true">|</span>\n'
              '          <a id="langEn" class="lang-link%s" href="%s" hreflang="en" lang="en" aria-label="English">EN</a>\n'
              '        </li>' % (el_cls, el_href, en_cls, en_href))
    call_text = t(lang, "callUs") if lang == "en" else "ΚΑΛΕΣΤΕ ΜΑΣ"
    call_i18n = "" if lang == "en" else ' data-i18n="callUs"'
    li.append('        <li><a class="call-btn" href="tel:+306942279665"%s>%s</a></li>'
              % (call_i18n, esc(call_text)))
    return (
        '<a class="skip-link" href="#main">%s</a>\n'
        '<header class="navbar" id="navbar">\n'
        '  <div class="container navbar-inner">\n'
        '    <a class="logo" href="index.html">\n'
        '      <picture>\n'
        '        <source type="image/webp" srcset="%slogo-naxos-balcony-240.webp 240w, %slogo-naxos-balcony-480.webp 480w"\n'
        '                sizes="(max-width:600px) 84px, 105px">\n'
        '        <img src="%slogo-naxos-balcony-240.png" width="240" height="128" alt="Naxos Balcony Apartments logo" decoding="async">\n'
        '      </picture>\n'
        '    </a>\n'
        '    <button class="nav-toggle" id="navToggle" aria-label="%s" aria-expanded="false" aria-controls="nav">\n'
        '      <span></span><span></span><span></span>\n'
        '    </button>\n'
        '    <nav class="nav" id="nav" aria-label="%s">\n'
        '      <ul class="nav-links">\n%s\n      </ul>\n'
        '    </nav>\n  </div>\n</header>'
        % ("Skip to content" if lang == "en" else "Μετάβαση στο περιεχόμενο",
           up, up, up,
           "Menu" if lang == "en" else "Μενού",
           "Main navigation" if lang == "en" else "Κύρια πλοήγηση",
           "\n".join(li)))

def footer(lang, mode):
    up = "../" if lang == "en" else ""
    addr = ""
    if mode == "index":
        addr = ('<p style="opacity:.8;"%s>%s</p>'
                % ("" if lang == "en" else ' data-i18n="address"', esc(t(lang, "address"))))
    if mode == "amenities" and lang == "el":
        phone = '<p data-i18n="phone">📞 6942279665</p>'
        email = '<p data-i18n="email">📧 naxosbalcony@gmail.com</p>'
    else:
        phone = "<p>📞 6942279665</p>"
        email = "<p>📧 naxosbalcony@gmail.com</p>"
    return (
        '<footer>\n  <div class="container">\n    <div class="footer-inner">\n'
        '      <div>\n        %s\n        %s\n        %s\n      </div>\n'
        '      <div>\n        <div class="socials">\n'
        '          <a href="https://www.facebook.com/naxosbalconyapartments" rel="noopener">Facebook</a>\n'
        '          <a href="https://www.instagram.com/naxos_balcony_apartments/" rel="noopener">Instagram</a>\n'
        '        </div>\n      </div>\n    </div>\n'
        '    <div class="license-number"%s>%s</div>\n'
        '    <div class="footer-legal">'
        '<a href="privacy.html">%s</a> · <a href="cookies.html">%s</a> · '
        '<button type="button" class="linklike" id="cookieSettings2">%s</button></div>\n'
        '  </div>\n</footer>'
        % (phone, email, addr,
           "" if lang == "en" else ' data-i18n="license"', esc(t(lang, "license")),
           esc(t(lang, "privacyLink")), esc(t(lang, "cookiesLink")),
           esc(t(lang, "cookieSettings"))))

def cookie_banner(lang):
    up = "../" if lang == "en" else ""
    return (
        '<div class="cookie-banner" id="cookieBanner" role="dialog" aria-live="polite" '
        'aria-label="%s" hidden>\n  <div class="cookie-inner">\n    <div class="cookie-copy">\n'
        '      <strong>%s</strong>\n      <p>%s</p>\n    </div>\n'
        '    <div class="cookie-actions">\n'
        '      <button type="button" class="cookie-btn cookie-reject" id="cookieReject">%s</button>\n'
        '      <button type="button" class="cookie-btn cookie-accept" id="cookieAccept">%s</button>\n'
        '      <a class="cookie-more" href="cookies.html">%s</a>\n'
        '    </div>\n  </div>\n</div>'
        % (esc(t(lang, "cookieTitle")), esc(t(lang, "cookieTitle")), esc(t(lang, "cookieText")),
           esc(t(lang, "cookieReject")), esc(t(lang, "cookieAccept")),
           esc(t(lang, "cookieMore"))))

def booking_fab(lang):
    """Floating booking button. Expands into direct-contact choices.

    Everything here is a plain link, so it works with JavaScript off: <details>
    opens, and call/email are ordinary hrefs. WhatsApp gets its prefilled text
    from the link, no script needed.
    """
    tel = BIZ.get("telephone", "")
    mail = BIZ.get("email", "")
    wa = re.sub(r"\D", "", tel)
    if wa.startswith("30"):
        wa = wa
    elif wa.startswith("0"):
        wa = "30" + wa[1:]
    else:
        wa = "30" + wa
    wa_href = "https://wa.me/%s?text=%s" % (wa, quote(t(lang, "bookWaText")))
    mail_href = "mailto:%s?subject=%s" % (mail, quote(t(lang, "bookMailSubject")))
    return (
        '<details class="book-fab" id="bookFab">\n'
        '  <summary aria-label="%s">\n'
        '    <span class="book-fab-ico" aria-hidden="true">📅</span>\n'
        '    <span class="book-fab-txt">%s</span>\n'
        '  </summary>\n'
        '  <div class="book-fab-menu">\n'
        '    <a class="book-fab-item" href="%s">\n'
        '      <span aria-hidden="true">💬</span> %s</a>\n'
        '    <a class="book-fab-item" href="tel:%s">\n'
        '      <span aria-hidden="true">📞</span> %s</a>\n'
        '    <a class="book-fab-item" href="%s">\n'
        '      <span aria-hidden="true">✉️</span> %s</a>\n'
        '  </div>\n'
        '</details>'
        % (esc(t(lang, "bookFabLabel")), esc(t(lang, "bookFab")),
           esc(wa_href), esc(t(lang, "contactWhatsAppButton")),
           esc(tel), esc(t(lang, "contactCallButton")),
           esc(mail_href), esc(t(lang, "contactEmailButton"))))

def head(lang, page, title, desc):
    up = "../" if lang == "en" else ""
    url = BASE + "/" + ("en/" if lang == "en" else "") + page["file"]
    el_url = BASE + "/" + page["file"]
    en_url = BASE + "/en/" + page["file"]
    stem = BIZ["heroImage"][:-4]
    return "\n".join([
        '<meta charset="UTF-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        "<title>%s</title>" % esc(title),
        '<meta name="description" content="%s">' % esc(desc),
        '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">',
        '<link rel="canonical" href="%s">' % url,
        '<link rel="alternate" hreflang="el" href="%s">' % el_url,
        '<link rel="alternate" hreflang="en" href="%s">' % en_url,
        '<link rel="alternate" hreflang="x-default" href="%s">' % el_url,
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="%s">' % esc(BIZ["name"]),
        '<meta property="og:title" content="%s">' % esc(title),
        '<meta property="og:description" content="%s">' % esc(desc),
        '<meta property="og:url" content="%s">' % url,
        '<meta property="og:image" content="%s/og-image.jpg">' % BASE,
        '<meta property="og:image:width" content="1200">',
        '<meta property="og:image:height" content="630">',
        '<meta property="og:image:alt" content="%s">' % esc(BIZ["name"] + " — Glinado, Naxos"),
        '<meta property="og:locale" content="%s">' % ("el_GR" if lang == "el" else "en_US"),
        '<meta property="og:locale:alternate" content="%s">' % ("en_US" if lang == "el" else "el_GR"),
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="%s">' % esc(title),
        '<meta name="twitter:description" content="%s">' % esc(desc),
        '<meta name="twitter:image" content="%s/og-image.jpg">' % BASE,
        '<meta name="theme-color" content="#0c1822">',
        '<link rel="icon" href="%sfavicon.ico" sizes="any">' % up,
        '<link rel="icon" type="image/png" sizes="32x32" href="%sfavicon-32.png">' % up,
        '<link rel="icon" type="image/png" sizes="192x192" href="%sfavicon-192.png">' % up,
        '<link rel="apple-touch-icon" href="%sapple-touch-icon.png">' % up,
        '<link rel="manifest" href="%ssite.webmanifest">' % up,
        # Inter is self-hosted (see fonts.css): no third-party connection to
        # block the first paint, and no visitor IP handed to Google Fonts.
        '<link rel="stylesheet" href="%sfonts.css">' % up,
        # Preload the WebP hero (browsers that cannot use it skip this link and
        # pick the JPEG from the <picture> instead, so nothing breaks).
        '<link rel="preload" as="image" type="image/webp" href="%s%s-1280.webp" '
        'imagesrcset="%s%s-480.webp 480w, %s%s-800.webp 800w, %s%s-1280.webp 1280w, %s%s-1600.webp 1600w" '
        'imagesizes="100vw">' % (up, stem, up, stem, up, stem, up, stem, up, stem),
        '<link rel="stylesheet" href="%sstyles.css">' % up,
    ])

GA = ("<script>\n"
      "/* GA4 with Consent Mode v2 - no analytics storage until the visitor agrees. */\n"
      "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n"
      "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',"
      "ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',"
      "security_storage:'granted',wait_for_update:500});\n"
      "gtag('js',new Date());gtag('config','" + GA4 + "',{anonymize_ip:true});\n"
      "</script>\n"
      '<script async src="https://www.googletagmanager.com/gtag/js?id=' + GA4 + '"></script>')

def scripts_for(lang, file):
    up = "../" if lang == "en" else ""
    s = []
    if file == "gallery.html":
        s.append('<script src="%sgallery-data.js" defer></script>' % up)
    s.append('<script src="%sconsent.js" defer></script>' % up)
    s.append('<script src="%ssite.js" defer></script>' % up)
    if file == "forecast-pro.html":
        s.append('<script src="%spro.js" defer></script>' % up)
    if lang == "el":
        s.append('<script src="%slang-nav.js" defer></script>' % up)
        s.append('<script src="%slang-system.js" defer></script>' % up)
    return "\n".join(s)

def shell(lang, page, title, desc, body_html, bcls=""):
    return "\n".join([
        "<!DOCTYPE html>",
        '<html lang="%s">' % lang,
        "<head>",
        head(lang, page, title, desc),
        GA,
        page.get("extra_head") or "",
        schemas(lang, page),
        "</head>",
        "<body%s>" % bcls,
        nav(page.get("active", ""), lang, page["file"]),
        '<main id="main">',
        body_html,
        "</main>",
        footer(lang, page.get("footer_mode", "plain")),
        booking_fab(lang),
        cookie_banner(lang),
        scripts_for(lang, page["file"]),
        "</body>",
        "</html>",
        "",
    ])

HERO_WIDTHS = (480, 800, 1280, 1600)


def hero_srcset(stem, ext):
    return ", ".join("%s-%d.%s %dw" % (stem, w, ext, w) for w in HERO_WIDTHS)


def hero_img(depth, cls):
    """Real <picture> with srcset instead of a CSS background, so the browser can
    pick the right size and the LCP image is the one we preload. WebP first,
    JPEG as the fallback, matching what the gallery tiles already do."""
    stem = BIZ["heroImage"][:-4]
    # Plain relative paths: asset_paths() prefixes them, and doing it here too
    # would yield ../../ on the English pages.
    # .hero picture/.page-hero picture is display:contents, so the <img> stays the
    # direct flex child it was before the wrapper and the layout is unchanged.
    return ('<picture><source type="image/webp" srcset="%s" sizes="100vw">'
            '<img class="%s" src="%s-1280.jpg" srcset="%s" '
            'sizes="100vw" width="1600" height="1067" alt="" aria-hidden="true" '
            'decoding="async" fetchpriority="high"></picture>'
            % (hero_srcset(stem, "webp"), cls, stem, hero_srcset(stem, "jpg")))

def inject_hero(body, depth):
    body = re.sub(r'(<section class="hero">)', r'\1\n  ' + hero_img(depth, "hero-bg"),
                  body, count=1)
    body = re.sub(r'(<section class="page-hero">)', r'\1\n  ' + hero_img(depth, "page-hero-bg"),
                  body, count=1)
    return body

def render(lang, page):
    depth = 0 if lang == "el" else 1
    body = open(os.path.join(HERE, page["body"]), encoding="utf-8").read()
    if page["file"] == "gallery.html":
        body = prerender_gallery(body, lang)
    if page["file"] == "forecast-pro.html":
        body = localize_pro(body, lang)
    else:
        body = localize(body, lang)
        if lang == "en":
            body = strip_i18n(body)
    body = inject_hero(body, depth)
    body = asset_paths(body, depth)
    title = page["title_el"] if lang == "el" else page["title_en"]
    desc = page["desc_el"] if lang == "el" else page["desc_en"]
    bcls = ' class="%s"' % page["body_class"] if page["body_class"] else ""
    return shell(lang, page, title, desc, body, bcls)

# ------------------------------------------------------------------ legal + 404

def legal_page(lang, kind):
    fn = "privacy.html" if kind == "privacy" else "cookies.html"
    title = t(lang, "privacyTitle" if kind == "privacy" else "cookiesTitle")
    desc = t(lang, "legalIntro")
    # The page's own title is the trail's second step, so this reuses the
    # existing privacyTitle / cookiesTitle key rather than adding new text.
    page = {"file": fn, "active": "", "footer_mode": "plain",
            "breadcrumb": "privacyTitle" if kind == "privacy" else "cookiesTitle",
            "extra_head": ""}
    rows = [("legalIntro", None),
            ("legalController", BIZ["name"] + " — " + BIZ["streetAddress"] + ", "
             + BIZ["addressRegion"] + " " + BIZ["postalCode"] + ", " + BIZ["addressCountry"]
             + " · " + BIZ["email"]),
            ("legalDataTitle", "legalDataText")]
    if kind == "cookies":
        rows.append(("legalCookiesTitle", "legalCookiesText"))
    rows += [("legalRightsTitle", "legalRightsText"),
             ("legalContactTitle", BIZ["email"] + " · " + BIZ["telephone"])]
    b = ['<section class="page-hero"><div class="container page-hero-inner">',
         "<h1>%s</h1>" % esc(title), "</div></section>",
         '<main class="wrap"><article class="prose">']
    for a, c in rows:
        if c is None:
            b.append("<p>%s</p>" % esc(t(lang, a)))
        else:
            b.append("<h2>%s</h2>" % esc(t(lang, a)))
            b.append("<p>%s</p>" % esc(t(lang, c)))
    b.append('<p class="muted">%s</p>' % esc(t(lang, "legalUpdated")))
    b.append("</article></main>")
    return shell(lang, page, title, desc, "\n".join(b))

def notfound_page(lang):
    title = t(lang, "notFoundTitle") + " | Naxos Balcony Apartments"
    desc = t(lang, "notFoundText")
    page = {"file": "404.html", "active": "", "footer_mode": "plain",
            "breadcrumb": None, "extra_head": ""}
    up = "../" if lang == "en" else ""
    b = ['<section class="page-hero"><div class="container page-hero-inner">',
         "<h1>%s</h1>" % esc(t(lang, "notFoundTitle")),
         "<p>%s</p>" % esc(desc), "</div></section>",
         '<main class="wrap"><article class="prose">',
         '<p><a class="btn-book" href="index.html">%s</a></p>'
         % (esc(t(lang, "notFoundHome")),), "</article></main>"]
    return shell(lang, page, title, desc, "\n".join(b))

# ------------------------------------------------------------------ main

os.makedirs(OUT, exist_ok=True)
os.makedirs(os.path.join(OUT, "en"), exist_ok=True)

n = 0
for lang in ("el", "en"):
    for page in PAGES:
        dest = (os.path.join(OUT, page["file"]) if lang == "el"
                else os.path.join(OUT, "en", page["file"]))
        open(dest, "w", encoding="utf-8").write(render(lang, page))
        n += 1
    for kind in ("privacy", "cookies"):
        fn = "privacy.html" if kind == "privacy" else "cookies.html"
        dest = os.path.join(OUT, fn) if lang == "el" else os.path.join(OUT, "en", fn)
        open(dest, "w", encoding="utf-8").write(legal_page(lang, kind))
        n += 1
    dest = os.path.join(OUT, "404.html") if lang == "el" else os.path.join(OUT, "en", "404.html")
    open(dest, "w", encoding="utf-8").write(notfound_page(lang))
    n += 1

print("wrote %d pages" % n)

# ------------------------------------------------------------------ sitemap

def sitemap():
    from datetime import date
    today = date.today().isoformat()
    urls = []
    for page in PAGES:
        f = page["file"]
        el = BASE + "/" + f
        en = BASE + "/en/" + f
        prio = page["sitemap_priority"]
        for loc, alt in ((el, "el"), (en, "en")):
            urls.append(
                "  <url>\n"
                "    <loc>%s</loc>\n"
                "    <lastmod>%s</lastmod>\n"
                "    <changefreq>monthly</changefreq>\n"
                "    <priority>%s</priority>\n"
                '    <xhtml:link rel="alternate" hreflang="el" href="%s"/>\n'
                '    <xhtml:link rel="alternate" hreflang="en" href="%s"/>\n'
                '    <xhtml:link rel="alternate" hreflang="x-default" href="%s"/>\n'
                "  </url>" % (loc, today, prio, el, en, el))
    for f, prio in (("privacy.html", "0.3"), ("cookies.html", "0.3")):
        for loc in (BASE + "/" + f, BASE + "/en/" + f):
            urls.append("  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n"
                        "    <changefreq>yearly</changefreq>\n"
                        "    <priority>%s</priority>\n  </url>" % (loc, today, prio))
    return ('<?xml version="1.0" encoding="UTF-8"?>\n'
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"\n'
            '        xmlns:xhtml="http://www.w3.org/1999/xhtml">\n'
            + "\n".join(urls) + "\n</urlset>\n")

open(os.path.join(OUT, "sitemap.xml"), "w", encoding="utf-8").write(sitemap())
print("wrote sitemap.xml")

# The admin tool reads/writes this file next to the pages it rebuilds.
import shutil
shutil.copyfile(os.path.join(HERE, "gallery-tiles.json"),
                os.path.join(OUT, "gallery-tiles.json"))
print("copied gallery-tiles.json")


