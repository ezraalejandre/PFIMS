# Hostinger deployment

This repository contains the Laravel application at `pfims_web/pfims_web`.
The Laravel document root is `pfims_web/pfims_web/public`.

## Before connecting Git

1. Create the Hostinger MySQL database and user, and record the database name,
   username, password, and host shown by hPanel. Do not put these values in
   Git.
2. Export and verify an SQL dump of the local `pfims_db` database, then import
   it into the new Hostinger database before running Laravel migrations. The
   repository does not contain create-migrations for every legacy operational
   table, so migrations alone cannot create a usable PFIMS database. The dump
   contains account and business data: keep it outside Git, import it only
   after the owner approves that transfer, and delete or securely archive it
   afterward.
3. Create a Google App Password for `evc.construction.pfims@gmail.com`.
   Gmail SMTP requires the App Password, not the normal mailbox password.
4. Merge the tested `web` branch into `main`; `main` is the production branch.

## Create the website in hPanel

1. From the paid-plan onboarding screen, choose **Create a new site**.
2. Choose the blank/custom PHP or HTML website path. Do not choose Hostinger
   Website Builder: Builder sites do not expose the Git, SSH, database, cron,
   and PHP controls required by Laravel.
3. Select the final domain or a temporary Hostinger domain and the preferred
   server location. Record the resulting site URL.
4. Open **Websites > Dashboard > PHP Configuration**, select PHP 8.2 or a
   newer version compatible with `composer.lock`, and confirm the required PHP
   extensions are enabled: PDO MySQL, mbstring, fileinfo, ZIP, OpenSSL, XML,
   DOM, JSON, and sessions.
5. Open **Databases > Management**, create the MySQL database and user, then
   import the approved SQL dump with phpMyAdmin.

## Hostinger Git and document root

Connect the GitHub repository through hPanel's Git integration, authorize the
GitHub account, select the repository and the `main` branch, and deploy it to
the site's `public_html` directory. Set the Git Root directory to
`public_html`—not to the nested Laravel directory. The repository-root
`.htaccess` routes web requests safely to the nested Laravel public directory.
On the current hPanel this is under **Websites > Dashboard > Advanced > Git >
Continue with GitHub**. If the Premium account shows an automatic-deployment
toggle, enable it for `main`; otherwise use **Redeploy** after future pushes.

## Server `.env`

Create `pfims_web/pfims_web/.env` on the server (never commit it) and fill in
the values from hPanel and the mailbox setup:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain>
APP_KEY=
APP_TIMEZONE=Asia/Manila
CORS_ALLOWED_ORIGINS=https://<your-domain>
SESSION_SECURE_COOKIE=true
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<hostinger-db-host>
DB_PORT=3306
DB_DATABASE=<hostinger-db-name>
DB_USERNAME=<hostinger-db-user>
DB_PASSWORD=<hostinger-db-password>

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=evc.construction.pfims@gmail.com
MAIL_PASSWORD=<google-app-password>
MAIL_FROM_ADDRESS=evc.construction.pfims@gmail.com
MAIL_FROM_NAME="PFI Management System"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

With Laravel 12's mail configuration, `MAIL_SCHEME=null` and port 587 allow
the SMTP transport to negotiate STARTTLS automatically. Keep `APP_DEBUG=false`
and `SESSION_SECURE_COOKIE=true` in production.

Keep `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` aligned with the
database/cache/queue services actually enabled for the site. Do not copy the
local `.env` wholesale: it contains local credentials and development values.

## First deploy commands

Enable SSH in hPanel, find the site's absolute home path under **FTP Accounts**,
then change into the Laravel directory. It will resemble:

```bash
cd /home/<account>/domains/<your-domain>/public_html/pfims_web/pfims_web
```

Run:

```bash
composer2 install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force       # only when APP_KEY is empty
php artisan migrate --force            # only migrations newer than the imported dump
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan ml:retrain
```

Do not run `php artisan db:seed` against the production database. The imported
dump is the source of production data; seeders contain development/sample
records.

The compiled Vite files in `public/build` are tracked in Git, so the first
deploy does not require Node.js on the hosting plan. Whenever `resources/css`
or `resources/js` changes, run `pnpm install --frozen-lockfile` and
`pnpm run build` locally, then commit the updated `public/build` files before
deploying `main`.

Create a custom cron job that runs Laravel's scheduler every minute. Use the
absolute path shown by **FTP Accounts**, for example:

```bash
/usr/bin/php /home/<account>/domains/<your-domain>/public_html/pfims_web/pfims_web/artisan schedule:run
```

hPanel cron schedules use UTC, while Laravel uses `APP_TIMEZONE=Asia/Manila`.
The application schedule retrains the ML model every Monday at 02:00 Manila
time. Verify cron output after its first run.

After deployment, check `https://<your-domain>/up`, sign in, and exercise both
OTP flows. If mail fails, inspect Laravel logs and verify the App Password and
SMTP values; never paste credentials into source or an issue.

Finally, confirm SSL is active, test role-based dashboards, uploads/downloads,
reports, logout/back-button protection, and both OTP flows. Check
`storage/logs/laravel.log` and the Hostinger deployment history for errors.
