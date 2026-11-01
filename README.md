# Global Trading — Field Reports

A PHP/MySQL application for field photo reports, administrator review, optional Gemini analysis and email notifications. The interface is in English. Sajjad is seeded as the first worker; administrators can add more workers. Clear reports can be approved automatically using Gemini; uncertain reports remain pending. AI can make mistakes, so administrators must monitor and correct records.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `fileinfo`, `curl`, `openssl`, `json`, and `session`; MySQL or MariaDB; Apache with `.htaccess` enabled.
- XAMPP Apache and MySQL for local use; phpMyAdmin for importing the schema.
- Upload directory `storage/uploads` must be writable by PHP. In `php.ini`, allow at least `upload_max_filesize = 5M`, `post_max_size = 32M`, and `max_file_uploads = 6`. Five image files, 5 MB each, are accepted per report. Restart Apache after changing php.ini.
- Real Gemini API credentials and a model available to your account are required for AI. Outbound HTTPS from PHP to Google's Gemini API must work.
- Email uses SMTP via the bundled PHPMailer library (version 7.1.1, LGPL 2.1). Configure a sending account in `.env`; the server must be able to connect to the provider over the configured SMTP port. The receiving address is managed in the admin dashboard, not in `.env`.

## XAMPP setup

1. Copy the project to `C:\xampp\htdocs\Sajjad_GT` and start Apache and MySQL.
2. In phpMyAdmin, create an empty `sajjad_gt` database using `utf8mb4`, then import `schema.sql` **once**. It creates the tables and the Sajjad worker. This is an initial schema, not a migration; do not reimport it over real records.
3. Copy `.env.example` to `.env` in the project root if `.env` is not already present. Edit it for your machine:

   ```env
   APP_URL=http://localhost/Sajjad_GT
   APP_TIMEZONE=Asia/Dubai
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=sajjad_gt
   DB_USERNAME=root
   DB_PASSWORD=
   GEMINI_API_KEY=
   GEMINI_MODEL=gemini-2.5-flash
   SMTP_HOST=
   SMTP_PORT=587
   SMTP_ENCRYPTION=tls
   SMTP_USERNAME=
   SMTP_PASSWORD=
   MAIL_FROM=
   MAIL_FROM_NAME=Global Trading
   ```

   Set the actual database password, Gemini key/model, and SMTP sending account yourself. The notification recipient is initially `hossam545mohamed@gmail.com` and can be changed in the administrator dashboard. Never commit or share `.env` or paste API keys into chat. Quote values containing leading or trailing spaces. `APP_URL` must match the full URL including the project folder; no trailing slash is required. Configure the server and PHP timezone consistently; the database connection is set to the PHP timezone's current UTC offset.
4. Open Command Prompt and run (the command asks for the credentials; password input is visible on screen):

   ```bat
   cd C:\xampp\htdocs\Sajjad_GT
   C:\xampp\php\php.exe scripts\create-admin.php
   ```

   Choose an email and a unique password of at least 12 characters. This command can also create additional administrators using unique email addresses. No default admin credentials are supplied.
5. Visit `http://localhost/Sajjad_GT/` and sign in. Under **Your team**, create a one-time link for Sajjad. Open it on Sajjad's own device within 7 days and tap **Connect my device**. The link activates a device for 90 days; the worker then opens the worker page without typing. An admin can revoke all worker devices and unused links. Signing out also revokes the current device; after expiry or sign-out, create a fresh link. Anyone possessing a link can activate it, so share privately.

   `localhost` on a phone points to the phone, not your computer. To test on another device, use a reachable server address as `APP_URL`. Production device links must use HTTPS, and should be shared over a trusted channel. Avoid exposing local XAMPP directly to the internet.

## SMTP setup and notification address

1. On the administrator dashboard, open **Notifications** and enter the address that should receive new submissions. The initial address is `hossam545mohamed@gmail.com`. Only administrators can change it; each change is recorded in the audit log. All new reports (not only receipts) are sent to this single address with their photos and AI result, if available. Changing it does not resend old reports.
2. In `.env`, set `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION` (`tls` for STARTTLS, `ssl` for implicit TLS, or `none` only for a trusted local relay), `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM`, and `MAIL_FROM_NAME`. SMTP credentials belong to the **sending account**, which can differ from the recipient. `MAIL_FROM` must be an address the provider permits the account to use. Both username and password may be blank only for a relay that does not require authentication. Never send credentials in chat or commit `.env`.
3. For example, if you choose Gmail as the sending provider, the usual settings are `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, and `SMTP_ENCRYPTION=tls`; use the sending account's address as `SMTP_USERNAME` and `MAIL_FROM`, and an app password as `SMTP_PASSWORD` if your Google account supports it. Google account security requirements and provider policies apply. The recipient address alone does **not** supply sender credentials. Other providers have their own host, port, login, and sender requirements.
4. Ensure the server can make outbound connections to the SMTP provider and has trusted CA certificates for TLS. Submit a test report and check its notification status on the report review page and the actual mailbox (including spam). A `sent` result means the SMTP server accepted it, not that it arrived in the inbox. Large attachments may be rejected. No SMTP password is shown in the dashboard.

## Upgrading an existing installation

If you already imported the earlier `schema.sql`, back up your database and import `migrations/001_notification_email.sql` and then `migrations/002_worker_audio.sql` using phpMyAdmin. If you already ran the first migration, run only the second. Do not re-import the full schema. The migrations add the settings and defaults without overwriting an existing saved address or sound preference. Add the new SMTP variables from `.env.example` to your existing `.env` (do not overwrite your existing database or Gemini settings). The old `MAIL_TO` setting is no longer used.

## Operation

- Administrators can enable or disable English voice guidance for all workers from **System settings** at the top of the dashboard. Sound defaults to on. Open or refresh worker pages to apply a change. When sound is on, the worker hears a short voice instruction on choosing an icon; then they take or select photos. The selected photos appear for review; the worker can send them or change the selection before submitting. If no activity was chosen, a large four-option chooser opens; selecting one submits the report. A blocking progress indicator remains visible until the server responds, and a success check appears only after the report has been saved. Choosing replacement photos replaces the previous selection; up to five can be selected together from the gallery. Turn on the device volume; use **Play again** to replay the instruction. Audio requires a browser capable of MP3 playback and may be affected by silent mode or browser settings. A receipt photo identical byte-for-byte to an earlier expense photo is blocked. This does not detect differently photographed, resized, or edited copies, and simultaneous submissions can still race; review potential duplicates manually.
- Reports and photos are saved before external AI/email calls. When Gemini reports a clear, unambiguous result, the system may approve the report automatically. For an expense this requires a single clear receipt, a positive final amount with at most two decimal places and an explicitly identified AED currency. Multiple-image expense reports, unclear data, unknown currency, missing fields, and failed or unconfigured AI stay pending and are excluded from approved spending. Retrying analysis from the review page updates suggestions but does not auto-approve an existing report. AI confidence is not a guarantee: review records and correct mistakes. With a slow AI connection the worker may wait while analysis and email run. Configure server execution/proxy timeouts accordingly. There is no background queue.
- The management email includes the worker, assigned client (or Not assigned), activity, current status, amount, AI summary and original photos as attachments. An approved report has a **Calculation is wrong** link; a pending report has **Review and approve**. The link requires administrator login and a confirmation on the website: opening an email or link alone never changes a report, since mail scanners can visit links automatically. Amounts are entered on the secure confirmation page, because email clients do not reliably support interactive form inputs. Moving a report to review excludes it from approved spending; approving an expense requires an AED amount. Attachment/server size limits may affect sending. The report page records the mail attempt: `sent` means the SMTP server accepted the message, **not** that it was delivered. Email is not automatically retried. Uploaded company images also leave the application when attached to email or sent to Gemini; check company privacy policies and vendor terms.
- Review automatically approved records and AI suggestions, correct mistakes, assign the project and save the decision. AI amount (only when the currency is AED), category and summary prefill empty fields if management has not already reviewed the report; existing administrator entries and deliberately cleared values are not overwritten. Auto-approved reports also store the AI summary as notes when notes are empty. The email action and review page can return an approved report to pending, excluding it from the totals. Approved expenses require an amount. The audit log records who changed a review and the before/after values. Project completion units are entered by an administrator, not inferred from images.
- Dashboard statistics use the selected month; spending includes only approved expense amounts, in AED, including automatic AI approvals. Date filters narrow the report list within that month. The list shows at most 100 recent matches; use filters to narrow results. The monthly AI summary is generated on demand from aggregated dashboard data and needs independent verification.

## Deployment and security

- Set `APP_URL` to the real HTTPS URL, including the enclosing project path, and configure DB and email for the server. The server needs outbound Gemini HTTPS access. Enable HTTPS for all users. Do not expose phpMyAdmin publicly.
- Apache must honor the included `.htaccess` files (`AllowOverride All` or an equivalent server configuration). Verify that requests to `/.env`, `/schema.sql`, `/storage/uploads/...`, `/includes/...`, `/migrations/...`, and `/scripts/...` are denied before uploading real data. If using Nginx or a server that ignores `.htaccess`, add equivalent explicit deny rules; ideally store secrets and uploads outside the web root. PHP serves photos through `photo.php` after authorization. Disable directory listings and production `display_errors`.
- Keep `.env` out of version control and backups that are publicly accessible. Restrict database credentials, maintain regular private backups of the database **and** uploads, and test restoration. Treat emailed attachments and device invitation URLs as sensitive. Web server logs may contain invite URLs; protect and rotate logs. An activated device is trusted until revoked/expired, so revoke it immediately if lost.
- This is an initial implementation, not an independently security-audited deployment. Test your hosting configuration, worker flow, image privacy, AI, and actual mail delivery end-to-end before using company records.
