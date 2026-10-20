# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a **PHP + MySQL** web application — a job-recruitment system for โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม (Demonstration School of Thaksin University, Secondary Division). UI text is Thai (`<html lang="th">`).

No framework, no Composer, no build step. Plain PHP files served directly.

- `index.php` — public site (news, job search, pre-apply, applicant/exam lookup, how-to)
- `file.php` — access-controlled serving for `assets/uploads/applications/` (applicant documents). That folder has an `.htaccess` denying all direct web access; every ID copy / résumé / medical cert / payment slip must be fetched via `file.php?f=<filename>`, which requires `$_SESSION['admin_id']` (or, for `*_payment_qr_*` only, an active `$_SESSION['apply']` matching that file). When adding a new applicant-document link, point it at `file.php`, never at the folder directly.
- `admin/` — admin login + dashboard (`admin/index.php`)
- `user/` — applicant-facing multi-step apply / pre-apply forms, job detail, edit application
- `config/db.php` — PDO/MySQL connection + shared helpers (`isAllowedUploadFile`, `syncPreapplicationMatches`). Credential precedence (later wins): hardcoded XAMPP defaults (`localhost`/`root`/no password) → `config/credentials.php` (gitignored, holds the real server DB creds) → `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` env vars (Docker sets these, so `docker compose` works even with a server `credentials.php` sitting in the tree). On DB failure it logs and shows a generic 500, never the PDO message.
- `assets/` — `css/style.css`, `js/main.js` (currently empty), `images/`, `uploads/` (user-uploaded ID copies, medical certs, payment slips — `uploads/.htaccess` blocks script execution)

The leftover `index.js` / `package.json` are an unused Node stub (`index.js` only `console.log`s).

Database: MySQL/MariaDB `recruitment_db`. No schema file is committed (contains applicant PII, so `*.sql` is gitignored). A working export lives at `docker/db-init/recruitment_db.sql` — tables: `admins`, `jobs`, `job_applications`, `applicants`, `preapplications`, `exam_documents`, `applications_and_payment`.

## Commands

- **Docker (recommended):** `docker compose up -d --build` → http://localhost:8000 (app), http://localhost:8081 (phpMyAdmin). MariaDB auto-imports `docker/db-init/*.sql` on first run. Full guide: `DOCKER.md`.
- **XAMPP:** installed at `C:\xampp`; start MySQL, put the project so document root is the project root. `C:\xampp\mysql\bin\` has `mysql.exe` / `mysqldump.exe` (not on PATH).

## Deployment

Target is a Thaksin University FTP/SFTP server (not Docker). See `DEPLOY.md` — FileZilla upload + phpMyAdmin import. `docker/`, `docker-compose.yml`, `*.md`, `index.js`, `package.json` are **not** uploaded.
