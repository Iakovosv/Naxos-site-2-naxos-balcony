# Ρυθμίσεις nginx για το www.naxosbalcony.gr

> **Για τον host / τη φιλοξενία.** Ο server είναι **nginx**, άρα το `.htaccess`
> του site **δεν** διαβάζεται. Αυτό το αρχείο μεταφράζει τους κανόνες του
> `.htaccess` σε nginx.

## Γιατί χρειάζεται

Ελέγχθηκε το live (2026-10-24) και **λείπουν**:

| Τι | Κατάσταση τώρα |
|---|---|
| `http://` → `https://` | ❌ **δεν** ανακατευθύνει (απαντά 200) |
| `X-Content-Type-Options` | ❌ λείπει |
| `X-Frame-Options` | ❌ λείπει |
| `Referrer-Policy` | ❌ λείπει |
| `Permissions-Policy` | ❌ λείπει |
| `Strict-Transport-Security` | ❌ λείπει |
| Το ιστορικό του καιρού δημόσιο | ⚠️ `weather_history.db` (9,6 MB), `history (1).csv` (2,2 MB) |

Όλα αυτά τα ορίζει το `.htaccess` του site — αλλά ο nginx δεν το διαβάζει.

---

## 1. Ανακατεύθυνση HTTP → HTTPS

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name www.naxosbalcony.gr naxosbalcony.gr;
    return 301 https://www.naxosbalcony.gr$request_uri;
}
```

## 2. Κεφαλίδες ασφαλείας

Μέσα στο `server { ... }` που σερβίρει το `public_html`:

```nginx
add_header X-Content-Type-Options   "nosniff"                        always;
add_header Referrer-Policy          "strict-origin-when-cross-origin" always;
add_header Permissions-Policy       "geolocation=(), microphone=(), camera=()" always;
add_header X-Frame-Options          "SAMEORIGIN"                     always;

# HSTS — ενεργοποίησέ το ΜΟΝΟ αφού βεβαιωθείς ότι το HTTPS δουλεύει παντού.
# Μετά την ενεργοποίηση, οι browsers θυμούνται για 1 χρόνο.
# add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

## 3. Προστασία των αρχείων δεδομένων

```nginx
# Το ιστορικό του καιρού δεν πρέπει να κατεβαίνει από τον browser.
location ~* ^/api/.*\.(db|csv|json)$ {
    # Τα *.json τα διαβάζει η PHP τοπικά — μπλοκάρουμε μόνο την άμεση πρόσβαση.
    deny all;
}
```

> **Προσοχή:** αν μπλοκάρεις τα `.json`, έλεγξε ότι ο καιρός δουλεύει ακόμα.
> Η PHP τα διαβάζει από τον δίσκο (όχι από HTTP), άρα **δεν** επηρεάζεται.

## 4. Σελίδα 404 + κατάλογος

```nginx
error_page 404 /404.html;
index index.html index.php;
```

## 5. Συμπίεση

```nginx
gzip on;
gzip_types text/html text/plain text/css application/javascript
           application/json application/xml image/svg+xml;
gzip_min_length 256;
```

## 6. Cache

```nginx
location ~* \.(jpg|jpeg|png|webp|ico|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000";
}
location ~* \.(css|js)$ {
    expires 1w;
    add_header Cache-Control "public, max-age=604800";
}
location = /index.html {
    add_header Cache-Control "no-cache";
}
```

---

## Έλεγχος μετά την αλλαγή

```bash
curl -sI https://www.naxosbalcony.gr/ | grep -iE 'x-content-type|x-frame|referrer|permissions'
curl -sI http://www.naxosbalcony.gr/ | head -1        # πρέπει 301
curl -s -o /dev/null -w '%{http_code}\n' https://www.naxosbalcony.gr/api/weather_history.db  # πρέπει 403
curl -s https://www.naxosbalcony.gr/api/weather-latest.php   # πρέπει JSON, όχι σφάλμα
```

**Το τελευταίο είναι το σημαντικό:** αν το `weather-latest.php` δίνει σφάλμα,
η ρύθμιση του `.json` ήταν πολύ αυστηρή — πες μου και θα το διορθώσω.
