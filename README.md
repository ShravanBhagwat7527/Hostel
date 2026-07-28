# Hostel Desk — PHP + MySQL backend

```
hostel-portal/
├── database/
│   └── schema.sql          ← import this first
├── backend/
│   ├── config.php          ← DB credentials
│   ├── db.php               ← PDO connection
│   ├── helpers.php          ← shared helpers (json, sessions, ticket numbers)
│   ├── setup_warden.php     ← CLI script to create a warden login
│   └── api/
│       ├── submit_complaint.php   POST  — student files a ticket
│       ├── list_complaints.php    GET   — student lookup / warden dashboard
│       ├── update_status.php      POST  — warden updates a ticket (auth required)
│       ├── warden_login.php       POST  — warden signs in
│       ├── warden_logout.php      POST  — warden signs out
│       └── session_check.php      GET   — is a warden already logged in?
└── frontend/
    └── index.html           ← the portal UI (calls the API above)
```

## 1. Create the database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `hostel_complaint_portal` database with three tables:
`wardens`, `complaints`, and `ticket_counters` (used to generate gap-free
`HC-2026-0001` style ticket numbers safely under concurrent submissions).

No warden account is seeded in the SQL file on purpose — passwords should be
hashed by PHP's `password_hash()`, not pasted into a `.sql` file.

## 2. Configure the database connection

Edit `backend/config.php` directly, or set these environment variables on
your server: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

## 3. Create your first warden login

```bash
cd backend
php setup_warden.php warden1 "a-strong-password" "Mr. R. Kulkarni"
```

Run it again with the same username any time you want to reset that
warden's password.

## 4. Run it

For local testing with PHP's built-in server, from the `hostel-portal/`
folder:

```bash
php -S localhost:8000
```

Then open **http://localhost:8000/frontend/index.html**.

For real deployment, upload the whole `hostel-portal/` folder to any
PHP 8+ / MySQL host (shared hosting, VPS, etc.) and point your browser at
`frontend/index.html`. The frontend calls the API using a relative path
(`../backend/api/...`), so keeping the `frontend/` and `backend/` folders
as siblings, exactly as in this zip, is all that's required — no extra
configuration.

If you ever deploy the frontend on a **different domain** than the
backend, you'll need to add CORS headers to each `api/*.php` file and
switch cookie `samesite` handling in `helpers.php` — same-origin, as
shipped, doesn't need any of that.

## How login works

- **Students** don't have accounts. They identify a ticket by name + room
  number, same as before — `list_complaints.php?mode=student&lookup=...`
  only ever returns tickets matching that search, never the full table.
- **Wardens** log in with a username/password (`warden_login.php`), which
  starts a PHP session (`httponly` cookie). `update_status.php` and the
  warden dashboard (`list_complaints.php?mode=warden`) both check that
  session and reject the request with `401` if no warden is logged in.
- Passwords are hashed with bcrypt via `password_hash()` /
  `password_verify()` — never stored or compared in plain text.
- All queries use PDO prepared statements, so user input is never
  concatenated into SQL.
