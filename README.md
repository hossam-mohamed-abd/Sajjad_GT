# Global Trading — Field Reports

A PHP/MySQL application for field photo reports, administrator review, optional Gemini analysis and email notifications. The interface is in English. Sajjad is seeded as the first worker; administrators can add more workers. AI results are suggestions only and never automatically change financial records or approve a report.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `fileinfo`, `curl`, `openssl`, `json`, and `session`; MySQL or MariaDB; Apache with `.htaccess` enabled.
- XAMPP Apache and MySQL for local use; phpMyAdmin for importing the schema.
- Upload directory `storage/uploads` must be writable by PHP. In `php.ini`, allow at least `upload_max_filesize = 5M`, `post_max_size = 32M`, and `max_file_uploads = 6`. Five image files, 5 MB each, are accepted per report. Restart Apache after changing php.ini.
- Real Gemini API credentials and a model available to your account are required for AI. Outbound HTTPS from PHP to Google's Gemini API must work.
- Email requires a working server mail transport configured for PHP `mail()`. XAMPP on Windows **does not usually send mail out of the box**; configure a mail transport or use an appropriately configured hosting server. `MAIL_TO` is the management notification address, and `MAIL_FROM` must be a valid address permitted by the server.

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
   MAIL_TO=
   MAIL_FROM=
   ```

   Set the actual database password, Gemini key/model, and management sender/recipient addresses yourself. Never commit or share `.env` or paste API keys into chat. Quote values containing leading or trailing spaces. `APP_URL` must match the full URL including the project folder; no trailing slash is required. Configure the server and PHP timezone consistently; the database connection is set to the PHP timezone's current UTC offset.
4. Open Command Prompt and run (the command asks for the credentials; password input is visible on screen):

   ```bat
   cd C:\xampp\htdocs\Sajjad_GT
   C:\xampp\php\php.exe scripts\create-admin.php
   ```

   Choose an email and a unique password of at least 12 characters. This command can also create additional administrators using unique email addresses. No default admin credentials are supplied.
5. Visit `http://localhost/Sajjad_GT/` and sign in. Under **Your team**, create a one-time link for Sajjad. Open it on Sajjad's own device within 24 hours and tap **Connect my device**. The link activates a device for 90 days; the worker then opens the worker page without typing. An admin can revoke all worker devices and unused links. Signing out also revokes the current device; after expiry or sign-out, create a fresh link. Anyone possessing a link can activate it, so share privately.

   `localhost` on a phone points to the phone, not your computer. To test on another device, use a reachable server address as `APP_URL`. Production device links must use HTTPS, and should be shared over a trusted channel. Avoid exposing local XAMPP directly to the internet.

## Operation

- The worker chooses an icon, takes a photo or selects photos, and submits. A receipt photo identical byte-for-byte to an earlier expense photo is blocked. This does not detect differently photographed, resized, or edited copies, and simultaneous submissions can still race; review potential duplicates manually.
- Reports and photos are saved before external AI/email calls. Failed or unconfigured AI does not prevent submission; the administrator can retry analysis from the review page. With a slow AI connection the worker may wait while analysis and email run. Configure server execution/proxy timeouts accordingly. There is no background queue.
- The management email includes the activity, worker, AI suggestion if available, and original photos as attachments. Attachment/server size limits may affect sending. The report page records the mail attempt: `sent` means PHP's mail transport accepted the message, **not** that it was delivered. Email is not automatically retried. Uploaded company images also leave the application when attached to email or sent to Gemini; check company privacy policies and vendor terms.
- Review the original photo and AI suggestions, enter confirmed values, assign the project and save the decision. Approved expenses require an amount. The audit log records who changed a review and the before/after values. Project completion units are entered by an administrator, not inferred from images.
- Dashboard statistics use the selected month; spending includes only approved expense amounts, in AED. Date filters narrow the report list within that month. The list shows at most 100 recent matches; use filters to narrow results. The monthly AI summary is generated on demand from aggregated dashboard data and needs independent verification.

## Deployment and security

- Set `APP_URL` to the real HTTPS URL, including the enclosing project path, and configure DB and email for the server. The server needs outbound Gemini HTTPS access. Enable HTTPS for all users. Do not expose phpMyAdmin publicly.
- Apache must honor the included `.htaccess` files (`AllowOverride All` or an equivalent server configuration). Verify that requests to `/.env`, `/schema.sql`, `/storage/uploads/...`, `/includes/...`, and `/scripts/...` are denied before uploading real data. If using Nginx or a server that ignores `.htaccess`, add equivalent explicit deny rules; ideally store secrets and uploads outside the web root. PHP serves photos through `photo.php` after authorization. Disable directory listings and production `display_errors`.
- Keep `.env` out of version control and backups that are publicly accessible. Restrict database credentials, maintain regular private backups of the database **and** uploads, and test restoration. Treat emailed attachments and device invitation URLs as sensitive. Web server logs may contain invite URLs; protect and rotate logs. An activated device is trusted until revoked/expired, so revoke it immediately if lost.
- This is an initial implementation, not an independently security-audited deployment. Test your hosting configuration, worker flow, image privacy, AI, and actual mail delivery end-to-end before using company records.
