# ⏱ FastTrack – Zeiterfassung

A simple, mobile-first PHP/MySQL time-tracking web application with clock-in/clock-out, overtime calculation, CSV export, and a brief undo feature.

---

## Screenshots

> Screenshots taken at Pixel 8a viewport (412 × 915 px)

| Dashboard | Einträge |
|-----------|----------|
| ![Dashboard](https://github.com/user-attachments/assets/627322f8-d415-4139-8839-9a54221d6b6a) | ![Einträge](https://github.com/user-attachments/assets/70c898b2-00ce-40f9-9525-72f04b3f7197) |

| Einträge – Inline-Bearbeitung | CSV Exportieren |
|-------------------------------|-----------------|
| ![Bearbeiten](https://github.com/user-attachments/assets/c9420776-7a0c-4822-8673-dc1504af5f3e) | ![Export](https://github.com/user-attachments/assets/8dcb73c2-2b18-4b32-a75a-58d002d2bf67) |

| Einstellungen | Anmelden |
|---------------|----------|
| ![Einstellungen](https://github.com/user-attachments/assets/ea97b178-c232-4f3b-b04c-61598869122e) | ![Login](https://github.com/user-attachments/assets/9fda6de8-d46d-4cb5-ab52-5bf4fd9a9173) |

---

## Features

- **Einstempeln / Ausstempeln** – one-tap clock in and out
- **Dashboard** – today's hours, this week vs. target, all-time overtime/undertime
- **Entries** – paginated list with inline editing and deletion
- **Undo** – 10-second undo toast after every action
- **Export** – CSV download with optional date-range filter
- **Settings** – configurable weekly-hours target, password change
- **Remember Me** – 30-day login cookie (token rotated on each use)
- **CSRF protection** on all POST forms
- **German UI** (Einstempeln, Ausstempeln, Überstunden…)
- Mobile-first, Bootstrap 5.3 responsive layout

---

## Requirements

| Component | Version   |
|-----------|-----------|
| PHP       | 8.1 +     |
| MySQL     | 5.7 + / 8 |
| Web server| Apache / Nginx (or PHP built-in for development) |

---

## Setup

### Schritt 1 – Voraussetzungen installieren

Stelle sicher, dass **PHP 8.1+**, **MySQL 5.7+/8** und ein Webserver (Apache oder Nginx) installiert sind.

**Ubuntu / Debian:**

```bash
sudo apt update
sudo apt install php8.1 php8.1-mysql mysql-server apache2 libapache2-mod-php8.1
sudo a2enmod rewrite
sudo systemctl start apache2 mysql
```

**macOS (Homebrew):**

```bash
brew install php mysql
brew services start php
brew services start mysql
```

**Windows:**
Verwende [XAMPP](https://www.apachefriends.org/) oder [WAMP](https://www.wampserver.com/), die PHP, MySQL und Apache gebündelt bereitstellen.

---

### Schritt 2 – Repository klonen

```bash
git clone https://github.com/felix-dieterle/FastTrack.git
cd FastTrack
```

---

### Schritt 3 – Datenbank anlegen

Melde dich an der MySQL-Konsole an und erstelle die Datenbank:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE fasttrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Optional: Eigenen Datenbankbenutzer anlegen (empfohlen für Produktion)
CREATE USER 'fasttrack_user'@'localhost' IDENTIFIED BY 'DEIN_SICHERES_PASSWORT';
GRANT ALL PRIVILEGES ON fasttrack.* TO 'fasttrack_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Schritt 4 – Datenbankschema importieren

```bash
mysql -u root -p fasttrack < database/001_initial_schema.sql
```

---

### Schritt 5 – Konfigurationsdatei erstellen

```bash
cp config.example.php config.php
```

Öffne `config.php` und trage deine Datenbankzugangsdaten ein:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fasttrack');
define('DB_USER', 'fasttrack_user');   // Benutzer aus Schritt 3
define('DB_PASS', 'DEIN_SICHERES_PASSWORT'); // Passwort aus Schritt 3
define('DB_CHARSET', 'utf8mb4');
```

> ⚠️ `config.php` ist in `.gitignore` eingetragen und wird niemals ins Repository übertragen.

---

### Schritt 6 – Webserver konfigurieren

#### Option A: Eingebauter PHP-Entwicklungsserver (schnell & einfach)

```bash
php -S localhost:8080
```

Öffne anschließend <http://localhost:8080> im Browser. Dieser Server eignet sich **nur für die lokale Entwicklung**.

#### Option B: Apache Virtual Host

Erstelle eine neue Konfigurationsdatei (z. B. `/etc/apache2/sites-available/fasttrack.conf`):

```apache
<VirtualHost *:80>
    ServerName fasttrack.local
    DocumentRoot /var/www/html/FastTrack

    <Directory /var/www/html/FastTrack>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/fasttrack_error.log
    CustomLog ${APACHE_LOG_DIR}/fasttrack_access.log combined
</VirtualHost>
```

Aktiviere die Seite und starte Apache neu:

```bash
sudo a2ensite fasttrack.conf
sudo systemctl reload apache2
```

#### Option C: Nginx Server Block

Erstelle eine neue Konfigurationsdatei (z. B. `/etc/nginx/sites-available/fasttrack`):

```nginx
server {
    listen 80;
    server_name fasttrack.local;
    root /var/www/html/FastTrack;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
}
```

Aktiviere den Block und starte Nginx neu:

```bash
sudo ln -s /etc/nginx/sites-available/fasttrack /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

---

### Schritt 7 – Dateiberechtigungen setzen (Linux/macOS)

```bash
# Webserver-Benutzer Lesezugriff geben
sudo chown -R www-data:www-data /var/www/html/FastTrack
# config.php vor anderen Benutzern schützen
chmod 640 /var/www/html/FastTrack/config.php
```

> Unter macOS mit dem eingebauten PHP-Server sind diese Schritte nicht notwendig.

---

### Schritt 8 – Ersten Benutzer registrieren

Öffne im Browser `/register.php` (z. B. <http://localhost:8080/register.php>) und lege einen Account an.  
Die Wochenstundenzahl ist standardmäßig auf **40 Stunden** gesetzt und kann jederzeit unter *Einstellungen* geändert werden.

---

### Schritt 9 – Fertig 🎉

Nach der Registrierung wirst du automatisch zur Startseite weitergeleitet. Du kannst dich jetzt ein- und ausstempeln, Einträge bearbeiten und deine Arbeitszeiten exportieren.

| Seite | URL |
|-------|-----|
| Dashboard | `/index.php` |
| Einträge | `/entries.php` |
| Exportieren | `/export.php` |
| Einstellungen | `/settings.php` |

---

## File Structure

```
.
├── config.example.php          # Copy → config.php, add DB credentials
├── config.php                  # (git-ignored) real credentials
├── index.php                   # Dashboard: clock in/out, stats
├── login.php                   # Login form with remember-me
├── logout.php                  # Destroys session + cookie
├── register.php                # New-user registration
├── entries.php                 # Paginated entry list with inline edit
├── settings.php                # Weekly hours + password change
├── export.php                  # CSV download with date-range filter
│
├── api/
│   ├── clock_in.php            # POST → create open time entry
│   ├── clock_out.php           # POST → close open time entry
│   ├── undo.php                # POST → undo last action
│   ├── entry_update.php        # POST → update existing entry
│   └── entry_delete.php        # POST → delete entry
│
├── includes/
│   ├── db.php                  # PDO singleton (get_db())
│   ├── auth.php                # Login/logout/remember-me/CSRF helpers
│   ├── functions.php           # format_duration, calculate_overtime, …
│   └── navbar.php              # Shared navigation bar
│
├── assets/
│   ├── css/style.css           # Custom styles on top of Bootstrap
│   └── js/app.js               # Fetch-based clock/undo/edit logic
│
└── database/
    └── 001_initial_schema.sql  # Initial DB schema (idempotent)
```

---

## Database Schema

| Table             | Purpose                              |
|-------------------|--------------------------------------|
| `users`           | Accounts with hashed password + weekly target |
| `time_entries`    | Clock-in/out rows with optional note |
| `remember_tokens` | 30-day rotating login tokens         |

---

## Adding Future Migrations

Name new SQL files sequentially: `002_add_column_xyz.sql`, `003_…`, etc. Run them manually or wire them into a simple migration runner. Each script uses `IF NOT EXISTS` / `IF EXISTS` to be idempotent.

---

## Security Notes

- All DB queries use **PDO prepared statements** – no SQL injection.
- Passwords hashed with `password_hash()` (bcrypt).
- CSRF token validated on every POST form.
- Remember-me tokens are 32-byte random hex values, rotated on each use.
- `config.php` (credentials) is git-ignored.

---

## License

MIT
