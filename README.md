# RGreen Technologies — Attendance Management System

Sign in -> upload the weekly attendance export -> the system reads the real
calendar dates in the file, totals each employee's hours, and automatically
emails every employee a formal report (a red alert if they're short, a green
confirmation if they met it) -> review, download, or reopen it later from
**History**.

## Required hours, working days and grace time
- On the **Upload** page you enter the **required hours for the week** (default 45), the **working days in the week** (default 5) and an optional **grace time per day in minutes** (0–60, leave empty for none). Defaults are `required_weekly_hours` and `working_days_per_week` in `config.php`. The working days are what you type, not counted from the file.
- **Daily target** = weekly hours ÷ working days (45h ÷ 5 = 9h). **Grace for the week** = grace per day × working days.
- **Per day** (9h target, 30 min grace): 9h 00m or more = **green**; 8h 30m up to just under 9h = **orange**; below 8h 30m = **red**. Days with 0h (leave/off) are not coloured.
- **For the week** (45h, 5 days, 30 min grace → 2h 30m allowance): 45h or more = green; 42h 30m up to just under 45h = orange ("Met with grace time"); below 42h 30m = red.
- The weekly hours, working days, daily target and grace time are saved with each report and shown on the report page, the individual reports, the Excel download and the emails.

## Emails
- Every employee email now has their own **day-by-day report attached as an Excel (.xlsx) file**, so they can download it straight from the email. If the attachment can't be built, the email is still sent without it.

## Login and roles
**Admin** (one account only)
- The very first time the app is opened it goes to a one-time **Create the admin account** page (email + password).
- After that, sign in from **Login → Admin login** with the admin email (or username) and password.

**Employees (normal users) — no registration needed**
- The admin adds each employee on the **Employees** page with their **email and a login password** (min 8 characters). Saving creates the employee's login automatically; the admin tells the employee the password.
- The employee signs in on the login page with that **email + password** and lands on **My hours**: their own individual report, day by day, for any uploaded week (with Print / Save as PDF). They cannot open upload, employees, users, history or settings.
- Employees can change their own password on the **My profile** page (current password + new password). It also shows their name, ID, department and email (read-only; the admin changes those).
- The admin can also set a new password for an employee by editing them and typing one (leave blank to keep the current one). Changing an employee's email also updates their login email.
- Employees found in an upload that have no email/password yet appear on the Employees page as **No login** until the admin fills those in.
- The **Employee logins** page lists who can sign in and lets the admin remove a login. Deleting an employee also deletes their login.
- After 5 wrong attempts a browser must wait 5 minutes.

Sessions end after 30 minutes of inactivity or when the browser closes.

## Data storage: MySQL
Everything is stored in MySQL (database `rgreen_attendance`), created and
kept up to date automatically — nothing to import by hand:

| Table | Holds |
|---|---|
| `admin_users` | the one admin login (email/username + hashed password) |
| `users` | employee logins (email + hashed password), each tied to one employee ID, created automatically from the Employees page |
| `employees` | employee ID, name, department, email |
| `mail_settings` | the SMTP details, set from the Mail Settings page |
| `reports` / `report_entries` | every weekly report: hours, status, day-by-day breakdown, email result |

If an older version of this app stored data in `data/*.json`, it is copied
into MySQL once, automatically, the first time it runs.

## Mail settings (configured in the app, not in a file)
Go to **Mail Settings** in the sidebar to enter the SMTP host, port,
encryption, username, password, and "from" name/email — no editing
`config.php` by hand. A **Send test email** button confirms the details work
before you rely on them. Until this is set up, uploads still calculate hours
normally, but emails are held back and clearly marked "not set up" on the
report, with a link straight back to this page.

## Supported attendance files
- **Biometric device weekly export** (e.g. ZKTeco "Employee Attendance
  Table"), detected automatically — several employees laid out side by side
  per sheet, with Before Noon / After Noon / Overtime punches per day. These
  exports have no email column, so email lives in the Employees directory.
- **Simple flat sheet**: one row per employee per day, with columns
  `Emp ID`, `Name`, `Email`, `Date`, `In Time`, `Out Time`.

## Employee directory
Manage employees on the **Employees** page: add, edit or delete by employee
ID, and search by ID or name. New IDs are added automatically the first time
they appear in an upload (name and department filled in, email left blank
until entered).

## Per-day hours and individual reports
- The report page shows an **Hours per day** table: every employee, every date of the period, plus totals. Click a name (or **Individual report**) for that person's own report: total, required, short/above, days worked, average per day, and a day-by-day table (with In/Out times when the sheet has them).
- The .xlsx download has a second sheet, **Hours Per Day**, with the same table as decimal hours.
- The email each employee gets already contains their own daily breakdown.

## Report history
Every calculated week is saved. **History** lists them all — period, upload
date, how many were below the requirement, how many emails went out — and
**Open** takes you back to that week's full report, with the same download
and retry-email options.

## Setup
1. Install PHP 8.1+ (extensions `pdo_mysql`, `zip`, `gd`, `mbstring`, `xml`,
   `dom`, `fileinfo`, `openssl`) and Composer. XAMPP already includes these.
2. Start **MySQL** in the XAMPP Control Panel.
3. In this folder run: `composer install`
4. Edit `config.php`: the `db` block (XAMPP default is user `root`, empty
   password), company name, and required weekly hours.
5. Run `php -S localhost:8000` and open it. Register the admin account, then
   go to **Mail Settings** before your first upload.

## Deployment
- Upload the whole folder (including hidden `.htaccess` files), run
  `composer install`, point `config.php`'s `db` block at your MySQL database.
- Make sure `uploads/` is writable. Set `'debug' => false` before going live.
- Back up by exporting the `rgreen_attendance` database.

## Files
- `src/Auth.php` — admin + employee accounts, sessions. `src/guard.php` (admin pages) / `src/user_guard.php` (employee pages) — the login gates
- `login.php` → `login_submit.php` (admin) / `user_login_submit.php` (employee email + password); `users.php` (admin's list of employee logins)
- `report.php` (Hours per day) → `employee_report.php` (admin) / `my_report.php` (employee)
- `src/Database.php` — connects to MySQL, creates the schema, imports old JSON data once
- `src/AttendanceProcessor.php` — detects the file layout, totals hours, works out the real calendar period
- `src/EmployeeDirectory.php` — employee CRUD + search, keyed by ID
- `src/MailSettings.php` / `src/MailService.php` — SMTP settings and the formal red/green report email
- `src/ReportStore.php` — saves and reopens every weekly report
- `index.php` -> `upload.php` -> `report.php` — the upload + auto-email flow
- `history.php` — past reports
- `settings.php` — Mail Settings (+ test email)
- `employees.php` -> `employees_edit.php` / `employees_delete.php` — the directory
- `report_download.php` — the .xlsx report download
- `send.php` — retries only the emails that failed or were skipped
#   A t t e n d a n c e _ M a n a g e m e n t _ S y s t e m  
 