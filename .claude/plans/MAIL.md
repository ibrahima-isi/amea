## Plan: Email Notifications via PHPMailer + Brevo

---

### Phase 1 — Infrastructure setup

**1.1 Install PHPMailer**
Add via Composer: `composer require phpmailer/phpmailer`
Commit `vendor/` exclusion in `.gitignore`, deploy via workflow.

**1.2 Brevo account setup**
- Create free account at brevo.com
- Verify domain `aeesgs.org` (add DNS TXT/DKIM records on Namecheap)
- Generate SMTP credentials
- Add to `.env` and GitHub secrets:
    - `MAIL_HOST=smtp-relay.brevo.com`
    - `MAIL_PORT=587`
    - `MAIL_USER=your_brevo_login`
    - `MAIL_PASS=your_brevo_smtp_key`
    - `MAIL_FROM=no-reply@aeesgs.org`
    - `MAIL_FROM_NAME=AEESGS`

**1.3 Create email service**
New file `functions/email-service.php` — a single `sendMail($to, $subject, $body)` function wrapping PHPMailer + Brevo SMTP. All other code calls this one function.

---

### Phase 2 — Email templates

Create clean HTML email templates in `templates/emails/`:
- `registration-confirmation.html` — sent to student on register
- `registration-finalized.html` — sent to student on finish/lock
- `new-registration-admin.html` — sent to admin when a student registers

Each template uses simple `{{placeholder}}` replacements consistent with the rest of the project.

---

### Phase 3 — Hook notifications into existing flows

| File | Event | Email sent to |
|---|---|---|
| `register.php` | Successful insert | Student (confirmation) + Admin (new registration alert) |
| `registration-details.php` | `action=finish` (is_locked) | Student (finalized confirmation) |

No other files touched for now.

---

### Phase 4 — Admin notification settings

Add an email field in `settings.php` for the admin notification address (already has a `contact_email` setting in the DB). Notifications to admin go to that address.

---

### Phase 5 — Deploy

- Update `.github/workflows/deploy.yml` to add the 6 new `MAIL_*` secrets
- Update `printf` in the workflow to write them to `.env`
- Add Composer install step to the workflow: `composer install --no-dev --optimize-autoloader`

---

### What this does NOT include (scope kept tight)

- No email queue / retry system
- No unsubscribe mechanism
- No email to students when admin edits their record (can add later)
- No password reset email changes (already working with `mail()`)

---

### Files created / modified

| Action | File |
|---|---|
| New | `functions/email-service.php` |
| New | `templates/emails/registration-confirmation.html` |
| New | `templates/emails/registration-finalized.html` |
| New | `templates/emails/new-registration-admin.html` |
| Modified | `register.php` |
| Modified | `registration-details.php` |
| Modified | `settings.php` (read admin email) |
| Modified | `.github/workflows/deploy.yml` |
| Modified | `.env` + GitHub secrets |
| New | `composer.json` |

---

**Approve this plan to proceed?**
