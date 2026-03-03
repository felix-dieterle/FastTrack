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

### 1. Clone the repository

```bash
git clone https://github.com/your-org/FastTrack.git
cd FastTrack
```

### 2. Create the database

```sql
CREATE DATABASE fasttrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Run the migration script

```bash
mysql -u root -p fasttrack < database/001_initial_schema.sql
```

### 4. Configure the application

```bash
cp config.example.php config.php
```

Open `config.php` and set your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fasttrack');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');
```

> ⚠️ `config.php` is listed in `.gitignore` and will never be committed.

### 5. Start the development server (optional)

```bash
php -S localhost:8080
```

Then open <http://localhost:8080> in your browser.

### 6. Register your first user

Navigate to `/register.php` and create an account. Weekly hours default to **40h** and can be changed in Settings.

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
